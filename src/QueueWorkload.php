<?php namespace Bbt;

use AMQPEnvelope;
use RuntimeException;

abstract class QueueWorkload
{
	public $counter;

    public $attempt;

    abstract public function load( AMQPEnvelope $envelope ): QueueWorkload;
	abstract protected function getAsRaw(): string;

    public function send(int $tsOrDelay = 0): void
    {
        if (empty($tsOrDelay)) {
            Queue::sendRawData($this->getQueueName(), $this->getAsRaw());
            return;
        }

        Queue::sendRawDataDelayed($this->getQueueName(), $this->getAsRaw(), $tsOrDelay);
    }

    public function reSend(int $tsOrDelay = 0): void
    {
        Queue::sendRawDataDelayed($this->getQueueName(), $this->getAsRaw(), $tsOrDelay, ['x-attempt' => $this->attempt + 1]);
    }

	public function getQueueName() :string
	{
		if( !defined('static::queueName') ) throw new RuntimeException('please define QueueWorkload::queueName constant');

		return static::queueName;
	}
	
	public static function getDummy() :QueueWorkload { return new static(); }
}