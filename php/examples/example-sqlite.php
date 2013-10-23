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


// Messente API PHP wrapper
if (!file_exists('../messente.php')) {
	die('Could not find messente.php');
}
require_once('../messente.php');


// Initialize Messente API
// No E-mail is sent when debug mode is on. Disable debug mode for live release.
$preferences = array(
	'username'		=> 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
	'password'		=> 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy',
	'debug'			=> true,					
	'error_email'	=> getenv("SERVER_ADMIN")	// E-mail that gets mail when something goes wrong	
);
$Messente = new Messente($preferences);


// Array of messages to send
$messages = array(
	array(
		'from'		=> '+37251916062',
		'to'		=> '+37251916062',
		'content'	=> 'Test to first number õ',
	)
);

$st_message = $db->prepare("INSERT INTO messages (sender_id, recipient, content, sent, insert_time) VALUES (:from, :to, :content, 1, strftime('%s','now'));");
$st_dlr = $db->prepare("INSERT INTO dlrs (message_id, dlr_id, time_sent) VALUES (:message_id, :dlr_id, strftime('%s','now'));");

// Send messages and show responses
foreach ($messages as $key => $message) {
	
	$result = $Messente->send_sms($message);
	
		
	// Check if we successfully sent the SMS to Messente
	if (!$result['error'] && $result['message_id']) {
	
		// Successful request
		echo "Successful API call to send SMS:<br/>\n";
		var_dump($result);
	
		try {
		
			$db->beginTransaction();
			
			$st_message->execute(array(
				':from'		=> $message['from'],
				':to'		=> $message['to'],
				':content'	=> $message['content'],
			));
			
			$st_dlr->execute(array(
				':message_id'	=> $db->lastInsertId(),
				':dlr_id'		=> $result['message_id']
			));
			
			$db->commit();
		
		} catch(PDOExecption $e) {
		
			echo "Failed to save message to database: ".$e->getMessage()."<br/>\n";
			$db->rollBack();

		}
		
	} else {
		
		// Failed request
		echo "Failed to make the API call request:<br/>\n";
		var_dump($result);
		
	}
		
} // foreach
