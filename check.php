<pre><?php

require __DIR__ . '/app.php';

$result = array();

$tcp = $meter->open();
$result['connect'] = $tcp;
$result = $meter->check_sock();
$meter->close();

print_r($result);
