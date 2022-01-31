<?php namespace Bbt;

class HpdWorker extends QueueWorkerScript
{
	protected $blockingMode = true;
	
	protected function process( $workload ) :void 
	{
		list($class, $id) = $workload;
		
		/** @var HpdInstance $hpd */
		$hpd = new $class($id);

		if( $hpd->isReady() )
		{
			$hpd->touch();
			$this->log__debug('previously processed, skip');

			return;
		}
		
		$hpd->preCache();
		$hpd->save();
	}

	protected function getQueueName() :string 
	{
		$subQ = $this->getParameter('subQueue', '');
		if( !empty($subQ) ) $subQ = '--'.$subQ;
		return HpdInstance::$queueName . $subQ;
	}
}