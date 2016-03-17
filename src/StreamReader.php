<?php

namespace OwlyCode\StreamingBird;

class StreamReader
{
    const FORMAT_JSON     = 'json';
    const FORMAT_XML      = 'xml';

    const METHOD_FILTER   = 'filter';
    const METHOD_SAMPLE   = 'sample';
    const METHOD_RETWEET  = 'retweet';
    const METHOD_FIREHOSE = 'firehose';
    const METHOD_LINKS    = 'links';
    const METHOD_USER     = 'user';
    const METHOD_SITE     = 'site';

    const URLS = [
        'site'     => 'https://sitestream.twitter.com/1.1/site',
        'user'     => 'https://userstream.twitter.com/2/user',
        'filter'   => 'https://stream.twitter.com/1.1/statuses/filter',
        'sample'   => 'https://stream.twitter.com/1.1/statuses/sample',
        'retweet'  => 'https://stream.twitter.com/1.1/statuses/retweet',
        'firehose' => 'https://stream.twitter.com/1.1/statuses/firehose',
        'links'    => 'https://stream.twitter.com/1.1/statuses/links'
    ];

    /**
     * @var Oauth
     */
    private $oauth;

    /**
     * @var Monitor
     */
    private $monitor;

    /**
    * @internal Moved from being a const to a variable, because some methods (user and site) need to change it.
    */
    protected $baseUrl = 'https://stream.twitter.com/1.1/statuses/';

    protected $method;
    protected $format;
    protected $count; //Can be -150,000 to 150,000. @see http://dev.twitter.com/pages/streaming_api_methods#count
    protected $followIds;
    protected $trackWords;
    protected $location;

    /**
     * @param Oauth   $oauth
     * @param string  $method
     * @param string  $format
     * @param boolean $lang
     */
    public function __construct(Oauth $oauth, $method = AbstractStream::METHOD_SAMPLE, $format = self::FORMAT_JSON, $lang = false)
    {
        $this->monitor = new Monitor();

        $this->monitor->register(Monitor::TYPE_MAX, 'max_idle_time', 0);
        $this->monitor->register(Monitor::TYPE_LAST, 'idle_time', 0);
        $this->monitor->register(Monitor::TYPE_COUNT, 'tweets');

        $this->oauth  = $oauth;
        $this->method = $method;
        $this->format = $format;
        $this->lang   = $lang;
    }

    /**
     * Consume from the streaming API.
     *
     * Will retry on any connection loss.
     */
    public function consume(callable $handler)
    {
        while (1) {
            $this->consumeOnce($handler);
        }
    }

    /**
     * Consume from the streaming API.
     *
     * Will not retry on connection loss.
     */
    protected function consumeOnce(callable $handler)
    {
        $connection = $this->connect();

        $lastStreamActivity = time();

        $connection->read(function ($tweet) use (&$lastStreamActivity, $handler) {
            $idle = (time() - $lastStreamActivity);

            $this->monitor->stat('max_idle_time', $idle);
            $this->monitor->stat('idle_time', $idle);
            $this->monitor->stat('tweets', 1);

            $lastStreamActivity = time();

            call_user_func($handler, $tweet, $this->monitor);
        });

        $connection->close();
    }

    /**
     * @param integer $timeout
     * @param integer $attempts
     *
     * @return Connection
     */
    protected function connect($timeout = 5, $attempts = 10)
    {
        $url      = self::URLS[$this->method] . '.' . $this->format;
        $urlParts = parse_url($url);
        $scheme   = $urlParts['scheme'] == 'https' ? 'ssl://' : 'tcp://';
        $port     = $urlParts['scheme'] == 'https' ? 443 : 80;

        // Setup params appropriately
        $requestParams = [];

        // Setup the language of the stream
        if ($this->lang) {
            $requestParams['language'] = $this->lang;
        }

        // Filter takes additional parameters
        if (($this->method === self::METHOD_FILTER || $this->method === self::METHOD_USER) && count($this->trackWords) > 0) {
            $requestParams['track'] = implode(',', $this->trackWords);
        }
        if (($this->method === self::METHOD_FILTER || $this->method === self::METHOD_SITE) && count($this->followIds) > 0) {
            $requestParams['follow'] = implode(',', $this->followIds);
        }

        if ($this->method === self::METHOD_FILTER && $this->location) {
            $requestParams['locations'] = implode(',', $this->location->getBoundingBox());
        }

        if ($this->count <> 0) {
            $requestParams['count'] = $this->count;
        }

        $connection = new Connection();
        $connection->open($scheme . $urlParts['host'], $port, $timeout, $attempts);
        $connection->authenticate($url, $requestParams, $this->oauth->getAuthorizationHeader($url, $requestParams));

        return $connection;
    }

    /**
    * Set host port
    *
    * @param string $host
    * @return void
    */
    public function setHostPort($port)
    {
        $this->hostPort = $port;
    }

    /**
    * Set secure host port
    *
    * @param int $port
    * @return void
    */
    public function setSecureHostPort($port)
    {
        $this->secureHostPort = $port;
    }

    /**
    * Returns public statuses from or in reply to a set of users. Mentions ("Hello @user!") and implicit replies
    * ("@user Hello!" created without pressing the reply button) are not matched. It is up to you to find the integer
    * IDs of each twitter user.
    * Applies to: METHOD_FILTER
    *
    * @param array $userIds Array of Twitter integer userIDs
    */
    public function setFollow(array $userIds = [])
    {
        sort($userIds); // Non-optimal but necessary

        $this->followIds = $userIds;
    }

    /**
    * Returns an array of followed Twitter userIds (integers)
    *
    * @return array
    */
    public function getFollow()
    {
        return $this->followIds;
    }

    /**
    * Specifies keywords to track. Track keywords are case-insensitive logical ORs. Terms are exact-matched, ignoring
    * punctuation. Phrases, keywords with spaces, are not supported. Queries are subject to Track Limitations.
    * Applies to: METHOD_FILTER
    *
    * See: http://apiwiki.twitter.com/Streaming-API-Documentation#TrackLimiting
    *
    * @param array $trackWords
    */
    public function setTrack(array $trackWords = [])
    {
        sort($trackWords); // Non-optimal, but necessary

        $this->trackWords = $trackWords;
    }

    /**
    * Returns an array of keywords being tracked
    *
    * @return array
    */
    public function getTrack()
    {
        return $this->trackWords;
    }

    /**
     * @param LocationInterface $location
     */
    public function setLocation(LocationInterface $location)
    {
        $this->location = $location;
    }

    /**
     * @return LocationInterface
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
    * Sets the number of previous statuses to stream before transitioning to the live stream. Applies only to firehose
    * and filter + track methods. This is generally used internally and should not be needed by client applications.
    * Applies to: METHOD_FILTER, METHOD_FIREHOSE, METHOD_LINKS
    *
    * @param integer $count
    */
    public function setCount($count)
    {
        $this->count = $count;
    }

    /**
    * Restricts tweets to the given language, given by an ISO 639-1 code (http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes).
    *
    * @param string $lang
    */
    public function setLang($lang)
    {
        $this->lang = $lang;
    }

    /**
    * Returns the ISO 639-1 code formatted language string of the current setting. (http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes).
    *
    * @param string $lang
    */
    public function getLang()
    {
        return $this->lang;
    }
}
