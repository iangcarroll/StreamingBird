<?php

namespace OwlyCode\StreamingBird\Location\Tests\Location;

use OwlyCode\StreamingBird\Location\Box;
use OwlyCode\StreamingBird\Location\Collection;

class CollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testBoundingBox()
    {
        $collection = new Collection([
            new Box(0, 0, 10, 10),
            new Box(20, 20, 30, 30)
        ]);

        $this->assertSame([0,0,10,10,20,20,30,30], $collection->getBoundingBox());
    }
}
