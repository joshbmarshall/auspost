<?php

namespace Cognito\Auspost;

/**
 * A shipment, made up of one or more parcels
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property Auspost $_auspost
 * @property string $shipment_reference
 * @property string $customer_reference_1
 * @property string $customer_reference_2
 * @property bool $email_tracking_enabled
 * @property Address $to
 * @property Address $from
 * @property Parcel[] $parcels
 */
class Shipment {

    private $_auspost;
    public $shipment_reference;
    public $customer_reference_1;
    public $customer_reference_2;
    public $email_tracking_enabled;
    public $from;
    public $to;
    public $parcels;

    public function __construct($api) {
        $this->_auspost = $api;
    }

    /**
     * Add the To address
     * @param Address $data The address to deliver to
     * @return $this
     */
    public function setTo($data) {
        $this->to = $data;
        return $this;
    }
    /**
     * Add the From address
     * @param Address $data The address to send from
     * @return $this
     */
    public function setFrom($data) {
        $this->from = $data;
        return $this;
    }

    public function addParcel($data) {
        $this->parcels[] = $data;
        return $this;
    }

    /**
     *
     * @return \Cognito\Auspost\Quote[]
     * @throws \Exception
     */
    public function getQuotes() {
        $items = [
            'from' => [
                'postcode' => $this->from->postcode,
                'country' => $this->from->country,
            ],
            'to' => [
                'postcode' => $this->to->postcode,
                'country' => $this->to->country,
            ],
            'items' => [],
        ];
        foreach ($this->parcels as $parcel) {
                $item = [
                'item_reference' => $parcel->item_reference,
                'length' => $parcel->length,
				'height' => $parcel->height,
				'width' => $parcel->width,
				'weight' => $parcel->weight,
            ];
            if ($parcel->value) {
                $item['features'] = [
                    'TRANSIT_COVER' => [
                        'attributes' => [
                            'cover_amount' => $parcel->value,
                        ]
                    ]
                ];
            }
            $items['items'][] = $item;
        }
        return $this->_auspost->getQuotes($items);
    }
}
