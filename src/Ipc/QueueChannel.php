<?php

namespace Server\Ipc;

use Server\Log;
use Symfony\Component\VarDumper\Caster\UuidCaster;
use SysvMessageQueue;

/**
 * Class QueueChannel
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server\Ipc
 */
class QueueChannel
{
    /**
     * @var string|false
     */
    protected string $file = '/tmp/1';

    /**
     * @var int
     */
    protected int $key;

    /**
     * @var resource|SysvMessageQueue|false
     */
    protected SysvMessageQueue|int|false $queue;

    protected int $creatorPid = 0;
    


    /**
     * @param  string  $filename
     * @param  int     $messageType
     */
    public function __construct(string $filename = '', protected int $messageType = 1)
    {
        // if (!$filename) {
            $this->file = \tempnam('/tmp', 'queue-ipc');
        // }else {
        //     $this->file = $filename. rando;
        // }

        $this->creatorPid = \getmypid();
    }


    /**
     * @return bool
     */
    public function start(): bool
    {
        if (!file_exists($this->file)) {
            touch($this->file);
        }

        $this->key = ftok($this->file, 'A');
        $this->queue = \msg_get_queue($this->key);

        return \msg_queue_exists($this->key);
    }


    /**
     * @return bool
     */
    public function connect() {
        if (empty($this->key) || !msg_queue_exists($this->key)) {
            return $this->start();
        }

        $this->queue = \msg_get_queue($this->key);

        return (bool) $this->queue;
    }


    /**
     * @return array|false
     * @throws \Exception
     */
    public function stat(): array|false {
        if (!$this->queue) {
            throw new \Exception('The message queue is not started');
        }

        return msg_stat_queue($this->queue);
    }


    /**
     * @param  string|array    $message
     * @param  int|null  $type
     * @param  bool      $blocking
     * @param            $errorCode
     *
     * @return bool
     */
    public function push(string|array $message, int $type = null, bool $blocking = false, &$errorCode = 0): bool {
        $type ??= $this->messageType;

        return msg_send($this->queue, $type,  $message, blocking: $blocking, error_code: $errorCode);
    }


    /**
     * @param  int       $maxSize
     * @param  int|null  $type
     * @param  int       $flags
     * @param  bool      $unserialize
     *
     * @return mixed
     */
    public function pop(int $maxSize = 4096, int $type = null, int $flags = \MSG_IPC_NOWAIT, bool $unserialize = true): mixed
    {
        $type ??= $this->messageType;

        msg_receive($this->queue, $type, $receivedType, $maxSize, $message, $unserialize, $flags);

        if (!$message) {
            return null;
        }

        return $message;
    }


    /**
     * @return int
     */
    public function release(): int
    {
        // skip release queue in case it create on another process
        if ($this->creatorPid !== \getmypid()) {
            Log::error("Can't release from non parent");

            return 0;
        }

        ;

        if (!\msg_remove_queue($this->queue)) {
            return 1;
        }

        if (!\unlink($this->file)) {
            return 2;
        }

        return 0;
    }
}
