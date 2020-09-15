<?php

namespace Cognito\Auspost;

/**
 * An address
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property string $type
 * @property string $name
 * @property string $business_name
 * @property string[] $lines
 * @property string $suburb
 * @property string $state
 * @property string $postcode
 * @property string $phone
 * @property string $email
 * @property string $country
 */
class Address {

    public $type          = '';
    public $name          = '';
    public $business_name = '';
    public $lines         = [];
    public $suburb        = '';
    public $state         = '';
    public $postcode      = '';
    public $phone         = '';
    public $email         = '';
    public $country       = 'AU';
    public $raw_details = [];

    public function __construct($details = []) {
        $this->raw_details = $details;
        foreach ($details as $key => $data) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $data;
        }
    }
}
