<?php

namespace Server;

use Server\Configurator\ConfiguratorContract;
use Server\Workers\SchedulerWoker;
use Server\Workers\WorkerContract as Worker;

class Scheduler extends Queue
{
    /**
     * @param  ConfiguratorContract  $configurator
     */
    public function __construct(ConfiguratorContract $configurator)
    {
        parent::__construct($configurator);
        \cli_set_process_title("laralord:scheduler");
    }


    protected function getWorker(): Worker
    {
        $worker = new SchedulerWoker($this->ipcChannel, $this->configurator);

        $worker->setWorkerNum($this->configurator->options['worker_num']);

        return $worker;
    }
}
