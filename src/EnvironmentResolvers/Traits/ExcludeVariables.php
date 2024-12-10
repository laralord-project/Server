<?php

namespace Server\EnvironmentResolvers\Traits;

use Server\Application\Environment;
use Server\EnvironmentResolvers\{FileEnvResolver, VaultEnvResolver};

/**
 *
 */
trait ExcludeVariables
{
    /**
     * @param  array  $exclude
     *
     * @return VaultEnvResolver|FileEnvResolver|ExcludeVariables
     */
    public function exclude(array $exclude): self
    {
        Environment::$exclude = $exclude;

        return $this;
    }

}
