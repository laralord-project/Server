<?php

namespace Server\Workers\Traits;

use OpenSwoole\{Process, Table, Timer};
use Server\Log;
use Server\Workers\MultiTenantServerWorker;
use Server\Workers\ServerWorker;

/**
 * Trait ForksManager
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Workers\Traits
 */
trait ForksManager
{
    /**
     * PIDs of created forks
     *
     * @var Table
     */
    private Table $forks;

    /**
     * The period of checking forked processes status
     *
     * @var int $forkCheckPreriond
     */
    public int $forkCheckPeriod = 1000;

    /**
     * @var int
     */
    public int $timerId;

    /**
     * @var int
     */
    private int $maxForks = 2;



    /**
     * @return void
     */
    protected function initForksManager()
    {
        Log::debug("Max forks:  {$this->maxForks}", );
        $this->forks = new Table(1024);
        $this->forks->column('pid', Table::TYPE_INT, 32);
        $this->forks->column('started_at', Table::TYPE_FLOAT);
        $this->forks->create();
        $this->setForkCleanTimer();
    }


    /**
     * @param  int  $maxForks
     *
     * @return ServerWorker|MultiTenantServerWorker|ForksManager
     */
    public function setMaxForks(int $maxForks): self
    {
        $this->maxForks = $maxForks;

        return $this;
    }

    /**
     * @return void
     */
    protected function setForkCleanTimer()
    {
        if (!empty($this->timerId) && Timer::exists($this->timerId)) {
            Timer::clear($this->timerId);
        }

        $this->timerId = Timer::tick($this->forkCheckPeriod, function () {
            $this->cleanStoppedProcesses();
        });
    }


    /**
     * @return array
     */
    protected function cleanStoppedProcesses(): array
    {
        $zombiePids = [];

        foreach ($this->forks as $row) {
            $pid = $row['pid'];

            $result = pcntl_waitpid($pid, $status, WNOHANG);

            if ($result == -1) {
                $errorCode = pcntl_get_last_error();
                // child process exited
                if ($errorCode !== 10) {
                    $message = \pcntl_strerror($errorCode);
                    Log::error("Fork process with PID $pid check is abnormal : $result,  error code: $errorCode, message : $message");
                }

                $this->forks->del($pid);
                continue;
            } elseif ($result > 0) {
                $zombiePids[] = $pid;
                $this->forks->del($pid);
                Log::debug('Child process exited. Forks still active: ', $this->getForks());
                continue;
            };

            Log::debug('Child process are still in process');



            //            $startedAt = $row['started_at'];
            // TODO implement correct exit the forks by timeout in case OOM
            // if(\microtime(true) > ($startedAt + $this->processTimout)) {
            //     Log::warning('Worker Fork timeout - send SIGKILL process terminating');
            //     Process::kill(\getmypid(), \SIGKILL);
            //     $this->forkPids->del($pid);
            // }
        }

        return $zombiePids;
    }


    public function isolate(
        \Closure $action,
        bool $wait = true,
        \Closure $finalize = null,
    ) {

        if ($this->forksLimitReached()) {
            $this->waitForForkRelease();
        }

        $process = new Process(function (Process $worker) use ($action, $finalize) {
            $this->registerFork($worker);

            try {
                $action();
            } catch (\Throwable $e) {
                Log::error($e);
            } finally {
                if ($finalize) {
                    try {
                        $finalize();
                    } catch (\Throwable $e) {
                        Log::error($e);
                    }
                }
            }
        }, false);

        $process->start();

        if ($wait) {
            $process->wait();
        }

        return $process;
    }


    /**
     * @param Process $process
     *
     * @return \Closure
     */
    public function getShutdownHandler(Process $process): \Closure
    {
        return function() use ($process) {
            $error = error_get_last();

            if ($error) {
                if (\method_exists($this, 'errorHandler')) {
                    $this->errorHandler($error);
                }

                Log::error('worker error', $error);
            }

            Log::info('Shotdown handled for pid:' . $process->pid);
            $this->releaseFork($process->pid);
        };
    }

    /**
     * @return array
     */
    public function getForks(): array
    {
        if (!$this->forks->count()) {
            return [];
        }

        $pids = [];

        foreach ($this->forks as $row) {
            $pids[] = $row['pid'];
        }

        return $pids;
    }


    /**
     * @param  int  $pid
     *
     * @return void
     */
    public function registerFork(Process $process)
    {
        $pid = $process->pid;
        $this->forks->set($pid, ['pid' => $pid, 'started_at' => \microtime(true)]);

        \register_shutdown_function(fn() => $this->getShutdownHandler($process));
        set_error_handler(fn() => $this->getShutdownHandler($process));
    }


    /**
     * @param int $pid
     *
     * @return void
     */
    public function releaseFork(int $pid) {
        $this->forks->del($pid);
    }


    /**
     * @param bool $waitForRelease
     *
     * @return bool
     */
    public function forksLimitReached(bool $waitForRelease = false):bool {

        if ($waitForRelease) {
            return $this->waitForForkRelease();
        }

        return $this->forks->count() > $this->maxForks;
    }


    /**
     * @param  int  $maxWaitTime - max wait time in microsecond
     * @param  int  $maxRetry
     *
     * @return bool
     */
    public function waitForForkRelease(int $maxWaitTime  = 100_000, int $maxRetry = 10): bool
    {
        $retry = 0;

        while ($this->forksLimitReached() && $retry < $maxRetry) {
            \usleep($maxWaitTime / $maxRetry);
            $this->cleanStoppedProcesses();
            $retry++;
        }

        return !$this->forksLimitReached();
    }
}
