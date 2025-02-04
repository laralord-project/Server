<?php

namespace Server\EnvironmentResolvers;

use Dotenv\Dotenv;
use Swoole\Timer;
use Server\Application\Environment;
use Server\EnvironmentResolvers\Traits\CommonVariables;
use Server\EnvironmentResolvers\Traits\ExcludeVariables;
use Server\Exceptions\EnvSourceNotFound;
use Server\Log;
use Server\Watcher;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Class DirectoryEnvResolver
 *
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\EnvironmentResolvers
 */
class DirectoryEnvResolver extends MultiTenantResolverAbstract implements EnvResolverContract
{
    use CommonVariables, ExcludeVariables;

    protected string $directory = './.envs';

    protected string $filenameRegex = '/^\.env\.([a-zA-Z0-9_-]+)$/';


    public function __construct(array $config = [])
    {
        $this->directory = $config['envs_dir'] ?? $this->directory;
    }


    public function boot(): bool
    {
        if (!\file_exists($this->directory)) {
            throw new EnvSourceNotFound("The env source directory {$this->directory} not found");
        }

        if (!\is_dir($this->directory)) {
            throw new EnvSourceNotFound("Provided env source path {$this->directory} is not a directory");
        }

        return parent::boot();
    }


    public function listSecrets(): array
    {
        $files = \array_filter(
            array_diff(\scandir($this->directory), ['.', '..']),
            function ($filename) {
                $path = "{$this->directory}/$filename";

                if (!\is_file($path)) {
                    Log::debug("$path is Not A File");
                    return false;
                }

                if (\str_starts_with('.env.', $filename)) {
                    Log::debug("$path is Not .env");
                    return false;
                }

                if (!\preg_match($this->filenameRegex, $filename, $matches)) {
                    Log::warning("Wrong filename format $filename. File skipped ");

                    return false;
                }

                return true;
            });

        return array_values(\array_map(function ($filename) {
            \preg_match($this->filenameRegex, $filename, $matches);

            return $matches[1];
        }, $files));
    }


    public function loadSecret(string $key = '', $quiet = true): ?Environment
    {
        $sourceFilePath = $this->getSecretPath($key);

        if (!\file_exists($sourceFilePath)) {
            throw new EnvSourceNotFound("The $sourceFilePath file not found");
        }
        $vars = Dotenv::parse(\file_get_contents($this->getSecretPath($key)));

        return (new Environment($vars, 0, \filemtime($this->getSecretPath($key))))
            ->setId(0)
            ->setKey($key)
            ->store();
    }


    public function getSecretPath(string $key): string
    {
        return "{$this->directory}/.env.$key";
    }


    public function sync(\Closure $action = null): void
    {
        $watcher = new Watcher();
        $watcher->watch([$this->directory]);
        $watcher->addCallback(function () use ($action) {
            $this->boot();
            $action();
        });
        // sync the environments twice per second
        Timer::tick(500, fn() => $watcher->detectChanges());
    }


    public function getEnvironment(): ?Environment
    {
        return null;
    }
}
