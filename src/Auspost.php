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
	private $raw_account    = null;
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
		$this->raw_account = $account_number;
		$this->account_number = str_pad($account_number, 10, '0', STR_PAD_LEFT); // Ensure the account number is zero padded 10 digits
		$this->test_mode = $test_mode;
	}

	/**
	 * Flags the Auspost instance as StarTrack
	 *
	 * @return self
	 */
	public function useStarTrack() {
		$this->account_number = $this->raw_account;
		return $this;
	}

	/**
	 * Perform a GetAccounts API call.
	 *
	 * @return Account the account data
	 * @throws Exception
	 */
	public function getAccountDetails() {
		$data = $this->sendGetRequest('accounts/' . $this->account_number, [], false);

		return new Account($data);
	}

	/**
	 * Get my address from the account details
	 *
	 * @return \Cognito\Auspost\Address|false
	 * @throws \Exception
	 */
	public function getMerchantAddress() {
		$account = $this->getAccountDetails();
		foreach ($account->addresses as $address) {
			if ($address->type == 'MERCHANT_LOCATION') {
				return $address;
			}
		}
		return false;
	}

	public function getFuelSurchargeInclusiveQuote($product_id, $input) {
		try {
			$items = [];
			if (!isset($input['from']['state'])) {
				throw new \Exception('Need State to get quote');
			}
			if (!isset($input['to']['state'])) {
				throw new \Exception('Need State to get quote');
			}
			foreach ($input['items'] as $item) {
				$items[] = [
					'product_id' => $product_id,
					'length' => $item['length'],
					'height' => $item['height'],
					'width' => $item['width'],
					'weight' => $item['weight'],
				];
			}
			$request = [
				'shipments' => [
					'from' => $input['from'],
					'to' => $input['to'],
					'items' => $items,
				],
			];
			$data = $this->sendPostRequest('prices/shipments', $request);
			$price_inc_gst = 0;
			$price_exc_gst = 0;
			foreach ($data['shipments'] as $shipment) {
				$price_exc_gst += $shipment['shipment_summary']['total_cost_ex_gst'];
				$price_inc_gst += $shipment['shipment_summary']['total_cost'];
			}
			return [
				'price_inc_gst' => $price_inc_gst,
				'price_exc_gst' => $price_exc_gst,
			];
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Perform a Prices/Items API call
	 *
	 * @param mixed $data
	 * @return Quote[]
	 * @throws \Exception
	 */
	public function getQuotes($input) {
		$data = $this->sendPostRequest('prices/items', $input);

		if (array_key_exists('errors', $data)) {
			foreach ($data['errors'] as $error) {
				throw new Exception($error['message']);
			}
		}
		$quotes = [];
		$is_subsequent_item = false;
		foreach ($data['items'] as $item) {
			if (array_key_exists('errors', $item)) {
				foreach ($item['errors'] as $error) {
					throw new Exception($error['message']);
				}
			}
			foreach ($item['prices'] as $price) {
				$fuel_surcharge_prices = $this->getFuelSurchargeInclusiveQuote($price['product_id'], $input);

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

				if ($fuel_surcharge_prices) {
					$quotes[$price['product_id']]['price_inc_gst'] = $fuel_surcharge_prices['price_inc_gst'];
					$quotes[$price['product_id']]['price_exc_gst'] = $fuel_surcharge_prices['price_exc_gst'];
				} else if (array_key_exists('bundled_price', $price) && $is_subsequent_item) {
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
			$is_subsequent_item = true;
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
		$data = $this->sendPostRequest('shipments', $input);

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

		$data = $this->sendPostRequest('labels', $request);

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
	 * @return Order
	 */
	public function createOrder($shipment_ids) {
		$request = [
			'shipments' => [],
		];
		foreach ($shipment_ids as $shipment_id) {
			$request['shipments'][] = [
				'shipment_id' => $shipment_id,
			];
		}

		$data = $this->sendPutRequest('orders', $request);
		if (!is_array($data)) {
			return new Order([
				'order_id' => 'None',
				'creation_date' => new \DateTime(),
				'manifest_pdf' => $data,
			]);
		}

		if (array_key_exists('errors', $data)) {
			foreach ($data['errors'] as $error) {
				throw new Exception($error['message']);
			}
		}

		// Get the url to the manifest pdf
		$data['order']['manifest_pdf'] = $this->sendGetRequest('accounts/' . $this->account_number . '/orders/' . $data['order']['order_id'] . '/summary');
		$summarydata = $this->convertResponse($data['order']['manifest_pdf']);

		if (is_array($summarydata) && array_key_exists('errors', $summarydata)) {
			foreach ($summarydata['errors'] as $error) {
				throw new Exception($error['message']);
			}
		}

		return new Order($data['order']);
	}

	/**
	 * Delete a shipment by id
	 * @param string $shipment_id
	 * @return bool
	 */
	public function deleteShipment($shipment_id) {
		$data = $this->sendDeleteRequest('shipments/' . $shipment_id, null);
		if (is_array($data) && array_key_exists('errors', $data)) {
			foreach ($data['errors'] as $error) {
				throw new Exception($error['message']);
			}
		}

		return true;
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
	 * Sends an HTTP GET request to the API.
	 *
	 * @param string $action the API action component of the URI
	 *
	 * @throws Exception on error
	 */
	private function sendGetRequest($action, $data = [], $include_account = true) {
		return $this->sendRequest($action, $data, 'GET', $include_account);
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
		return $this->sendRequest($action, $data, 'POST');
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
		return $this->sendRequest($action, $data, 'PUT');
	}

	/**
	 * Sends an HTTP DELETE request to the API
	 *
	 * @param string $action
	 * @param mixed $data
	 * @return void
	 * @throws \Exception
	 */
	private function sendDeleteRequest($action, $data) {
		return $this->sendRequest($action, $data, 'DELETE');
	}

	/**
	 * Sends an HTTP POST request to the API.
	 *
	 * @param string $action the API action component of the URI
	 * @param array  $data   assoc array containing the data to send
	 * @param string $type   GET, POST, PUT, DELETE
	 *
	 * @throws Exception on error
	 */
	private function sendRequest($action, $data, $type, $include_account = true) {
		$encoded_data = $data ? json_encode($data) : '';
		$headers = [
			'Authorization: ' . 'Basic ' . base64_encode($this->api_key . ':' . $this->api_password),
		];
		if ($include_account) {
			$headers[] = 'Account-Number: ' . $this->account_number;
		}

		$url = 'https://' . self::API_HOST . $this->baseUrl() . $action;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($type == 'PUT' || $type == 'POST') {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded_data);
			$headers[] = 'Content-Type: application/json';
		}
		if ($type == 'PUT') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		}
		if ($type == 'DELETE') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($ch);

		if (!$response) {
			return false;
		}
		return $this->convertResponse($response);
	}

	/**
	 * Convert the lines of response data into an associative array.
	 *
	 * @param string $data lines of response data
	 * @return array associative array
	 */
	private function convertResponse($data) {
		$response = json_decode($data, true);
		if ($data && is_null($response)) {
			return $data; // Could be an inline pdf
		}
		return $response;
	}
}
