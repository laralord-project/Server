<?php

namespace Server\Workers\Traits;

use OpenSwoole\{ Table, Timer};
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
    private Table $forkPids;

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
     * Process timeout microtime - float
     *
     * @var float|int
     */
    public float $processTimout = 5;

    /**
     * @var int
     */
    private int $maxForks = 2;



    /**
     * @return void
     */
    private function initForksManager()
    {

        Log::debug("Max FORKS {$this->maxForks}", );
        $this->forkPids = new Table(1024);
        $this->forkPids->column('pid', Table::TYPE_INT, 32);
        $this->forkPids->column('started_at', Table::TYPE_FLOAT);
        $this->forkPids->create();
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
        if (isset($this->timer) && Timer::exists($this->timerId)) {
            Timer::clear($this->timerId);
        }

        Timer::tick($this->forkCheckPeriod, function () {
            $this->cleanZombieProcesses();
        });
    }


    /**
     * @return array
     */
    protected function cleanZombieProcesses(): array
    {
        $zombiePids = [];

        foreach ($this->forkPids as $row) {
            $pid = $row['pid'];

            $result = pcntl_waitpid($pid, $status, WNOHANG);

            if ($result == -1) {
                Log::error("Fork process with PID $pid check is abnormal: $result");
                $this->forkPids->del($pid);
                continue;
            } elseif ($result > 0) {
                $zombiePids[] = $pid;
                $this->forkPids->del($pid);
                Log::debug('Child process exited');
                continue;
            };

            $startedAt = $row['started_at'];

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
    public function getForkPids(): array
    {
        if (!$this->forkPids->count()) {
            return [];
        }

        $pids = [];

        foreach ($this->forkPids as $row) {
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
        $this->forkPids->set($pid, ['pid' => $pid, 'started_at' => \microtime(true)]);
    }


    public function forksLimitReached(bool $waitForRelease = true):bool {
        return $this->forkPids->count() > $this->maxForks;
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
            $this->cleanZombieProcesses();
            $retry++;
        }

        return !$this->forksLimitReached();
    }
}
