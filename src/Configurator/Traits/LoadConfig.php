<?php

namespace Server\Configurator\Traits;

use Dotenv\Dotenv;
use Psr\Log\LogLevel;
use Server\EnvironmentResolvers\DirectoryEnvResolver;
use Server\Log;

trait LoadConfig
{
    public string $configEnvFile = '';

    /**
     * Environment variables loaded for .env on Server root
     *
     * @var array
     */
    protected array $serverEnvs = [];

    public string $logLevel = LogLevel::ERROR;

    private array $synchronizerOptions = [];

    public string $envSource = 'vault';

    public array $arguments = [];

    /**
     * @return $this
     */
    public function loadConfig(): self
    {
        if ($this->configEnvFile) {
            if (\file_exists($this->configEnvFile)) {
                $this->serverEnvs = Dotenv::parse(\file_get_contents($this->configEnvFile));
            } else {
                Log::error("Server config file '{$this->configEnvFile}' not found ");
                exit(2);
            }
        }

        $this->parseArguments();

        array_walk($this->config, function ($option, $key) {
            $argKey = $option[1] ?? '';

            if (!empty($this->arguments[$argKey])) {
                Log::info('Retrieved from arguments', [$key , $this->arguments[$argKey]]);
            }
            $value = $this->arguments[$argKey] ?? $_ENV[$key] ?? $this->serverEnvs[$key] ?? $option[0];
            $this->setOptions($key, $value);
        });

        if (\is_string($this->watchTargets)) {
            $this->watchTargets = \explode(',', $this->watchTargets);
        }

        // Create/update the logger
        Log::init(self::$logChannel, logLevel: $this->logLevel);
        Log::notice("Server config loaded from {$this->configEnvFile}");
        Log::notice('Log channel: '.self::$logChannel.' Log Level:'.$this->logLevel);
        return $this;
    }


    /**
     * @return array|int[]
     */
    public function getOptions()
    {
        return $this->options;
    }


    /**
     * @param $key
     * @param $value
     *
     * @return void
     */
    public function setOptions($key, $value)
    {
        $option = $this->config[$key];
        $configType = $this->getFieldType($key);
        $configKey = $option[1] ?? null;

        if (!$configKey) {
            return;
        }

        switch ($configType) {
            case self::CONFIG_TYPE_SERVER:
                if (\property_exists($this, $configKey)) {
                    $this->{$configKey} = $value ?: $_ENV[$key] ?? $this->serverEnvs[$key] ?? $option[0];

                    if ($configKey === 'mode') {
                        $this->envSourceConfig['mode'] = $this->{$configKey};
                    }
                } else {
                    Log::error("The property $configKey doesn't exists on config");
                }
                break;
            case self::CONFIG_TYPE_OPTION:
                $this->options[$configKey] = $value ?: $_ENV[$key] ?? $this->serverEnvs[$key] ?? $option[0];
                break;
            case self::CONFIG_TYPE_ENVIRONMENT:
                $this->envSourceConfig[$configKey] = $value ?:$_ENV[$key] ?? $this->serverEnvs[$key] ?? $option[0];

                break;
            case self::CONFIG_TYPE_SYNCHRONIZER:
                if (!isset($this->synchronizerOptions)) {
                    $this->synchronizerOptions = [];
                }

                $this->synchronizerOptions[$configKey] = $value ?: $_ENV[$key] ?? $this->serverEnvs[$key] ?? $option[0];

                break;
            default:
                Log::warning("The config $configKey not recognised");
        }
    }


    /**
     * @return array
     */
    public function listServerVariables(): array
    {
        return \array_keys($this->config);
    }


    /**
     * @param $key
     *
     * @return string
     */
    private function getFieldType($key)
    {
        $byKey = match ($key) {
            'SCHEDULER_LOG_LEVEL',
            'ENV_SOURCE',
            'QUEUE_LOG_LEVEL' => self::CONFIG_TYPE_SERVER,
            default => null
        };

        if ($byKey) {
            return $byKey;
        }

        $prefix = \explode('_', $key)[0];

        return match ($prefix) {
            'OPTION','QUEUE','SCHEDULER' => self::CONFIG_TYPE_OPTION,
            'ENV' => self::CONFIG_TYPE_ENVIRONMENT,
            'SYNC' => self::CONFIG_TYPE_SYNCHRONIZER,
            'SERVER', 'S3-PROXY' => self::CONFIG_TYPE_SERVER,
            default => self::CONFIG_TYPE_SERVER
        };
    }


    /**
     * @return array
     */
    public function getRedisConfig(): array {
        return [
            'host' => $this->synchronizerOptions['redis_host'],
            'port' => $this->synchronizerOptions['redis_port'],
            'prefix' => $this->synchronizerOptions['redis_prefix'],
            'username' => $this->synchronizerOptions['redis_username'],
            'password' => $this->synchronizerOptions['redis_password'],
        ];
    }


    /**
     * @return array
     */
    public function getCliOptions(): array {
        return \array_map(fn(array $value) => $value[1], $this->config);
    }

    function parseArguments(array $args = []): array
    {
        $args = $args ?: $_SERVER['argv'];

        // Filter only arguments that start with "--"
        $filteredArgs = array_filter($args, fn($arg) => str_starts_with($arg, '--'));

        // Convert each argument into a key-value pair
        return $this->arguments = array_reduce($filteredArgs, function ($result, $arg) {
            // Remove the "--" prefix and split by "="
            [$key, $value] = explode('=', ltrim($arg, '--'), 2) + [null, null];

            // Extra mapping arguments aliases
            $key= match($key) {
                'file' => 'env_file',
                'source' => 'envSource',
                'dir' => 'envs_dir',
                'log-level' => 'logLevel',
                default => $key
            };

            if ($key !== null && $value !== null) {
                $result[$key] = $value;
            }
            return $result;
        }, []);
    }

}
