<?php

namespace Server\EnvironmentResolvers;

use GuzzleHttp\Exception\GuzzleException;
use Server\Application\Environment;
use Server\Log;

abstract class MultiTenantResolverAbstract implements EnvResolverContract
{
    abstract public function sync(\Closure $action = null): void;


    abstract public function listSecrets(): array;


    abstract protected function loadSecret(string $key = '', $quiet = true): ?Environment;


    abstract public function getSecretPath(string $key): string;


    /**
     * @return bool
     * @throws \Exception
     */
    public function boot(): bool
    {
        Environment::setResolver($this);

        $keys = $this->listSecrets();
        $this->refreshEnvironments($keys);

        return true;
    }


    /**
     * Refresh environment
     *
     * @param  array  $keys
     *
     * @return array - the list of changes on environment variables
     * @throws GuzzleException
     */
    public function refreshEnvironments(array $keys): array
    {
        Log::info('Tenant keys: ', $keys);

        $changes = [
            'created' => [],
            'updated' => [],
            'removed' => [],
        ];

        \array_walk($keys, function ($key, $index) use (&$changes) {
            $saved = Environment::find($key);
            $env = $this->loadSecret("$key", true);

            if (!$env) {
                return;
            }

            $env->id = $index;
            $env['TENANT_ID'] = $key;

            if (!$saved) {
                $changes['created'][] = $key;
            } elseif ($saved->isDiff($env)) {
                $changes['updated'][] = $key;
            }

            $env->store();
        });

        $storedEnvs = Environment::getKeys();

        // unload removed applications
        $removed = array_diff($storedEnvs, \array_values($keys));

        $changes['removed'] = \array_values($removed);

        \array_walk($removed, function ($key) {
            Environment::delete($key);
        });

        $changes = \array_filter($changes, fn(array $value) => (bool) $value);

        if (!$changes) {
            Log::debug('No env variables changes');
        } else {
            Log::debug('Environment variables changed', $changes);
        }

        return $changes;
    }


    /**
     * @param string $appId
     * @param bool $asArray
     *
     * @return array|Environment|null
     * @throws \Exception
     */
    public function resolve(string $appId = ''):? Environment
    {
        if (!$appId) {
            throw new \Exception('The application\'s ID is not specified.');
        }

        return Environment::find($appId);
    }

    /**
     * @return array
     */
    public function getApplications(): array
    {
        return Environment::getKeys();
    }


    /**
     * @param  string  $file
     *
     * @return bool
     */
    public function storeToEnvFile(string $key, string $file): bool|string
    {
        $env = $this->loadSecret($key)->toArray();
        $envFileContent = "";

        \array_walk($env, function ($value, $key) use (&$envFileContent) {
            if (!$key) {
                return;
            }
            if (\is_null($value)) {
                $value = "null";
            }

            if (\is_array($value)) {
                $value = \json_encode($value);
            }

            // Use double quotes and escape necessary characters for shell compatibility
            $escapedValue = \addcslashes($value, "\$\"\\");
            $envFileContent .= "$key=\"$escapedValue\"\n";
        });

        $dirname = \pathinfo($file)['dirname'] ?? '';

        if ($dirname && !\file_exists($dirname)) {
            \mkdir($dirname, 0777, true);
        }

        $updated = \file_put_contents($file, $envFileContent);

        Log::notice("Env file '{$file}' ".($updated ? 'updated' : 'update failed'));

        return $file;
    }
}
