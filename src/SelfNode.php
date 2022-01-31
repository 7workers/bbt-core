<?php namespace Bbt;

use PettyRest\Client;
use PettyRest\Request;
use PettyRest\Response;
use Psr\Http\Client\ClientExceptionInterface;

abstract class SelfNode
{
    /**
     * @throws ClientExceptionInterface
     */
    public static function sendQueueData(string $queueName, string $data, ?array $headers = []): void
    {
        $r = new class('/sys/queue') extends Request {
            public $queueName;
            public $data;
            public $headers;
            public function getResponseDummy(): Response {return new class() extends Response {};}
        };

        $r->queueName  = $queueName;
        $r->data       = $data;
        $r->headers = $headers;

        self::getClient()->sendRequest($r);
    }

    /**
     * @throws ClientExceptionInterface
     */
    public static function getCacheAndDelete(string $key)
    {
        $r = new class('/sys/getCacheAndDelete') extends Request {
            public $key;
            public function getResponseDummy(): Response {return new class() extends Response {
                public $value;
            };}
        };

        $r->key = $key;

        $response = self::getClient()->sendRequest($r);

        return $response->value ?? null;
    }

    /**
     * @throws ClientExceptionInterface
     */
    public static function getCache(string $key, ?bool $deleteOnRead=false):mixed
    {
        $r = new class('/sys/getCache') extends Request {
            public $key;
            public $deleteOnRead;
            public function getResponseDummy(): Response {return new class() extends Response {
                public $value;
            };}
        };

        $r->key = $key;
        $r->deleteOnRead = $deleteOnRead;

        $response = self::getClient()->sendRequest($r);

        return $response->value ?? null;
    }

    protected static function getClient(): Client
    {
        static $client;

        if( null == $client ) {
            $client = new class(Config::$selfNode['host'], 'x') extends Client{
                protected function getExceptionClass(\Throwable $e): string {return ExternalApiError::class;}
            };
            $client->forceScheme = 'http';
        }

        return $client;
    }
}