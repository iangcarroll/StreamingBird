<?php

namespace OwlyCode\StreamingBird\Location\Tests\Location;

use OwlyCode\StreamingBird\Location\Box;

class BoxTest extends \PHPUnit_Framework_TestCase
{
    public function testBoundingBox()
    {
        $box = new Box(0, 0, 10, 10);

        $this->assertSame([0,0,10,10], $box->getBoundingBox());
    }
}
