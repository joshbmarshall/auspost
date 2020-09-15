<?php

namespace Cognito\Auspost;

/**
 * Read-only Postage Product details
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property string $type
 * @property string[] $lines
 * @property string $suburb
 * @property string $state
 * @property string $postcode
 * @property string $country
 */
class PostageProduct {

    public $type;
    public $group;
    public $product_id;
    public $contract;
    public $authority_to_leave_threshold;
    public $features;
    public $options;
    public $shipment_features;
    public $raw_details = [];

    public function __construct($details) {
        $this->raw_details = $details;
        foreach ($details as $key => $data) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $data;
        }
    }
}
