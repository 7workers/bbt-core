<?php namespace Bbt;

abstract class CliScript
{
	public const LOG_LEVEL__ERROR = 1;
	public const LOG_LEVEL__WARN  = 2;
	public const LOG_LEVEL__INFO  = 3;
	public const LOG_LEVEL__DEBUG = 4;
	public const LOG_LEVEL__TRACE = 5;

	protected $fnameLog;
	protected $logTo = 'FILE';
	protected $logLevel = 'info';
	protected $logPrefix;

    /**
     * @see getParameter()
     * @var array 'param'=>'value'
     */
    protected $scriptParameters = [];

    protected $isShutdownRequested = false;

    protected $context = [
        'pid' => 0
    ];

	/**
	 *
	 * script starting time
	 * @var int
	 */
	private $startTime;

	public static $dirLogs              = '/var/log';
	public static $dirScriptSessionData = '/var/data';
	public static $defaultLogLevel      = 'info';

    /**
     * @var \Monolog\Logger
     */
    protected $logger;

	abstract protected function runMain();

    /**
     * @throws \Exception
     */
    public function __construct()
	{
		$this->startTime = time();

		$this->scriptParameters = $this->parseCLIParameters();

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, [$this, 'onPcntlSignal']);
        pcntl_signal(SIGINT, [$this, 'onPcntlSignal']);
        pcntl_signal(SIGHUP, [$this, 'onPcntlSignal']);

        $this->setupLogger();

        if (0 < count(array_intersect(['h', 'help', '?'], array_keys($this->scriptParameters)))) {
            $this->displayHelp();
            exit(0);
        }

        $this->resetContext();
	}

    public function onPcntlSignal($sigNo, $sigInfo)
    {
        if( $this->isShutdownRequested ) {
            $this->log__info('received again signal=' . $sigNo.' terminating now');
            exit(0);
        }

        $this->isShutdownRequested = true;
        $this->log__info('received signal=' . $sigNo.' next signal will force termination');
    }

    protected function resetContext(): void
    {
        $this->context = ['pid' => getmypid()];
    }

    /**
     * @throws \Exception
     */
    private function setupLogger(): void
    {
        if( isset($this->scriptParameters['nolog']) ) {
            $this->logger = Logger::setupNullLogger($this);
            return;
        }

        if (isset($this->scriptParameters['debug']) || isset($_ENV['BBT_DEV']) ) {
            $level = $this->scriptParameters['level'] ?? 'debug';
            $this->logger = Logger::setupStdoutLogger($this, $level);
            return;
        }

        $level = strtolower($this->scriptParameters['level'] ?? self::$defaultLogLevel);

        if (isset($this->scriptParameters['log'])) {
            $this->logger = Logger::setupFileLogger($this, $this->getParameter('log'), $level);
            return;
        }

        $this->logger = Logger::getLogger($this, $level);
    }

	public function run()
	{
		$this->onScriptStart();
		$this->runMain();
		$this->onScriptExit();
	}
	
	protected function onScriptStart() {}
	protected function onScriptExit() {}

	protected function getRunningTime() :int { return (time() - $this->startTime); }
    protected function getStartTime() : int { return $this->startTime; }

    private function parseCLIParameters() :array
    {
		$result = array ();
		$params = $GLOBALS['argv'];
		reset($params);

		/** @noinspection PhpAssignmentInConditionInspection */
        foreach ($params as $tmp => $p) {
            if ($p[0] == '-') {
                $paramName = substr($p, 1);
                $value     = true;

                if (@$paramName[0] == '-') {
                    $paramName = substr($paramName, 1);
                    if (strpos($p, '=') !== false) {
                        [$paramName, $value] = explode('=', substr($p, 2), 2);
                    }
                }

                $result[$paramName] = $value;
            } else {
                $result[] = $p;
            }
        }

        return $result;
	}

    public function getParameter($parameter_name, $default_value = '__NOT_DEFINED__')
    {
        if (!isset($this->scriptParameters[$parameter_name]) and $default_value === '__NOT_DEFINED__') {
            echo("parameter {$parameter_name} is not specified\n\n");
            $this->displayHelp();
            exit(1);
        }

        if (isset($this->scriptParameters[$parameter_name])) {
            $val = $this->scriptParameters[$parameter_name];

            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            if (is_int($default_value)) return (int)$val;

            return $val;
        }

        return $default_value;
    }
	
	protected function setLogPrefix(string $prefix) :void
    {
        $this->logPrefix = $prefix;
    }

	/**
	 * Logs a message to the standard output if debug us enabled
	 *
	 * @see self::$debug
	 *
	 * @param string   $message
	 * @param  $logLevel
	 */
	protected function log( $message, $logLevel=null, $context=[] ) :void
    {
        if( null === $logLevel ) $logLevel = $this->logLevel;

        if( is_array($message) || is_object($message) ) $message = json_encode($message);

        $context = array_merge($this->context, $context);

        if (!empty($this->logPrefix)) {
            $context['logPrefix'] = trim($this->logPrefix);
        }

        $this->logger->log($logLevel, $message, $context);
	}
	
	protected function log__emergency($message, $context=[]) :void { $this->log($message, 'error', $context);}
	protected function log__alert($message, $context=[]) :void { $this->log($message, 'warning', $context);}
	protected function log__critical($message, $context=[]) :void { $this->log($message, 'warning', $context);}
	protected function log__error($message, $context=[]) :void { $this->log($message, 'error', $context);}
	protected function log__warning($message, $context=[]) :void { $this->log($message, 'warning', $context);}
	protected function log__notice($message, $context=[]) :void { $this->log($message, 'warning', $context);}
	protected function log__info($message, $context=[]) :void  { $this->log($message, 'info', $context);}
	protected function log__debug($message, $context=[]) :void { $this->log($message, 'debug', $context);}

	protected function log__warn($message, $context=[]) :void { $this->log($message, 'warning', $context);}
	protected function log__trace($message, $context=[]) :void { $this->log($message, 'debug', $context);}

	protected function reportAlive($message, $context=[]) :void
    {
		static $tsLastReported = null;
		
		if (is_null($tsLastReported)) $tsLastReported = $this->getRunningTime();
		if ( ($this->getRunningTime() - $tsLastReported ) < 120) return ;

		$tsLastReported = $this->getRunningTime();

		$this->log__info('Still alive. '.$message.'   memory used: '.round(memory_get_usage()/1024).'kb', $context);
	}

	protected function getTimeSinceLastStart()
	{
		return Cache::Memcache()->get(Cache::KEY_TIME_SINCE_LAST_RUN.'_'.get_called_class());
	}

	protected function setTimeSinceLastStart( $timestamp = null ) :void
    {
		Cache::Memcache()->set(Cache::KEY_TIME_SINCE_LAST_RUN.'_'.get_called_class(), $timestamp ?? time());
	}

	/**
	 * @param null|string $idSession
	 *
	 * @param null        $defaultData
	 *
	 * @return mixed|null
	 */
	protected function loadScriptSessionData( ?string $idSession=null, $defaultData=null )
	{
		$fname = $this->getFnameSessionData((string) $idSession);
		
		if( !file_exists($fname) ) return $defaultData;
		
		return unserialize(file_get_contents($fname), [false]);
	}

	/**
	 * @param             $data
	 * @param null|string $idSession
	 *
	 * @throws \Exception
	 */
	protected function saveScriptSessionData( $data, ?string $idSession=null ) :void
    {
		$fname = $this->getFnameSessionData((string) $idSession);
		
		$result = file_put_contents($fname, serialize($data));
		
		if( $result === false ) throw new \RuntimeException('session data file not writable: '.$fname);
	}
	
	private function getFnameSessionData(string  $idSession) :string
    {
		$className = get_called_class();
		$className = str_replace('\\',  '_', $className);
		
		$fnameSessionData = self::$dirScriptSessionData.'/'.$className.'_'.($idSession).'.dat';
		
		return $fnameSessionData;
	}
	
	public function displayHelp()
	{
		echo("Parameters:\n");
		echo("\t--debug\t\t\t display log\n");
		echo("\t--nolog\t\t\t do not log to file\n");
		echo("\t--level=INFO\t\t\t log level: EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG\n");
		echo("\t--log=fname.log\t\t log file\n\n");
		echo("Log file: ".$this->fnameLog."\n\n");
		echo("create file manually to start logging into file\n\n");
	}

}



