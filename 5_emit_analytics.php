<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->exchange_declare('analytics_topic', 'topic', false, true, false);

$event = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'user.register';

$data = implode(' ', array_slice($argv, 2));
if (empty($data)) {
    $data = "Hello World!";
}

$msg = new AMQPMessage($data);

$channel->basic_publish($msg, 'analytics_topic', $event);

echo ' [x] Sent ', $event, ':', $data, "\n";

$channel->close();
$connection->close();
