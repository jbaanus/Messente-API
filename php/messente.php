<?php

/*
 * Official PHP library for Messente API
 * https://messente.com
 *
 */

if (!function_exists('curl_init')) {
	die('Messente API PHP wrapper requires CURL module for PHP');
}

class Messente {

	public $version = '0.1.7';
	private $use_backup_route = false;

	function __construct($preferences = array()) {
		if (!isset($preferences['username'])) die('No username set');
		if (!isset($preferences['password'])) die('No API key set');

		$default_preferences = array(
			'debug'			=> false,
			'error_email'	=> '',
			'secure'		=> false,
			'dlr-url'		=> ''
		);

		$preferences = array_merge($default_preferences, $preferences);
		$this->preferences = $preferences;
		
		
		// Reuse connection to make sending SMS faster
		$curl_version = curl_version();
		
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($this->ch, CURLOPT_USERAGENT, "Messente PHP library v".$this->version." (curl ".$curl_version['version'].")");
		
		// Fix for Squid proxy
		// See http://www.php.net/manual/en/function.curl-setopt.php#106891 for more details
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Expect:"));

	}
	
	
	// Determine the API endpoint URL
	private function __getApiUrl() {
		
		if ($this->preferences['secure']) {
			$url = 'https://';
		} else {
			$url = 'http://';
		}
		
		if ($this->use_backup_route) {
		
			if ($this->preferences['debug']) {
				echo "Warning! Using backup route...<br/>\n";
			}
		
			$url .= 'api3.messente.com/';
		} else {
			$url .= 'api2.messente.com/';
		}
		
		return $url;
		
	}



	
	function check_balance() {

		$post_fields = array(
			'username' => $this->preferences['username'],
			'password' => $this->preferences['password']
		);

		curl_setopt($this->ch, CURLOPT_URL, 
			$this->__getApiUrl().'get_balance/?'.http_build_query($post_fields));
		
		$content = curl_exec($this->ch);
		$curl_info = curl_getinfo($this->ch);
		
		// Check if cURL request was OK
		if (curl_errno($this->ch) || $curl_info['http_code'] != 200){
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message'	=> $this->get_error_message($content, curl_error($this->ch)),
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
			$post_fields['url'] = $this->__getApiUrl().'get_balance/';
			$this->email_error($content, $error_message, $post_fields);

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
				'message_id'	=> null,
				'raw'			=> null
			);
			
		}

		if (!isset($message['to'])) {
		
			return array(
				'error'			=> true,
				'error_message' => 'Message has no receiver',
				'error_code'	=> null,
				'sms_unique_id'	=> null,
				'message_id'	=> null,
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
				'message_id'	=> null,
				'raw'			=> null
			);
			
		}

		$post_fields = array(
			'username'	=> $this->preferences['username'],
			'password'	=> $this->preferences['password'],
			'dlr-url'	=> $this->preferences['dlr-url'],
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

		if (isset($message['autoconvert']) && !empty($message['autoconvert'])) {
			$post_fields['autoconvert'] = $message['autoconvert'];
		}

		curl_setopt($this->ch, CURLOPT_URL, 
			$this->__getApiUrl().'send_sms/?'.http_build_query($post_fields));
		
		
		// Get the response as string
		$content = curl_exec($this->ch);
		$curl_info = curl_getinfo($this->ch);
		
		// Split data to 3 sections: STATUS UNIQUE_MESSAGE_ID BLOB
		$data = explode(' ', $content, 3);
		
		
		// Check if cURL request was OK, if there was a temporary error or invalid response
		if (curl_errno($this->ch) || $curl_info['http_code'] != 200 || substr($content, 0, 6) == 'FAILED' || count($data) < 2){
		
			if (!$this->use_backup_route) {
				$this->use_backup_route = true;
				return $this->send_sms($message);
			}
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message'	=> $this->get_error_message($content, curl_error($this->ch)),
				'sms_unique_id' => null,
				'message_id'	=> null,
				'raw'			=> $content
			);
			
		}

				
		if ($data[0] == 'OK') {

			// Try to use main route again after successful response from backup route
			$this->use_backup_route = false;
			return array(
				'error'			=> false,
				'error_code'	=> null,
				'error_message' => null,
				'sms_unique_id' => $data[1],
				'message_id'	=> $data[1],
				'raw'			=> $content
			);

		} else {

			$error_message = $this->get_error_message($content);
			$post_fields['url'] = $this->__getApiUrl().'send_sms/';
			$this->email_error($content, $error_message, $post_fields);

			return array(
				'error'			=> true,
				'error_code'	=> $data[1],
				'error_message' => $error_message,
				'sms_unique_id' => null,
				'message_id'	=> null,
				'raw'			=> $content
			);
		}
		
	} // function send_sms




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
	} // function __format_phone





	/**
	 * Return error message accoring to API response
	 */
	function get_error_message($code, $curl_error = null) {
	
		// We had cURL error
		if ($curl_error) {
			return 'cURL error: '.$curl_error;
		}
	
		switch ($code) {
		case 'ERROR 101':
			return 'Access is restricted, wrong credentials. Check your username, password.';
		case 'ERROR 102':
			return 'Parameters are wrong or missing.';
		case 'ERROR 103':
			$ip = $this->__findMyIpAddress();
			return 'Current IP address '.$ip.' is not allowed. Check API settings page in Messente.';
		case 'ERROR 104':
			return 'Destination country for this number was not found.';
		case 'ERROR 105':
			return 'No such country or area code or invalid phone number format.';
		case 'ERROR 106':
			return 'Destination country is not supported.';
		case 'ERROR 107':
			return 'Not enough credit on account.';
		case 'ERROR 111':
			return 'Sender parameter "from" is invalid or not allowed.';
		case 'FAILED 209':
			return 'Server failure, try again.';
		default:
			return "Unknown error";
		}
		
	} // function get_error_message


	private function __findMyIpAddress(){
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_URL, 'http://bot.whatismyipaddress.com');
		$content = curl_exec($ch);
		$curl_info = curl_getinfo($this->ch);
		
		if (curl_errno($ch) || $curl_info['http_code'] != 200) {
			return 'Error: '.curl_error($ch);
		}
		
		return trim($content);
		
	} // function __findMyIpAddress



	function email_error($error_code, $error, $post) {

		// Skip non-fatal errors
		if (in_array($error_code, array('FAILED 209'))) return false;

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
	} // function email_error




	function get_dlr_response($message_id) {
		if (!$message_id) {
			return array(
				'error'			=> true,
				'error_message'	=> 'No Message ID specified.',
				'error_code'	=> null,
				'status'		=> null,
				'raw'			=> null
			);
		}


		$post_fields = array(
			'username'  => $this->preferences['username'],
			'password'  => $this->preferences['password'],
			'sms_unique_id' => $message_id,
		);


		curl_setopt($this->ch, CURLOPT_URL, 
			$this->__getApiUrl().'get_dlr_response/?'.http_build_query($post_fields));
			
		$content = curl_exec($this->ch);
		$curl_info = curl_getinfo($this->ch);
		
		// Check if cURL request was OK
		if (curl_errno($this->ch) || $curl_info['http_code'] != 200){
		
			if (!$this->use_backup_route) {
				$this->use_backup_route = true;
				return $this->get_dlr_response($message_id);
			}
		
			return array(
				'error'			=> true,
				'error_code'	=> null,
				'error_message'	=> $this->get_error_message($content, curl_error($this->ch)),
				'status'		=> null,
				'raw'			=> $content
			);
			
		} elseif (substr($content,0,2) == 'OK') {
		
			// Try to use main route again after successful response from backup route
			$this->use_backup_route = false;

			$data = explode(' ', $content, 2);
			return array(
				'error'			=> false,
				'error_message' => null,
				'error_code'	=> null,
				'status'		=> $data[1],
				'raw'			=> $content
			);

		} else {
		
			if (!$this->use_backup_route) {
				$this->use_backup_route = true;
				return $this->get_dlr_response($message_id);
			}

			$error_message = $this->get_error_message($content);
			$post_fields['url'] = $this->__getApiUrl().'get_dlr_response/';
			$this->email_error($content, $error_message, $post_fields);

			return array(
				'error'			=> true,
				'error_code'	=> $content,
				'error_message'	=> $error_message,
				'status'		=> null,
				'raw'			=> $content
			);

		}
	} // function get_dlr_response
	
	function get_country_price($code = null) {
		
		if (!$code) {
			return array(
				'error'			=> true,
				'error_message'	=> 'No DLR id specified.',
				'error_code'	=> null,
				'country'		=> null,
				'prices'		=> null,
				'raw'			=> null
			);
		}

		// Use JSON as preferred format
		$post_fields = array(
			'username'  => $this->preferences['username'],
			'password'  => $this->preferences['password'],
			'country'	=> $code,
			'format'	=> 'json'
		);


		curl_setopt($this->ch, CURLOPT_URL, 
			$this->__getApiUrl().'prices/?'.http_build_query($post_fields));
			
		$content = curl_exec($this->ch);
		$curl_info = curl_getinfo($this->ch);
		
		// Check if the request failed and should be tetried?
		if (curl_errno($this->ch) || $curl_info['http_code'] != 200 || substr($content,0,6) == 'FAILED'){
		
			if (!$this->use_backup_route) {
				$this->use_backup_route = true;
				return $this->getCountryPrice($code);
			}
		
			return array(
				'error'			=> true,
				'error_message'	=> $this->get_error_message($content, curl_error($this->ch)),
				'error_code'	=> null,
				'country'		=> null,
				'prices'		=> null,
				'raw'			=> $content
			);
		
		// Request itself was successful but failed with error code. Do not try to make the request again
		} elseif (substr($content,0,5) == 'ERROR') {
			
			$error_message = $this->get_error_message($content);
			$this->email_error($content, $error_message, array('request' => $request));
			
			$error_message = $this->get_error_message($content);
			$post_fields['url'] = $this->__getApiUrl().'prices/';
			$this->email_error($content, $error_message, $post_fields);


			return array(
				'error'			=> true,
				'error_code'	=> $content,
				'error_message'	=> $error_message,
				'country'		=> null,
				'prices'		=> null,
				'raw'			=> $content
			);
			
		
		// Request was successful
		} else {
		
			// Try to use main route again after successful response from backup route
			$this->use_backup_route = false;
			
			$data = json_decode($content, true);
			
			// Check if the content was correctly formatted JSON
			if (!$data) {
			
				return array(
					'error'			=> true,
					'error_code'	=> null,
					'error_message'	=> 'Could not decode JSON response string',
					'country'		=> null,
					'prices'		=> null,
					'raw'			=> $content
				);

			}
			
			// Make the country data look better
			$country = array(
				'country'	=> $data['country'],	// To comply with API docs
				'code'		=> $data['country'],
				'name'		=> $data['name'],
				'prefix'	=> $data['prefix']
			);

			return array(
				'error'			=> false,
				'error_code'	=> null,
				'error_message'	=> null,
				'country'		=> $country,
				'prices'		=> $data['networks'],
				'raw'			=> $content
			);

		}
		
	} // function get_country_price
	
} // class Messente
