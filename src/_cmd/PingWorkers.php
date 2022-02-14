<?php namespace Bbt;

require __DIR__.'/../../../../autoload.php';

class PingWorkers extends CliScript
{
    protected function runMain(): void
    {
        $arQueues = explode(',', $this->getParameter('q'));

        foreach ($arQueues as $queue) {
            $dCommand = [
                '~~~QUEUE_WORKER_COMMAND~~~' => 'PING',
                'S'                          => 0,
                'P'                          => [],
            ];

            Queue::sendData($queue, $dCommand);
        }
    }
}

(new PingWorkers())->run();