#!/usr/bin/php
<?php

require __DIR__ . '/../../../vendor/autoload.php';

use OwlyCode\StreamingBird\Stub\TwitterStream;

$stub = new TwitterStream('127.0.0.1', 9999);
$stub->import(__DIR__ . '/../stream.txt');
$stub->start();
