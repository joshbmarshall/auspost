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

		foreach ($data['items'] as $item) {
			if (array_key_exists('errors', $item)) {
				foreach ($item['errors'] as $error) {
					throw new Exception($error['message']);
				}
			}
			foreach ($item['prices'] as $price) {
				if (!array_key_exists($price['product_id'], $quotes)) {
					$signature_on_delivery_option = false;
					$authority_to_leave_option = false;
					$dangerous_goods_allowed = false;
					if (array_key_exists('signature_on_delivery_option', $price['options'])) {
						$signature_on_delivery_option = $price['options']['signature_on_delivery_option'];
					}
					if (array_key_exists('authority_to_leave_option', $price['options'])) {
						$authority_to_leave_option = $price['options']['authority_to_leave_option'];
					}
					if (array_key_exists('dangerous_goods_allowed', $price['options'])) {
						$dangerous_goods_allowed = $price['options']['dangerous_goods_allowed'];
					}
					$quotes[$price['product_id']] = [
						'product_id' => $price['product_id'],
						'product_type' => $price['product_type'],
						'signature_on_delivery_option' => $signature_on_delivery_option,
						'authority_to_leave_option' => $authority_to_leave_option,
						'dangerous_goods_allowed' => $dangerous_goods_allowed,
						'price_inc_gst' => 0,
						'price_exc_gst' => 0,
					];
				}
				if (array_key_exists('bundled_price', $price)) {
					$quotes[$price['product_id']]['price_inc_gst'] += $price['bundled_price'];
					$quotes[$price['product_id']]['price_exc_gst'] += $price['bundled_price_ex_gst'];
				} else {
					$quotes[$price['product_id']]['price_inc_gst'] += $price['calculated_price'];
					$quotes[$price['product_id']]['price_exc_gst'] += $price['calculated_price_ex_gst'];
				}

				if (!$signature_on_delivery_option) {
					$quotes[$price['product_id']]['signature_on_delivery_option'] = false;
				}
				if (!$authority_to_leave_option) {
					$quotes[$price['product_id']]['authority_to_leave_option'] = false;
				}
				if (!$authority_to_leave_option) {
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
	 * Perform a Shipments API call
	 *
	 * @param mixed $data
	 * @return array
	 * @throws \Exception
	 */
	public function shipments($input) {
		$this->sendPostRequest('shipments', $input);
		$data = $this->convertResponse($this->getResponse()->data);
		$this->closeSocket();

		if (array_key_exists('errors', $data)) {
			foreach ($data['errors'] as $error) {
				throw new Exception($error['message']);
			}
		}

		return $data;
	}

	/**
	 * Get all labels for the shipments referenced by id
	 * @param string[] $shipment_ids
	 * @param LabelType $label_type
	 * @return string url to label file
	 */
	public function getLabels($shipment_ids, $label_type) {
		$group_template = [
			'layout' => $label_type->layout_type,
			'branded' => $label_type->branded,
			'left_offset' => $label_type->left_offset,
			'top_offset' => $label_type->top_offset,
		];
		$groups = [];
		foreach ([
			'Parcel Post',
			'Express Post',
			'StarTrack',
			'Startrack Courier',
			'On Demand',
			'International',
			'Commercial',
		] as $group) {
			$groups[] = array_merge($group_template, [
				'group' => $group,
			]);
		}

		$shipments = [];
		foreach ($shipment_ids as $shipment_id) {
			$shipments[] = [
				'shipment_id' => $shipment_id,
			];
		}

		$request = [
			'wait_for_label_url' => true,
			'preferences' => [
				'type' => 'PRINT',
				'format' => $label_type->format,
				'groups' => $groups,
			],
			'shipments' => $shipments,
		];

		$this->sendPostRequest('labels', $request);
		$data = $this->convertResponse($this->getResponse()->data);
		$this->closeSocket();

		if (array_key_exists('errors', $data)) {
			foreach ($data['errors'] as $error) {
				throw new Exception($error['message']);
			}
		}
		foreach ($data['labels'] as $label) {
			return $label['url'];
		}

		return '';
	}

	/**
	 * Create an order and return a manifest
	 * @param string[] $shipment_ids
	 * @return string url to ? file
	 */
	public function createOrder($shipment_ids) {
		$request = [
			'shipments' => [],
		];
		foreach ($shipment_ids as $shipment_id) {
			$request['shipments'] = [
				'shipment_id' => $shipment_id,
			];
		}

		$this->sendPutRequest('orders', $request);
		$data = $this->convertResponse($this->getResponse()->data);
		$this->closeSocket();

		if (array_key_exists('errors', $data)) {
			foreach ($data['errors'] as $error) {
				throw new Exception($error['message']);
			}
		}

		// Get the url to the manifest pdf
		$this->sendGetRequest('accounts/' . $this->account_number . '/orders/' . $data['order']['order_id'] . '/summary');
		$data['order']['manifest_pdf'] = $this->getResponse()->data;
		$summarydata = $this->convertResponse($data['order']['manifest_pdf']);
		$this->closeSocket();

		if (is_array($summarydata) && array_key_exists('errors', $summarydata)) {
			foreach ($summarydata['errors'] as $error) {
				throw new Exception($error['message']);
			}
		}

		return new Order($data['order']);
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
	 * @param string $type   POST or PUT
	 *
	 * @throws Exception on error
	 */
	private function sendPostRequest($action, $data, $type = 'POST') {
		$encoded_data = json_encode($data);

		$this->createSocket();
		$headers = $this->buildHttpHeaders($type, $action, strlen($encoded_data), true);

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
	 * Sends an HTTP PUT request to the API.
	 *
	 * @param string $action the API action component of the URI
	 * @param array  $data   assoc array containing the data to send
	 *
	 * @throws Exception on error
	 */
	private function sendPutRequest($action, $data) {
		return $this->sendPostRequest($action, $data, 'PUT');
	}

	/**
	 * Gets the response from the API.
	 *
	 * @return \stdClass
	 */
	private function getResponse() {
		$headers = array();
		$data    = '';
		$currently_reading_headers = true;

		while (!feof($this->socket)) {
			if ($currently_reading_headers) {
				$line = fgets($this->socket);
				$line = trim($line);
				if ($line == '') {
					$currently_reading_headers = false;
				} else {
					$headers[] = $line;
				}
			} else {
				$data .= fread($this->socket, 4096);
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
		return json_decode($data, true);
	}
}
