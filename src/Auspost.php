<?php

namespace Cognito\Auspost;

use Exception;

/**
 * Interact with the Australian Post API
 *
 * @package Cognito
 * @author Josh Marshall <josh@jmarshall.com.au>
 */
class Auspost {

	private $api_key        = null;
	private $api_password   = null;
	private $account_number = null;
	private $test_mode      = null;

	const API_SCHEME = 'tls://';
	const API_HOST   = 'digitalapi.auspost.com.au';
	const API_PORT   = 443;
	const HEADER_EOL = "\r\n";

	private $socket; // socket for communicating to the API

	/**
	 *
	 * @param string $api_key The AusPost API Key
	 * @param string $api_password The AusPost API Password
	 * @param string $account_number The AusPost Account number
	 * @param bool $test_mode Whether to use test mode or not
	 */
	public function __construct($api_key, $api_password, $account_number, $test_mode = false) {
		$this->api_key = $api_key;
		$this->api_password = $api_password;
		$this->account_number = str_pad($account_number, 10, '0', STR_PAD_LEFT); // Ensure the account number is zero padded 10 digits
		$this->test_mode = $test_mode;
	}

	/**
	 * Perform a GetAccounts API call.
	 *
	 * @return Account the account data
	 * @throws Exception
	 */
	public function getAccountDetails() {
		$this->sendGetRequest('accounts/' . $this->account_number);
		$data = $this->convertResponse($this->getResponse()->data);
		$this->closeSocket();

		return new Account($data);
	}

	/**
	 * Perform a Prices/Items API call
	 *
	 * @param mixed $data
	 * @return Quote[]
	 * @throws \Exception
	 */
	public function getQuotes($input) {
		$this->sendPostRequest('prices/items', $input);
		$data = $this->convertResponse($this->getResponse()->data);
		$this->closeSocket();

		$quotes = [];
		dump($data);
		foreach ($data['items'] as $item) {
			foreach ($item['prices'] as $price) {
				if (!array_key_exists($price['product_id'], $quotes)) {
					$quotes[$price['product_id']] = [
						'product_id' => $price['product_id'],
						'product_type' => $price['product_type'],
						'signature_on_delivery_option' => $price['options']['signature_on_delivery_option'],
						'authority_to_leave_option' => $price['options']['authority_to_leave_option'],
						'dangerous_goods_allowed' => $price['options']['dangerous_goods_allowed'],
						'price_inc_gst' => 0,
						'price_exc_gst' => 0,
					];
				}
				$quotes[$price['product_id']]['price_inc_gst'] += $price['bundled_price'];
				$quotes[$price['product_id']]['price_exc_gst'] += $price['bundled_price_ex_gst'];

				if (!$price['options']['signature_on_delivery_option']) {
					$quotes[$price['product_id']]['signature_on_delivery_option'] = false;
				}
				if (!$price['options']['authority_to_leave_option']) {
					$quotes[$price['product_id']]['authority_to_leave_option'] = false;
				}
				if (!$price['options']['authority_to_leave_option']) {
					$quotes[$price['product_id']]['authority_to_leave_option'] = false;
				}
			}
		}
		foreach ($quotes as $key => $data) {
			$quotes[$key] = new Quote($data);
		}
		return $quotes;
	}

	/**
	 * Start a new shipment for lodging or quoting
	 * @return \Cognito\Auspost\Shipment
	 */
	public function newShipment() {
		return new Shipment($this);
	}

	/**
	 * Get the base URL for the api connection
	 *
	 * @return string
	 */
	private function baseUrl() {
		if ($this->test_mode) {
			return '/test/shipping/v1/';
		}
		return '/shipping/v1/';
	}

	/**
	 * Creates a socket connection to the API.
	 *
	 * @throws Exception if the socket cannot be opened
	 */
	private function createSocket() {
		$this->socket = fsockopen(
			self::API_SCHEME . self::API_HOST,
			self::API_PORT,
			$errno,
			$errstr,
			15
		);
		if ($this->socket === false) {
			throw new Exception('Could not connect to Australia Post API: ' . $errstr, $errno);
		}
	}

	/**
	 * Builds the HTTP request headers.
	 *
	 * @param string $request_type    GET/POST/HEAD/DELETE/PUT
	 * @param string $action          the API action component of the URI
	 * @param int    $content_length  if true, content is included in the request
	 * @param bool   $include_account if true, include the account number in the header
	 *
	 * @return array each element is a header line
	 */
	private function buildHttpHeaders($request_type, $action, $content_length = 0, $include_account = false) {
		$a_headers   = array();
		$a_headers[] = $request_type . ' ' . $this->baseUrl() . $action . ' HTTP/1.1';
		$a_headers[] = 'Authorization: ' . 'Basic ' . base64_encode($this->api_key . ':' . $this->api_password);
		$a_headers[] = 'Host: ' . self::API_HOST;
		if ($content_length) {
			$a_headers[] = 'Content-Type: application/json';
			$a_headers[] = 'Content-Length: ' . $content_length;
		}
		$a_headers[] = 'Accept: */*';
		if ($include_account) {
			$a_headers[] = 'Account-Number: ' . $this->account_number;
		}
		$a_headers[] = 'Cache-Control: no-cache';
		$a_headers[] = 'Connection: close';
		return $a_headers;
	}

	/**
	 * Sends an HTTP GET request to the API.
	 *
	 * @param string $action the API action component of the URI
	 *
	 * @throws Exception on error
	 */
	private function sendGetRequest($action) {
		$this->createSocket();
		$headers = $this->buildHttpHeaders('GET', $action);

		if (fwrite(
			$this->socket,
			implode(self::HEADER_EOL, $headers) . self::HEADER_EOL . self::HEADER_EOL
		) === false) {
			throw new Exception('Could not write to Australia Post API');
		}
		fflush($this->socket);
	}

	/**
	 * Sends an HTTP POST request to the API.
	 *
	 * @param string $action the API action component of the URI
	 * @param array  $data   assoc array containing the data to send
	 *
	 * @throws Exception on error
	 */
	private function sendPostRequest($action, $data) {
		$encoded_data = json_encode($data);

		$this->createSocket();
		$headers = $this->buildHttpHeaders('POST', $action, strlen($encoded_data), true);

		if (fwrite(
			$this->socket,
			implode(self::HEADER_EOL, $headers) . self::HEADER_EOL . self::HEADER_EOL
		) === false) {
			throw new Exception('Could not write to Australia Post API');
		}

		if (fwrite($this->socket, $encoded_data) === false) {
			throw new Exception('Could not write to Australia Post API');
		}

		fflush($this->socket);
	}

	/**
	 * Gets the response from the API.
	 *
	 * @return \stdClass
	 */
	private function getResponse() {
		$headers = array();
		$data    = array();
		$currently_reading_headers = true;

		while (!feof($this->socket)) {
			$line = fgets($this->socket);
			if ($currently_reading_headers) {
				$line = trim($line);
				if ($line == '') {
					$currently_reading_headers = false;
				} else {
					$headers[] = $line;
				}
			} else {
				$data[] = $line;
			}
		}

		$response = new \stdClass;
		$response->headers = $headers;
		$response->data = $data;

		return $response;
	}

	/**
	 * Closes the socket.
	 */
	private function closeSocket() {
		fclose($this->socket);
		$this->socket = false;
	}

	/**
	 * Convert the lines of response data into an associative array.
	 *
	 * @param array $data lines of response data
	 * @return array associative array
	 */
	private function convertResponse($data) {
		return json_decode(implode("\n", $data), true);
	}
}
