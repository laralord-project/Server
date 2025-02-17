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
                Log::error("Fork process with PID $pid check is abnormal : $result");
                $this->forks->del($pid);
                continue;
            } elseif ($result > 0) {
                $zombiePids[] = $pid;
                $this->forks->del($pid);
                Log::debug('Child process exited. Forks still active: ', $this->getForks());
                continue;
            };

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
    public function registerFork(int $pid)
    {
        $this->forks->set($pid, ['pid' => $pid, 'started_at' => \microtime(true)]);
    }


    public function forksLimitReached(bool $waitForRelease = true):bool {
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
            Log::debug('Max worker fork reached. waiting for release. '.$retry);
            \usleep($maxWaitTime / 10);
            $this->cleanStoppedProcesses();
            $retry++;
        }

        return !$this->forksLimitReached();
    }
}
