<?php namespace Bbt;

use MongoDB\Driver\WriteConcern;

class AspectatorWorker extends QueueWorkerScript
{
	private $arBuffers = [];
	
	protected $blockingMode = true;
	protected $tickPeriod = 20;
	private $arIgnores = [];

	protected function onScriptStart() :void 
	{
		$fname = $this->getParameter('ignore', '');
		
		if( !empty($fname) && file_exists($fname) )
		{
			$this->arIgnores = file($fname, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			$this->arIgnores = array_map('trim', $this->arIgnores);
		}
	}

	protected function process( $workload ) :void
	{
		[ 'C' => $classEntity, 'E' => $idEntity, 'A' => $idAspect, 'U' => $idEvent ] = $workload;
		
		if( $this->isIgnored($classEntity, $idEntity, $idAspect, $idEvent) ) return;
		
		$now = new \DateTime();

		$idInBuffers = $classEntity.'~'.$idEntity.'~'.$idAspect;
		
		$timestamp = $now->format('H:i');
		
		$idLog = $now->format('ymd_H:i').'~'.$classEntity.'~'.$idEntity.'~'.$idAspect;

		if( !isset($this->arBuffers[$idInBuffers]) ) $this->arBuffers[$idInBuffers] = [];
		
		if( !isset($this->arBuffers[$idInBuffers][$timestamp]) )
		{
			$this->arBuffers[$idInBuffers] = [
				$timestamp => [
					'_id'    => $idLog,
					'entity' => $idEntity,
					'class'  => $classEntity,
					'aspect' => $idAspect,
					'date'   => (int)$now->format('ymd'),
					'h'      => (string)$now->format('H'),
					'm'      => (string)$now->format('i'),
					'w'      => (string)$now->format('N'),
					'n'      => 0,
					'u'      => [],
				],
			];
			
			if( null === $idEvent ) unset($this->arBuffers[$idInBuffers][$timestamp]['u']);
		}
		
		$this->arBuffers[$idInBuffers][$timestamp]['n'] ++;
		
		if( null!==$idEvent )
		{
			$this->arBuffers[$idInBuffers][$timestamp]['u'][] = $idEvent;
		}
	}

	protected function onScriptExit() :void
	{
		$this->flushBuffers();
	}

	protected function tick(): void
	{
		$this->flushBuffers();
	}

	private function flushBuffers(): void
	{
		$this->log__trace('flushing buffers...');
		
		$coll = $this->getUniquesCollection();
		
		foreach( $this->arBuffers as $k => $dBufferPerTime_each )
		{
			foreach( $dBufferPerTime_each as $timestamp_each => $dBuffer_each )
			{
				if( isset($dBuffer_each['u']) )
				{
					$dBuffer_each['u'] = array_values(array_unique($dBuffer_each['u']));

					$this->arBuffers[$k][$timestamp_each]['u'] = $dBuffer_each['u'];
				}

				$idLog    = $dBuffer_each['_id'];
				$nofHits  = $dBuffer_each['n'];
				
				if( $nofHits >0 )
				{
					$this->log__debug('writing DB:'.$idLog);
					
					$dNewRecord = [
						'entity' => $dBuffer_each['entity'],
						'aspect' => $dBuffer_each['aspect'],
						'class'  => $dBuffer_each['class'],
						'date'   => $dBuffer_each['date'],
						'h'      => $dBuffer_each['h'],
						'm'      => $dBuffer_each['m'],
						'w'      => $dBuffer_each['w'],
					];
					
					if( isset($dBuffer_each['u']) )
					{
						$coll->updateById( $idLog,
							[ '$addToSet' => [ 'u' => [ '$each' => $dBuffer_each['u'] ] ], '$inc' => [ 'n' => $nofHits ], '$setOnInsert' => $dNewRecord ],
							[ 'upsert' => true, [ 'writeConcern' => new WriteConcern(0) ] ]
						);
						
					}
					else
					{
						$coll->updateById( $idLog,
							[ '$inc' => [ 'n' => $nofHits ], '$setOnInsert' => $dNewRecord ],
							[ 'upsert' => true, [ 'writeConcern' => new WriteConcern(0) ] ]
						);						
					}
	
				}
				
				unset($this->arBuffers[$k][$timestamp_each]);
			}
		}
	}
	
	private function isIgnored( string $classEntity, string $idEntity, string $idAspect, $idEvent ) :bool 
	{
		if( empty(trim($idEntity)) ) return true;
		if( in_array($classEntity.'~'.$idEntity, $this->arIgnores) ) return true;

		if( false !== strstr($idEntity, ' ./[]{}"\',*&!%\\?') ) return true;
		
		return false;
	}
	
	private function getUniquesCollection() 
	{
		return Db::getCollection('uniques', 'aspectator');
	}
	
	protected function getQueueName(): string { return Aspectator::$QUEUE_NAME; }


}