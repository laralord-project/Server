<?php

namespace Server\EnvironmentResolvers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use OpenSwoole\Timer;
use Server\Application\Environment;
use Server\Configurator\ServerConfigurator;
use Server\EnvironmentResolvers\Traits\CommonVariables;
use Server\EnvironmentResolvers\Traits\ExcludeVariables;
use Server\Log;
use Server\Server;

/**
 * Class VaultEnvResolver
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\EnvironmentResolvers
 */
class VaultEnvResolver extends MultiTenantResolverAbstract implements EnvResolverContract
{
    use CommonVariables, ExcludeVariables;

    const AUTH_TYPE_TOKEN = 'token';

    const AUTH_TYPE_KUBERNETES = 'kubernetes';

    /**
     * @var string
     */
    private $version = 'v1';

    /**
     * @var mixed|string
     */
    private string $vaultAddr;

    /**
     * @var string|mixed
     */
    private string $token;

    private array $tokenData;

    /**
     * @var int the timestamp of token expiration
     */
    private int $tokenExpireAt;

    /**
     * @var string|mixed
     */
    private string $storage = 'secret';

    /**
     * @var mixed|string
     */
    private mixed $secretPrefix;

    private string $authType = self::AUTH_TYPE_TOKEN;

    /**
     * @var string
     */
    private string $updatedAt;

    /**
     * Single Mode mean - the server is served only for one application
     * if true - multi-tenancy - disabled
     *
     * @var bool|mixed
     */
    private bool $singleMode = true;

    /**
     * The secret key for single mode,
     * for multi-tenant mode this key should be the project path
     * where the application keys will be listed
     *
     * @var string
     */
    private string $key;

    /**
     * Sync project's .env file with Vault secret
     * Could be required if some other service use the env variables
     *
     * @var bool|string
     */
    public bool|string $envFileUpdate = false;

    /**
     * @var Environment
     */
    private Environment $env;

    private int $updatePeriod = 1;

    /**
     * @var array
     */
    private array $headers = [
        'X-Vault-Request' => true,
        'Content-Type'    => 'application/json',
    ];

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var bool
     */
    private bool $hasBeenBootstraped = false;


    /**
     * @param  Server  $server
     * @param  array  $config
     */
    public function __construct(private array $config = [])
    {
        $this->configure($config);
    }


    /**
     * @param $config
     *
     * @return void
     * @throws \Exception
     */
    public function configure($config)
    {
        $this->config = $config;
        $this->vaultAddr = $config['vault_addr'] ?: "{$config['vault_schema']}://{$config['vault_addr']} : {$config['vault_port']}";
        $this->token = $config['vault_token'] ?? '';
        $this->secretPrefix = trim($config['vault_prefix'], " /");
        $this->singleMode = ($config['mode'] ?? ServerConfigurator::MODE_SINGLE) !== ServerConfigurator::MODE_MULTI_TENANT;
        $this->key = $config['vault_key'];
        $this->storage = trim($config['vault_storage'], " /");
        $this->updatePeriod = (int) $config['vault_update_period'] ?? 0;
        $this->authType = $config['vault_auth_type'];

        if ($this->authType === self::AUTH_TYPE_TOKEN && !$this->token) {
            throw new \Exception('The token required for Vault auth');
        }

        if ($this->authType === self::AUTH_TYPE_KUBERNETES) {
            if (empty($this->config['vault_auth_role'])) {
                throw new \Exception('The ENV_VAULT_AUTH_ROLE required for Vault kubernetes auth');
            }

            if (!\file_exists($this->config['vault_sa_token_path'])) {
                throw new \Exception("ServiceAccount token file not found on {$this->config['vault_sa_token_path']}");
            }
        }

        if ($this->singleMode) {
            $this->envFileUpdate = \in_array($config['env_file_update'], ['true', true], strict: true)
                ? $this->config['env_file']
                : false;
            $this->key = trim($config['vault_key'], " /");
        }

        $this->initClient();
    }


    /**
     * @return void
     */
    public function initClient()
    {
        $this->headers['X-Vault-Token'] = "$this->token";

        $this->client = new Client([
            "base_uri" => "{$this->vaultAddr}/{$this->version}/$this->storage/",
            "headers"  => $this->headers,
        ]);
    }


    /**
     * @return bool
     * @throws GuzzleException
     */
    public function boot(): bool
    {
        try {
            if ($this->singleMode) {
                $this->env = $this->loadSecret($this->key, false);
                $this->env->id = 0;
                $this->env->store();

                if ($this->envFileUpdate) {
                    $this->storeToEnvFile($this->key, $this->envFileUpdate);
                }

                Log::info("Environment variables loaded from Vault");

                return true;
            }

            parent::boot();
        } catch (ClientException $e) {
            $this->processError($e);

            return false;
        }

        return true;
    }


    /**
     * @param  \Closure|null  $action
     *
     * @return void
     */
    public function sync(\Closure $action = null): void
    {
        if (!$this->updatePeriod) {
            Log::alert("Vault sync disabled.");

            return;
        }

        Timer::tick($this->updatePeriod * 1000, function () use ($action) {
            Log::notice("Sync environment variables with Vault.");

            try {
                if ($this->singleMode) {
                    $env = $this->loadSecret($this->key, false);
                    $env->id = 0;
                    $changes = $this->env->isDiff($env);

                    if ($changes) {
                        $this->env = $env;
                        $this->env->store();

                        // execute the external callback
                        $action && $action($env);

                        // storet the updates to file if required
                        $this->envFileUpdate && $this->storeToEnvFile($this->key, $this->envFileUpdate);
                    }
                } else {
                    $keys = $this->listSecrets();
                    $changes = $this->refreshEnvironments($keys);

                    if ($changes) {
                        $action && $action($changes);
                    }
                }
            } catch (ClientException $e) {
                $this->processError($e);
            }
        });
    }



    /**
     * @param $appId
     *
     * @return array
     * @throws \Exception
     */
    public function getEnvironmentVariables($appId = ''): array
    {
        if ($this->singleMode) {
            return $this->env->toArray();
        }

        return parent::getEnvironmentVariables($appId); // TODO: Change the autogenerated stub
    }


    /**
     * @return array
     * @throws GuzzleException
     */
    public function listSecrets(): array
    {
        $this->resolveToken();
        $response = $this->client->request('LIST', "metadata/{$this->secretPrefix}");

        $json = \json_decode($response->getBody(), true);

        // skip sub-keys
        return \array_filter($json['data']['keys'] ?? [], function ($value) {
            return !\str_ends_with($value, '/');
        });
    }


    /**
     * @param  string  $key
     *
     * @return Environment|null
     * @throws GuzzleException
     */
    public function loadSecret(string $key = '', $quiet = true): Environment|null
    {
        $key = $key ?: $this->key;
        $secretPath = $this->getSecretPath($key);

        try {
            $this->resolveToken();
            $response = $this->client->get("data/$secretPath");
        } catch (ClientException $e) {
            Log::error($e);

            if (!$quiet) {
                throw $e;
            }

            return null;
        }

        $json = \json_decode($response->getBody(), true);

        $data = $json['data']['data'] ?? throw new \Exception("No secrets for application $key");
        $metadata = $json['data']['metadata'] ?? throw new \Exception("No metadata for application $key");

        return (new Environment($data, $metadata['version'], $metadata['created_time']))
            ->setKey($key);
    }


    /**
     * @param $key
     *
     * @return string
     */
    public function getSecretPath($key): string
    {
        $pathParts = \array_filter([$this->secretPrefix, $key], fn($part) => (bool) $part);

        return \implode('/', $pathParts);
    }


    /**
     * @param  ClientException  $e
     *
     * @return void
     */
    private function processError(ClientException $e)
    {
        Log::error("Vault request failed: ".$e->getRequest()->getMethod()
            .' '
            .$e->getRequest()->getUri()
        );
        Log::error("With Code: ".$e->getResponse()->getStatusCode());
        Log::error("Failed to fetch the secret: ".$e->getResponse()->getBody()."\n");

        // exit only if no envs was loaded previously
        if (!isset($this->env) && !isset($this->envs)) {
            exit(1);
        }
    }


    /**
     * @return Environment
     */
    public function getEnvironment(): Environment
    {
        return $this->env;
    }


    /**
     * @return bool
     */
    public function isTokenExpired(): bool
    {
        if (empty($this->token) || empty($this->tokenExpireAt)) {
            return true;
        }

        // renew the token 10 minutes before the token expiration
        if (($this->tokenExpireAt - time()) < 10 * 60) {
            return true;
        }

        return false;
    }


    /**
     * @return bool|void
     * @throws GuzzleException
     */
    public function resolveToken()
    {
        if ($this->authType !== self::AUTH_TYPE_KUBERNETES) {
            return true;
        }

        if ($this->isTokenExpired()) {
            Log::info('Retrieving the token using kubernetes auth, token file: '.$this->config['vault_sa_token_path']);

            $saToken = \file_get_contents($this->config['vault_sa_token_path']);
            // http://vault.databases:8200/v1/auth/kubernetes/login
            $authEndpoint = $this->config['vault_auth_endpoint'] ?? 'kubernetes';
            $url = "{$this->vaultAddr}/{$this->version}/auth/$authEndpoint/login";
            $headers = $this->headers;

            if (isset($headers['X-Vault-Token'])) {
                unset($headers['X-Vault-Token']);
            }

            $options = [
                'headers' => $headers,
                'json'    => [
                    "jwt"  => $saToken,
                    "role" => $this->config['vault_auth_role'],
                ],
            ];

            $response = $this->client->post($url, $options);

            if ($response->getStatusCode() !== 200) {
                Log::error('Failed to retrieve the token using SA:  '.$response->getStatusCode().$response->getBody());

                return false;
            }

            $this->tokenData = \json_decode((string) $response->getBody(), true);

            $this->token = $this->tokenData['auth']['client_token'];
            $this->tokenExpireAt = time() + $this->tokenData['auth']['lease_duration'];

            $this->headers['X-Vault-Token'] = "$this->token";
            $this->initClient();
        }
    }
}
