<?php namespace Bbt;

use AMQPEnvelope;
use AMQPQueue;

abstract class QueueRoutedWorkerScript extends CliScript
{
	public static $defaultMaxExecutionTime = 65;

	protected $tickPeriod = 0;              // call tick() every defined seconds. see tickIfTime()

	private $exchangeName;
	private $queueName;
	private $routingKey;
	private $workerMaxExecutionTime = 60;
	private $counterProcessed       = 0;


	abstract protected function route( $workload ): ?string;
	abstract protected function process( $workload, string $key ): void;
	abstract protected function getExchangeName() :string;

	protected function runMain() :void
	{
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGHUP, SIG_DFL);

		$this->routingKey = $this->getRoutingKey();

		$this->exchangeName = $this->getExchangeName();
		$this->queueName    = $this->getQueueName();

		Queue::getAmqpQueue($this->queueName)->bind($this->exchangeName, $this->routingKey);

		if( !empty($this->scriptParameters['WORKLOAD']) )
		{
			Queue::sendRawData($this->queueName, $this->getParameter('WORKLOAD'));
			
			$this->log__info('workload sent');
			
			return;
		}
		
		$command = $this->getParameter('cmd', '');
		
		if( !empty($command) )
		{
			$mult  = $this->getParameter('mult', 1);
			$sleep = $this->getParameter('sleep', 0);
			
			$arParameters = $this->scriptParameters;

            unset($arParameters[0], $arParameters['cmd'], $arParameters['mult'], $arParameters['sleep']);

            $this->log__info('COMMAND='.$command.' sending '.$mult.' times, sleep='.$sleep.' params='.json_encode($arParameters));
			
			$dCommand = [
				'~~~QUEUE_WORKER_COMMAND~~~' => $command,
				'S' => $sleep,
			    'P' => $arParameters
			];
			
			for( ; $mult>0; $mult--) Queue::sendData($this->queueName, $dCommand);
			
			$this->log__info('Command sent');
			
			return;
		}
		
		$this->workerMaxExecutionTime = $this->getParameter('maxExecutionTime', self::$defaultMaxExecutionTime);
		
		$this->log__debug('starting worker for queue='.$this->queueName);
		
		$this->runMain_blockingMode();
		
		$this->log__debug('finishing. items processed='.$this->counterProcessed.' time='.$this->getRunningTime());
	}

	/** @noinspection PhpUnusedPrivateMethodInspection */
	private function runMain_blockingMode() :void
	{
		if( $this->logTo == 'ECHO' ) $this->log__debug('WARNING: Running in blocking mode, onNoWorkload() will not be invoked. Execution time may be endless.');
		
		$that = $this;

		/** @noinspection PhpUnusedParameterInspection
		 * @param AMQPEnvelope $message
		 * @param AMQPQueue    $q
		 *
		 * @return bool
		 */
		$callBackFunc = function( AMQPEnvelope $message, AMQPQueue $q) use( $that ) {

            if( $that->isShutdownRequested ) return false;

			$mTimeStart = microtime(true);
			$workload = unserialize(base64_decode($message->getBody()), [false]);
			$key = $message->getRoutingKey();
			
			$q->nack($message->getDeliveryTag());
			
			if( is_array($workload) && isset($workload['~~~QUEUE_WORKER_COMMAND~~~']) )
			{
				$command = $workload['~~~QUEUE_WORKER_COMMAND~~~'];
				$parameters = $workload['P'];
				
				$this->log__debug('COMMAND='.$command.' parameters='.json_encode($parameters));

				switch( $command )
				{
					case 'EXIT':
						
	                    $this->log__debug('exiting (by command)');
	                    if( !empty($workload['S']) ) sleep($workload['S']);
	                    
	                    return false;
	                    
					case 'PING':
						
	                    $this->log__debug('PING received.');
	                    
	                    $this->tickIfTime();
	                    
						break;
						
					default: $this->onCommand($command, $parameters);
				}
				
				if( !empty($workload['S']) ) sleep($workload['S']);
				
				$that->log__debug('done. time='.round(microtime(true) - $mTimeStart, 3));
				
				if( $that->getRunningTime() > $that->workerMaxExecutionTime ) return false;
				
				return true;
			}
			
			$that->log__debug($workload);

			if( $key==='' )
			{
				$keyRouted = $this->route($workload);

				if( null !== $keyRouted )
				{
					Queue::sendDataRouted($this->getExchangeName(), $workload, $keyRouted);
				}
			}
			else
			{
				$this->process($workload, $key);
			}

            if( $that->isShutdownRequested ) return false;

			$this->tickIfTime();

			/** @noinspection PhpUndefinedVariableInspection */
			$that->log__debug('done. time='.round(microtime(true) - $mTimeStart, 3).' memory:'.round(memory_get_usage() / (1024 * 1024), 2).' Mb');
			
			$that->counterProcessed++;
			
			if( $that->getRunningTime() > $that->workerMaxExecutionTime ) return false;
			
			return true;
		};

		$consumerTag = mt_rand().'~~'.microtime(true).mt_rand();
		
		$q = Queue::getAmqpQueue($this->queueName);

		try
		{
			$q->consume($callBackFunc, AMQP_NOPARAM, $consumerTag);
			$q->cancel($consumerTag);
		}
		catch( \AMQPConnectionException | \AMQPChannelException $e )
		{
			$this->log__error('RABBIT-MQ ERROR: '.$e->getMessage());
		}
	}

	protected function tickIfTime() :void
	{
	    static $tsLastCalled;
	    
	    if( empty($this->tickPeriod) ) return;
	    
	    if( is_null($tsLastCalled) )
        {
            $tsLastCalled = time();
            return;
        }

        if( (time() - $tsLastCalled) < $this->tickPeriod ) return;
	    
	    $tsLastCalled = time();
	    
	    $this->log__debug('calling tick()...');
	    
	    $this->tick();
	}

	protected function onNoWorkload() :void{}
	protected function tick() :void {}

    public function onPcntlSignal($sigNo, $sigInfo)
    {
        exit(0);
    }

    protected function onCommand( $command, /** @noinspection PhpUnusedParameterInspection */
	                              array $parameters) :void
	{
		$this->log__error('onCommand method not implemented but command received='.$command);
	}

    public function displayHelp() :void
    {
        parent::displayHelp();

        echo("QueueWorker:\n");
        echo("\t--cmd=COMMAND\t\t\t send command (system commands: EXIT)\n");
        echo("\t--mult=X\t\t\t repeat command X times\n");
        echo("\t--sleep=S\t\t\t sleep S seconds after command sent\n");
        echo("\n\n");
    }

	protected function getRoutingKey() :string
	{
		return $this->getParameter('routingKey', '');
	}

	protected function getQueueName()
	{
		return $this->getExchangeName().'._'.$this->getRoutingKey();
	}

}