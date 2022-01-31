<?php namespace Bbt;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

abstract class NyholmApiServer extends ApiServer
{
    public function hydrateNextServerRequest(RequestResponseTrace $rrt): bool
    {
        static $once;

        if ($once) return false;

        $psr17Factory = new Psr17Factory();

        $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        $rrt->request = $creator->fromGlobals();

        $once = true;

        return true;
    }
}