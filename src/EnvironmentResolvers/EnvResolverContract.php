<?php

namespace Server\EnvironmentResolvers;

use Server\Application\Environment;
use Server\Server;

interface EnvResolverContract
{

    public function __construct(array $config = []);

    public function boot(): bool;
    public function getEnvironmentVariables(): array;
    public function getApplications(): array;

    public function common(array $common): self;
    public function exclude(array $excludeKeys): self;
    /**
     * Used for single tenant only
     */
    public function getEnvironment():? Environment;

    public function sync(\Closure $action): void;
}
