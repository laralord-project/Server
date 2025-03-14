<?php

namespace Server\EnvironmentResolvers;

use Server\Application\Environment;
use Server\Server;

interface EnvResolverContract
{

    public function __construct(array $config = []);

    public function boot(): bool;


    /**
     * Return the Environment variables array by Application ID
     *
     * @param string $appId
     * @param bool $asArray
     *
     * @return array|Environment|null
     */
    public function resolve(string $appId = ''):? Environment;

    /**
     * Return the list of all applications
     *
     * @return array
     */
    public function getApplications(): array;

    /**
     * Add the array of the common environment variables
     * which will be added to each environment
     *
     * @param array $common
     *
     * @return self
     */
    public function common(array $common): self;

    /**
     * Set the environment variable keys
     * which will be excluded from each environment
     *
     * @param array $excludeKeys
     *
     * @return self
     */
    public function exclude(array $excludeKeys): self;
    /**
     * Used for single tenant only
     */
    public function getEnvironment():? Environment;

    /**
     * Sync internal storage of all environment variables with external source
     *
     * @param \Closure $action
     *
     * @return void
     */
    public function sync(\Closure $action): void;
}
