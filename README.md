## Запустить локально в докере

```
docker run -d --hostname localhost --name rabbitmq -p 5672:5672 -p 15672:15672 rabbitmq:3-management
```

Веб панель по адресу [http://localhost:15672](http://localhost:15672)

## Acknowledgement

```
$callback = function ($msg) {
  echo ' [x] Received ', $msg->getBody(), "\n";
  sleep(substr_count($msg->getBody(), '.'));
  echo " [x] Done\n";
  $msg->ack();
};

// 4th parameter is "no ack"
$channel->basic_consume('task_queue', '', false, false, false, false, $callback);
```
 
## Durable channel + messages

The durability options let the tasks survive even if RabbitMQ is restarted.

```
$msg = new AMQPMessage($data, [
  'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
]);

// 3rd parameter is "durable"
$channel->queue_declare('task_queue', false, true, false, false);
```

## Fair dispatch (QoS)

We can use the `basic_qos` method with the `prefetch_count` = `1` setting. This tells RabbitMQ not to give more than one message to a worker at a time. Or, in other words, don't dispatch a new message to a worker until it has processed and acknowledged the previous one. Instead, it will dispatch it to the next worker that is not still busy.

```
$channel->basic_qos(null, 1, false);
```

## Publish/subscribe

Publish/subscribe модель без ack с анонимными очередями. Очереди и сообщения не durable, то есть не сохраняются при перезагрузке. Fanout обменник рассылает поступившие к нему сообщения во все очереди, о которых он знает (для этого делаем bind в консюмере).

```
// producer.php
$channel->exchange_declare('logs', 'fanout', false, false, false);
$channel->basic_publish(new AMQPMessage($data), 'logs');


// consumer.php
$channel->exchange_declare('logs', 'fanout', false, false, false);

list($queue_name, ,) = $channel->queue_declare('', false, false, true, false);

// bind anonymouse queue and exchange named "logs"
$channel->queue_bind($queue_name, 'logs');

$callback = function ($msg) {
    echo ' [x] ', $msg->getBody(), "\n";
};

$channel->basic_consume($queue_name, '', false, true, false, false, $callback);
```

## Direct exchange

В одну очередь можно посылать сообщения с разными "routing key", чтобы разделить воркеры одной очереди.

```
// producer.php
$channel->exchange_declare('direct_logs', 'direct', false, false, false);
$severity = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'info';
$channel->basic_publish(new AMQPMessage($data), 'direct_logs', $severity);


// consumer.php
$channel->exchange_declare('direct_logs', 'direct', false, false, false);

list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);
foreach (['info', 'warning', 'error] as $severity) {
    $channel->queue_bind($queue_name, 'direct_logs', $severity);
}

echo " [*] Waiting for logs. To exit press CTRL+C\n";

$callback = function ($msg) {
    echo ' [x] ', $msg->getRoutingKey(), ':', $msg->getBody(), "\n";
};

$channel->basic_consume($queue_name, '', false, true, false, false, $callback);
```


## Topic exchange

Позволяет организовать более гибкий обменник, чем direct. В routing key можно передавать слова разделанные точкой, например "logs.info.node-1" и биндить консюмеров по маске. Например, "logs.#" - слушать все логи, "logs.\*.node-1" - логи любых ошибок для node-1.

```
// producer.php
$channel->exchange_declare('topic_logs', 'topic', false, false, false);
$routing_key = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'anonymous.info';
$channel->basic_publish(new AMQPMessage($data), 'topic_logs', $routing_key);


// consumer.php
$channel->exchange_declare('topic_logs', 'topic', false, false, false);
list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);

foreach (['js.#', 'php.critical', '*.info'] as $binding_key) {
    $channel->queue_bind($queue_name, 'topic_logs', $binding_key);
}

echo " [*] Waiting for logs. To exit press CTRL+C\n";

$callback = function ($msg) {
    echo ' [x] ', $msg->getRoutingKey(), ':', $msg->getBody(), "\n";
};

$channel->basic_consume($queue_name, '', false, true, false, false, $callback);
```
