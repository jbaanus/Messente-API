<?php

// Messente API PHP wrapper
if (!file_exists('messente.php')) {
	die('Could not find messente.php');
} 

require_once('messente.php');

$preferences = array(
	'username'		=> 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
	'password'		=> 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy',
	'debug'			=> true,				// No E-mail is sent when debug mode is on! Disable this for live release.
	'error_email'	=> ''					// Current API administrator e-mail that gets e-mail when something gets really wrong	
);

// Initialize API
$Messente = new Messente($preferences);


// Array of messages to send
$messages = array(
	array(
		'to'		=> '+44XXXXXXXXX',
		'content'	=> 'Test to first number õ',
	),
	array(
		'to'		=> '+44XXXXXXXXX',
		'content'	=> 'Make sure the encoding of the text is correct - š',
	)
);


// Send messages and show responses
foreach ($messages as $key => $message) {
	
	$result = $Messente->send_sms($message);
		
	echo "Result: \n";
	print_r($result);
	
}