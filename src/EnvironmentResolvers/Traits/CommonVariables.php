<?php

namespace Server\EnvironmentResolvers\Traits;

use Server\Application\Environment;
use Server\EnvironmentResolvers\{FileEnvResolver, VaultEnvResolver};

trait CommonVariables
{
    /**
     * @param  array  $common
     *
     * @return FileEnvResolver|CommonVariables|VaultEnvResolver
     */
    public function common(array $common): self
    {
        Environment::$common = $common;

        return $this;
    }

}
