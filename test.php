<pre><?php

$energy = new Mercury230('tcp://192.168.1.40', 5000, 73);

echo "Opening socket... " . $energy->open_conn() . "\n\n";

print_r($energy->get_energy());

echo "\n\nClosing socket... " . $energy->close_conn();


