<?php

/*
 * Download the pricelist and parse the response
 */

// Set cURL options
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
curl_setopt($ch, CURLOPT_POST, true);

// Set endpoint URL and auth. parameters
curl_setopt($ch, CURLOPT_URL, 'http://api2.messente.com/pricelist/');
curl_setopt($ch, CURLOPT_POSTFIELDS, array(
	'username'	=> 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
	'password'	=> 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy',
));

// Make the request	
$content = curl_exec($ch);
$curl_info = curl_getinfo($ch);


// Check if the request failed and should be tetried?
if (curl_errno($ch) || $curl_info['http_code'] != 200) {

	var_dump($content);
	die('Error making HTTP request');

} elseif (substr($content,0,6) == 'FAILED' || substr($content,0,5) == 'ERROR') {
		
	var_dump($content);
	die('Error returned when making request');

} else {

	// Save the pricelist to a file
	$fh = fopen('/tmp/messente_pricelist.csv', 'w+');
	fwrite($fh, $content);
	fseek($fh, 0);
	
	// Read the column titles
	$columns = fgetcsv($fh, 1000, ",");
	
	while (($data = fgetcsv($fh, 1000, ",")) !== false) {
	
		$prices = array_combine($columns, $data);
		
		// Prints out the array for prices
		var_dump($prices);
	
	}
	
}
