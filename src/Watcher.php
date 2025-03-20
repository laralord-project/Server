<?php

namespace Server;

use Closure;

/**
 * Class Watcher
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server
 */
class Watcher
{
    /**
     * @var array
     */
    protected $callbacks = [];

    /**
     * @var mixed|false|resource
     */
    protected mixed $inotify;

    /**
     * @var array
     */
    protected $watchers = [];

    /**
     * @var int
     */
    protected $events = IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVED_TO | IN_MOVED_FROM | IN_ISDIR;

    /**
     * @var array|mixed
     */
    protected array $wdConstants;


    /**
     *
     */
    public function __construct()
    {
        $this->inotify = \inotify_init();
        stream_set_blocking($this->inotify, 0);
        $this->wdConstants = require __DIR__.'/watcher_actions.php';

        if (\function_exists('opcache_reset') && ini_get('opcache.enable_cli')) {
            $this->addCallback($this->opcacheResetCallback());
        }
    }


    /**
     * @return void
     */
    public function destroy()
    {
        //removing watchers
        \array_walk($this->watchers, fn($watcher) => $watcher && \inotify_rm_watch($this->inotify, $watcher));

        // closing inotify resource
        fclose($this->inotify);
        $this->watchers = [];
        $this->inotify = null;
    }


    /**
     * @param  Closure  $callback
     *
     * @return self
     */
    public function addCallback(Closure $callback): self
    {
        $this->callbacks[] = $callback;

        return $this;
    }


    /**
     * @param  string|array  $path
     * @param  int           $eventsMask
     * @param                $recursive
     * @param  string        $basePath
     *
     * @return array|array[]|\array[][]|int|int[]|\int[][]|null[]|\null[][]|void
     */
    public function watch(string|array $path, int $eventsMask = 0, $recursive = true, string $basePath = '')
    {
        if (\is_array($path)) {
            $watchers = \array_map(fn($pathItem) => $this->watch($pathItem, $eventsMask, $recursive, $basePath), $path);

            return \array_filter($watchers, fn($watcher) => (bool) $watcher);
        }

        // Adding base path to path if the path is not absolute
        if ($basePath && !\str_starts_with($path, '/')) {
            $path = \rtrim($basePath, '/').'/'.$path;
        }

        $events = $eventsMask ?: $this->events;

        if (!\file_exists($path)) {
            return null;
        }

        // Add watch to current directory
        $watchId = inotify_add_watch($this->inotify, $path, $events);
        $this->watchers[$watchId] = $path;

        if (\is_dir($path)) {
            // Scan the directory for subdirectories and add watches
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                $fullPath = $path.'/'.$file;

                if (\is_dir($fullPath) && $recursive) {
                    $this->watch($fullPath); // Recursive call
                }
            }

            return $watchId;
        }
    }


    /**
     * @return void
     */
    public function detectChanges()
    {
        $changes = \inotify_read($this->inotify);

        if ($changes) {
            $changes = \array_map(function (array $event) {
                $wdConstants = $this->wdConstants;
                $action = $wdConstants[$event['mask']][0] ?? $event['mask'];
                $path = $this->watchers[$event['wd']];
                $event += [
                    'path' => rtrim($path, '/'),
                    'action' => $action,
                ];

                Log::warning( "$path - {$action}",
                    Log::$logger->isHandling('info') ? $event : []
                );

                return $event;
            }, $changes);

            \array_walk($this->callbacks, fn(Closure $callback) => $callback($changes));
        }
    }

    /**
     * @return Closure
     */
    public function opcacheResetCallback(): Closure
    {
        return function (array $changes) {
            if (!function_exists('opcache_invalidate')) {
                Log::debug('OPCache invalidate not available—skipping');
                return;
            }

            foreach ($changes as $change) {
                $dirPath = $change['path']; // e.g., /path/to/app/Http/Controllers
                $fileName = $change['name'] ?? ''; // e.g., TenantController.php or empty for dir events
                $fullPath = rtrim($dirPath, '/') . ($fileName ? '/' . $fileName : '');

                if (!file_exists($fullPath)) {
                    Log::debug("Path not found: {$fullPath}—skipping");
                    continue;
                }

                if (is_dir($fullPath)) {
                    // Recursively invalidate PHP files in directory
                    $this->invalidateDirectory($fullPath);
                } elseif (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
                    // Invalidate single PHP file
                    $result = opcache_invalidate($fullPath, true); // true forces recompile
                    Log::debug("Invalidated $fullPath: " . ($result ? 'Success' : 'Failed'));
                }
            }
        };
    }

    /**
     * Recursively invalidate OPCache for PHP files in a directory
     * @param string $dirPath
     * @return void
     */
    protected function invalidateDirectory(string $dirPath): void
    {
        $files = scandir($dirPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $fullPath = "$dirPath/$file";
            if (is_dir($fullPath)) {
                $this->invalidateDirectory($fullPath); // Recursive
            } elseif (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
                $result = opcache_invalidate($fullPath, true);
                Log::debug("Invalidated $fullPath: " . ($result ? 'Success' : 'Failed'));
            }
        }
    }
}
