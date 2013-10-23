<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


// Init PDO_SQLITE for storing messages
if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers())) {
	die('This example requires SQlite3 PDO driver for PHP');
}
$db_file = 'messages.db';
if (!file_exists($db_file)) {
	die('Could not find database file '.$db_file);
}
$db = new PDO("sqlite:".$db_file);


// Check if we have all required parameters
if (!isset($_REQUEST['sms_unique_id']) || !isset($_REQUEST['status']) || !isset($_REQUEST['err'])) {
	die('Invalid DLR request');
}

$stmnt = $db->prepare("
	UPDATE dlrs
	SET time_dlr = strftime('%s','now'),
		status = :status,
		err = :err
	WHERE dlr_id = :dlr_id
;");

$stmnt->execute(array(
	':status'	=> $_REQUEST['status'],
	':err'		=> $_REQUEST['err'],
	':dlr_id'	=> $_REQUEST['sms_unique_id']
));

die('OK');
