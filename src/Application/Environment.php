<?php

namespace Server\Application;

use OpenSwoole\Table;
use phpDocumentor\Reflection\Types\This;
use Server\Log;

/**
 * Class Environment
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Application
 */
class Environment implements \ArrayAccess
{
    /**
     * The environments variables to add to each environment's global $_ENV
     *
     * @var string[][]
     */
    static array $common = [];

    /**
     * The list of variabled which will be excluded from global $_ENV
     *
     * @var string[]
     */
    static array $exclude = [];

    /**
     * Swoole table to store the serialized object to share the env variables between the processes
     *
     * @var Table
     */
    static Table $storage;

    /**
     * @var int
     */
    public int $id;

    /**
     * @var string
     */
    public string $key;


    /**
     * @param  array  $vars
     * @param  int    $version
     * @param  int|string    $created_at  The timestamp where the variables was created
     */
    public function __construct(private array $vars, private int $version, public int|string $created_at)
    {
        if (\is_string($this->created_at)) {
            $this->created_at = \strtotime($this->created_at);
        }
    }


    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->vars;
    }


    /**
     * @return array|\string[][]
     */
    public static function getCleaned()
    {
        $env = \array_filter(
            $_ENV,
            fn($key) => !\in_array($key, self::$exclude),
            \ARRAY_FILTER_USE_KEY
        );

        return array_merge($env, self::$common);
    }


    /**
     * @return array
     */
    public function env(): array
    {
        return \array_merge(self::getCleaned(), $this->vars);
    }


    /**
     * @param  array   $vars
     * @param  int     $version
     * @param  string  $createdAt
     *
     * @return bool
     */
    public function update(array $vars, int $version, string $createdAt): bool
    {
        $this->vars = $vars;
        $createdAt = \strtotime($createdAt);

        if ($this->created_at !== $createdAt || $version !== $this->version) {
            $this->created_at = $createdAt;
            $this->version = $version;

            return true;
        }

        return false;
    }


    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }


    /**
     * @param  string  $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->vars[$name];
    }


    /**
     * @param  string  $name
     * @param          $value
     *
     * @return void
     */
    public function __set(string $name, $value)
    {
        if (is_string($value)) {
            $this->vars[$name] = $value;

            return;
        }

        $this->vars[$name] = \json_encode($value);
    }


    /**
     * @param  mixed  $offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return \key_exists($offset, $this->vars);
    }


    /**
     * @param  mixed  $offset
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->vars[$offset];
    }


    /**
     * @param  mixed  $offset
     * @param  mixed  $value
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($value)) {
            $this->vars[$offset] = $value;

            return;
        }

        $this->vars[$offset] = \json_encode($value);
    }


    /**
     * @param  mixed  $offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->vars[$offset]);
    }


    /**
     * @return Table
     */
    public static function initTable(): Table
    {
        $storage = self::$storage = new Table(1024);
        $storage->column('id', Table::TYPE_INT, 32);
        $storage->column('version', Table::TYPE_INT, 32);
        $storage->column('created_at', Table::TYPE_INT, 32);
        $storage->column('variables', Table::TYPE_STRING, 10000);
        $storage->create();


        return $storage;
    }


    /**
     * @return void
     */
    public function store(): self
    {
        if (!isset(self::$storage)) {
            self::initTable();
        }
        $data = [
            'id' => $this->id,
            'version' => $this->version,
            'created_at' => $this->created_at,
            'variables' => \json_encode($this->vars),
        ];
        self::$storage->set($this->key, $data);

        return $this;
    }


    /**
     * @param $key
     *
     * @return bool
     */
    public static function delete($key): bool
    {
        return self::$storage->del($key);
    }


    /**
     * @return array
     */
    public static function getKeys(): array
    {
        $keys = [];

        foreach (Environment::$storage as $key => $value) {
            $keys[] = $key;
        }

        return $keys;
    }


    /**
     * Compare two environments metadata
     *
     * @param  Environment  $env
     *
     * @return bool
     */
    public function isDiff(self $env): bool
    {
        if ($env->key !== $this->key) {
            Log::debug("Key is different self: {$this->key} vs {$env->key}");
        }

        if ($env->created_at !== $this->created_at) {
            Log::debug("Created at field {$this->key} is different self: {$this->created_at} vs {$env->created_at}");
        }

        if ($env->version !== $this->version) {
            Log::debug("Version field {$this->key} is different self: {$this->version} vs {$env->version}");
        }


        return ($env->key !== $this->key)
            || ($env->created_at !== $this->created_at)
            || ($env->version !== $this->version);
    }


    /**
     * @param $key
     *
     * @return self|null
     */
    public static function find($key): self|null
    {
        if (!isset(self::$storage)) {
            self::initTable();

            return null;
        }

        if (!self::$storage->exists($key)) {
            return null;
        }

        $row = self::$storage->get($key);

        $env = new self(\json_decode($row['variables'], true), $row['version'], $row['created_at']);
        $env->key = $key;
        $env->id = $row['id'];

        return $env;
    }


    /**
     * Refresh the data from storage
     *
     * @return $this
     */
    public function fresh(): self
    {
        $row = self::$storage->get($this->key);

        $this->version = $row['version'];
        $this->created_at = $row['created_at'];
        $this->vars = \json_decode($row['variables'], true);

        return $this;
    }


    public function getKey(): string
    {
        return $this->key;
    }


    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }


    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }
}
