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
 * @property string $product_id The AusPost product to use for this shipment
 * @property string $shipment_id The AusPost generated id when lodged
 * @property \DateTime $shipment_lodged_at The time the shipment was lodged
 */
class Shipment {

    private $_auspost;
    public $shipment_reference;
    public $customer_reference_1 = '';
    public $customer_reference_2 = '';
    public $email_tracking_enabled = true;
    public $from;
    public $to;
    public $parcels;
    public $delivery_instructions = '';

    public $product_id;
    public $shipment_id;
    public $shipment_lodged_at;

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
        $request = [
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
            $request['items'][] = $item;
        }
        return $this->_auspost->getQuotes($request);
    }

    public function lodgeShipment() {
        // Determine if Domestic or International
        $domestic_shipping = $this->to->country == 'AU';

        if ($domestic_shipping) {
            // Lodge domestic shipment
        } else {
            // Lodge international shipment
        }
        $request = [
            'shipment_reference' => $this->shipment_reference,
            'customer_reference_1' => $this->customer_reference_1,
            'customer_reference_2' => $this->customer_reference_2,
            'email_tracking_enabled' => $this->email_tracking_enabled,
            'from' => [
                'name'          => $this->from->name,
                'business_name' => $this->from->business_name,
                'lines'         => $this->from->lines,
                'suburb'        => $this->from->suburb,
                'state'         => $this->from->state,
                'postcode'      => $this->from->postcode,
                'country'       => $this->from->country,
                'phone'         => $this->from->phone,
                'email'         => $this->from->email,
            ],
            'to' => [
                'name'          => $this->to->name,
                'business_name' => $this->to->business_name,
                'lines'         => $this->to->lines,
                'suburb'        => $this->to->suburb,
                'state'         => $this->to->state,
                'postcode'      => $this->to->postcode,
                'country'       => $this->to->country,
                'phone'         => $this->to->phone,
                'email'         => $this->to->email,
                'delivery_instructions' => $this->delivery_instructions,
            ],
            'items' => [],
        ];
        foreach ($this->parcels as $parcel) {
            $item = [
                'item_reference' => $parcel->item_reference,
                'product_id'     => $this->product_id,
                'length'         => $parcel->length,
                'height'         => $parcel->height,
                'width'          => $parcel->width,
                'weight'         => $parcel->weight,
                'authority_to_leave' => $parcel->authority_to_leave,
                'allow_partial_delivery' => $parcel->allow_partial_delivery,
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
            $request['items'][] = $item;
        }

        $response = $this->_auspost->shipments(['shipments' => $request]);

        foreach ($response['shipments'] as $shipment) {
            $this->shipment_id = $shipment['shipment_id'];
            $this->shipment_lodged_at = new \DateTime($shipment['shipment_creation_date']);
            foreach ($shipment['items'] as $item) {
                foreach ($this->parcels as $key => $parcel) {
                    if ($parcel->item_reference != $item['item_reference']) {
                        continue;
                    }
                    $this->parcels[$key]->item_id = $item['item_id'];
                    $this->parcels[$key]->tracking_article_id = $item['tracking_details']['article_id'];
                    $this->parcels[$key]->tracking_consigment_id = $item['tracking_details']['consignment_id'];
                }
            }
        }
    }

    /**
     * Get the labels for this shipment
     * @param LabelType $labelType
     * @return string url to label
     * @throws \Exception
     */
    public function getLabel($labelType) {
        return $this->_auspost->getLabels([$this->shipment_id], $labelType);
    }

}
