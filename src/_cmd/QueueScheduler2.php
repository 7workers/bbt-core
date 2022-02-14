<?php namespace Bbt;

use AMQPEnvelope;

require __DIR__.'/../../../../autoload.php';

CliScript::$dirScriptSessionData = '/tmp/';
CliScript::$dirLogs = '/tmp/';

class QueueScheduler2 extends QueueConsumerScript
{
    private $arWaitingItems = [];

    protected $tickPeriod   = 10;

    protected function onScriptStart() :void
    {
        $hostname = getenv('RABBITMQ_HOST');

        if ( !empty($hostname)) {
            Queue::$server['host'] = $hostname;
            $this->log__debug('"'.Queue::$server['host'].'" host is used for RabbitHQ because found environment variable RABBITMQ_HOST');
        }

        $this->arWaitingItems = $this->loadScriptSessionData(null, []);
    }

    protected function consume(QueueWorkload $wl): void
    {
        /** @var QueueSchedulerWorkload $wl */

        $this->arWaitingItems[] = [$wl->queue, $wl->ts, $wl->data, $wl->headers];

        if ($this->logLevel >= self::LOG_LEVEL__TRACE) {
            $this->log__trace($wl->queue.' scheduled for '.date('H:i:s', $wl->ts).' data:'.$wl->data);
        } else {
            $this->log__debug($wl->queue.' scheduled for '.date('H:i:s', $wl->ts));
        }
    }

    protected function tick() :void
    {
        $now = time();

        foreach ($this->arWaitingItems as $i => [$queueName, $tsOrDelay, $workloadOriginal, $headers]) {
            if ($tsOrDelay > $now) continue;

            if ($this->logLevel >= self::LOG_LEVEL__TRACE) {
                $this->log__debug('sending workload to ' . $queueName . ' WL=' . json_encode($workloadOriginal));
            } else {
                $this->log__debug('sending workload to ' . $queueName);
            }

            Queue::sendRawData($queueName, $workloadOriginal, $headers);

            unset($this->arWaitingItems[$i]);
        }
    }

    /**
     * @throws \Exception
     */
    protected function onScriptExit() :void
    {
        $this->saveScriptSessionData($this->arWaitingItems);
    }

    protected function getWorkloadObject(): QueueWorkload {return QueueSchedulerWorkload::getDummy(); }
}

class QueueSchedulerWorkload extends QueueWorkload
{
    public const        queueName = 'bbt.sch'; // this is hardcoded in \Bbt\Queue::SCHEDULER2 for the sake of performance

    public $queue;
    public $ts;
    public $data;
    public $headers;

    public function load(AMQPEnvelope $envelope): QueueWorkload
    {
        $this->data    = $envelope->getBody();
        $this->headers = $envelope->getHeaders();

        $this->queue = $this->headers['x-queue'];
        $this->ts = $this->headers['x-ts'];

        unset($this->headers['x-queue'], $this->headers['x-ts']);

        return $this;
    }

    protected function getAsRaw(): string{return '';}
}

(new QueueScheduler2())->run();