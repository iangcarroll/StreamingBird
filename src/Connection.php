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
        // If we have a socket connection, we can attempt a HTTP request - Ensure blocking read for the moment
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

        // First line is response
        list($httpVer, $httpCode, $httpMessage) = preg_split('/\s+/', trim(fgets($this->connection, 1024)), 3);

        // Response buffers
        $respHeaders = $respBody = '';
        $isChunking = false;

        // Consume each header response line until we get to body
        while ($hLine = trim(fgets($this->connection, 4096))) {
            $respHeaders .= $hLine."\n";
            if (strtolower($hLine) == 'transfer-encoding: chunked') {
                $isChunking = true;
            }
        }

        // If we got a non-200 response, we need to backoff and retry
        if ($httpCode != 200) {
            // Twitter will disconnect on error, but we want to consume the rest of the response body (which is useful)
            //TODO: this might be chunked too? In which case this contains some bad characters??
            while ($bLine = trim(fgets($this->connection, 4096))) {
                $respBody .= $bLine;
            }

            throw new TwitterException(sprintf('Twitter API responsed a "%s" status code.', $httpCode));
        } // End if not http 200
        elseif(!$isChunking) {
            throw new Exception("Twitter did not send a chunking header. Is this really HTTP/1.1? Here are headers:\n$respHeaders");   //TODO: rather crude!
        }

        stream_set_blocking($this->connection, 0);
    }

    /**
     * @param callable $callback
     * @param integer  $timeout
     */
    public function read(callable $callback, $timeout = 5)
    {
        $this->pool = array($this->connection);

        // We use a blocking-select with timeout, to allow us to continue processing on idle streams
        while ($this->connection !== null && !feof($this->connection) && stream_select($this->pool, $fdw, $fde, $timeout) !== false) {

            // @todo safeguard no tweets but connection OK. (reconnect)

            // Process stream/buffer
            $this->pool = array($this->connection); // Must reassign for stream_select()

            //Get a full HTTP chunk.
            $chunkInfo = trim(fgets($this->connection)); //First line is hex digits giving us the length

            if ($chunkInfo == '') {
                continue;
            }

            //Append one HTTP chunk to $this->buff
            $len=hexdec($chunkInfo);   //$len includes the \r\n at the end of the chunk (despite what wikipedia says)
            $streamInput='';
            $len+=2;    //For the \r\n at the end of the chunk

            while (!feof($this->connection)) {
                $streamInput.=fread($this->connection, $len-strlen($streamInput));

                if (strlen($streamInput)>=$len) {
                    break;
                }
            }

            $this->buffer.=substr($streamInput, 0, -2);   //This is our HTTP chunk

            while (1) {
                $eol = strpos($this->buffer, "\r\n");  //Find next line ending
                if ($eol===0) {  // if 0, then buffer starts with "\r\n", so trim it and loop again
                    $this->buffer = substr($this->buffer, $eol+2);  // remove the "\r\n" from line start
                    continue; // loop again
                }
                if ($eol===false) {
                    break;   //Time to get more data
                }

                call_user_func($callback, substr($this->buffer, 0, $eol));

                $this->buffer = substr($this->buffer, $eol+2);    //+2 to allow for the \r\n
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
