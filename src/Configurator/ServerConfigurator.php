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
class ServerConfigurator implements ConfiguratorContract
{
    use LoadConfig;

    public static string $logChannel = 'laralord:server';

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
    public int $port = 8000;

    /**
     * @var bool
     */
    public bool $watch = false;

    /**
     * @var string
     */
    public string $mode = self::MODE_SINGLE;

    public string $warmUp = '/';

    public string $tenantKey = 'header.TENANT-ID';
    public string $fallbackTenantId = '';

    public int $maxForks = 5;

    /**
     * @var array|int[]
     */
    public array $options = [
        'dispatch_mode'              => 2,
        'log_level'                  => 4,
        // 'log_file' => '/dev/stderr',
        // 'log_rotation' => Constant::LOG_ROTATION_DAILY,
        'worker_num'                 => 2,
        'task_worker_num'            => 0,  // The amount of task workers to start
        'backlog'                    => 128, // TCP backlog connection number
        'chroot'                     => '/var/www',
        'upload_tmp_dir'             => '/tmp',
        'user'                       => 'www',
        'group'                      => 'www',
        'max_request_execution_time' => 4,
        'reload_async'               => true,
        // 'max_request' => 1000,
        'enable_static_handler'      => true,
        'document_root'              => '/var/www/public',
        'static_handler_locations'   => [],
        'enable_coroutine'           => false,
        'package_max_length'         => 16 * 1024 * 1024,
        'buffer_output_size'         => 64 * 1024 * 1024,
        'http_compression'           => true,
        'http_compression_level'     => 1,
    ];

    /**
     * @var string[]
     */
    public $watchTargets = [
        'app',
        'bootstrap',
        'config',
        'database',
        'public',
        'resources',
        'routes',
        'composer.lock',
        '.env',
    ];

    public $envSourceConfig = [];

    /**
     * @var array
     */
    protected array $config = [
        'SERVER_HOST'               => ['0.0.0.0', 'host', 'Server host.'],
        'SERVER_PORT'               => [8000, 'port', 'Server port. '],
        'SERVER_WATCH'              => [false, 'watch', 'Enable the WATCH mode for development.'],
        'SERVER_WATCH_TARGET'       => [
            [
                'app',
                'bootstrap',
                'config',
                'database',
                'public',
                'resources',
                'routes',
                'composer.lock',
                '.env',
            ],
            'watchTargets',
            "The array of path for change detection.",
        ],
        'SERVER_LOG_LEVEL'          => [
            "NOTICE",
            'logLevel',
            'Log level for server: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY',
        ],
        'SERVER_ENV_FILE'           => [
            '/secrets/.laralord-env',
            'configEnvFile',
            'Server configuration .env file',
        ],
        'SERVER_MAX_FORKS'          => [
            5,
            'maxForks',
            'Maximum number of forks allowed per worker. Limit of concurrent processes calculated by OPTION_WORKERS * (SERVER_MAX_FORKS + 1)',
        ],
        'SERVER_MODE'               => ['single', 'mode', 'Environment resolver mode: single, multi-tenant'],
        'SERVER_WARM_UP'            => [
            '/healthcheck',
            'warmUp',
            'Endpoint which will be triggered to warm up Laravel application on Single mode',
        ],
        'SERVER_TENANT_KEY'         => [
            'header.TENANT-ID',
            'tenantKey',
            'Has format \'method.key\' Tenant resolve method: header, jwt, oidc, cookie.',
        ],
        'SERVER_FALLBACK_TENANT_ID' => [
            '',
            'fallbackTenantId',
            'Fallback tenant key which will be used in case no tenant resolved. Multi-tenant mode',
        ],
        'ENV_SOURCE'                => [
            'vault',
            'envSource',
            'The environments variables source, possible values: --source=vault or dir for multi-tenant. --source=vault,file otherwise',
        ],
        'ENV_TENANT_ID'             => [
            '',
            'tenant_id',
            'Context of execution the command.',
        ],
        'ENV_VAULT_ADDR'            => ["http://vault:8200", 'vault_addr', 'Vault Hashicorp. server address'],
        'ENV_VAULT_TOKEN'           => ["", 'vault_token', 'Vault Hashicorp. token in case token auth used'],
        'ENV_VAULT_STORAGE'         => ["secret", 'vault_storage', 'Vault Hashicorp. KV storage'],
        'ENV_VAULT_PREFIX'          => [
            "",
            'vault_prefix',
            'Vault Hashicorp. key prefix. Path to environments on kv storage',
        ],
        'ENV_VAULT_KEY'             => ["secrets", 'vault_key', 'Vault Hashicorp. secret key for single mode'],
        'ENV_VAULT_AUTH_TYPE'       => ["", 'vault_auth_type', 'Vault Hashicorp. auth type: token, kubernetes'],
        'ENV_VAULT_AUTH_ENDPOINT'   => [
            "kubernetes",
            'vault_auth_endpoint',
            'Vault Hashicorp. auth login endpoint. Default: kubernetes',
        ],
        'ENV_VAULT_SA_TOKEN_PATH'   => [
            "/var/run/secrets/kubernetes.io/serviceaccount/token",
            'vault_sa_token_path',
            'Vault Hashicorp. Service Account token path. Default: /var/run/secrets/kubernetes.io/serviceaccount/token',
        ],
        'ENV_VAULT_AUTH_ROLE'       => ["", 'vault_auth_role', 'Vault Hashicorp. auth role for kubernetes auth'],
        'ENV_VAULT_UPDATE_PERIOD'   => [
            "1",
            'vault_update_period',
            'The period of update the secret in minutes:  0 - update is disabled',
        ],
        'ENV_FILE'                  => ["/var/www/.env", 'env_file', 'Path to project\'s .env'],
        'ENV_DIR'                   => [
            './.envs',
            'envs_dir',
            "The directory with .env files. Required if ENV_SOURCE is 'dir'",
        ],
        'ENV_FILE_UPDATE'           => [
            false,
            'env_file_update',
            'Specify does the .env file update required on Vault secret updated',
        ],

        'OPTION_WORKERS'               => [
            10,
            'worker_num',
            'The number of worker processes to start. By default this is set to the number of CPU cores you have.',
        ],
        'OPTION_TASK_WORKER'           => [0, 'task_worker_num', 'Set the number of task worker processes to create.'],
        'OPTION_USER'                  => [
            'www',
            'user',
            'Set the operating system user of the worker and task worker child processes.',
        ],
        'OPTION_GROUP'                 => [
            'www',
            'group',
            'Set the operating system user of the worker and task worker child processes.',
        ],
        'OPTION_STATIC_FILES_ENABLED'  => [false, 'enable_static_handler', 'Enable the WATCH mode for development.'],
        'OPTION_DOCUMENT_ROOT'         => [
            '/var/www/public',
            'document_root',
            'The base path on the project for static files locations.',
        ],
        'OPTION_STATIC_FILE_LOCATIONS' => [
            [''],
            'static_handler_locations',
            'An array list of directories that are allowed to be served as static files by the Swoole server.',
        ],
        'OPTION_MAX_EXECUTION_TIME'    => [2, 'max_request_execution_time', 'HTTP Server max execution time, seconds'],
        'APP_BASE_PATH'                => ['/var/www', 'basePath', 'Laravel project\'s path'],
    ];


    /**
     *
     */
    public function __construct()
    {
        $this->configEnvFile = $_ENV['SERVER_ENV_FILE'] ?? '';

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
}
