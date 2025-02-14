<?php

namespace Server;

use OpenSwoole\{Event, Process, Timer};
use Server\Configurator\ConfiguratorContract;
use Server\EnvironmentResolvers\{DirectoryEnvResolver, EnvResolverContract, VaultEnvResolver};
use Server\Ipc\QueueChannel;
use Server\Traits\HasConfigurator;
use Server\Workers\{QueueWorker, SchedulerWoker, WorkerContract as Worker};

/**
 * Class Queue
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server
 * @property string $logLevel
 * @property string $basePath
 * @property string $configEnvFile
 * @property string $mode
 * @property string[] $watchTargets
 */
class Queue
{
    use HasConfigurator;

    /**
     * @var EnvResolverContract
     */
    private VaultEnvResolver|DirectoryEnvResolver|EnvResolverContract $envResolver;

    /**
     * @var SchedulerWoker|QueueWorker|Worker
     */
    protected SchedulerWoker|QueueWorker|Worker $worker;

    /**
     * @var Watcher
     */
    protected Watcher $watcher;

    /**
     * @var Process[]
     */
    protected array $workers = [];

    /**
     * @var bool
     */
    protected $terminate = false;

    protected $reload = false;

    /**
     * @var QueueChannel
     */
    protected QueueChannel $ipcChannel;


    /**
     * @param  ConfiguratorContract  $configurator
     */
    public function __construct(protected readonly ConfiguratorContract $configurator)
    {
        \cli_set_process_title("laralord:queue");
//        Log::info('Config', [$this->configurator])
    }


    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $this->envResolver = $this->initEnvResolver();
        $this->envResolver->common(['APP_BASE_PATH' => $this->basePath])
            ->exclude($this->configurator->listServerVariables())
            ->boot();
        // Init the child to parent queue
        $this->initIpc();
        $this->worker = $this->getWorker();
        $this->startWorkers();

        // scheduling the Vault Environment variables synchronization
        $this->envResolver->sync(function (array $changes) {
            Log::notice('Environment variables changed', $changes);
            // send the message to child processes to refresh the keys
            $this->dispatchMessage(['action' => 'reload']);
        });

        $this->registerSignalsHandler();
        $this->addConfigWatcher();

        Event::wait();
    }


    /**
     * @return Worker
     */
    protected function getWorker(): Worker
    {
        return new QueueWorker($this->basePath, $this->configurator, $this->ipcChannel);
    }


    /**
     * @return void
     */
    public function registerSignalsHandler(): void
    {
        Timer::tick(100, function () {
            \array_walk($this->workers, function (Process $process, $index) {
                try {
                    $this->handleMessage($this->ipcChannel->pop());
                } catch (\Exception $e) {
                    Log::error($e);
                    exit (1);
                }
            });

            if (!$this->workers) {
                //TODO: Implement reload workers in case the server config changed
                Log::notice("All Workers stopped. Exit main process.");
                $this->ipcChannel->release();
                Event::exit();
            }

            /** @var array|false $processEvents */
            $event = Process::wait(false);

            if ($event) {
                Log::debug('Child event: ', $event);
                $pid = $event['pid'];
                $this->workers = \array_filter($this->workers, fn($worker) => $worker->pid !== $pid);
            }
        });

        \pcntl_async_signals(true);
        \pcntl_signal(\SIGINT, $this->getSignalHandler(), true);
        \pcntl_signal(\SIGTERM, $this->getSignalHandler(), true);
    }


    /**
     * @return \Closure
     */
    public function getSignalHandler(): \Closure
    {
        return function ($signal) {
            Log::warning("Started process termination. Signal:  $signal");
            $this->terminate();
            Log::warning('Wait for stop workers"');
        };
    }


    /**
     * @param  array|string|null  $message
     *
     * @return array|string[]
     * @throws \Exception
     */
    public function handleMessage(array|string|null $message): array
    {
        if (!$message) {
            return [];
        }

        if (\is_string($message)) {
            $parsed = \json_decode($message, true);

            if (!$parsed) {
                Log::error("Failed to parse the message: $message");

                return ['message' => $message];
            }
            $message = $parsed;
        }

        switch ($message['action'] ?? '') {
            case 'stopped':
                Log::info('Received the STOPPED action for process: ', $message);

                break;
            case 'pong':
                Log::debug('Response: ', [$message, 'status' => $this->ipcChannel->stat()]);
                break;
            case 'started':
                break;
            default:
                Log::error('Unknown action on message', [$message]);
        }

        return $message;
    }


    /**
     * @param $pid
     *
     * @return bool
     */
    public function processExists($pid)
    {
        return \file_exists("/proc/$pid");
    }


    /**
     * @return QueueChannel
     */
    protected function initIpc()
    {
        $this->ipcChannel = new QueueChannel('/tmp/laralord.queue.sock');
        $this->ipcChannel->start();

        return $this->ipcChannel;
    }


    /**
     * @return void
     */
    protected function startWorkers()
    {
        do {
            $process = new Process($this->worker->getProcessHandler(count($this->workers)), false);
            $process->useQueue(count($this->workers), Process::IPC_NOWAIT);
            $process->setBlocking(false);
            $process->start();
            $this->workers[] = $process;
        } while (count($this->workers) < $this->configurator->options['worker_num']);
    }


    /**
     * @return void
     */
    protected function terminate()
    {
        Log::warning('SIGINT: Process terminating');
        $this->terminate = true;
    }


    public function reload()
    {
        $this->reload = true;
        $this->dispatchMessage(['action' => 'reload']);
    }


    /**
     * @param  array  $data
     *
     * @return void
     */
    protected function dispatchMessage(array $data)
    {
        $data = json_encode($data);

        foreach ($this->workers as $process) {
            $process->push($data);
        }
    }


    /**
     * @return void
     */
    public function addConfigWatcher()
    {
        if ($this->configEnvFile && !isset($this->watcher)) {
            $this->watcher = new Watcher();
            $this->watcher->addCallback(function (array $changes) {
                Log::notice('Config updated');
                $this->configurator->loadConfig();
                $this->envResolver->configure($this->configurator->envSourceConfig);
                $this->reload();
            });

            $this->watcher->watch($this->configEnvFile, basePath: $this->basePath);

            $timer = Timer::tick(3000, fn() => $this->watcher->detectChanges());

            Log::info("Watching the config file {$this->configEnvFile}", [
                'timer' => $timer,
                'info'  => Timer::info($timer),
            ]);
        }
    }
}
