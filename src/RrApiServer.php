<?php namespace Bbt;

use Spiral\RoadRunner;
use Nyholm\Psr7;

// https://roadrunner.dev/docs/php-worker
abstract class RrApiServer extends ApiServer
{
    private $rrPSR7Worker;

    public function __construct()
    {
        parent::__construct();

        $psrFactory = new Psr7\Factory\Psr17Factory();

        $this->rrPSR7Worker = new RoadRunner\Http\PSR7Worker(RoadRunner\Worker::create(), $psrFactory, $psrFactory, $psrFactory);
    }

    public function hydrateNextServerRequest(RequestResponseTrace $rrt): bool
    {
        $rrt->request = $this->rrPSR7Worker->waitRequest();

        return true;
    }

    protected function emitResponse(RequestResponseTrace  $rrt):void
    {
        $content = $rrt->response->getBody()->getContents();
        $size = strlen($content);

        $this->rrPSR7Worker->respond($rrt->response);

        $rrt->rawResponse = $content;
    }
}