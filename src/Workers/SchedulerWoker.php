<?php

namespace Server\Workers;

use Monolog\Level;
use Server\Application\Environment;
use Server\Configurator\ConfiguratorContract;
use Server\Configurator\SchedulerConfigurator;
use Server\Ipc\{QueueChannel, RedisMutex};
use Server\Log;
use Server\Workers\Traits\WithRedisMutex;

/**
 * Class SchedulerWorker
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Workers
 */
class SchedulerWoker extends CommandWorkerAbstract implements WorkerContract
{
    use WithRedisMutex;

    /**
     * @var bool
     */
    protected bool $loopCompleted = true;

    /**
     * @var float
     */
    protected float $startedAt;

    /**
     * @var float|null
     */
    protected float|null $completedAt;

    /**
     * @var float
     */
    protected float $nextCycleScheduledAt;

    /**
     * @var int
     */
    protected int $workerNum = 2;

    /**
     * @var string
     */
    protected string $name = "laralord:scheduler";


    /**
     * @param  SchedulerConfigurator|ConfiguratorContract  $configurator
     */
    public function __construct(
        protected QueueChannel $parentChannel,
        protected SchedulerConfigurator|ConfiguratorContract $configurator
    ) {
        $this->basePath = $configurator->basePath;
        $this->initMutex();
    }

    /**
     * @return int
     */
    public function getWorkerNum(): int
    {
        return $this->workerNum;
    }


    /**
     * @param  int  $workerNum
     *
     * @return void
     */
    public function setWorkerNum(int $workerNum): void
    {
        $this->workerNum = $workerNum;
    }


    /**
     * @param $server
     * @param $workerId
     *
     * @return void
     */
    protected function initWorker($server, $workerId): void
    {
        parent::initWorker($server, $workerId);
        $this->scheduleNextMinute();
        self::$mutex->connect();
    }


    /**
     * @return Environment|null
     */
    public function nextApplication(): Environment|null
    {
        if ($this->stopSignal) {
            $this->stopWorker();
        }

        $appKeys = $this->getApplicationKeys();

        if ($this->index >= count($appKeys) && !$this->loopCompleted) {
            $this->loopCompleted = true;
            $this->completedAt = microtime(true);
            $cycleDuration = round($this->completedAt - $this->startedAt, 2);

            Log::debug("Cron cycle take {$cycleDuration}s");
            $this->scheduleNextMinute();

            if ($cycleDuration > 60) {
                Log::critical("The cron tab overlap: previous cron tab cycle wasn't completed during 1 minute");
            }
        }

        if ($this->nextCycleScheduledAt > $this->now()) {
            return null;
        }

        if ($this->loopCompleted) {
            Log::debug('Schdeule Cycle started,');
            $this->loopCompleted = false;
            $this->startedAt = $this->now();
            $this->completedAt = null;
            $this->index = 0;
        }

        return parent::nextApplication();
    }


    /**
     * @param  float|null  $time
     *
     * @return float
     */
    public function getNextMinute(float $time = null): float
    {
        $time = $time ?: microtime(true);

        return ceil($time / 60) * 60;
    }


    /**
     * @return float
     */
    public function now(): float
    {
        return microtime(true);
    }


    /**
     * @param  string  $tenantId
     *
     * @return bool
     */
    public function lockTenant(string $tenantId): bool
    {
        $value = "Worker {$this->workerId}: at " . \microtime(true);

        $key = $tenantId . "_". (int)($this->nextCycleScheduledAt - 60);

        if (self::$mutex->lock($key,ttl: 70, value: $value)) {

            return true;
        }

        return false;
    }


    /**
     * @return void
     */
    public function scheduleNextMinute()
    {
        // add 10ms gap to de-synchronize with mutex
        $this->nextCycleScheduledAt = $this->getNextMinute() + 1;

        if (isset($this->workerId)) {
            $this->nextCycleScheduledAt += 0.1 * $this->workerId;
        }

    }


    /**
     * @return void
     */
    public function releaseAllTenants()
    {
    }


    /**
     * @param  string  $tenantId
     * @param  bool  $force
     *
     * @return int
     */
    public function releaseTenant(string $tenantId, bool $force = false): bool
    {
        return true;
    }


    /**
     * @return string[]
     */
    protected function getCommand(): array
    {
        $command = ['artisan', 'schedule:run'];

        if (Log::$logger->isHandling(Level::Info)) {
            $command[] = '-vvv';
        }

        return $command;
    }


    /**
     * @return \Closure
     */
    public function getRequestHandler(): \Closure
    {
        return function () {
       };
    }


    /**
     * @return \Closure
     */
    public function getInitHandler(): \Closure
    {
        return function () {};
    }


    protected function shouldContinueLoop(): bool
    {
        return !$this->stopSignal;
    }
}
