<?php namespace Bbt\Dpf;

use Bbt\Queue;
use Bbt\Cache;

abstract class DpfInstance
{
    public static $arDpfClasses = [];
    public static $autoQueue = true;
    public static $queueName = 'dpf';
    public static $ignoreForwardSelfNodeQueue;
    public static $cacheKeyPrefix = 'dpf';
    public static $uriLoadingGif = '/_public/img/loading.gif';
    //public static $uriLoadingGif = '/_public/img/blank.gif';
    protected $serialKey;

    /**
     * constructed will be called with serial key as parameter.
     * You must construct related object so you can use in the renderMain() and  getProcessedData()
     *
     * @see DpfInstance::convertObject2SerialKey()
     *
     *@param string $serialKey
     */
    abstract protected function __construct($serialKey);

    /**
     * render HTML, you MUST call $this->renderHook() to inject hook later used in the javascriptHook()
     */
    abstract public function renderMain();

    /**
     * process missing (heavy) data and return as array
     * @return mixed $data
     */
    abstract public function getProcessedData();

    /**
     * @return int cache TTL in seconds
     */
    abstract protected function getCacheTtl();



    /**
     * override if you want certain class of DPF objects go into specific queue
     * usually "return $this::classShortcut;" will do the job
     *
     * @return string $subQueue;
     */
    protected function getSubQueueName() { return null; }

    /**
     * render Dpf widget/fragment
     *
     * @param $object
     *
     */
    public static function render( $object ) :void
    {
        $serialKey = static::convertObject2SerialKey($object);
        $class = get_called_class();

        /** @var DpfInstance $dpf */
        $dpf = new $class($serialKey);
        $dpf->serialKey = $serialKey;
        $dpf->withObject($object);
        $dpf->renderMain();
    }

    /**
     * convert any object into serial key
     * every instance constructor should do the opposite job so md5() will not work
     * you MUST be able to convert serial key into object again the __construct()
     *
     * @param $object
     * @return string
     */
    protected static function convertObject2SerialKey( $object )
    {
        if (is_object($object) and property_exists($object, '_id')) {
            return (string)$object->_id;
        }

        if( is_numeric($object) ) return (string) $object;

        if( is_null($object) ) return 'null';

        $calledClass = get_called_class();

        throw new \InvalidArgumentException('Can not convert object to serial key. Implement '.$calledClass.'::convertObject2SerialKey()');
    }

    /**
     * render special IMG tag with related serial key and class shortcut so later JS may inject data into it
     *
     * @param null $uriLoadingGif
     */
    protected function renderHook($uriLoadingGif=null) :void
    {
        $classShortcut = $this::classShortcut;
        $serialKey = $this->serialKey;

        if( self::$autoQueue ) $this->queue();

        /**
         * please override SRC with the CSS if possible, this src is defined as stub
         */
        if( is_null($uriLoadingGif) ) $uriLoadingGif = self::$uriLoadingGif;

        ?><img src="<?=$uriLoadingGif?>" class="dpf-hook dpf-class-<?=$classShortcut?>" alt="<?=$serialKey?>"/><?
    }

    /**
     * call getProcessedData() and put into cache
     */
    public function preCache() :void
    {
        $data = $this->getProcessedData();

        if( !is_null($data) )
        {
            $classShortcut = constant(get_called_class().'::classShortcut');
            $cacheKey = self::$cacheKeyPrefix.'.'.$classShortcut.'~'.$this->serialKey;

            Cache::Memcache()->set($cacheKey, $data, $this->getCacheTtl());
        }
    }

    public function dropCache() :void
    {
        $classShortcut = constant(get_called_class().'::classShortcut');
        $cacheKey = self::$cacheKeyPrefix.'.'.$classShortcut.'~'.$this->serialKey;

        Cache::Memcache()->delete($cacheKey);
    }

    /**
     * @return mixed|null cached data or null if not cached
     */
    public function getPreCachedData()
    {
        $classShortcut = constant(get_called_class().'::classShortcut');
        $cacheKey = self::$cacheKeyPrefix.'.'.$classShortcut.'~'.$this->serialKey;

        $data = Cache::Memcache()->get($cacheKey);

        return ( $data === false ) ? null : $data;
    }

    public function touchCache() :void
    {
        $classShortcut = constant(get_called_class().'::classShortcut');
        $cacheKey = self::$cacheKeyPrefix.'.'.$classShortcut.'~'.$this->serialKey;

        $ttl = $this->getCacheTtl();

        Cache::Memcache()->touch($cacheKey, $ttl);
    }

    public static function constructByQueueWorkload($workload) :DpfInstance
    {
        return self::constructByRequestedHook($workload);
    }

    public static function constructByRequestedHook( $hookRequested ) :DpfInstance
    {
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        @list($classShortcut, $serialKey) = @explode('~', $hookRequested);

        foreach (self::$arDpfClasses as $dpfClass) {
            $dpfAnchor = constant($dpfClass . '::classShortcut');

            if (!empty($dpfAnchor) and $dpfAnchor == $classShortcut) {
                /** @var DpfInstance $instance */
                $instance            = new $dpfClass($serialKey);
                $instance->serialKey = $serialKey;
                return $instance;
            }
        }

        throw new \Exception('Can not construct instance='.$classShortcut.'. Make sure all Dpf classes listed in the DpfInstance::$arDpfClasses[]');
    }

    public function queue($ignoreProxy=false, ?int $delay=0) :void
    {
        static $queueProxy = [];

        $classShortcut = $this::classShortcut;

        $serial = $this->serialKey;

        $cachedId = $classShortcut.'~'.$serial;

        if( !$ignoreProxy && in_array($cachedId, $queueProxy)) return;

        $queueProxy[] = $cachedId;

        $subQueue = $this->getSubQueueName();

        $queueName = is_null($subQueue) ? self::$queueName : self::$queueName.'-'.$subQueue;

        if( self::$ignoreForwardSelfNodeQueue === true ) {
            $saved = Queue::$enabledForwardToSelfNode;
            Queue::$enabledForwardToSelfNode = null;
        }

        if ($delay > 0) {
            Queue::scheduleWorkload($queueName, $classShortcut . '~' . $serial, $delay);

            if( self::$ignoreForwardSelfNodeQueue === true ) {
                /** @noinspection PhpUndefinedVariableInspection */
                Queue::$enabledForwardToSelfNode = $saved;
            }
            return;
        }

        Queue::sendData($queueName, $classShortcut.'~'.$serial );

        if( self::$ignoreForwardSelfNodeQueue === true ) {
            /** @noinspection PhpUndefinedVariableInspection */
            Queue::$enabledForwardToSelfNode = $saved;
        }

        $cacheKey_reQueue = self::$cacheKeyPrefix.'.'.$classShortcut.'~'.$this->serialKey.'~rqL';

        Cache::Memcache()->set($cacheKey_reQueue, true, 40); // 5 minutes
    }

    public function reQueueIfPossible() :void
    {
        $classShortcut = constant(get_called_class().'::classShortcut');
        $cacheKey_reQueue = self::$cacheKeyPrefix.'.'.$classShortcut.'~'.$this->serialKey.'~rqL';

        if( false !== Cache::Memcache()->get($cacheKey_reQueue) ) return;

        $this->queue(true);
    }

    protected function withObject($object):void { }
}