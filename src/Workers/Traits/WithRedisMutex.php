<?php

namespace Server\Workers\Traits;

use Server\Ipc\RedisMutex;
use Server\Log;

trait WithRedisMutex
{

    /**
     * @var RedisMutex
     */
    private static RedisMutex $mutex;


    public function initMutex()
    {
        $redisConfig = $this->configurator->getRedisConfig();
        $redisConfig['prefix'] = $redisConfig['prefix'].$this->name;

        self::$mutex = new RedisMutex($redisConfig);

        return self::$mutex;
    }
}
