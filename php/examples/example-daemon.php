<?php
header('Content-type: text/html; charset=utf-8');
function flush_buffers(){ 
    ob_end_flush(); 
    ob_flush(); 
    flush(); 
    ob_start(); 
} 
ob_start();


ini_set('display_errors', 1);
error_reporting(E_ALL);

// Remove time limit for daemon
set_time_limit(3);


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

// Array of message ID's that had temporary error when sending SMS
$retry_queue = array();


// Initialize Messente API
// No E-mail is sent when debug mode is on. Disable debug mode for live release.
$preferences = array(
	'username'		=> 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
	'password'		=> 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy',
	'debug'			=> true,					
	'error_email'	=> getenv("SERVER_ADMIN")	// E-mail that gets mail when something goes wrong	
);
$Messente = new Messente($preferences);



$st_message_sent = $db->prepare("UPDATE messages SET sent = 1 WHERE id = :message_id");
$st_dlr = $db->prepare("INSERT INTO dlrs (message_id, dlr_id, time_sent) VALUES (:message_id, :dlr_id, strftime('%s','now'));");
$st_dlr_failed = $db->prepare("INSERT INTO dlrs (message_id, status, err, time_dlr, time_sent) VALUES (:message_id, :status, :err, strftime('%s','now'), strftime('%s','now'));");

while (true) {

	echo "Selecting messages to send... ";
	
	$st_messages = $db->prepare("SELECT id, content, recipient, sender_id FROM messages WHERE sent = 0");
	$st_messages->execute();
	
	echo "found ".$st_messages->rowCount()." SMS to send<br/>\n";

	// Send messages and show responses
	while ($message = $st_messages->fetch(PDO::FETCH_ASSOC)) {
		
		$result = $Messente->send_sms(array(
			'from'		=> $message['sender_id'],
			'to'		=> $message['recipient'],
			'content'	=> $message['content']
		));
		
			
		// Check if we successfully sent the SMS to Messente
		if (!$result['error'] && $result['message_id']) {
		
			// Successful request
			echo "Successful API call to send SMS:<br/>\n";
			var_dump($result);	
			
			$st_message_sent->execute(array(
				':message_id'	=> $message['id'],
			));
			
			$st_dlr->execute(array(
				':message_id'	=> $message['id'],
				':dlr_id'		=> $result['message_id']
			));
					
			
		// Check if it was a temporary error
		} elseif ($result['error'] && substr($result['error_code'],0,6) == 'FAILED') {
		
			// Keep count of failed retries
			if (isset($retry_queue[$message['id']])) {
				$retry_queue[$message['id']]++;
			} else {
				$retry_queue[$message['id']] = 1;
			}
			
			// Failed request
			echo "Failed to make the API call request, retry<br/>\n";
			var_dump($result);
			
			// We have reached the max retry
			if ($retry_queue[$message['id']] > 3) {
			
				$st_message_sent->execute(array(
					':message_id'	=> $message['id'],
				));
				
				$st_dlr->execute(array(
					':message_id'	=> $message['id'],
					':status'		=> 'ERROR',
					':err'			=> 999
				));
				
				unset($retry_queue[$message['id']]);
				
				// Failed request
				echo "Failed to make the API call request, do not retry any more.<br/>\n";
				var_dump($result);
				
			}
		
		} else {
		
			// Failed request
			echo "Failed to make the API call request:<br/>\n";
			var_dump($result);
			
		}
			
	} // while messages
	
	$st_messages = null;
		
	// Sleep a bit
	echo "Sleeping 5 seconds...<br/>\n";
	
	// Show printed texts
	flush_buffers();


	sleep(5);
	
} // everlasting while loop	
