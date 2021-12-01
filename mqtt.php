<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/merc230.php';

$meter = new Mercury230('tcp://192.168.1.40', 5000, 73);
$tcp = $meter->open();

$info = $meter->get_meter();
$stored = $meter->get_stored();
$moment = $meter->get_moment();

$meter->close();

// MQTT
$server   = '192.168.1.11';
$port     = 1883;
$clientId = 'energy-meter';

$mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
$connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
    ->setUsername('tasmota')
    ->setPassword('tasmota')
    ->setTlsSelfSignedAllowed(true);
$mqtt->connect();
$mqtt->publish('homeassistant/sensor/energy/meter', json_encode($info), 0);
$mqtt->publish('homeassistant/sensor/energy/stored', json_encode($stored), 0);
$mqtt->publish('homeassistant/sensor/energy/moment', json_encode($moment), 0);
$mqtt->disconnect();
