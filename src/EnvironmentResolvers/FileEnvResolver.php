<?php

namespace Server\EnvironmentResolvers;

use Dotenv\Dotenv as SwooleDotEnv;
use OpenSwoole\Timer;
use Server\{Application\Environment, EnvironmentResolvers\Traits\CommonVariables,
    EnvironmentResolvers\Traits\ExcludeVariables, Log, Watcher};

class FileEnvResolver implements EnvResolverContract
{
    use CommonVariables, ExcludeVariables;

    public string $envFile = '';

    protected Environment $env;


    public function __construct(array $config = [])
    {
        $this->envFile = $config['env_file'];
        Log::debug('Using '.self::class."to resolve environment variables");
    }


    public function boot(): bool
    {
        if (!\file_exists($this->envFile)) {
            Log::warning("File $this->envFile not found.");
            Log::warning("Running without project's environment variables");
            $this->env = new Environment([], 0, \time());
            $this->env->id = 0;
            $this->env->setKey($this->envFile);
            $this->env->store();
        } else {
            $vars = SwooleDotEnv::parse(\file_get_contents($this->envFile));
            $createdTime = \filemtime($this->envFile);

            $this->env = new Environment($vars, 0, $createdTime);
            $this->env->id = 0;
            $this->env->setKey($this->envFile);
            $this->env->store();
            Log::debug("Env Variables loaded from: {$this->envFile}");
        }

        return true;
    }


    public function sync(\Closure $action): void
    {
        $watcher = new Watcher();
        $watcher->watch([$this->envFile]);
        $watcher->addCallback(function () use ($action) {
            $this->boot();
            $action();
        });
        // sync the environments twice per second
        Timer::tick(500, fn() => $watcher->detectChanges());
    }


    public function resolve(string $appId = ''):? Environment
    {
        return $this->env;
    }


    public function getApplications(): array
    {
        return [];
    }


    public function getEnvironment(): Environment
    {
        return $this->env;
    }
}
