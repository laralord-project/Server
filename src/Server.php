<?php

namespace Server;

use OpenSwoole\{HTTP\Server as HttpServer, Server as SwooleServer};
use Server\Configurator\ConfiguratorContract;
use Server\Configurator\ServerConfigurator;
use Server\EnvironmentResolvers\{EnvResolverContract, FileEnvResolver, MultiTenantResolverAbstract, VaultEnvResolver};
use Server\Exceptions\ResolverNotFoundException;
use Server\Traits\HasConfigurator;
use Server\Workers\{MultiTenantServerWorker, ServerWorker, WorkerContract as Worker};
use Swoole\Timer;

/**
 * Class Server
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server
 * @property string $host
 * @property int $port
 * @property bool $watch
 * @property string $logLevel
 * @property string $basePath
 * @property string $configEnvFile
 * @property string $mode
 * @property string[] $watchTargets
 *
 * @mixin ServerConfigurator
 */
class Server
{
    use HasConfigurator;

    /**
     * @var string
     */
    public static string $logChannel = 'Laralord';

    /**
     * @var HttpServer
     */
    private HttpServer $server;

    /**
     * @var Watcher
     */
    private Watcher $watcher;

    /**
     * @var Worker
     */
    private Worker $worker;

    /**
     * @var EnvResolverContract|FileEnvResolver|VaultEnvResolver
     */
    private EnvResolverContract|VaultEnvResolver|FileEnvResolver $envResolver;


    /**
     *
     */
    public function __construct(protected ServerConfigurator|ConfiguratorContract $configurator)
    {
        \cli_set_process_title("laralord:server");
    }


    /**
     * @return void
     */
    public function start()
    {
        $this->server = new HttpServer($this->host, $this->port, SwooleServer::POOL_MODE);
        $this->initEnvResolver()
            ->common([
                'APP_BASE_PATH'          => $this->basePath,
                'APP_RUNNING_IN_CONSOLE' => false,
            ])
            ->exclude($this->configurator->listServerVariables());

        if (\method_exists($this->envResolver, 'setAsyncResolver') && $this->envResolverMode === 'async') {
            $this->envResolver->setAsyncResolver(true);
        }

        $this->envResolver->boot();

        $this->server->set($this->options);
        $this->worker = $this->getWorker();

        Log::info('Starting server');

        $this->watch && $this->initWatcher();

        chdir($this->basePath);

        $this->registerHandlers();

        $this->server->start();
    }


    public function envStore(string $envFile = '')
    {
        if (empty($this->envResolver)) {
            $this->initEnvResolver();
        }

        $tenantKey = $this->configurator->envSourceConfig['tenant_id']
            ?: $this->configurator->envSourceConfig['vault_key']
                ?: '';

        if (!$tenantKey) {
            Log::error('Tenant ID is not specified by ENV_TENANT_ID, ENV_VAULT_KEY env variable or \'--tenant_id=\' argument');
            exit (2);
        }

        if (!\method_exists($this->envResolver, 'storeToEnvFile')) {
            throw new \Exception("Environment resolver ".\get_class($this->envResolver)
                ."doesn't support this operation");
        }

        $envFile = $envFile ?: $this->configurator->envSourceConfig['env_file'];

        // force update .env file
        $this->configurator->envSourceConfig['env_file_update'] = true;

        $this->envResolver->loadSecret($tenantKey);

        return $this->envResolver->storeToEnvFile($tenantKey, $envFile);
    }


    /**
     * @return Worker
     * @throws ResolverNotFoundException
     */
    protected function getWorker(): Worker
    {
        if ($this->mode === ServerConfigurator::MODE_SINGLE) {
            return (new ServerWorker($this->envResolver->getEnvironment(), $this->warmUp))
                ->setMaxForks($this->maxForks);
        }

        return (new MultiTenantServerWorker($this->basePath, $this->tenantKey, $this->fallbackTenantId))
            ->setMaxForks($this->maxForks);
    }


    /**
     * @return void
     */
    protected function initWatcher()
    {
        $this->watcher = new Watcher();
        $this->addConfigWatcher();
        $this->watcher->watch($this->watchTargets, basePath: $this->basePath);
        // TODO fix the reload of workers / currently the reload doesn't update the workers context
        $this->watcher->addCallback(fn(array $changes) => $this->server->reload());

        Log::debug('Init watcher. Watching: ', $this->watchTargets ?: []);
    }


    /**
     * @return void
     */
    protected function addConfigWatcher()
    {
        // skip if no config file provided
        if (!$this->configEnvFile) {
            return;
        }

        // skip for local .env files resolver
        if (!($this->envResolver instanceof MultiTenantResolverAbstract)) {
            return;
        }

        // add the config file to watch targets
        if (!\in_array($this->configEnvFile, $this->watchTargets)) {
            $this->configurator->watchTargets[] = $this->configEnvFile;
        }

        // catch the changes on callback
        $this->watcher->addCallback(function (array $changes) {
            $configChanged = \array_filter($changes, fn($value) => $value['path'] === $this->configEnvFile);

            if ($configChanged) {
                // reloading the config from config env file
                $this->configurator->loadConfig();
                // reconfigure the env resolver with updated config
                $this->envResolver->configure($this->configurator->envSourceConfig);
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
        $this->envResolver->sync(function () {
            if ((($this->worker instanceof ServerWorker) && $this->worker->shouldWarmUp)
            ) {
                $this->server->reload();
            }
        }, 5);

        $this->server->on("start", function (HttpServer $server) {
            \cli_set_process_title("laralord:server");
            $scheme = 'http';

            Log::warning("Laralord Server started on {$scheme}://{$server->host}:{$server->port}\n");
            Log::notice('Server started');

            $this->watch && $this->addWatcherTimer();
        });

        $this->server->on(
            'ManagerStart',
            fn(SwooleServer $server) => \cli_set_process_title("laralord:server:manager")
        );

        $this->server->on('request', $this->worker->getRequestHandler());

        $this->server->on("WorkerStart", $this->worker->getInitHandler());

        $this->server->on('WorkerError', function (
            HttpServer $server,
            int $workerId,
            int $workerPid,
            int $exitCode,
            int $signal
        ) {
            Log::info('Worker Error',
                ['exit_code' => $exitCode, 'signal' => $signal, 'pid' => $workerPid, 'worker_id' => $workerId]);
        });

        $this->server->on('BeforeReload', function ($server) {
            // $this->server->sendMessage('terminate', 0);
        });

        $this->server->on('AfterReload', function ($server) {
            Log::debug('Server Reloaded');
        });
        // Pipe message event
        $this->server->on("pipeMessage", function ($server, $src_worker_id, $message) {
            Log::notice("Message from worker {$src_worker_id}: {$message}");
            echo "Message from worker {$src_worker_id}: {$message}\n";
        });
    }


    /**
     * @return void
     */
    private function addWatcherTimer()
    {
        Timer::tick(3000, fn() => $this->watcher->detectChanges());
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
