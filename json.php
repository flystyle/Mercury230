<?php

header('Content-type: application/json');

require __DIR__ . '/merc230.php';

$meter = new Mercury230('tcp://192.168.1.40', 5000, 73);

$tcp = $meter->open();
$result = array();

if( isset( $_GET['now'] ) ) {

    $result = $meter->get_moment();

} else {

    $result['meter'] = $meter->get_meter();
    $result['meter']['connect'] = $tcp;
    $result['stored'] = $meter->get_stored();
    $result['moment'] = $meter->get_moment();

}

$meter->close();

echo json_encode($result, TRUE);
