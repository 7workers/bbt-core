<?php namespace Bbt;

use MongoDB\BSON\Regex;
use MongoDB\Driver\WriteConcern;
use MongoDB\Operation\Aggregate;
use MongoDB\Operation\BulkWrite;

class AspectatorAggregator extends CliScript
{
	protected function runMain() :void
	{
		$this->log__debug('starting');
		
		$now = new \DateTime();
		
		$nowOverride = $this->getParameter('now', '');
		
		if( !empty($nowOverride) ) $now = new \DateTime($nowOverride);
		
		/** @noinspection PhpUnhandledExceptionInspection */
		$hourAgo = (clone($now))->sub(new \DateInterval('PT1H'));

		$idFilterDay = '^'.$hourAgo->format('ymd').'_';
		
		$this->getUniquesCollection()->aggregate([
			[ '$match' => [ '_id' => new Regex($idFilterDay) ] ],
			[ '$unwind' => '$u'],
			[ '$project' => [ 'u' => true, 'class' => true, 'entity' => true, 'aspect' => true ] ],
			[ '$group' => [ '_id' => [ 'class' => '$class', 'aspect' => '$aspect', 'entity' => '$entity', 'un' => '$u' ], 'un' => [ '$sum' => 1 ] ] ],
			[ '$group' => [ '_id' => [ 'class' => '$_id.class', 'entity' => '$_id.entity', 'aspect' => '$_id.aspect'], 'uniques' => ['$sum' => 1]]] ,
			[ '$out' => '_agg_day_uniques'],
		]);
		
		$this->getUniquesCollection()->aggregate([
			[ '$match' => [ '_id' => new Regex($idFilterDay) ] ],
			[ '$project' => [ 'n' => true, 'class' => true, 'entity' => true, 'aspect' => true ] ],
			[ '$group' => [ '_id' => [ 'class' => '$class', 'aspect' => '$aspect', 'entity' => '$entity', ], 'hits' => [ '$sum' => '$n' ] ] ],
			[ '$out' => '_agg_day_hits'],
		]);
		
		$this->updateStatsDayFromAggregation(\Fdt\Db::getCollection('_agg_day_uniques', 'aspectator'), $hourAgo);
		$this->updateStatsDayFromAggregation(\Fdt\Db::getCollection('_agg_day_hits', 'aspectator'), $hourAgo);

		\Fdt\Db::getCollection('_agg_day_uniques', 'aspectator')->deleteMany([], [ 'writeConcern' => new WriteConcern(0) ]);
		\Fdt\Db::getCollection('_agg_day_hits', 'aspectator')->deleteMany([], [ 'writeConcern' => new WriteConcern(0) ]);

		
		$idFilterHour = '^'.$hourAgo->format('ymd_H');
		
		$this->getUniquesCollection()->aggregate([
			[ '$match' => [ '_id' => new Regex($idFilterHour) ] ],
			[ '$unwind' => '$u'],
			[ '$project' => [ 'u' => true, 'class' => true, 'entity' => true, 'aspect' => true ] ],
			[ '$group' => [ '_id' => [ 'class' => '$class', 'aspect' => '$aspect', 'entity' => '$entity', 'un' => '$u' ], 'un' => [ '$sum' => 1 ] ] ],
			[ '$group' => [ '_id' => [ 'class' => '$_id.class', 'entity' => '$_id.entity', 'aspect' => '$_id.aspect'], 'uniques' => ['$sum' => 1]]] ,
			[ '$out' => '_agg_hour_uniques'],
		]);
		
		$this->getUniquesCollection()->aggregate([
			[ '$match' => [ '_id' => new Regex($idFilterHour) ] ],
			[ '$project' => [ 'n' => true, 'class' => true, 'entity' => true, 'aspect' => true ] ],
			[ '$group' => [ '_id' => [ 'class' => '$class', 'aspect' => '$aspect', 'entity' => '$entity', ], 'hits' => [ '$sum' => '$n' ] ] ],
			[ '$out' => '_agg_hour_hits'],
		]);
		
		$this->updateStatsHourFromAggregation(\Fdt\Db::getCollection('_agg_hour_uniques', 'aspectator'), $hourAgo);
		$this->updateStatsHourFromAggregation(\Fdt\Db::getCollection('_agg_hour_hits', 'aspectator'), $hourAgo);

		\Fdt\Db::getCollection('_agg_hour_uniques', 'aspectator')->deleteMany([], [ 'writeConcern' => new WriteConcern(0) ]);
		\Fdt\Db::getCollection('_agg_hour_hits', 'aspectator')->deleteMany([], [ 'writeConcern' => new WriteConcern(0) ]);
		
		$this->log__debug('done');die();
	}
	
	private function updateStatsHourFromAggregation( MongoDbCollection $collection, \DateTime $hourAgo )
	{
		$collStats = Db::getCollection('stats_h', 'aspectator');
		
		$fieldStats_hours = 'h'.$hourAgo->format('H');
		$date = (int)$hourAgo->format('ymd');
		
		$arBulk = [];
			
		foreach( $collection->find() as $dUniquesAgg_each )
		{
			['class' => $classEntity, 'aspect' => $aspect, 'entity' => $idEntity] = $dUniquesAgg_each['_id'];

			$dNewRecord_hours = [
				'class'  => $classEntity,
				'entity' => $idEntity,
				'aspect' => $aspect,
				'date' => $date,
			];
			
			//$idStats_days = $classEntity.'~'.$idEntity.'~'.$aspect.'~'.$hourAgo->format('ymd');

			$idStats_hours = [
				'class'  => $classEntity,
				'entity' => $idEntity,
				'aspect' => $aspect,
				'date'   => $date,
			];
			
			$dSet = [
			];
			
			if( isset($dUniquesAgg_each['uniques']) )
			{
				$dSet['hu.'.$fieldStats_hours] = $dUniquesAgg_each['uniques'];
			}
			
			if( isset($dUniquesAgg_each['hits']) )
			{
				$dSet['hh.'.$fieldStats_hours] = $dUniquesAgg_each['hits'];
			}

			$arBulk[] = [ 'updateOne' => [
				          [ '_id' => $idStats_hours ],
			              [
				              '$set'         => $dSet,
				              //'$setOnInsert' => $dNewRecord_hours,
			              ],
			              [ 'upsert' => true ],
			]];
			
			if( count($arBulk)>999 )
			{
				$collStats->bulkWrite($arBulk, ['writeConcern' => new WriteConcern(0)]);
				$arBulk = [];
			}
			
		}

		if( count($arBulk) > 0 )
		{
			$collStats->bulkWrite($arBulk, [ 'writeConcern' => new WriteConcern(0) ]);
		}
		
	}
	
	private function updateStatsDayFromAggregation( MongoDbCollection $collection, \DateTime $hourAgo )
	{
		
		$collStats_days = Db::getCollection('stats_d', 'aspectator');
		
		$fieldStats_days = 'd'.$hourAgo->format('ymd');
		
		$arBulk = [];
			
		foreach( $collection->find() as $dUniquesAgg_each )
		{
			['class' => $classEntity, 'aspect' => $aspect, 'entity' => $idEntity] = $dUniquesAgg_each['_id'];

			$dNewRecord_hours = [
				'class'  => $classEntity,
				'entity' => $idEntity,
				'aspect' => $aspect,
			];
			
			//$idStats_days = $classEntity.'~'.$idEntity.'~'.$aspect;
			
			$idStats_days = [
				'class' => $classEntity,
				'entity' => $idEntity,
				'aspect' => $aspect,
			];
			
			$dSet = [
			];
			
			if( isset($dUniquesAgg_each['uniques']) )
			{
				$dSet['du.'.$fieldStats_days] = $dUniquesAgg_each['uniques'];
			}
			
			if( isset($dUniquesAgg_each['hits']) )
			{
				$dSet['dh.'.$fieldStats_days] = $dUniquesAgg_each['hits'];
			}

			$arBulk[] = [ 'updateOne' => [
				          [ '_id' => $idStats_days ],
			              [
				              '$set'         => $dSet,
				              //'$setOnInsert' => $dNewRecord_hours,
			              ],
			              [ 'upsert' => true ],
			]];
			
			if( count($arBulk)>999 )
			{
				$collStats_days->bulkWrite($arBulk, ['writeConcern' => new WriteConcern(0)]);
				$arBulk = [];
			}
			
		}

		if( count($arBulk) > 0 )
		{
			$collStats_days->bulkWrite($arBulk, [ 'writeConcern' => new WriteConcern(0) ]);
		}
		
	}
	
	private function getUniquesCollection() 
	{
		/** @noinspection PhpUnhandledExceptionInspection */
		return Db::getCollection('uniques', 'aspectator');
	}
}