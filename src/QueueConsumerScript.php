<?php namespace Bbt;

use AMQPChannelException;
use AMQPConnectionException;
use AMQPEnvelope;
use AMQPEnvelopeException;
use AMQPExchange;
use AMQPQueue;
use AMQPQueueException;
use RuntimeException;
use Throwable;

abstract class QueueConsumerScript extends CliScript
{
	public static $defaultMaxExecutionTime = 65;

    public static $queueTimeout = 5;
	
	protected $tickPeriod = 0;              // call tick() every defined seconds. see tickIfTime()

	private   $workerMaxExecutionTime = 60;
	private   $counterProcessed       = 0;
	
	abstract protected function consume( QueueWorkload $wl ): void;
	abstract protected function getWorkloadObject(): QueueWorkload;
	
	protected function runMain() : void
	{
        $pidToKill = $this->getParameter('kill',  0);

        if (!empty($pidToKill)) {
            $isKilled = $this->killMe($pidToKill);
            if( $isKilled ) return;
            $this->log__warning('still not terminated PID='.$pidToKill.'. giving up.');
            return;
        }

        if (isset($this->scriptParameters['kill'])) return;

		$command = $this->getParameter('cmd', '');

        if (!empty($command)) {

            $mult  = $this->getParameter('mult', 1);
            $sleep = $this->getParameter('sleep', 0);

            $arParameters = $this->scriptParameters;

            unset($arParameters[0], $arParameters['cmd'], $arParameters['mult'], $arParameters['sleep']);

            /** @noinspection PhpUnhandledExceptionInspection */
            $this->sendCommand($command, $arParameters, $mult, $sleep);

            $this->log__debug('Command sent');

            return;
        }
		
		$this->runConsumer();
	}

    private function killMe($pid): bool
    {
        $this->log__info('attempt to kill PID=' . $pid . '...');

        posix_kill($pid, SIGINT);
        sleep(1);

        if (!file_exists('/proc/' . $pid)) {
            $this->log__info('PID=' . $pid . ' terminated');
            return true;
        }

        $this->log__info('sending KILL command for PID=' . $pid . '...');

        $this->sendCommand('KILL', ['PID' => $pid, 't' => time()+10]);

        sleep(2);

        for ($i = 5; $i > 0; $i--) {

            posix_kill($pid, SIGINT);
            sleep(1);

            if (!file_exists('/proc/' . $pid)) {
                $this->log__info('PID=' . $pid . ' terminated');
                return true;
            }
        }

        posix_kill($pid, SIGKILL);
        sleep(1);

        if (!file_exists('/proc/' . $pid)) {
            $this->log__info('PID=' . $pid . ' terminated (killed)');
            return true;
        }

        return false;
    }

    /**
     * @throws AMQPEnvelopeException
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     */
    protected function runConsumer() :void
	{
		$this->workerMaxExecutionTime = $this->getParameter('maxExecutionTime', self::$defaultMaxExecutionTime);
		
		$that = $this;
		
		$wl = $this->getWorkloadObject();
		$wl->counter = 0;

        if (isset($this->scriptParameters['flush'])) {
            /**
             * @param AMQPEnvelope $message
             * @param AMQPQueue    $q
             *
             * @return bool
             * @throws AMQPChannelException
             * @throws AMQPConnectionException
             */
            $callBackFunc = static function (AMQPEnvelope $message, AMQPQueue $q) use ($that) {
                $q->ack($message->getDeliveryTag());
                $that->counterProcessed++;
                if ($that->getRunningTime() > $that->workerMaxExecutionTime) return false;

                return true;
            };
        } else {
            $callBackFunc = static function (AMQPEnvelope $message, AMQPQueue $q) use ($that, $wl) {

                if( $that->isShutdownRequested ) return false;

                $q->ack($message->getDeliveryTag());

                $command = $message->getHeader('cmd');

                if ( !empty($command)) {
                    $sleep = $message->getHeader('sleep');
                    $continueConsumption = $that->onCommand($command, unserialize($message->getBody(), [false]));
                    if ( !empty($sleep)) sleep($sleep);
                    return $continueConsumption;
                }

                $wl->load($message);

                $attempt  = $message->getHeader('x-attempt');

                if ($attempt === false) $attempt = 1;

                $wl->attempt = $attempt;

                if ($that->logLevel === self::LOG_LEVEL__TRACE) {
                    $that->log__trace(get_class_nons($wl).json_encode($wl));
                }

                $wl->counter++;

                $that->consume($wl);
                $that->resetContext();

                $that->counterProcessed++;

                $that->tickIfTime();

                if ($that->getRunningTime() > $that->workerMaxExecutionTime) return false;

                return true;
            };
		}

		$consumerTag = mt_rand().'~~'.microtime(true).mt_rand();

        $qName = $this->getQueueName();

        $this->log__debug('starting. queue='.$qName);

        $q = Queue::getAmqpQueueNewConnectionWithTimeout($qName, self::$queueTimeout);

        while (true) {
            try {

                $q->consume($callBackFunc, AMQP_NOPARAM, $consumerTag);
                $q->cancel($consumerTag);

                $perSecond = round($this->counterProcessed / (0.001 + $this->getRunningTime()), 2);

                $this->log__debug("finished. processed=".$this->counterProcessed."\ttime=".$this->getRunningTime()."\t\t~".$perSecond.'/sec');
                return;

            } catch (\AMQPQueueException $e) {
                $q->cancel($consumerTag);
                //$this->log__debug('AMQP timeout, waiting for signals before connecting again...');
                $this->tickIfTime();
                sleep(self::$queueTimeout);
                $q = Queue::getAmqpQueueNewConnectionWithTimeout($qName,self::$queueTimeout);
                $this->tickIfTime();
            }

            if( $this->isShutdownRequested ) return;
        }
	}

    protected function onCommand(string $command, ?array $arParameters): bool
    {
        switch ($command) {
            case 'KILL':
                $pid = $arParameters['PID'];

                if ( $pid == $this->context['pid'] ) {
                    $this->log__info('this is my pid. exiting.');
                    return false;
                }

                if ( file_exists('/proc/' . $pid)) {
                    if( ($arParameters['t']??0) > time() ) {
                        $this->sendCommand('KILL', ['PID' => $pid, 't' => $arParameters['t']]);
                        sleep(1);
                    }
                }

                return true;

            case 'EXIT':

                $this->log__debug('exiting (by command)');
                return false;

            case 'PING':

                $this->log__debug('PING received.');
                $this->tickIfTime();
                return true;

            default:
                throw new RuntimeException('unknown command='.$command);
        }
    }
	
	protected function tickIfTime() :void
	{
	    static $tsLastCalled;
	    
	    if( empty($this->tickPeriod) ) return;

        if (is_null($tsLastCalled)) {
            $tsLastCalled = time();
            return;
        }

        if( (time() - $tsLastCalled) < $this->tickPeriod ) return;
	    
	    $tsLastCalled = time();
	    
	    $this->log__debug('calling tick()...');
	    
	    $this->tick();
	}
	
	protected function tick() :void {}

	/**
	 * @param $command
	 *
	 * @throws AMQPChannelException
	 * @throws AMQPConnectionException
	 * @throws \AMQPExchangeException
	 */
	private function sendCommand( $command, $arParameters, $mult=1, $sleep=0 ): void
	{
		$this->log__debug('COMMAND='.$command.' sending '.$mult.' times, sleep='.$sleep.' params='.json_encode($arParameters));

		$wl = $this->getWorkloadObject();

		$ch = Queue::getAmqpChannel();
		
		$amqpExchange = new AMQPExchange($ch);

        for (; $mult > 0; $mult--) {
            $amqpExchange->publish(
                serialize($arParameters),
                $wl->getQueueName(),
                AMQP_NOPARAM,
                [
                    'headers' => ['cmd' => $command, 'sleep' => $sleep],
                ]
            );
        }
	}

	public function displayHelp()
	{
		parent::displayHelp();
		
		echo("\t--flush\t\t\trun in flush mode - get workload and ignore\n\n");
	}

	protected function getQueueName():string
    {
        return $this->getWorkloadObject()->getQueueName();
    }

}