<?php namespace Bbt;

require __DIR__.'/../vendor/autoload.php';

CliScript::$dirScriptSessionData = '/tmp/';
CliScript::$dirLogs = '/tmp/';

class QueueScheduler extends QueueWorkerScript
{
    private $arWaitingWorkload = [];

    protected $tickPeriod   = 10;

    public function run() {
        $hostname = getenv('RABBITMQ_HOST');
        if ( !empty($hostname)) {
            Queue::$server['host'] = $hostname;
            $this->log__debug('"'.Queue::$server['host'].'" host is used for RabbitHQ because found environment variable RABBITMQ_HOST');
        }

        parent::run();
    }

    protected function onScriptStart() :void
    {
        $this->arWaitingWorkload = $this->loadScriptSessionData(null, []);
    }

    protected function process( $workload ) :void
    {
        [$queueName, $ts, $workloadOriginal ] = $workload;

        if( $this->logLevel >= self::LOG_LEVEL__TRACE ) {
            $this->log__trace($queueName . ' scheduled for ' . date('H:i:s', $ts) . ' WL:' . json_encode($workloadOriginal));
        } else {
            $this->log__debug($queueName.' scheduled for '.date('H:i:s', $ts));
        }

        $this->arWaitingWorkload[] = [$queueName, $ts, $workloadOriginal];
    }

    protected function tick() :void
    {
        $now = time();

        foreach ($this->arWaitingWorkload as $i => [$queueName, $tsOrDelay, $workloadOriginal]) {
            if ($tsOrDelay > $now) continue;

            $posSlash = strpos($queueName, '/');

            if ($this->logLevel >= self::LOG_LEVEL__TRACE) {
                $this->log__debug('sending workload to ' . $queueName . ' WL=' . json_encode($workloadOriginal));
            } else {
                $this->log__debug('sending workload to ' . $queueName);
            }

            if (false === $posSlash) {
                Queue::sendData($queueName, $workloadOriginal);
            } else {
                $exchangeName = substr($queueName, 0, $posSlash);
                $routingKey   = substr($queueName, $posSlash + 1) ?? null;

                Queue::sendDataRouted($exchangeName, $workloadOriginal, $routingKey);
            }

            unset($this->arWaitingWorkload[$i]);
        }
    }

    protected function onScriptExit() :void
    {
        $this->saveScriptSessionData($this->arWaitingWorkload);
    }

    protected function getQueueName() :string { return Queue::SCHEDULER; }
}

(new QueueScheduler())->run();