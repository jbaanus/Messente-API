<?php

if (!function_exists('curl_init')) {
	die('Messente API PHP wrapper requires CURL module for PHP');
}

class Messente {

	public $version = '0.1.3';

	function __construct($preferences) {
		if (!isset($preferences['username'])) die('No username set');
		if (!isset($preferences['password'])) die('No API key set');

		$default_preferences = array(
			'debug'   => false,
			'error_email' => '',
			'secure'  => false
		);

		$preferences = array_merge($default_preferences, $preferences);

		if ($preferences['secure']) {
			$this->messente_url = 'https://api2.messente.com/';
		} else {
			$this->messente_url = 'http://api2.messente.com/';
		}
		$this->preferences = $preferences;


		// Reuse connection to make sending SMS faster
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($this->ch, CURLOPT_USERAGENT, "Messente PHP library v".$this->version." (curl)");
		
		// Fix for Squid proxy
		// See http://www.php.net/manual/en/function.curl-setopt.php#106891 for more details
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Expect:"));

	}




	function check_balance() {

		$post_fields = array(
			'username' => $this->preferences['username'],
			'password' => $this->preferences['password']
		);

		curl_setopt($this->ch, CURLOPT_URL, $this->messente_url.'get_balance/');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
		
		$content = curl_exec($this->ch);
		
		// Check if cURL request was OK
		if (curl_errno($this->ch)){
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message'	=> 'cURL error: '.curl_error($this->ch),
				'eur'			=> null,
				'raw'			=> $content
			);
			
		}

		
		$data = explode(' ', $content, 2);
		
		
		if (count($data) < 2) {
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message' => 'Invalid response from Messente',
				'eur'			=> 0,
				'raw'			=> $content
			);
		
		}
		

		if ($data[0] == 'OK') {
			
			return array(
				'error'   		=> false,
				'error_code'	=> null,
				'error_message' => null,
				'eur'			=> $data[1],
				'raw'			=> $content
			);
			
		} else {

			$error_message = $this->get_error_message($content);
			$this->email_error($content, $error_message, array('request' => $post_fields));

			return array(
				'error'			=> true,
				'error_code'	=> $data[0],
				'error_message' => $error_message,
				'eur'			=> 0,
				'raw'			=> $content
			);
		}
	}





	function send_sms($message) {
	
		if (!isset($message['content'])) {
		
			return array(
				'error'			=> true,
				'error_message' => 'Message has no content',
				'error_code'	=> null,
				'sms_unique_id'	=> null,
				'raw'			=> null
			);
			
		}

		if (!isset($message['to'])) {
		
			return array(
				'error'			=> true,
				'error_message' => 'Message has no receiver',
				'error_code'	=> null,
				'sms_unique_id'	=> null,
				'raw'			=> null
			);
			
		}



		// Validate phone number format and return error when invalid
		$to = $this->__format_phone($message['to']);
		if (!$to) {
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message' => 'Invalid phone number format',
				'sms_unique_id' => null,
				'raw'			=> null
			);
			
		}

		$post_fields = array(
			'username'	=> $this->preferences['username'],
			'password'	=> $this->preferences['password'],
			'text'		=> $message['content'],
			'to'		=> $to
		);

		if (isset($message['from']) && !empty($message['from'])) {
			$post_fields['from'] = $message['from'];
		}

		if (isset($message['time_to_send']) && !empty($message['time_to_send'])) {
			$post_fields['time_to_send'] = strtotime($message['time_to_send']);
		}

		if (isset($message['charset']) && !empty($message['charset'])) {
			$post_fields['charset'] = $message['charset'];
		}

		if (isset($message['dlr-url']) && !empty($message['dlr-url'])) {
			$post_fields['dlr-url'] = $message['dlr-url'];
		}

		if (isset($message['autocorrect']) && !empty($message['autocorrect'])) {
			$post_fields['autocorrect'] = $message['autocorrect'];
		}

		curl_setopt($this->ch, CURLOPT_URL, $this->messente_url.'send_sms/');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
		$content = curl_exec($this->ch);
		
		
		// Check if cURL request was OK
		if (curl_errno($this->ch)){
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message'	=> 'cURL error: '.curl_error($this->ch),
				'sms_unique_id' => null,
				'raw'			=> $content
			);
			
		}

		
		// Split data to 3 sections: STATUS UNIQUE_MESSAGE_ID BLOB
		$data = explode(' ', $content, 3);
		
		if (count($data) < 2) {
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message' => 'Invalid response from Messente',
				'sms_unique_id' => null,
				'raw'			=> $content
			);
		
		}

		if ($data[0] == 'OK') {

			return array(
				'error'			=> false,
				'error_code'	=> null,
				'error_message' => null,
				'sms_unique_id' => $data[1],
				'raw'			=> $content
			);

		} else {

			$error_message = $this->get_error_message($content);
			$post_fields['url'] = $this->messente_url.'send_sms/';
			$this->email_error($content, $error_message, $post_fields);

			return array(
				'error'			=> true,
				'error_code'	=> $data[1],
				'error_message' => $error_message,
				'sms_unique_id' => null,
				'raw'			=> $content
			);
		}
	}




	/**
	 * Formats receiver number - removes spaces, leading 0's and +
	 * Whitespaces are also removed
	 */
	function __format_phone($number) {
		$number = ltrim($number, '+');
		$number = ltrim($number, '0');
		$number = str_replace(' ', '',$number);
		
		// Are only numbers left?
		if (!is_numeric($number)) return false;
		
		return $number;
	}





	/**
	 * Return error message accoring to API response
	 */
	function get_error_message($code) {
		switch ($code) {
		case 'ERROR 101':
			return 'Access is restricted, wrong credentials. Check your username, password.';
		case 'ERROR 102':
			return 'Parameters are wrong or missing.';
		case 'ERROR 103':
			$ip = $this->__findMyIpAddress();
			return 'Current IP address '.$ip.' is not allowed. Check API settings page in Messente.';
		case 'ERROR 105':
			return 'No such country or area code or invalid phone number format.';
		case 'ERROR 106':
			return 'Destination country is not supported.';
		case 'ERROR 107':
			return 'Not enough credit on account.';
		case 'ERROR 111':
			return 'Sender parameter "from" is invalid or not allowed.';
		case 'FAILED 208':
			return 'Messente was unable to determine Account balance, try again.';
		case 'FAILED 209':
			return 'Server failure, try again.';
		default:
			return "Unknown error [$code]";
		}
	}


	private function __findMyIpAddress(){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_URL, $this->messente_url.'send_sms/');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
		$content = curl_exec($ch);
		return trim($content);
	}



	function email_error($error_code, $error, $post) {

		// Skip non-fatal errors
		if (in_array($error_code, array('FAILED 210', 'FAILED 209'))) return false;

		if (!empty($this->preferences['error_email']) && !$this->preferences['debug']) {
			$content = 'Messente API returned an error while trying to communicate with Messente!'."\n\n";
			$content .= 'Error: '.$error."\n\n";
			$content .= 'API request parameters:'."\n";

			foreach ($post as $param => $value) {
				$content .= "  [".$param."] => ".$value."\n";
			}


			return mail($this->preferences['error_email'], 'Messente API - ERROR!', $content);
		}

		return false;
	}




	function get_dlr_response($sms_unique_id) {
		if (!$sms_unique_id) {
			return array(
				'error'			=> true,
				'error_message'	=> 'No DLR id specified.',
				'error_code'	=> null,
				'status'		=> null,
				'raw'			=> null
			);
		}


		$post_fields = array(
			'username'  => $this->preferences['username'],
			'password'  => $this->preferences['password'],
			'sms_unique_id' => $sms_unique_id,
		);


		curl_setopt($this->ch, CURLOPT_URL, $this->messente_url.'get_dlr_response/');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
		$content = curl_exec($this->ch);
		
		// Check if cURL request was OK
		if (curl_errno($this->ch)){
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message'	=> 'cURL error: '.curl_error($this->ch),
				'status'		=> null,
				'raw'			=> $content
			);
			
		} elseif (substr($content,0,2) == 'OK') {

			$data = explode(' ', $content, 2);
			return array(
				'error'			=> false,
				'error_message' => null,
				'error_code'	=> null,
				'status'		=> $data[1],
				'raw'			=> $content
			);

		} else {

			$error_message = $this->get_error_message($content);
			$this->email_error($content, $error_message, array('request' => $request));

			return array(
				'error'			=> true,
				'error_code'	=> $content,
				'error_message'	=> $error_message,
				'status'		=> null,
				'raw'			=> $content
			);

		}
	}
}
