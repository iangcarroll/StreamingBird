<?php

namespace OwlyCode\StreamingBird\Location\Tests;

use OwlyCode\StreamingBird\Oauth;

class OauthTest extends \PHPUnit_Framework_TestCase
{
    /**
     * This ensure the headers stay consistent.
     */
    public function testGetAuthorizationHeader()
    {
        $oauth = new Oauth('ckey', 'csecret', 'otoken', 'osecret');

        $headers = 'OAuth realm="",oauth_consumer_key="ckey",oauth_nonce="foo",oauth_signature_method="HMAC-SHA1",'.
                   'oauth_timestamp="1458246915",oauth_version="1.0A",oauth_token="otoken",oauth_signature="QdgCl5yrJwQe4%2BiSSCf4xX6v60A%3D"';

        $this->assertSame($headers, $oauth->getAuthorizationHeader('http://bird.com/stream/users', [ 'sort' => 'date' ], [
            'oauth_nonce'     => 'foo',
            'oauth_timestamp' => 1458246915
        ]));
    }
}

