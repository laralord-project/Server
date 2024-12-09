<?php

namespace Server\Traits;

use Server\EnvironmentResolvers\{DirectoryEnvResolver, EnvResolverContract, FileEnvResolver, VaultEnvResolver};
use Server\Log;

trait HasConfigurator
{
    /*
    * @param  string  $name
    *
    * @return mixed
    */
    public function __get(string $name)
    {
        return $this->configurator->{$name};
    }


    /**
     * @param  string  $name
     *
     * @return mixed
     */
    public function __set(string $name, mixed $value)
    {
        return $this->configurator->{$name} = $value;
    }

    /**
     * @return VaultEnvResolver|FileEnvResolver|EnvResolverContract
     * @throws \Exception
     */
    public function initEnvResolver(): VaultEnvResolver|FileEnvResolver|EnvResolverContract
    {
        Log::debug("Init config resolver for {$this->configurator->envSource}");

        return $this->envResolver = match ($this->configurator->envSource) {
            'file' => new FileEnvResolver($this->configurator->envSourceConfig),
            'vault' => new VaultEnvResolver($this->configurator->envSourceConfig),
            'dir' => new DirectoryEnvResolver($this->configurator->envSourceConfig),
            default => throw new \Exception("Environment resolver {$this->configurator->envSource} doesn't exists"),
        };
    }
}
