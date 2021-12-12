<?php

require __DIR__ . '/app.php';

header('Content-type: application/json');

$tcp = $meter->open();
$result = array();

if( isset( $_GET['moment'] ) ) {

    $result = $meter->get_moment();

} else {

    $result['meter'] = $meter->get_meter();
    $result['meter']['connect'] = $tcp;
    $result['stored'] = $meter->get_stored();
    $result['moment'] = $meter->get_moment();

}

$result['time'] = date('c');

$meter->close();

echo json_encode($result, TRUE);
