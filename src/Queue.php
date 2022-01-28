<?php namespace Bbt;


use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use AMQPQueue;

class Queue
{
    public const SCHEDULER = 'bbt_sch';
    public const SCHEDULER2 = 'bbt.sch'; // this is hardcoded also in QueueSchedulerWorkload::queueName

    public static $server
        = [
            'host'      => '127.0.0.1',
            'login'     => 'guest',
            'password'  => 'guest',
        ];

    protected static $arQueues = [];

    public static $enabledForwardToSelfNode;
    /**
     * @var array
     */
    public static $arLocalQueues = [];

    public static function sendData($queueName, $data) :void
	{
		self::sendRawData($queueName, base64_encode(serialize($data)));
	}

    public static function sendRawData($queueName, string $data, ?array $headers=[]) :void
	{
        $attributes = [];

        if (!empty($headers)) $attributes['headers'] = $headers;

        if (true !== self::$enabledForwardToSelfNode || in_array($queueName, self::$arLocalQueues)) {
            self::getAmqpExchange()->publish($data, $queueName, AMQP_NOPARAM, $attributes);
            return;
        }

        SelfNode::sendQueueData($queueName, $data, $headers);
	}


    /**
     * Schedule workload for later processing, see \Bbt\QueueScheduler2
     *
     * @param string     $queueName
     * @param            $data
     * @param int        $tsOrDelay delay in seconds or timestamp
     * @param array|null $headers
     *
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public static function sendRawDataDelayed( string $queueName, $data, int $tsOrDelay, ?array $headers=[] ) :void
    {
        if( $tsOrDelay < 2000000 ) $tsOrDelay = time() + $tsOrDelay;

        $attributes = [
            'headers' => $headers ?? []
        ];

        $attributes['headers']['x-ts']    = $tsOrDelay;
        $attributes['headers']['x-queue'] = $queueName;

        if( true !== self::$enabledForwardToSelfNode  || in_array($queueName, self::$arLocalQueues) ) {
            self::getAmqpExchange()->publish($data, self::SCHEDULER2, AMQP_NOPARAM, $attributes);
            return;
        }

        SelfNode::sendQueueData(self::SCHEDULER2, $data, $attributes['headers']);
    }

	public static function sendDataRouted( string $exchangeName, $data, ?string $key=null, ?int $ttlSeconds=null )
	{
		$exchange = self::getExchange($exchangeName);

		$dFlags = [];

		if( null!==$ttlSeconds )
		{
			$dFlags['expiration'] = 1000 * $ttlSeconds;
		}

		$exchange->publish(base64_encode(serialize($data)), $key, AMQP_NOPARAM, $dFlags);
	}

	public static function getExchange( string $exchangeName ) :AMQPExchange
	{
		static $cached = [];

		if( isset($cached[$exchangeName]) ) return $cached[$exchangeName];

		$exchange = new AMQPExchange(self::getAmqpChannel());
		$exchange->setType(AMQP_EX_TYPE_DIRECT);
		$exchange->setName($exchangeName);
		$exchange->setFlags(AMQP_DURABLE);
		$exchange->declareExchange();

		$cached[$exchangeName] = $exchange;

		return $exchange;
	}

    /**
     * Schedule workload for later processing
     *
     * @param string $queueName
     * @param        $workload
     * @param int    $tsOrDelay delay in seconds or timestamp
     */
    public static function scheduleWorkload( string $queueName, $workload, int $tsOrDelay ) :void
    {
        if( $tsOrDelay < 2000000 ) $tsOrDelay = time() + $tsOrDelay;

        self::sendData(self::SCHEDULER, [ $queueName, $tsOrDelay, $workload ]);
    }

    public static function scheduleRoutedWorkload( string $exchangeName, $routingKey, $workload, int $tsOrDelay ) :void
    {
	    if( $tsOrDelay < 2000000 ) $tsOrDelay = time() + $tsOrDelay;

	    self::sendData(self::SCHEDULER, [ $exchangeName.'/'.$routingKey, $tsOrDelay, $workload ]);
    }

	public static function getData($queueName, $waitTimeoutSeconds=0)
	{
		$q = self::getAmqpQueue($queueName);

		$tsExit = time() + $waitTimeoutSeconds;

        do {
            /** @noinspection PhpAssignmentInConditionInspection */
            while ($envelope = $q->get(AMQP_AUTOACK)) {
                $data = $envelope->getBody();
                if ( !empty($data)) return unserialize(base64_decode($data), [false]);
            }

            if (time() >= $tsExit) return null;

            usleep(600000);
        } while (true);
	}

	public static function getAmqpQueueNewConnectionWithTimeout($queueName, $timeout=1)
    {
        $ch = new AMQPChannel(self::getNewAmqpConnectionWithTimeout($timeout));

        $q = new AMQPQueue($ch);
        $q->setName($queueName);
        $q->setFlags(AMQP_NOPARAM);
        $q->declareQueue();

        return $q;
    }

	/**
	 * @param $queueName
	 *
	 * @return AMQPQueue
	 * @throws \AMQPChannelException
	 * @throws \AMQPConnectionException
	 * @throws \AMQPQueueException
	 */
	public static function getAmqpQueue($queueName)
	{
		static $arQueues;

        if ( !isset($arQueues[$queueName])) {
            $ch = self::getAmqpChannel();

            $q = new AMQPQueue($ch);
            $q->setName($queueName);
            $q->setFlags(AMQP_NOPARAM);
            $q->declareQueue();

            $arQueues[$queueName] = $q;
        } else {
            $q = $arQueues[$queueName];
        }
	
		return $q;
	}

	public static function getAmqpChannel()
	{
		static $channel;

		if( !is_null($channel)) return $channel;

		$cnn = self::getAmqpConnection();
		$channel = new AMQPChannel($cnn);

		return $channel;
	}

    public static function getAmqpExchange(): AMQPExchange
    {
        static $amqpExchange;

        if (is_null($amqpExchange)) {
            $ch           = self::getAmqpChannel();
            $amqpExchange = new AMQPExchange($ch);
        }

        return $amqpExchange;
    }

	public static function getNewAmqpConnectionWithTimeout($timeout=1):AMQPConnection
    {
        $cfg = self::$server;
        $cfg['read_timeout'] = $timeout;

        $cnn = new AMQPConnection($cfg);
        $cnn->connect();

        return $cnn;
    }

	public static function getAmqpConnection():AMQPConnection
    {
        static $cnn;

        if( !is_null($cnn) ) return $cnn;

        $cnn = new AMQPConnection(self::$server);
        $cnn->connect();

        return $cnn;
    }

	public static function getQueueCount($queueName)
	{
		if( !isset(self::$arQueues[$queueName]) )
		{
			$ch = self::getAmqpChannel();

			$q = new AMQPQueue($ch);
			$q->setName($queueName);
			$q->setFlags(AMQP_NOPARAM);

			self::$arQueues[$queueName] = $q;
		}
		else
		{
			$q = self::$arQueues[$queueName];
		}

		return $q->declareQueue();
	}

	public static function skipMessages($nofMessagesToSkip, $queueName)
	{
		for( ; $nofMessagesToSkip > 0; $nofMessagesToSkip--)
		{
			self::getData($queueName);
		}
	}
}