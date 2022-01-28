<?php namespace Bbt\Dpf;

use Bbt\QueueWorkerScript;

class QueueProcessDpf extends QueueWorkerScript
{
	protected $help = '--subQueue= --maxExecutionTime=60 --debug=0';
	
	protected $blockingMode = true;

	protected function process( $workload ) :void
	{
		$this->logPrefix = $workload.' ';
		
		try {

			$dpfInstance = DpfInstance::constructByQueueWorkload($workload);

			$data = $dpfInstance->getPreCachedData();

			if( !is_null($data) )
			{
				//$dpfInstance->touchCache();
				
				$this->log__debug('data is already processed');
				
				return;
			}

			$dpfInstance->preCache();

			$dpfInstance = null;
			unset($dpfInstance);

		}
		catch(\Exception $e)
		{
			$this->log__error($e->getMessage());
		}
	}

	protected function getQueueName() :string 
	{
		$subQueue = $this->getParameter('subQueue', '');

		$queueName = DpfInstance::$queueName;

		if( !empty($subQueue) ) $queueName.= '-'.$subQueue;

		return $queueName;
	}
}