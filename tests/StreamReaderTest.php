<?php

namespace OwlyCode\StreamingBird\Location\Tests;

use OwlyCode\StreamingBird\Location\Box;
use OwlyCode\StreamingBird\Oauth;
use OwlyCode\StreamingBird\StreamReader;

class StreamReaderTest extends \PHPUnit_Framework_TestCase
{
    public function testFilterReading()
    {
        $connection = $this->getConnection();
        $oauth      = $this->getOauth();

        $oauth
            ->expects($this->once())
            ->method('getAuthorizationHeader')
            ->with('https://stream.twitter.com/1.1/statuses/filter.json', [ 'track' => 'hello', 'follow' => '1,2', 'locations' => '0,0,1,1' ])
            ->willReturn('CREDENTIALS')
        ;

        $connection
            ->expects($this->once())
            ->method('open')
            ->with('ssl://stream.twitter.com', 443, 5, 10);
        ;

        $connection
            ->expects($this->once())
            ->method('authenticate')
            ->with('https://stream.twitter.com/1.1/statuses/filter.json', [ 'track' => 'hello', 'follow' => '1,2', 'locations' => '0,0,1,1' ], 'CREDENTIALS');
        ;

        $connection
            ->expects($this->once())
            ->method('read')
            ->will($this->returnCallback(function (callable $callable) {
                sleep(1);
                call_user_func($callable, ['text' => 'a tweet']);
            }));
        ;

        $reader = new StreamReader($connection, $oauth, StreamReader::METHOD_FILTER);

        $reader->setTrack(['hello']);
        $reader->setFollow([ 1, 2 ]);
        $reader->setLocation(new Box(0,0, 1, 1));

        $reader->consume(function ($tweet) use ($reader, &$used) {
            $used = true;
            $this->assertSame('a tweet', $tweet['text']);
            $reader->stop();
        });

        $this->assertSame(1, $reader->getMonitor()->get('tweets'));
        $this->assertSame(1, $reader->getMonitor()->get('max_idle_time'));
        $this->assertSame(1, $reader->getMonitor()->get('idle_time'));

        $this->assertTrue($used);
    }

    public function getConnection()
    {
        return $this
            ->getMockBuilder('OwlyCode\StreamingBird\Connection')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    public function getOauth()
    {
        return $this
            ->getMockBuilder('OwlyCode\StreamingBird\Oauth')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }
}
