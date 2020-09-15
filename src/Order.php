<?php

namespace Cognito\Auspost;

/**
 * An Order, returned from a pricing request
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property string $order_id
 * @property \DateTime $creation_date
 * @property $manifest_pdf
 */
class Order {

    public $order_id;
    public $creation_date;
    public $manifest_pdf;
    public $raw_details = [];

    public function __construct($details) {
        $this->raw_details = $details;
        foreach ($details as $key => $data) {
            if ($key == 'order_creation_date') {
                $this->creation_date = new \DateTime($data);
                continue;
            }
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $data;
        }
    }
}
