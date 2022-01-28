<?php namespace Bbt;

use Gelf\Message;
use Gelf\Publisher;
use Gelf\Transport\UdpTransport;
use Monolog\Formatter\GelfMessageFormatter;
use Monolog\Handler\GelfHandler;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;

abstract class Logger
{
    public const TYPE__STDOUT = 'stdout';
    public const TYPE__FILE = 'file';
    public const TYPE__GELF = 'gelf';
    public const TYPE__NULL = 'null';

    public static $type = 'stdout';

    public static $level = 'info';

    public static $dirLogs = '/var/log';

    public static $gelfHost = '127.0.0.1';
    public static $gelfPort = 12201;
    public static $gelfFacility = 'local5';

    /**
     * @var LoggerInterface
     */
    protected static $arLoggers = [];

    /**
     * @throws \Exception
     */
    public static function getLogger($who, $level=null, $type=null): LoggerInterface
    {
        $who = self::stringifyWho($who);

        if (isset(self::$arLoggers[$who])) return self::$arLoggers[$who];

        $level = $level ?? self::$level;
        $type = $type ?? self::$type;

        switch ($type) {
            default:
            case self::TYPE__STDOUT:
                return self::setupStdoutLogger($who, $level);
            case self::TYPE__FILE:
                return self::setupFileLogger($who, self::$dirLogs.'/'.$who.'.log', $level);
            case self::TYPE__GELF:
                return self::setupGelfLogger($who, self::$gelfHost, self::$gelfPort, self::$gelfFacility, $level);
            case self::TYPE__NULL:
                return self::setupNullLogger($who);
        }
    }

    public static function autoAdjustRuntime(): void
    {
        if( !isset($GLOBALS['argv']) ) return;

        foreach ($GLOBALS['argv'] as $arg_each) {
            if( $arg_each === '--debug' ) {
                self::$type = 'stdout';
            }

            /** @noinspection PhpStrFunctionsInspection */
            if (strpos($arg_each, '--logType=') === 0) {
                self::$type = substr($arg_each, 10);
            }

            /** @noinspection PhpStrFunctionsInspection */
            if (strpos($arg_each, '--level=') === 0) {
                self::$level = substr($arg_each, 8);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public static function setupFileLogger($who, string $filename, $level): LoggerInterface
    {
        $who = self::stringifyWho($who);

        $handler = new StreamHandler($filename, $level);
        self::$arLoggers[$who] = new \Monolog\Logger($who, [$handler]);

        return self::$arLoggers[$who];
    }

    /**
     * @throws \Exception
     */
    public static function setupStdoutLogger($who, $level): LoggerInterface
    {
        $who = self::stringifyWho($who);

        $handler = new StreamHandler('php://stdout', $level);
        self::$arLoggers[$who] = new \Monolog\Logger($who, [$handler]);

        return self::$arLoggers[$who];
    }

    public static function setupGelfLogger($who, string $host, int $port, string $facility, $level): LoggerInterface
    {
        $who = self::stringifyWho($who);

        $transport = new UdpTransport($host, $port, UdpTransport::CHUNK_SIZE_WAN);
        $publisher = new Publisher($transport);

        $handler = new GelfHandler($publisher, $level);

        $formatter = new class (null, null, '') extends GelfMessageFormatter {
            public $extraClass;
            public function format(array $record): Message
            {
                $record['extra']['class'] = $this->extraClass;
                return parent::format($record);
            }
        };

        $formatter->extraClass = $who;

        $handler->setFormatter($formatter);
        self::$arLoggers[$who] = new \Monolog\Logger($facility, [$handler]);

        return self::$arLoggers[$who];
    }

    public static function setupNullLogger($who): LoggerInterface
    {
        $who = self::stringifyWho($who);

        self::$arLoggers[$who] = new NullLogger();

        return self::$arLoggers[$who];
    }

    public static function stringifyWho($who): string
    {
        if (is_object($who)) {
            $reflect = new ReflectionClass($who);
            $who = $reflect->getShortName();
        } elseif ($pos = strrpos($who, '\\')) {
            $who = substr($who, $pos + 1);
        }
        return $who;
    }


}