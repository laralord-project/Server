<?php

namespace Server;

use Monolog\Level;
use Server\Configurator\{ConfiguratorContract, QueueConfigurator, S3ProxyConfigurator, SchedulerConfigurator,
    ServerConfigurator};
use Server\Exceptions\EnvSourceNotFound;

/**
 * Class CliProcessor
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server
 */
class CliProcessor
{
    public $arguments = [
        [
            'keys'     => ['--watch', '-w'],
            'field'    => 'watch',
            'no_value' => true,
        ],
        [
            'keys'     => ['--host', '-h'],
            'field'    => 'host',
            'no_value' => false,
        ],
        [
            'keys'     => ['--port', '-p'],
            'field'    => 'port',
            'no_value' => false,
        ],

        [
            'keys'     => ['--debug', '-d'],
            'field'    => '',
            'no_value' => true,
        ],
    ];

    protected ConfiguratorContract|ServerConfigurator|null $configurator;

    protected string $subject;

    protected string $command;

    public bool $debug;


    public function __construct()
    {
        Log::init('Laralord', 'php://stderr', $_ENV['SERVER_LOG_LEVEL'] ?? Level::Notice);

        $command = $_SERVER['argv'][1] ?? 'help';
        // parse the command server:start or queue:work

        $this->subject = \explode(':', $command)[0] ?? '';
        $this->command = \explode(':', $command)[1] ?? $_SERVER['argv'][2] ?? '';

        $this->configurator = $this->getConfigurator($this->subject);
    }


    public function getConfigurator(string $subject): ConfiguratorContract|null
    {
        return match ($subject) {
            'server' => new ServerConfigurator(),
            's3-proxy' => new S3ProxyConfigurator(),
            'queue' => new QueueConfigurator(),
            'scheduler' => new SchedulerConfigurator(),
            'env' => new ServerConfigurator(),
            default => null,
        };
    }


    /**
     * @return void
     */
    public function exec()
    {
        switch ($this->subject) {
            case 'server':
                $this->processServerCommand();

                return;
            case 's3-proxy':
                $this->processS3ProxyServerCommand();

                return;
            case 'queue':
                $this->processQueueCommand();

                return;
            case 'scheduler':
                $this->processSchedulerCommand();

                return;
            case 'env':
                $this->processEnvCommands();

                return;
            case '--version':
            case '-v':
            case 'version';
                echo "Version: ".$this->getVersion()."\n";

                return;
            case 'license';
                require "license.php";

                return;
            default:
                echo "Command subject not found: \n\n";
                echo "Laralord command format: 'laralord <subject> <command> ' \n\n";
                echo "Supported subjects: server, s3-proxy, queue, scheduler, env, license, version \n\n";
        }
    }


    /**
     * @throws \Exception
     */
    private function processS3ProxyServerCommand()
    {
        $this->configurator->loadConfig();
        $server = new S3ProxyServer($this->configurator);
        $this->parseArguments();

        switch ($this->command) {
            case 'start':
            case 'serve':
                $server->start();
                break;
            case 'help':
                $version = $this->getVersion();
                echo "Version: $version \n\n";
                $server->help();

                break;
            case 'version':
                $version = $this->getVersion();
                echo "Laralord Server \n\n";
                echo "Version: $version \n\n";

                break;
            default:
                echo "The command not specified. Use one of following: serve, help \n\n";
                exit(1);
        }
    }


    private function processServerCommand()
    {
        $this->configurator->loadConfig();
        $server = new Server($this->configurator);

        switch ($this->command) {
            case 'start':
            case 'serve':
                $this->parseArguments();
                $server->start();
                break;
            case 'env:store':
                $server->envStore($args[2] ?? '');
                break;
            case 'help':
                $version = $this->getVersion();
                echo "Version: $version \n\n";
                $server->help();

                break;
            case 'version':
                $version = $this->getVersion();
                echo "Laralord Server \n\n";
                echo "Version: $version \n\n";

                break;
            default:
                echo "The command not specified. Use one of following: serve, help \n\n";
                exit(1);
        }
    }


    private function processQueueCommand()
    {
        $this->configurator->loadConfig();
        $queue = new Queue($this->configurator);

        switch ($this->command) {
            case 'work':
            case 'start':
                $this->parseArguments();
                $queue->run();
                break;
            case 'help':
                echo "Version: {$this->getVersion()} \n";
                // $this->queue->help();
                break;
            case 'version':
                $version = $this->getVersion();
                echo "Laralord multi-tenant queue \n\n";
                echo "Version: $version \n\n";

                break;
            default:
                echo "The command not specified. Use one of following: serve, help \n\n";
                exit(1);
        }
    }


    private function processSchedulerCommand()
    {
        $this->configurator->loadConfig();

        $scheduler = new Scheduler($this->configurator);

        switch ($this->command) {
            case 'run':
            case 'start':
                $this->parseArguments();
                $scheduler->run();
                break;
            case 'help':
                echo "Version: {$this->getVersion()} \n";
                // $this->queue->help();
                break;
            case 'version':
                $version = $this->getVersion();
                echo "Laralord multi-tenant scheduler \n\n";
                echo "Version: $version \n\n";

                break;
            default:
                echo "The command not specified. Use one of following: run - alias start, help, version \n\n";
                exit(1);
        }
    }


    private function processEnvCommands()
    {
        $this->configurator->loadConfig();
        $server = new Server($this->configurator);

        switch ($this->command) {
            case 'store':
                try {
                    $file = $server->envStore();
                } catch (EnvSourceNotFound $e) {
                    Log::error($e->getMessage());
                    exit (1);
                }
                echo "Env variables stored on $file" . \PHP_EOL;
                break;
            case 'list':
                Log::setExitOnError(true);
                $envResolver = $server->initEnvResolver();
                /** @var array $tenantIds */
                $tenantIds = $envResolver->listSecrets();
                \array_walk($tenantIds, function ($tenantId) {
                    echo $tenantId.\PHP_EOL;
                });

                break;
            default:
                echo "The command '{$this->command}' doesn't exist. Use one of following: store, list \n\n";
                exit(1);
        }

        return null;
    }


    private function parseArguments(array $args = [])
    {
        $arguments = $args ?: \array_slice($_SERVER['argv'], 2);
        Log::debug('Arguments', $arguments);
        $configArguments = isset($this->configurator) ? $this->configurator->getCliOptions() : [];
        Log::debug('Cli Options :', $configArguments);

        \array_walk($arguments, function ($arg) use (&$notFound) {
            Log::info($arg);
            if ($arg === $this->command) {
                return;
            }
            $arg = explode('=', $arg);
            // $key = \ltrim($arg[0], '--');
            $key = $arg[0];
            $value = $arg[1] ?? null;

            $setting = $this->array_first($this->arguments, fn($setting) => \in_array($key, $setting['keys']));

            if (!$setting) {
                throw new \Exception("Argument $key doesnt exists");
            }

            if (!($setting['no_value'] ?? false) && $value === null) {
                throw new \Exception("Argument $key required value");
            }

            if ($setting['no_value'] ?? false) {
                $value = true;
            }

            if (!empty($setting['field'])) {
                $this->configurator->setOptions($key, $value);
                return;
            }
            Log::info("Argument $key");


        });
    }


    private function array_first(array $array, \Closure $closure): mixed
    {
        foreach ($array as $key => $value) {
            $result = (bool) $closure($value, $key);

            if ($result) {
                return $value;
            }
        }

        return null;
    }


    /**
     * @return string
     */
    public function getVersion(): string
    {
        return "@version@";
    }
}
