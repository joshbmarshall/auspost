<?php

namespace Cognito\Auspost;

/**
 * A Quote, returned from a pricing request
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property string $product_id The Australia Post product / shipment type to send this parcel
 * @property string $product_type The Australia Post name of the product
 * @property bool $signature_on_delivery_option
 * @property bool $authority_to_leave_option
 * @property bool $dangerous_goods_allowed
 * @property float $price_inc_gst
 * @property float $price_exc_gst
 */
class Quote {

    public $product_id;
    public $product_type;
    public $signature_on_delivery_option = false;
    public $authority_to_leave_option = false;
    public $dangerous_goods_allowed = false;
    public $price_inc_gst;
    public $price_exc_gst;
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
