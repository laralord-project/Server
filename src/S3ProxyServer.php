<?php

namespace Server;

use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\S3\S3Client;
use GuzzleHttp\{Client, Handler\CurlHandler, HandlerStack};
use OpenSwoole\{HTTP\Server as HttpServer, Table};
use Server\Configurator\{ConfiguratorContract, S3ProxyConfigurator};
use Server\Traits\HasConfigurator;
use Server\Workers\{S3ProxyWorker, WorkerContract as Worker};
use Swoole\Timer;

/**
 * Class Server
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server
 * @property string   $host
 * @property int      $port
 * @property bool     $watch
 * @property string   $logLevel
 * @property string   $basePath
 * @property string   $configEnvFile
 * @property string   $mode
 * @property string[] $watchTargets
 * @mixin S3ProxyConfigurator
 */
class S3ProxyServer extends HttpServer
{
    use HasConfigurator;

    /**
     * @var string
     */
    public static string $logChannel = 'Laralord';

    /**
     * @var HttpServer|self
     */
    private HttpServer|self $server;

    /**
     * @var Watcher
     */
    private Watcher $watcher;

    /**
     * @var Worker
     */
    public Worker $worker;

    private S3Client $s3Client;

    private Table $cache;


    /**
     *
     */
    public function __construct(protected ConfiguratorContract $configurator)
    {
        parent::__construct($this->configurator->host, $this->configurator->port, self::POOL_MODE);
        \cli_set_process_title("laralord:s3-proxy");
    }


    /**
     * @return void
     */
    public function start(): bool
    {
        // $this = new HttpServer($this->host, $this->port, SwooleServer::POOL_MODE);
        $this->configurator->loadConfig();

        $this->initS3Client();

        $this->set($this->options);
        $this->initCacheStorage();
        $this->worker = $this->getWorker();

        Log::info('Starting server');

        $this->watch && $this->initWatcher();

        $this->registerHandlers();

        return parent::start();
    }


    private function initS3Client(): S3Client
    {
        $client = new Client([
            'handler' => HandlerStack::create(new CurlHandler()),
            // 'debug' => true,
        ]);

        $s3Config = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $this->accessKey,
                'secret' => $this->secretKey,
            ],
            'http_handler' => new GuzzleHandler($client),
        ];

        if ($this->s3Endpoint) {
            $s3Config += [
                'endpoint' => $this->s3Endpoint,
                'use_path_style_endpoint' => $this->s3UsePathStype,
            ];
        }

        $this->s3Client = new S3Client($s3Config);

        return $this->s3Client;
    }


    private function initCacheStorage(): Table
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        $storage = $this->cache = new Table(1024);
        $storage->column('key', Table::TYPE_STRING, 100);
        $storage->column('s3_path', Table::TYPE_STRING, 2000);
        $storage->column('content_type', Table::TYPE_STRING, 200);
        $storage->column('content_length', Table::TYPE_INT, 32);
        $storage->column('created_at', Table::TYPE_INT, 32);
        $storage->column('last_usage', Table::TYPE_INT, 32);
        $storage->column('metadata', Table::TYPE_STRING, 10000);
        $storage->create();

        return $storage;
    }


    /**
     * @return Worker
     */
    protected function getWorker(): Worker
    {
        return new S3ProxyWorker($this->s3Client, $this->bucket, $this->cache, $this->cacheDir);
    }


    /**
     * @return void
     */
    protected function initWatcher()
    {
        $this->watcher = new Watcher();
        $this->addConfigWatcher();
        $this->watcher->watch($this->watchTargets, basePath: $this->basePath);
        $this->watcher->addCallback(function (array $changes) {
            $this->reload();
        });
        Log::debug('Init watcher. Watching: ', $this->watchTargets ?: []);
    }


    /**
     * @return void
     */
    protected function addConfigWatcher(): void
    {
        // skip if no config file provided
        if (!$this->configEnvFile) {
            return;
        }

        // add the config file to watch targets
        if (!\in_array($this->configEnvFile, $this->watchTargets)) {
            $this->configurator->watchTargets[] = $this->configEnvFile;
        }
        // catch the changes on callback
        $this->watcher->addCallback(function (array $changes) {
            Log::debug('Changes detected', $changes);
            $configChanged = \array_filter($changes, fn($value) => $value['path'] === $this->configEnvFile);

            if ($configChanged) {
                // reloading the config from config env file
                $this->configurator->loadConfig();
                $this->initS3Client();
                $this->worker = $this->getWorker();
            }
        });
    }


    /**
     * @return void
     */
    public function help()
    {
        $this->output("Command format: server [command] \n\n");
        $this->output("The command not specified. Use one of following: serve, help \n\n");
        $this->configurator->getInfo();
    }



    /**
     * @return void
     */
    private function registerHandlers()
    {
        $this->on("start", function (self $server) {
            \cli_set_process_title("laralord:s3-proxy");
            $scheme = 'http';

            Log::warning("Laralord S3 Proxy Server started on {$scheme}://{$server->host}:{$server->port}\n");
            Log::notice('S3 Proxy Server started');

            $this->watch && $this->addWatcherTimer();
        });

        $this->on(
            'ManagerStart',
            fn(self $server) => \cli_set_process_title("laralord:s3-proxy:manager")
        );

        $this->on('request', function ($request, $response) {
            $this->worker->getRequestHandler()($request, $response);
        });

        $this->on("WorkerStart", function (self $server, $workerId) {
            $server->worker->getInitHandler()($server, $workerId);
        });

        $this->on('BeforeReload', function ($server) {
        });
        // Pipe message event
        $this->on("pipeMessage", function ($server, $src_worker_id, $message) {
            Log::notice("Message from worker {$src_worker_id}: {$message}");
            echo "Message from worker {$src_worker_id}: {$message}\n";
        });

        // Triggered when the server is shutting down
        $this->on("Shutdown", function($server)
        {
            Log::info('Server Shutdown');
        });
    }


    /**
     * @return void
     */
    private function addWatcherTimer()
    {
        Timer::tick(3000, function () {
            Log::debug('Watcher Tick');
            $this->watcher->detectChanges();
        });
    }


    /**
     * @param $message
     *
     * @return void
     */
    public function output($message)
    {
        echo $message;
    }
}
