<?php

namespace Cognito\Auspost;

/**
 * An address
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property string $type
 * @property string $first_name
 * @property string $last_name
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
    public $first_name    = '';
    public $last_name     = '';
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
        if (!$this->name) {
            $this->name = trim($this->first_name . ' ' . $this->last_name);
        }
        if (!$this->first_name) {
            $parts = explode(' ', $this->name, 2);
            if (count($parts) > 1) {
                $this->first_name = $parts[0];
                $this->last_name = $parts[1];
            } else {
                $this->first_name = $this->name;
                $this->last_name = $this->name;
            }
        }
    }
}
