<?php

require __DIR__ . '/app.php';

use Analog\Logger;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;


$logger = null;
$mode = '';

if ($cfg['debug']) {
    $logger = new Logger;
    $logger->handler(Analog\Handler\File::init(__DIR__ . '/mqtt.log'));
    $logger->debug('Starting MQTT Publishing');
}


if (!empty($argv[1])) {
    $mode = $argv[1];
} else {
    $mode = $_GET['mode'];
}


$meter->open();

$moment = $meter->get_moment();
$info = $meter->get_meter();
$stored = $meter->get_stored();

$meter->close();


try {
    $client = new MqttClient($cfg['mqtt_srv'], $cfg['mqtt_port'], $cfg['mqtt_client'], MqttClient::MQTT_3_1, null, $logger);

    $connectionSettings = (new ConnectionSettings)
        ->setUsername($cfg['mqtt_user'])
        ->setPassword($cfg['mqtt_password'])
        ->setUseTls(false)
        ->setTlsSelfSignedAllowed(true)
        ->setTlsVerifyPeer(false);

    $client->connect($connectionSettings, true);

    if ($cfg['debug']) {
        // Register an event handler which logs all published messages of any topic and QoS level.
        $handler = function (MqttClient $client, string $topic, string $message, ?int $messageId, int $qualityOfService, bool $retain) use ($logger) {
            $logger->info('Sending message [{messageId}] on topic [{topic}] using QoS {qos}: {message}', [
                'topic' => $topic,
                'message' => $message,
                'messageId' => $messageId ?? 'no id',
                'qos' => $qualityOfService,
            ]);
        };
        $client->registerPublishEventHandler($handler);
    }

    if ($mode === 'stored') {
        $client->publish('homeassistant/sensor/energy/meter', json_encode($info), MqttClient::QOS_AT_MOST_ONCE);
        $client->publish('homeassistant/sensor/energy/stored', json_encode($stored), MqttClient::QOS_AT_LEAST_ONCE);
    } else {
        $client->publish('homeassistant/sensor/energy/moment', json_encode($moment), MqttClient::QOS_AT_LEAST_ONCE);
    }

    $client->disconnect();
} catch (MqttClientException $e) {
    $logger->error('Publishing a message using QoS 0 failed. An exception occurred.', ['exception' => $e]);
}

if ($cfg['debug']) {
    $logger->debug('End MQTT');
}
