<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/merc230.php';

use Analog\Logger;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;


$log = '';
$logger = new Logger;
$logger->handler(Analog\Handler\Variable::init ($log));
// $logger->handler(Analog\Handler\File::init('mqtt.log'));
$logger->debug('Starting MQTT Publishing');

$meter = new Mercury230('tcp://192.168.1.40', 5000, 73);
$tcp = $meter->open();

$info = $meter->get_meter();
$stored = $meter->get_stored();

$meter->close();


try {
    $client = new MqttClient('192.168.1.11', '1883', 'energy', MqttClient::MQTT_3_1, null, $logger);

    $connectionSettings = (new ConnectionSettings)
        ->setUsername('tasmota')
        ->setPassword('tasmota')
        ->setUseTls(false)
        ->setTlsSelfSignedAllowed(true)
        ->setTlsVerifyPeer(false);

    $client->connect($connectionSettings, true);

    // Register an event handler which logs all published messages of any topic and QoS level.
    // $handler = function (MqttClient $client, string $topic, string $message, ?int $messageId, int $qualityOfService, bool $retain) use ($logger) {
    //     $logger->info('Sending message [{messageId}] on topic [{topic}] using QoS {qos}: {message}', [
    //         'topic' => $topic,
    //         'message' => $message,
    //         'messageId' => $messageId ?? 'no id',
    //         'qos' => $qualityOfService,
    //     ]);
    // };
    // $client->registerPublishEventHandler($handler);

    $client->publish('homeassistant/sensor/energy/meter', json_encode($info), MqttClient::QOS_AT_MOST_ONCE);
    $client->publish('homeassistant/sensor/energy/stored', json_encode($stored), MqttClient::QOS_AT_LEAST_ONCE);

    $client->disconnect();
} catch (MqttClientException $e) {
    $logger->error('Publishing a message using QoS 0 failed. An exception occurred.', ['exception' => $e]);
}

$logger->debug('End MQTT');