<?php

namespace OwlyCode\StreamingBird\Location\Tests;

use OwlyCode\StreamingBird\Monitor;

class MonitorTest extends \PHPUnit_Framework_TestCase
{
    public function testCount()
    {
        $monitor = new Monitor();
        $monitor->register(Monitor::TYPE_COUNT, 'foo');

        $this->assertSame(0, $monitor->get('foo'));

        $monitor->stat('foo', 1);
        $monitor->stat('foo', 1);

        $this->assertSame(2, $monitor->get('foo'));

        $monitor->clear('foo');

        $this->assertSame(0, $monitor->get('foo'));
    }

    public function testLast()
    {
        $monitor = new Monitor();
        $monitor->register(Monitor::TYPE_LAST, 'foo', 42);

        $this->assertSame(42, $monitor->get('foo'));

        $monitor->stat('foo', 1337);

        $this->assertSame(1337, $monitor->get('foo'));

        $monitor->clear('foo');

        $this->assertSame(42, $monitor->get('foo'));
    }

    public function testMax()
    {
        $monitor = new Monitor();
        $monitor->register(Monitor::TYPE_MAX, 'foo');

        $this->assertSame(0, $monitor->get('foo'));

        $monitor->stat('foo', 3);
        $monitor->stat('foo', 10);
        $monitor->stat('foo', 5);

        $this->assertSame(10, $monitor->get('foo'));

        $monitor->clear('foo');

        $this->assertSame(0, $monitor->get('foo'));
    }

    public function testMin()
    {
        $monitor = new Monitor();
        $monitor->register(Monitor::TYPE_MIN, 'foo');

        $this->assertSame(PHP_INT_MAX, $monitor->get('foo'));

        $monitor->stat('foo', 3);
        $monitor->stat('foo', 10);
        $monitor->stat('foo', 5);

        $this->assertSame(3, $monitor->get('foo'));

        $monitor->clear('foo');

        $this->assertSame(PHP_INT_MAX, $monitor->get('foo'));
    }

    public function testAvg()
    {
        $monitor = new Monitor();
        $monitor->register(Monitor::TYPE_AVG, 'foo');

        $this->assertSame(0, $monitor->get('foo'));

        $monitor->stat('foo', 0);
        $monitor->stat('foo', 10);
        $monitor->stat('foo', 10);
        $monitor->stat('foo', 10);

        $this->assertSame(7.5, $monitor->get('foo'));

        $monitor->clear('foo');

        $this->assertSame(0, $monitor->get('foo'));
    }

    public function testGetAllAsString()
    {
        $monitor = new Monitor();
        $monitor->register(Monitor::TYPE_AVG, 'foo');
        $monitor->register(Monitor::TYPE_MAX, 'bar', 42);

        $this->assertSame("foo = 0\nbar = 42", $monitor->getAllAsString());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot get non existing dimension "foo"
     */
    public function testGetNonExistingDimension()
    {
        $monitor = new Monitor();
        $monitor->get('foo');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot add stat for non existing dimension "foo"
     */
    public function testStatNonExistingDimension()
    {
        $monitor = new Monitor();
        $monitor->stat('foo', 1);
    }
}
