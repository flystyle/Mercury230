<pre><?php

require __DIR__ . '/merc230.php';

$result = array();

$meter = new Mercury230('tcp://192.168.1.40', 5000, 73);
$tcp = $meter->open();

// $info = $meter->get_meter();
// $stored = $meter->get_stored();
// $moment = $meter->get_moment();

$info = $meter->get_stored();

$meter->close();

print_r($info);