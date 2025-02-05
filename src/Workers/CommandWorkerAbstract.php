<?php

namespace Server\Workers;

use OpenSwoole\Process;
use Server\Application\Environment;
use Server\Log;

/**
 * Class CommandWorkerAbstract
 *
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Workers
 */
abstract class CommandWorkerAbstract extends WorkerAbstract implements WorkerContract
{
    /**
     * @var Process
     */
    protected Process $process;

    /**
     * @var int
     */
    protected int $maxWorkerPerTenant = 1;

    /**
     * @var bool
     */
    protected bool $stopSignal = false;

    /**
     * @var array|null
     */
    private array|null $appKeys = null;

    /**
     * @var int
     */
    protected int $index = 0;

    /**
     * @var int
     */
    protected int $childPid = 0;

    /**
     * @var int
     */
    private int $sleepBetweenCycles = 100_000;

    /**
     * Currently processing application
     *
     * @var string
     */
    protected string $tenantId;


    /**
     * @return string[]
     */
    abstract protected function getCommand(): array;


    /**
     * @return bool
     */
    abstract protected function shouldContinueLoop(): bool;


    abstract function lockTenant(string $tenantId): bool|int;


    abstract function releaseTenant(string $tenantId): bool|int;


    abstract function releaseAllTenants();

    /**
     * @param $workerId
     *
     * @return \Closure
     */
    public function getProcessHandler($workerId): \Closure
    {
        return function (Process $process) use ($workerId) {
            $this->initWorker($process, $workerId);

            try {
                $this->mainLoop();
            } catch (\Throwable $e) {
                Log::error($e);
            }
        };
    }


    /**
     * @return void
     * @throws \Exception
     */
    public function mainLoop()
    {
        do {
            \usleep(50000);
            $this->checkMessages();

            if ($this->isBusy()) {
                continue;
            }

            if ($this->stopSignal) {
                Log::info('Stop the worker');
                $this->stopWorker();
                continue;
            }

            $app = $this->nextApplication();

            if (!$app) {
                if (!$this->shouldContinueLoop()) {
                    $this->stopWorker();
                }

                continue;
            }

            $this->childPid = $this->isolate($this->work($app), wait: false)->pid;
        } while ($this->shouldContinueLoop());
    }


    /**
     * @param $server
     * @param $workerId
     *
     * @return void
     */
    protected function initWorker($server, $workerId): void
    {
        Log::$logger = Log::$logger->withName(Log::$logger->getName().":worker-$workerId");
        $this->name = "{$this->name}-$workerId";
        cli_set_process_title($this->name);
        $this->process = $server;
        $this->workerId = $workerId;
        $this->parentChannel->connect();
        $this->sendMessage(['action' => 'started']);
        chdir($this->basePath);

        require_once "{$this->basePath}/vendor/autoload.php";

        Log::info("Worker $workerId started with pid: ".\getmypid());

        $this->registerSignalsHandler();
    }


    /**
     * @return bool
     */
    public function isBusy(): bool
    {
        if (!$this->childPid) {
            return false;
        }

        $result = pcntl_waitpid($this->childPid, $status, WNOHANG);

        if ($result == -1) {
            Log::error('Child process check is abnormal ');
        } elseif ($result > 0) {
            Log::debug('Child process exited');
            $this->childPid = 0;
            $this->releaseTenant($this->tenantId);
            $this->tenantId = '';

            return false;
        }


        return true;
    }


    /**
     * @return void
     */
    public function checkMessages(): void
    {
        do {
            $message = $this->processMessage($this->process->pop());
        } while ($message);
    }


    /**
     * @param  string  $message
     * @param  bool  $stopAllowed
     *
     * @return array
     */
    public function processMessage(string $message, bool $stopAllowed = false): array
    {
        if (!$message) {
            return [];
        }

        $data = \explode("\n", $message);

        foreach ($data as $message) {
            if (!$message) {
                continue;
            }

            $parsed = \json_decode($message, true);

            if (!$parsed) {
                Log::error($message);

                continue;
            }

            Log::info('Message : ', $parsed);

            switch ($parsed['action']) {
                case 'reload':
                    $this->appKeys = null;
                    $this->stopSignal = true;
                    Log::info('Tenant applications reloaded');

                    break;
                case 'stop':
                    $this->stopSignal = true;

                    break;
                case 'ping':
                    $this->sendMessage(['action' => 'pong']);

                    break;
                default:
                    Log::error("Worker Unknown action on message", $parsed);
            }
        }

        return $data;
    }


    /**
     * @param  array  $message
     *
     * @return bool
     */
    public function sendMessage(array $message)
    {
        $message['worker_id'] = $this->workerId;
        $message['pid'] = \getmypid();

        return $this->parentChannel->push($message);
    }


    /**
     * @return void
     */
    public function registerSignalsHandler(): void
    {
        // Listen for signals in the main process
        pcntl_async_signals(true);
        \pcntl_signal(\SIGINT, $this->getSignalHandler(), false);
        \pcntl_signal(\SIGTERM, $this->getSignalHandler(), false);
    }


    /**
     * @return \Closure
     */
    public function getSignalHandler(): \Closure
    {
        return function ($signal) {
            Log::error("Waiting for worker-{$this->workerId} release. Signal: $signal");
            $this->stopSignal = true;
        };
    }


    /**
     * @return void
     */
    public function stopWorker()
    {
        Log::info('Stop workers');
        $this->sendMessage(['action' => 'stopped']);
        $this->process->exit(0);
    }


    /**
     * @return array
     */
    public function getApplicationKeys(): array
    {
        if ($this->appKeys === null) {
            $appKeys = Environment::getKeys();
            \shuffle($appKeys);
            $this->appKeys = $appKeys;
        }

        return $this->appKeys;
    }


    /**
     * Return the closure which will be executed on forked process
     *
     * @param  Environment  $tenant
     *
     * @return \Closure
     */
    public function work(Environment $tenant): \Closure
    {
        return function () use ($tenant) {
            cli_set_process_title("$this->name-fork");
            define('LARAVEL_START', microtime(true));
            try {
                $_ENV = $tenant->env();
                $_ENV['WORKER_ID'] = $this->workerId;
                if ($this->app ?? '') {
                    Log::error('Isolation issue - ');
                }
                $this->createApplication();

                $command = $this->getCommand();
                $_SERVER['argv'] = $command;

                $kernel = $this->getKernel($this->app);
                $status = $kernel->handle(
                    $input = new ($this->getProjectClass('@Component\Console\Input\ArgvInput',
                        'Symfony'))($command),
                        $this->getOutput($_ENV['TENANT_ID'] ?? '')
                );

                if ($status) {
                    Log::error("Tenant command exit with non zero status: $status", );
                }

                $kernel->terminate($input, $status);
                Process::kill(\getmypid(), \SIGKILL);
            } catch (\Throwable $e) {
                Log::error($e);
            }
            finally {
                Process::kill(\getmypid(), \SIGKILL);
            }
        };
    }


    /**
     * Select next Application for worker run
     *
     * @return Environment|Null
     */
    public function nextApplication(): Environment|null
    {
        $appKeys = $this->getApplicationKeys();

        if (!count($this->appKeys)) {
            Log::error("No applications loaded for processing");

            return null;
        }

        if ($this->index >= count($this->appKeys)) {
            usleep($this->sleepBetweenCycles);
            $this->index = 0;
        }

        $appKey = $this->appKeys[$this->index] ?? null;

        $this->index++;

        // skip is worker already assigned for application
        if (!$this->lockTenant($appKey)) {
            return null;
        }

        $this->tenantId = $appKey;

        return Environment::find($appKey);
    }


    /**
     * Add multi-tenant format for messages from artisan commands
     *
     * @param $appKey
     * @param $addPrefix
     *
     * @return mixed
     */
    protected function getOutput($appKey, $addPrefix = true)
    {
        $output = new ($this->getProjectClass('@Component\Console\Output\ConsoleOutput', 'Symfony'));

        if (!$addPrefix) {
            return $output;
        }

        $formatterClass = get_class($output->getFormatter());

        $worker = \cli_get_process_title();
        $newFormatterCode = "return
        new class extends $formatterClass {
            public function format(null|string \$message): null|string
            {
                if (!\$message) {
                    return null;
                }
                    \$datetimePrefix = '[' . now()->format('Y-m-d\TH:i:s.uP') . '] ';
                    \$datetimePrefix .= \" [tenant-id: $appKey] \";
                    \$datetimePrefix .= \" [worker: $worker] \";
        
                \$message = \$datetimePrefix . \$message . PHP_EOL;
                return parent::format(\$message);
            }
        };";

        $output->setFormatter(eval($newFormatterCode));

        return $output;
    }


    /**
     * Method return the resolved Console/Kernel instance
     *
     * @param $app
     *
     * @return mixed
     */
    protected function getKernel($app)
    {
        return $app->make($this->getProjectClass("@Contracts\\Console\\Kernel"));
    }
}
