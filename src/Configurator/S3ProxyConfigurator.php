<?php

namespace Server\Configurator;

use Server\Configurator\Traits\LoadConfig;
use Server\Log;

/**
 * Class ServerConfigurator
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Configurator
 */
class S3ProxyConfigurator implements ConfiguratorContract
{
    use LoadConfig;

    public static string $logChannel = 'laralord:s3-proxy';

    /**
     * @var string
     */
    public string $basePath = '/var/www';

    /**
     * @var string
     */
    public string $host = '0.0.0.0';

    /**
     * @var int
     */
    public int $port = 8001;

    /**
     * @var bool
     */
    public bool $watch = false;

    /**
     * @var string
     */
    public string $mode = self::MODE_SINGLE;

    public string $warmUp = '/';

    public string $cacheDir = "/tmp/s3-proxy";

    public string $bucket = "";
    public string $region = "";
    public string $accessKey = "";
    public string $secretKey = "";
    public string $s3Endpoint = "";
    public string $s3Scheme = "";
    public bool $s3UsePathStyle = false;

    /**
     * @var array|int[]
     */
    public array $options = [
        'log_level'                  => 4,
        // 'log_file' => '/tmp/openswoole.log',
        // 'log_rotation' => Constant::LOG_ROTATION_DAILY,
        'worker_num'                 => 4,
        'task_worker_num'            => 8,  // The amount of task workers to start
        'backlog'                    => 128, // TCP backlog connection number
        'chroot'                     => '/var/www',
        'upload_tmp_dir'             => '/tmp',
        'user'                       => 'www',
        'group'                      => 'www',
        'max_request_execution_time' => 2,
        'reload_async'               => true,
        // 'max_request' => 1000,
        'enable_static_handler'      => false,
        'enable_coroutine'           => true,
        'package_max_length'         => 16 * 1024 * 1024,
        'compression_min_length'     => 128,
    ];

    /**
     * @var string[]
     */
    public $watchTargets = [
        '.env',
    ];

    public $envSourceConfig = [];

    /**
     * @var array
     */
    protected array $config = [
        'S3_PROXY_HOST'         => ['0.0.0.0', 'host', 'Server host.'],
        'S3_PROXY_PORT'         => [8001, 'port', 'Server port. '],
        'S3_PROXY_WATCH'        => [false, 'watch', 'Enable the WATCH mode for development.'],
        'S3_PROXY_WATCH_TARGET' => [
            [
                "/secrets/.laralord-env",
            ],
            'watchTargets',
            "The array of path for change detection.",
        ],
        'SERVER_LOG_LEVEL'      => [
            "NOTICE",
            'logLevel',
            'Log level for server: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY',
        ],
        'SERVER_ENV_FILE'       => [
            '/secrets/.laralord-env',
            'configEnvFile',
            'Server configuration .env file',
        ],
        'SERVER_ENV_SOURCE'     => [
            'file',
            'envSource',
            'The environments variables source, possible values: --file, --file=$filepath, vault',
        ],
        'S3_CACHE_DIR'          => [
            '/tmp/s3-proxy',
            'cacheDir',
            'The directory to store the cache',
        ],

        'S3_PROXY_BUCKET'            => ["", 'bucket', 'S3 bucket name'],
        'S3_PROXY_REGION'            => ["", 'region', 'S3 bucket region'],
        'S3_PROXY_ACCESS_KEY'        => ["", 'accessKey', 'S3 Access Key'],
        'S3_PROXY_SECRET_KEY'        => ["", 'secretKey', 'S3 Secret Key'],
        'S3_PROXY_S3_ENDPOINT'       => ["", 's3Endpoint', 'S3 Endpoint'],
        'S3_PROXY_S3_SCHEME'         => ["https", 's3Scheme', 'S3 scheme'],
        'S3_PROXY_S3_USE_PATH_STYLE' => [false, 's3UsePathStyle', 'S3 use path style endpoint'],

        'ENV_VAULT_ADDR'          => ["http://vault:8200", 'vault_addr', 'Vault Hashicorp. server address'],
        'ENV_VAULT_TOKEN'         => ["", 'vault_token', 'Vault Hashicorp. token in case token auth used'],
        'ENV_VAULT_AUTH_TYPE'     => ["", 'vault_auth_type', 'Vault Hashicorp. auth type: token, kubernetes'],
        'ENV_VAULT_AUTH_ENDPOINT' => [
            "kubernetes",
            'vault_auth_endpoint',
            'Vault Hashicorp. auth login endpoint. Default: kubernetes',
        ],
        'ENV_VAULT_SA_TOKEN_PATH' => [
            "/var/run/secrets/kubernetes.io/serviceaccount/token",
            'vault_sa_token_path',
            'Vault Hashicorp. Service Account token path. Default: /var/run/secrets/kubernetes.io/serviceaccount/token',
        ],
        'ENV_VAULT_AUTH_ROLE'     => ["", 'vault_auth_role', 'Vault Hashicorp. auth role for kubernetes auth'],
        'ENV_VAULT_STORAGE'       => ["secret", 'vault_storage', 'Vault Hashicorp. KV storage'],
        'ENV_VAULT_PREFIX'        => [
            "",
            'vault_prefix',
            'Vault Hashicorp. key prefix. Path to environments on kv storage',
        ],
        'ENV_VAULT_KEY'           => ["secrets", 'vault_key', 'Vault Hashicorp. secret key for single mode'],
        'ENV_VAULT_UPDATE_PERIOD' => [
            "1",
            'vault_update_period',
            'The period of update the secret in minutes:  0 - update is disabled',
        ],
        'ENV_FILE'                => ["/var/www/.env", 'env_file', 'Path to project\'s .env'],
        'ENV_FILE_UPDATE'         => [
            false,
            'env_file_update',
            'Specify does the .env file update required on Vault secret updated',
        ],

        'OPTION_S3_PROXY_WORKERS'   => [
            10,
            'worker_num',
            'The number of worker processes to start. By default this is set to the number of CPU cores you have.',
        ],
        'OPTION_TASK_WORKER'        => [0, 'task_worker_num', 'Set the number of task worker processes to create.'],
        'OPTION_USER'               => [
            'www',
            'user',
            'Set the operating system user of the worker and task worker child processes.',
        ],
        'OPTION_GROUP'              => [
            'www',
            'group',
            'Set the operating system user of the worker and task worker child processes.',
        ],
        'OPTION_MAX_EXECUTION_TIME' => [2, 'max_request_execution_time', 'HTTP Server max execution time, seconds'],
        'APP_BASE_PATH'             => ['/www', 'basePath', 'Laravel project\'s path'],
    ];


    /**
     *
     */
    public function __construct()
    {
        $this->configEnvFile = $_ENV['S3_PROXY_ENV_FILE'] ?? '';

        Log::debug('Server configurator from env file: ' . $_ENV['SERVER_ENV_FILE']);
    }


    /**
     * @return void
     */
    public function getInfo()
    {
        echo "Used following ENV variables for server configuration: \n";
        array_walk($this->config, function ($field, $key) {
            $default = \is_array($field[0]) ? \json_encode($field[0]) : $field[0];
            $configType = $this->getFieldType($key);

            $type = match ($configType) {
                self::CONFIG_TYPE_SERVER => 'Server config',
                self::CONFIG_TYPE_OPTION => 'Config Option',
                self::CONFIG_TYPE_ENVIRONMENT => 'Project\'s environment config',
                default => 'Project settings'
            };

            echo "$type '$key' {$field[2]} Default: ";
            echo \is_string($field[0]) ? "'$default'" : $default;
            echo "\n";
        });

        echo "\nMore options could be found on documentation: \n";
        echo "https://openswoole.com/docs/modules/swoole-server/configuration \n\n";
    }


    public function getCliFields(): array
    {
        return [];
    }
}
