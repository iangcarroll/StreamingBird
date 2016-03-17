<?php

namespace OwlyCode\StreamingBird\Location\Tests\Location;

use OwlyCode\StreamingBird\Location\Circle;

class CircleTest extends \PHPUnit_Framework_TestCase
{
    public function testBoundingBox()
    {
        $circle = new Circle(0, 0, 100);

        $this->assertSame([
            -0.90000000000000002,
            -0.90000000000000002,
            0.90000000000000002,
            0.90000000000000002
        ], $circle->getBoundingBox());
    }
}
