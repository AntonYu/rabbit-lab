<?php

require_once './vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();
$channel->queue_declare('hello', false, false, false, false);

$message = new AMQPMessage('Hello world!');
$channel->basic_publish($message, '', 'hello');

echo '[x] Sent hello world!' . PHP_EOL;

$channel->close();
$connection->close();
