<?php

namespace Cognito\Auspost;

/**
 * Definition of a Parcel
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property string $product_id The Australia Post product / shipment type to send this parcel
 * @property string $item_reference
 * @property float $length The Length of this parcel in centimetres
 * @property float $width The Width of this parcel in centimetres
 * @property float $height The Height of this parcel in centimetres
 * @property float $weight The Weight of this parcel in kilograms
 * @property float $value The coverage price for insurance on this parcel
 * @property bool $contains_dangerous_goods
 * @property bool $authority_to_leave
 * @property bool $allow_partial_delivery
 * @property string $packaging_type
 * @property string $atl_number
 * @property array $features
 */
class Parcel {

    public $product_id = '';
    public $item_reference = '';
    public $length;
    public $height;
    public $width;
    public $weight;
    public $value;
    public $contains_dangerous_goods = false;
    public $authority_to_leave = false;
    public $allow_partial_delivery = true;
    public $packaging_type;
    public $atl_number;
    public $features = [];

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
