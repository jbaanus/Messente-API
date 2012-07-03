<?php

// Messente API PHP wrapper
if (!file_exists('messente.php')) {
	die('Could not find messente.php');
} 

// Require the Messente class
require_once('messente.php');


// First register an account and activate sender, then you can activate your API
// To determine your IP address, use http://www.whatismyip.com/ or other similar service
$preferences = array(
	'username'		=> 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
	'password'		=> 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy',
	'debug'			=> true,		// No E-mail is sent when debug mode is on! Disable this for live release.
	'error_email'	=> ''			// Current API administrator e-mail that gets e-mail when something gets really wrong	
);


$Messente = new Messente($preferences);
$result = false;


// The message was set to be sent
if (!empty($_POST['send_sms'])) {

	$message = $_POST['message'];
	$result = $Messente->send_sms($message);

}
if (!empty($_POST['get_balance'])) {
	
	$result = $Messente->check_balance();
	
}
if (!empty($_POST['get_dlr_response'])) {
	
	$result = $Messente->get_dlr_response($_POST['sms_unique_id']);
	
}



?>
<!DOCTYPE html>
<html>
	<head>
		<title>Messente API example usage</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<style type="text/css">
			LABEL {
				display: block;
			}
		</style>
	</head>
	<body>
		<?php if (!empty($result)): ?>

			<div>
				<? if (isset($message) && !empty($message)): ?>
					<b>Query parameters:</b><br/>
					<code>
						<?php
							foreach ($message as $key => $value) {
								echo $key.': '.$value."<br/>\n";
							}
						?>
					</code>
					<br/>
				<? endif; ?>
				
				<b>Messente's response to the query:</b><br/>
				<code>
					<?php
						foreach ($result as $key => $value) {
							echo $key.': '.$value."<br/>\n";
						}
					?>
				</code>
			</div>		
		
		<?php endif; ?>
	
		<h2>Check balance</h2>
		<form method="post" action="example.php">
			<input type="submit" name="get_balance" value="Get balance">
		</form>
		
		<h2>Send SMS</h2>
		<form method="post" action="example.php">
			<label>From:</label><input name="message[from]">
			<label>To:</label><input name="message[to]">
			<label>Content:</label><textarea name="message[content]"></textarea>
			<label>DLR URL:</label><input name="message[dlr-url]">
			<br/>
			<input type="submit" name="send_sms" value="Send SMS">
		</form>
		
		<h2>Get DLR</h2>
		<form method="post" action="example.php">
			<label>SMS unique ID:</label><input name="sms_unique_id">
			<br/>
			<input type="submit" name="get_dlr_response" value="Get DLR status">
		</form>
</body>
</html>
