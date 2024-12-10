<?php

namespace Server\Ipc;

use Redis;
use Server\Log;

class RedisMutex
{
    private $redis;

    private string $prefix = 'laralord-mutex';

    private int $maxRetries = 3;

    private int $connectRetry = 0;

    private ?string $mutexId = null;

    private float $lastRefreshTime = 0;

    /**
     * Refresh period in seconds
     *
     * @var float
     */
    private float $refreshPeriod = 1.0;

    protected array $config = [
        'host'       => 'redis',
        'port'       => 6379,
        'timeout'    => 1,
        'persistent' => true,
    ];


    public function __construct(array $config)
    {
        $this->config = \array_merge($this->config, $config);

        if ($this->config['prefix'] ?? '') {
            $this->prefix = $this->config['prefix'];
        }

        ini_set('default_socket_timeout', -1);
        $this->redis = new Redis();
    }


    public function connect()
    {
        $config = $this->config;
        $this->redis->pconnect($config['host'], $config['port'], $config['timeout'] ?? 1);

        if (!empty($config['password'])) {
            $this->redis->auth([$config['username'], $config['password']]);
        }

        if ($this->redis->isConnected() && $this->redis->ping()) {
            Log::debug('Redis connected');
        } else {
            Log::error("Can't connect to redis");

            return false;
        }

        if ($this->redis->set($this->prefix.'test', 1, ['ex' => 10])) {
            Log::debug('Redis Connection works');
        } else {
            Log::error('test mutex lock failed');
        }

        return true;
    }


    /**
     * @param  string  $key
     * @param  float|int  $ttl
     * @param  mixed  $value
     *
     * @return bool|array|Redis
     */
    public function lock(string $key, float|int $ttl = 120, mixed $value = 1): bool
    {
        $mutexKey = "{$this->prefix}:$key";
        $expiration = 0;

        try {
            $value = $value ?: ($mutexKey.\microtime(true));

            // Try to acquire the lock
            $result = $this->redis->setnx($mutexKey, $value);

            if ($result) {
                $this->redis->expire($mutexKey, 180, 'NX');
            }

            Log::debug('Locking the key', [
                'key'        => $mutexKey,
                'expiration' => $expiration,
                'raw_result' => $result,
                'result'     => (bool) $result,
                'timestamp'  => \microtime(true),
            ]);

            return (bool) $result;
        } catch (\RedisException $e) {
            Log::error('Redis connection issue: '.$e->getMessage());
            Log::error($e);

            if ($this->connect()) {
                return $this->lock($key, $ttl, $value);
            };

            return false;
        }
    }


    public function incr(string $key, int $max, int|string $value = 1): bool|string
    {
        $mutexKey = "{$this->prefix}:$key";
        $mutexId = (string) \microtime(true);

        $result = $this->redis->eval(
            $this->getIncrScript(),
            [$mutexKey, $max, $value, 2, $mutexId],
            1
        );

        if ($result) {
            $this->mutexId = $mutexId;
            $this->lastRefreshTime = \microtime(true);
        }

        Log::debug("Locking the key:", [
            'method'     => 'incr',
            'key'        => $mutexKey,
            'mutex_id'   => $mutexId,
            'result'     => (bool) $result,
            'raw_result' => $result,
            'value'      => $value,
            'max'        => $max,
        ]);

        return (bool) $result;
    }


    protected function getIncrScript(): string
    {
        return <<<'LUA'
        -- Input arguments:
        -- KEYS[1] = the prefix for keys (mutexKey)
        -- ARGV[1] = the maximum allowed keys (max)
        -- ARGV[2] = the lock value
        -- ARGV[3] = the TTL for the lock (in seconds)
        -- ARGV[4] = the pre-generated mutex ID

        -- Find keys matching the prefix
        local keys = redis.call('keys', KEYS[1] .. "*")

        -- If the count exceeds max, return false
        if #keys >= tonumber(ARGV[1]) then
            return false
        end

        -- Add the new lock key with the provided mutex ID
        local lockKey = KEYS[1] .. ":" .. ARGV[4]
        redis.call('set', lockKey, ARGV[2], 'EX', ARGV[3])

        -- Return the provided mutex ID
        return ARGV[4]
    LUA;
    }


    public function refresh(string $key): bool
    {
        if (!$this->mutexId) {
            return false;
        }

        $mutexKey = "{$this->prefix}:$key:{$this->mutexId}";

        // skip refresh the mutex to avoid redis too overload
        if (($this->lastRefreshTime + $this->refreshPeriod) > \microtime(true)) {
            return false;
        }

        $result = $this->redis->expire($mutexKey, 2);

        Log::debug("Refresh the key:", [
            'method'     => 'refresh',
            'key'        => $mutexKey,
            'mutex_id'   => $this->mutexId,
            'result'     => (bool) $result,
            'raw_result' => $result,
            'timestamp'  => \microtime(true),
        ]);

        if (!$result) {
            $this->mutexId = null;
            $this->lastRefreshTime = 0;

            return false;
        }

        $this->lastRefreshTime = \microtime(true);

        return $result;
    }


    public function decr(string $key): bool
    {
        $mutexKey = "{$this->prefix}:$key:{$this->mutexId}";
        $result = $this->redis->del($mutexKey);

        Log::debug("Locking the key:", [
            'method'     => 'decr',
            'key'        => $mutexKey,
            'mutex_id'   => $this->mutexId,
            'result'     => (bool) $result,
            'raw_result' => $result,
            'timestamp'  => \microtime(true),
        ]);

        $this->mutexId = null;
        $this->lastRefreshTime = 0;

        return $result;
    }
}
