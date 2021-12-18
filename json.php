<?php

require __DIR__ . '/app.php';

header('Content-type: application/json');

$tcp = $meter->open();
$result = array();

if( isset( $_GET['mode'] ) && 'moment' === isset( $_GET['mode'] ) ) {

    $result = $meter->get_moment();

} else {

    $result['meter'] = $meter->get_meter();
    $result['meter']['connect'] = $tcp;
    $result['stored'] = $meter->get_stored();
    $result['moment'] = $meter->get_moment();

}

$result['time'] = date('c');

$meter->close();

if ( isset( $result['moment'] ) && !empty( $result['moment'] ) ) {
    echo json_encode($result, TRUE);
} else {
    echo json_encode( array('error' => true, 'message' => $tcp), TRUE );
}
