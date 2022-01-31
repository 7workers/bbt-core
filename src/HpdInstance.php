<?php namespace Bbt;

abstract class HpdInstance
{
	public static $queueName = 'bbt.hpd';
    public static $ignoreForwardSelfNodeQueue;

	public $id;
	
	protected $cacheKey;
	protected $ttl = 180;
	
	private $data;
	private $isReady = false;
	
	abstract public function preCache();
	
	public function save()
	{
		$arFields = get_object_vars($this);
		
		Cache::Memcache()->set($this->cacheKey, $arFields, $this->ttl);
	}
	
	public function isReady()
	{
		if( $this->isReady ) return true;
		
		$this->data = Cache::Memcache()->get($this->cacheKey);
		
		if( empty($this->data) ) return false;
		
		$this->isReady = true;
		
		return true;
	}
	
	public function loadData()
	{
        if (empty($this->data)) {
            $data = Cache::Memcache()->get($this->cacheKey);
        } else {
            $data = $this->data;
        }

        if (empty($data)) {
            throw new \Exception('no HPD data found for ' . $this->cacheKey);
        }

        foreach ($data as $k => $v) {
            $this->{$k} = $v;
            unset($data[$k]);
        }
	}

	public function touch()
	{
		Cache::Memcache()->touch($this->cacheKey, $this->ttl);
	}
	
	public function dropCache()
	{
		Cache::Memcache()->delete($this->cacheKey);
	}
	
	public function ensureQueued()
	{
		$hpdId = $this->id;
		
		$class = get_called_class();

        if( self::$ignoreForwardSelfNodeQueue === true ) {
            $saved = Queue::$enabledForwardToSelfNode;
            Queue::$enabledForwardToSelfNode = null;
        }
		
		Queue::sendData($this->getQueueName(), [$class, $hpdId]);

        if( self::$ignoreForwardSelfNodeQueue === true ) {
            /** @noinspection PhpUndefinedVariableInspection */
            Queue::$enabledForwardToSelfNode = $saved;
        }
	}
	
	protected function getQueueName()
	{
		$subQ = $this->getSubQueueName();

		return self::$queueName.(is_null($subQ) ? '' : '--'.$subQ);
	}
	
	protected function getSubQueueName() { return null;}
}