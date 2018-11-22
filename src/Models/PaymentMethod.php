<?php

namespace Ptuchik\Billing\Models;

/**
 * Class PaymentMethod
 * @package Ptuchik\Billing\Models
 */
class PaymentMethod
{
    public $token;
    public $type = 'credit_card';
    public $default = false;
    public $gateway;
    public $description;
    public $imageUrl;
    public $holder;
    public $country;
    public $zip;
    public $additional = [];

    /**
     * PaymentMethod constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        if (!is_null($token = array_get($data, 'token'))) {
            $this->token = $token;
        }

        if (!is_null($type = array_get($data, 'type'))) {
            $this->type = $type;
        }

        if (!is_null($default = array_get($data, 'default'))) {
            $this->default = !empty($default);
        }

        if (!is_null($gateway = array_get($data, 'gateway'))) {
            $this->gateway = $gateway;
        }

        if (!is_null($description = array_get($data, 'description'))) {
            $this->description = $description;
        }

        if (!is_null($holder = array_get($data, 'holder'))) {
            $this->holder = $holder;
        }

        if (!is_null($country = array_get($data, 'country'))) {
            $this->country = $country;
        }

        if (!is_null($zip = array_get($data, 'zip'))) {
            $this->zip = $zip;
        }

        if (!is_null($additional = array_get($data, 'additional'))) {
            $this->additional = array_wrap($additional);
        }
    }

    /**
     * Convert instance to array
     * @return array
     */
    public function toArray()
    {
        return [
            'token'       => $this->token,
            'type'        => $this->type,
            'default'     => $this->default,
            'gateway'     => $this->gateway,
            'description' => $this->description,
            'imageUrl'    => $this->imageUrl,
            'holder'      => $this->holder,
            'country'     => $this->country,
            'zip'         => $this->zip,
            'additional'  => $this->additional
        ];
    }

    /**
     * Convert instance to JSON
     * @return false|string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
}