<?php

namespace OwlyCode\StreamingBird;

class Connection
{
    const RETRY_TIME = 2;
    const USER_AGENT = 'TwitterStreamReader/1.0RC +https://github.com/owlycode/twitter-stream-reader';

    /**
     * @var resource
     */
    public $connection;

    /**
     * @var resource[]
     */
    public $pool;

    /**
     * @var string
     */
    public $buffer;

    /**
     * @param string  $host
     * @param integer $port
     * @param integer $timeout
     * @param integer $attempts
     */
    public function open($host, $port, $timeout = 5, $attempts = 10)
    {
        $this->buffer = '';

        @$this->connection = fsockopen($host, $port, $errNo, $errStr, $timeout);

        while (!$this->connection || !is_resource($this->connection)) {
            $attempts--;

            if ($attempts <= 0) {
                throw new ConnectLimitExceededException($errStr, $errNo);
            }

            sleep(self::RETRY_TIME);
            @$this->connection = fsockopen($host, $port, $errNo, $errStr, $timeout);
        }
    }

    /**
     * @param string $url
     * @param array  $params
     * @param string $credentials
     */
    public function authenticate($url, array $params, $credentials)
    {
        stream_set_blocking($this->connection, 1);

        $urlParts = parse_url($url);
        $postData = http_build_query($params, null, '&', PHP_QUERY_RFC3986);

        $streamHeaders = "POST " . $urlParts['path'] . " HTTP/1.1\r\n";
        $streamHeaders.= "Host: " . $urlParts['host'] . "\r\n";
        $streamHeaders.= "Connection: Close\r\n";
        $streamHeaders.= "Content-type: application/x-www-form-urlencoded\r\n";
        $streamHeaders.= "Content-length: " . strlen($postData) . "\r\n";
        $streamHeaders.= "Accept: */*\r\n";
        $streamHeaders.= 'Authorization: ' . $credentials . "\r\n";
        $streamHeaders.= 'User-Agent: ' . self::USER_AGENT . "\r\n";
        $streamHeaders.= "\r\n";
        $streamHeaders.= $postData . "\r\n";
        $streamHeaders.= "\r\n";

        fwrite($this->connection, $streamHeaders);

        list($httpVer, $httpCode, $httpMessage) = preg_split('/\s+/', trim(fgets($this->connection, 1024)), 3);

        $respHeaders = $respBody = '';
        $isChunking = false;

        while ($hLine = trim(fgets($this->connection, 4096))) {

            $respHeaders .= $hLine."\n";
            if (strtolower($hLine) == 'transfer-encoding: chunked') {
                $isChunking = true;
            }
        }

        if ($httpCode != 200) {
            while ($bLine = trim(fgets($this->connection, 4096))) {
                $respBody .= $bLine;
            }

            throw new TwitterException(sprintf('Twitter API responsed a "%s" status code.', $httpCode));
        } elseif (!$isChunking) {
            throw new TwitterException("Twitter did not send a chunking header. Is this really HTTP/1.1? Here are headers:\n$respHeaders");   //TODO: rather crude!
        }

        stream_set_blocking($this->connection, 0);
    }

    /**
     * @param callable $callback
     * @param integer  $timeout
     */
    public function read(callable $callback, $timeout = 5)
    {
        $this->pool = [$this->connection];

        while ($this->connection !== null && !feof($this->connection) && stream_select($this->pool, $fdw, $fde, $timeout) !== false) {

            // @todo safeguard no tweets but connection OK. (reconnect)

            $this->pool = [$this->connection];

            $chunkInfo = trim(fgets($this->connection));

            if (!$chunkInfo) {
                continue;
            }

            $len = hexdec($chunkInfo) + 2;
            $streamInput = '';

            while (!feof($this->connection)) {
                $streamInput .= fread($this->connection, $len-strlen($streamInput));

                if (strlen($streamInput)>=$len) {
                    break;
                }
            }

            $this->buffer .= substr($streamInput, 0, -2);

            $data = json_decode($this->buffer, true);

            if ($data) {
                call_user_func($callback, $data);
                $this->buffer = '';
            }
        }
    }

    public function close()
    {
        if (is_resource($this->connection)) {
            fclose($this->connection);
        }

        $this->connection = null;
    }
}
