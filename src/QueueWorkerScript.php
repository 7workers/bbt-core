<?php namespace Bbt;


use AMQPEnvelope;
use AMQPQueue;

abstract class QueueWorkerScript extends CliScript
{
	public static $defaultMaxExecutionTime = 65;
    public static $queueTimeout = 5;

    protected $blockingMode = true;
	
	protected $tickPeriod = 0;              // call tick() every defined seconds. see tickIfTime()
	
	private   $queueName;
	private   $workerMaxExecutionTime = 60;
	private   $counterProcessed       = 0;

	abstract protected function process($workload) :void;
	abstract protected function getQueueName() :string;

    public function run()
    {
        $this->queueName = $this->getQueueName();

        $pidToKill = $this->getParameter('kill',  0);

        if (!empty($pidToKill)) {
            $isKilled = $this->killMe($pidToKill);
            if( $isKilled ) return;
            $this->log__warning('still not terminated PID='.$pidToKill.'. giving up.');
            return;
        }

        if (isset($this->scriptParameters['kill'])) return;

        $command = $this->getParameter('cmd', '');

        if( !empty($command) ) {

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

        parent::run();
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

        $this->log__info('sending KILL command via queue='.$this->queueName.' for PID=' . $pid . '...');

        $dCommand = [
            '~~~QUEUE_WORKER_COMMAND~~~' => 'KILL',
            'P' => ['PID' => $pid, 't' => time()+10 ]
        ];

        Queue::sendData($this->queueName, $dCommand);

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

    protected function runMain() :void
	{
		$this->queueName = $this->getQueueName();

        if ( !empty($this->scriptParameters['WORKLOAD'])) {
            Queue::sendRawData($this->queueName, $this->getParameter('WORKLOAD'));

            $this->log__info('workload sent');

            return;
        }

		$this->workerMaxExecutionTime = $this->getParameter('maxExecutionTime', self::$defaultMaxExecutionTime);
		
		$this->log__debug('starting worker for queue='.$this->queueName);

        if ($this->blockingMode) {
            $this->runMain_blockingMode();
        } else {
            $this->runMain_nonBlockingMode();
        }
		
		$this->log__debug('finishing. items processed='.$this->counterProcessed.' time='.$this->getRunningTime());
	}

    /**
     * @throws \AMQPEnvelopeException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    private function runMain_blockingMode() :void
	{
		if( $this->logTo == 'ECHO' ) $this->log__debug('WARNING: Running in blocking mode, onNoWorkload() will not be invoked. Execution time may be endless.');
		
		$that = $this;

		/**
		 * @param AMQPEnvelope $message
		 * @param AMQPQueue    $q
		 *
		 * @return bool
		 */
		$callBackFunc = function( AMQPEnvelope $message, AMQPQueue $q) use( $that ) {

            if( $that->isShutdownRequested ) return false;
			
			$mTimeStart = microtime(true);
			$workload = unserialize(base64_decode($message->getBody()), [false]);
			
			$q->ack($message->getDeliveryTag());
			
			if( is_array($workload) and isset($workload['~~~QUEUE_WORKER_COMMAND~~~']) ) {
				$command = $workload['~~~QUEUE_WORKER_COMMAND~~~'];
				$parameters = $workload['P'];
				
				$this->log__debug('COMMAND='.$command.' parameters='.json_encode($parameters));

                switch ($command) {
                    case 'KILL':
                        $pid = $parameters['PID'];

                        if ( $pid == $this->context['pid'] ) {
                            $this->log__info('this is my pid. exiting.');
                            return false;
                        } else {
                            $this->log__info('PID NOT MATCHED');
                        }

                        if ( file_exists('/proc/' . $pid)) {
                            if( ($parameters['t']??0) > time() ) {
                                Queue::sendData($that->queueName, $workload);
                                sleep(1);
                            }
                        }

                        break;
                    case 'EXIT':

                        $this->log__debug('exiting (by command)');
                        if ( !empty($workload['S'])) sleep($workload['S']);

                        return false;

                    case 'PING':

                        $this->log__debug('PING received.');

                        $this->tickIfTime();

                        break;

                    default:
                        $this->onCommand($command, $parameters);
                }
				
				if( !empty($workload['S']) ) sleep($workload['S']);
				
				$that->log__debug('done. time='.round(microtime(true) - $mTimeStart, 3));
				
				if( $that->getRunningTime() > $that->workerMaxExecutionTime ) return false;
				
				return true;
			}
			
			$that->log__debug($workload);

            $that->process($workload);
            $that->resetContext();

            if( $that->isShutdownRequested ) return false;

			$this->tickIfTime();

			$that->log__debug('done. time='.round(microtime(true) - $mTimeStart, 3).' memory:'.round(memory_get_usage() / (1024 * 1024), 2).' Mb');
			
			$that->counterProcessed++;
			
			if( $that->getRunningTime() > $that->workerMaxExecutionTime ) return false;
			
			return true;
		};

		$consumerTag = mt_rand().'~~'.microtime(true).mt_rand();

        $q = Queue::getAmqpQueueNewConnectionWithTimeout($this->queueName, self::$queueTimeout);

        while (true) {
            try {
                $q->consume($callBackFunc, AMQP_NOPARAM, $consumerTag);
                $q->cancel($consumerTag);
                return;
            } catch (\AMQPQueueException $e) {
                $q->cancel($consumerTag);
                //$this->log__debug('AMQP timeout, waiting for signals before connecting again...');
                $this->tickIfTime();
                sleep(self::$queueTimeout);
		        $q = Queue::getAmqpQueueNewConnectionWithTimeout($this->queueName,self::$queueTimeout);
                $this->tickIfTime();
            }

            if( $this->isShutdownRequested ) return;
        }
	}
	
	private function runMain_nonBlockingMode() :void
	{
		do {

			$workload = Queue::getData($this->queueName, 1);
				
			if( !is_null($workload) )
			{
				$mTimeStart = microtime(true);
				
				if( is_array($workload) and isset($workload['~~~QUEUE_WORKER_COMMAND~~~']) )
				{
					$command = $workload['~~~QUEUE_WORKER_COMMAND~~~'];
					$parameters = $workload['P'];
					
					$this->log__debug('COMMAND='.$command.' parameters='.json_encode($parameters));

                    if ($command == 'EXIT') {
                        $this->log__debug('exiting (by command)');
                        if ( !empty($workload['S'])) sleep($workload['S']);
                        break;
                    }

                    if ($command == 'PING') {
                        $this->log__debug('PING received');

                        $this->tickIfTime();
                    }

					$this->onCommand($command, $parameters);
					
					if( !empty($workload['S']) ) sleep($workload['S']);
					
					$this->log__debug('done. time='.round(microtime(true) - $mTimeStart, 3));
					
					continue;
				}
				
				$this->log__debug($workload);

				$this->process($workload);
                $this->resetContext();

				$this->log__debug('done. time='.round(microtime(true) - $mTimeStart,3));
				$this->log__debug('Memory:'.round(memory_get_usage()/(1024*1024),2).' Mb');
				
				$this->counterProcessed++;
			}
			else
			{
				$this->tickIfTime();
				$this->onNoWorkload();
			}

		} while($this->getRunningTime() < $this->workerMaxExecutionTime );
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

	protected function onNoWorkload() :void{}
	protected function tick() :void {}
	
	protected function breakProcessing() :void
    {
        // temporary "hack" 
        $this->workerMaxExecutionTime = -1;
    }

	protected function onCommand( $command, array $parameters) :void
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


}