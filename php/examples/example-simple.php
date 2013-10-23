<?php

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
$message = array(
	'from'		=> '+37251916062',
	'to'		=> '+37251916062',
	'content'	=> 'Test to first number õ',
);

echo "Sending SMS message:<br/>\n";
var_dump($message);

$result = $Messente->send_sms($message);

echo "Result:<br/>\n";
var_dump($result);
