<?php

namespace Server;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Class Log
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package Server
 * @method static void info(string $message, array $context = []) Logs an info message.
 * @method static void notice(string $message, array $context = []) Logs a notice message.
 * @method static void warning(string $message, array $context = []) Logs a warning message.
 * @method static void error(string $message, array $context = []) Logs an error message.
 * @method static void critical(string $message, array $context = []) Logs a critical message.
 * @method static void alert(string $message, array $context = []) Logs an alert message.
 * @method static void emergency(string $message, array $context = []) Logs an emergency message.
 * @method static void debug(string $message, array $context = []) Logs a debug message.
 */
class Log
{
    /**
     * @var Logger
     */
    public static Logger $logger;


    private static $exitOnError = false;

    /**
     * Initialize the logger.
     *
     * @param  string  $channelName
     * @param  string  $logFilePath
     * @param  int     $logLevel
     */
    public static function init(
        string $channelName,
        string $logFilePath = 'php://stderr',
        int|string|Level $logLevel = Level::Notice
    ): void {
        if (isset(self::$logger)) {
            self::$logger->close();
        }

        $handler = new StreamHandler($logFilePath, $logLevel);
        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);

        // Optional: Customize the log format
        // $handler->setFormatter(new LineFormatter(null, null, true, true));

        self::$logger = new Logger($channelName);
        self::$logger->pushHandler($handler);
    }


    /**
     * @param  string  $name
     * @param  array   $arguments
     *
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $result = self::$logger->$name(...$arguments);

        if (self::$exitOnError &&\in_array($name, ['error', 'critical'])) {
            echo "Exit 1" . \PHP_EOL;

            exit (1);
        }

        return $result;
    }


    public static function isExitOnError(): bool
    {
        return self::$exitOnError;
    }


    public static function setExitOnError(bool $exitOnError): void
    {
        self::$exitOnError = $exitOnError;
    }
}
