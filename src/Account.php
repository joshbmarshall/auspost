<?php

namespace Cognito\Auspost;

/**
 * Read-only Account details
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property string $account_number
 * @property string $name
 * @property \DateTime $valid_from
 * @property \DateTime $valid_to
 * @property bool $expired
 * @property Address[] $addresses
 * @property string $merchant_location_id
 * @property bool $credit_blocked
 */
class Account {

    public $account_number;
    public $name;
    public $valid_from;
    public $valid_to;
    public $expired;
    public $addresses;
    public $merchant_location_id;
    public $credit_blocked;

    public function __construct($details) {
        foreach ($details as $key => $data) {
            if ($key == 'addresses') {
                $addresses = [];
                foreach ($data as $address_data) {
                    $addresses[] = new Address($address_data);
                }
                $this->$key = $addresses;
            } else if ($key == 'valid_from' || $key == 'valid_to') {
                $this->$key = new \DateTime($data);
            } else {
                $this->$key = $data;
            }
        }
    }
}
