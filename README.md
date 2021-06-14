# Australia Post API

Interact with the AusPost API

## Installation

Installation is very easy with composer:

    composer require cognito/auspost

## Setup

Get a business account at Australia Post and request API access

## Usage

```
<?php
	$auspost = new \Cognito\Auspost\Auspost('Your API Key', 'Your API Password', 'Your Account Number', $testmode);

	// Get your account details
	$account = $auspost->getAccountDetails();

	// Create a shipment

	$shipment = $auspost->newShipment()
		->setFrom(new \Cognito\Auspost\Address([
			'first_name' => 'Joe',
			'last_name' => 'Tester',
			// OR 'name' => 'Joe Tester',
			'lines' => [
				'11 MyStreetname Court',
			],
			'suburb' => 'MySuburb',
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
			'suburb' => 'HerSuburb',
			'state' => 'NSW',
			'postcode' => '2430',
			'country' => 'AU',
			'phone' => '035555XXXX',
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

	foreach ($itemQuotes as $quote) {
		var_dump($quote->product_id);
		var_dump($quote->product_type);
		var_dump($quote->price_inc_gst);
	}

	// Set details about the shipment and lodge it
	$shipment->shipment_reference = 'OurInternalID';
	$shipment->customer_reference_1 = 'INV #12345';
	$shipment->customer_reference_2 = '';
	$shipment->product_id = '7E55'; // The AusPost product returned in the Quote
	$shipment->delivery_instructions = 'Leave in a dry place out of the sun';

	$shipment->lodgeShipment();

	var_dump($shipment->shipment_id);

	// Print the labels for all the parcels in a number of shipments
	$shipment_ids = [
		'NIUK0Eavr1EAAAF0BfAdXIsx',
		'i9MK0Eav1ywAAAF0t8kdXI84',
		$shipment->shipment_id,
	];
	$label_pdf = $auspost->getLabels($shipment_ids, new \Cognito\Auspost\LabelType([
		'layout_type' => \Cognito\Auspost\LabelType::LAYOUT_A4_FOUR_PER_PAGE,
		'branded' => false,
	]));

	var_dump($label_pdf); // It's the url to the pdf

	// Create a manifest for the shipments
	$order = $auspost->createOrder([
		'NIUK0Eavr1EAAAF0BfAdXIsx',
		'i9MK0Eav1ywAAAF0t8kdXI84',
	]);

	// Output the manifest pdf to the screen
	header('Content-Type: application/pdf');
	die($order->manifest_pdf);
```
