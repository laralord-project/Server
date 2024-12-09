<?php

namespace Server\Workers;

use Monolog\Level;
use Server\Configurator\ConfiguratorContract;
use Server\Configurator\QueueConfigurator;
use Server\Configurator\SchedulerConfigurator;
use Server\Ipc\QueueChannel;
use Server\Log;
use Server\Workers\Traits\WithRedisMutex;

/**
 * Class QueueWorker
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Workers
 */
class QueueWorker extends CommandWorkerAbstract implements WorkerContract
{
    use WithRedisMutex;

    /**
     * @var string
     */
    protected string $name = "laralord:queue-worker";


    /**
     * @param  string  $basePath
     * @param  QueueChannel  $parentChannel
     * @param  string  $queue
     */
    public function __construct(
        protected string $basePath,
        protected SchedulerConfigurator|QueueConfigurator|ConfiguratorContract $configurator,
        protected QueueChannel $parentChannel,
        protected string $queue = 'default'
    ) {
        $this->initMutex();
        $this->maxWorkerPerTenant = $this->configurator->options['max_jobs'] ?? 1;
    }


    protected function initWorker($server, $workerId): void
    {
        parent::initWorker($server, $workerId); // TODO: Change the autogenerated stub
        self::$mutex->connect();
    }


    public function isBusy(): bool
    {
        $isBusy = parent::isBusy();

        if ($isBusy) {
            self::$mutex->refresh($this->tenantId);
        }

        return $isBusy;
    }

    /**
     * @param  string  $tenantId
     *
     * @return bool
     */
    public function lockTenant(string $tenantId): bool
    {
        $result = self::$mutex->incr(
            $tenantId,
            $this->maxWorkerPerTenant,
            "Locked by Worker #{$this->workerId} ".\microtime(true));

        return (bool) $result;
    }


    /**
     * @param  string  $tenantId
     *
     * @return int
     */
    public function releaseTenant(string $tenantId): int
    {
        return self::$mutex->decr($tenantId);
    }


    /**
     * @return bool
     */
    protected function shouldContinueLoop(): bool
    {
        return true;
    }


    /**
     * @return string[]
     */
    protected function getCommand(): array
    {
        $command = [
            'artisan',
            'queue:work',
            '--once',
            // '--max-jobs=3',
            '--stop-when-empty',
            '--sleep=0',
            "--queue={$this->queue}",
        ];

        if (!Log::$logger->isHandling(Level::Debug)) {
            $command[] = '-q';
        } else {
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
        // TODO: Implement getInitHandler() method.
    }


    public function releaseAllTenants()
    {
        // TODO: Implement releaseAllWorkers() method.
    }
}