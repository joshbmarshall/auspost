<?php

class AusPostTest extends \PHPUnit\Framework\TestCase {

	private const API_KEY = 'Insert your api key here';
	private const API_PASSWORD = 'Insert your api password here';
	private const API_ACCOUNT_NUMBER = 'Insert your api account number here';

	private function getAuspost() {
		return new \Cognito\Auspost\Auspost(self::API_KEY, self::API_PASSWORD, self::API_ACCOUNT_NUMBER, true);
	}

	public function testGetQuote() {
		$auspost = $this->getAuspost();
		$account = $auspost->getAccountDetails();
		$this->assertSame(self::API_ACCOUNT_NUMBER, $account->account_number);
	}

	public function testShipment() {
		$auspost = $this->getAuspost();
		$shipment = $auspost->newShipment()
			->setFrom(new \Cognito\Auspost\Address([
				'first_name' => 'Joe',
				'last_name' => 'Tester',
				// OR 'name' => 'Joe Tester',
				'lines' => [
					'11 MyStreetname Court',
				],
				'suburb' => 'Kallangur',
				'state' => 'QLD',
				'postcode' => '4503',
				'country' => 'AU',
			]))
			->setTo(new \Cognito\Auspost\Address([
				'first_name' => 'Mary',
				'last_name' => 'Tester',
				'lines' => [
					'10 ReceiverStreetname St',
				],
				'suburb' => 'Taree',
				'state' => 'NSW',
				'postcode' => '2430',
				'country' => 'AU',
				'phone' => '0355550000',
				'email' => 'mary@XXXXX.com.au',
			]))
			->addParcel(new \Cognito\Auspost\Parcel([
				'item_reference' => 'pkg1',
				'length' => 5,
				'height' => 4,
				'width' => 45,
				'weight' => 0.55,
				'value' => 200,
			]))
			->addParcel(new \Cognito\Auspost\Parcel([
				'item_reference' => 'pkg2',
				'length' => 12,
				'height' => 12,
				'width' => 20,
				'weight' => 1.55,
				'value' => 50,
			]));
		// Get costing for the shipment for the various AusPost products available
		$itemQuotes = $shipment->getQuotes();

		$this->assertSame(2, count($itemQuotes));

		$counter = 0;
		foreach ($itemQuotes as $quote) {
			$counter++;
			switch ($counter) {
				case 1:
					$this->assertSame('EXPRESS POST + SIGNATURE', $quote->product_type);
					break;
				case 2:
					$this->assertSame('PARCEL POST + SIGNATURE', $quote->product_type);
					break;
			}
		}

		// Set details about the shipment and lodge it
		$shipment->shipment_reference = 'OurInternalID';
		$shipment->customer_reference_1 = 'INV #12345';
		$shipment->customer_reference_2 = '';
		$shipment->product_id = '7E55'; // The AusPost product returned in the Quote
		$shipment->delivery_instructions = 'Leave in a dry place out of the sun';

		$this->assertEmpty($shipment->shipment_id);

		$shipment->lodgeShipment();

		$this->assertNotEmpty($shipment->shipment_id);

		// Print the labels for all the parcels in a number of shipments
		$shipment_ids = [
			$shipment->shipment_id,
		];
		$label_pdf = $auspost->getLabels($shipment_ids, new \Cognito\Auspost\LabelType([
			'layout_type' => \Cognito\Auspost\LabelType::LAYOUT_A4_FOUR_PER_PAGE,
			'branded' => false,
		]));

		$this->assertNotEmpty($label_pdf);

		// Create a manifest for the shipments
		$order = $auspost->createOrder($shipment_ids);
		$this->assertNotEmpty($order->manifest_pdf);
	}

	public function testDeleteShipment() {
		$auspost = $this->getAuspost();
		$shipment = $auspost->newShipment()
			->setFrom(new \Cognito\Auspost\Address([
				'first_name' => 'Joe',
				'last_name' => 'Tester',
				// OR 'name' => 'Joe Tester',
				'lines' => [
					'11 MyStreetname Court',
				],
				'suburb' => 'Kallangur',
				'state' => 'QLD',
				'postcode' => '4503',
				'country' => 'AU',
			]))
			->setTo(new \Cognito\Auspost\Address([
				'first_name' => 'Mary',
				'last_name' => 'Tester',
				'lines' => [
					'10 ReceiverStreetname St',
				],
				'suburb' => 'Taree',
				'state' => 'NSW',
				'postcode' => '2430',
				'country' => 'AU',
				'phone' => '0355550000',
				'email' => 'mary@XXXXX.com.au',
			]))
			->addParcel(new \Cognito\Auspost\Parcel([
				'item_reference' => 'pkg1',
				'length' => 5,
				'height' => 4,
				'width' => 45,
				'weight' => 0.55,
				'value' => 200,
			]))
			->addParcel(new \Cognito\Auspost\Parcel([
				'item_reference' => 'pkg2',
				'length' => 12,
				'height' => 12,
				'width' => 20,
				'weight' => 1.55,
				'value' => 50,
			]));
		// Get costing for the shipment for the various AusPost products available
		$itemQuotes = $shipment->getQuotes();

		$this->assertSame(2, count($itemQuotes));

		$counter = 0;
		foreach ($itemQuotes as $quote) {
			$counter++;
			switch ($counter) {
				case 1:
					$this->assertSame('EXPRESS POST + SIGNATURE', $quote->product_type);
					break;
				case 2:
					$this->assertSame('PARCEL POST + SIGNATURE', $quote->product_type);
					break;
			}
		}

		// Set details about the shipment and lodge it
		$shipment->shipment_reference = 'OurInternalID';
		$shipment->customer_reference_1 = 'INV #12345';
		$shipment->customer_reference_2 = '';
		$shipment->product_id = '7E55'; // The AusPost product returned in the Quote
		$shipment->delivery_instructions = 'Leave in a dry place out of the sun';

		$this->assertEmpty($shipment->shipment_id);

		$shipment->lodgeShipment();

		$this->assertNotEmpty($shipment->shipment_id);

		// Print the labels for all the parcels in a number of shipments
		$shipment_ids = [
			$shipment->shipment_id,
		];
		$label_pdf = $auspost->getLabels($shipment_ids, new \Cognito\Auspost\LabelType([
			'layout_type' => \Cognito\Auspost\LabelType::LAYOUT_A4_FOUR_PER_PAGE,
			'branded' => false,
		]));

		$this->assertNotEmpty($label_pdf);

		// Delete the shipment
		$result = $auspost->deleteShipment($shipment->shipment_id);
		$this->assertTrue($result);

		// Deleted shipment doesnt give labels
		$this->expectExceptionMessage('does not exist');
		$label_pdf = $auspost->getLabels($shipment_ids, new \Cognito\Auspost\LabelType([
			'layout_type' => \Cognito\Auspost\LabelType::LAYOUT_A4_FOUR_PER_PAGE,
			'branded' => false,
		]));
	}
}
