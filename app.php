<pre><?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/merc230.php';

$cfg = parse_ini_file(__DIR__ . '/config.ini');

$meter = new Mercury230($cfg['meter_ip'], $cfg['meter_port'], $cfg['meter_addr']);
