# Australia Post API

Interact with the AusPost API

## Installation

Installation is very easy with composer:

	composer require cognito/auspost

## Setup

Get a business account at Australia Post and request API access

## Usage

	<?php
	$auspost = new \Cognito\Auspost\Auspost('Your API Key', 'Your API Password', 'Your Account Number');
	$accounts = $auspost->getAccountDetails();
