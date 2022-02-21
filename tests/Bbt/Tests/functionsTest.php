<?php
namespace Bbt\Tests;
use PHPUnit\Framework\TestCase;

use function Bbt\hydrateFromJsonString;

class functionsTest extends TestCase
{
    public function testHydrateFromJsonString()
    {
        $jsonString = '{"stringField":"stringValue","intField":42,"arrayField":["a","r","r","a","y"],"objField":{"arr":[1,2,3],"bool":true}}';

        $a = new class() {
            public string $stringField;
            public int    $intField;
            public array  $arrayField;
            public array  $objField;
        };

        hydrateFromJsonString($a, $jsonString);

        $this->assertEquals('stringValue', $a->stringField);
    }

}
