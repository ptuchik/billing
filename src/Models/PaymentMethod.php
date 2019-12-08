<?php

namespace Ptuchik\Billing\Models;

use Illuminate\Support\Arr;
use Ptuchik\Billing\Constants\PaymentMethods;
use Ptuchik\Billing\Factory;

/**
 * Class PaymentMethod
 * @package Ptuchik\Billing\Models
 */
class PaymentMethod
{
    public $token;
    public $type;
    public $last4;
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
        $this->type = Factory::getClass(PaymentMethods::class)::CREDIT_CARD;

        if (!is_null($token = Arr::get($data, 'token'))) {
            $this->token = $token;
        }

        if (!is_null($type = Arr::get($data, 'type'))) {
            $this->type = $type;
        }

        if (!is_null($last4 = Arr::get($data, 'last4'))) {
            $this->last4 = $last4;
        }

        if (!is_null($default = Arr::get($data, 'default'))) {
            $this->default = !empty($default);
        }

        if (!is_null($gateway = Arr::get($data, 'gateway'))) {
            $this->gateway = $gateway;
        }

        if (!is_null($description = Arr::get($data, 'description'))) {
            $this->description = $description;
        }

        if (!is_null($holder = Arr::get($data, 'holder'))) {
            $this->holder = $holder;
        }

        if (!is_null($country = Arr::get($data, 'country'))) {
            $this->country = $country;
        }

        if (!is_null($zip = Arr::get($data, 'zip'))) {
            $this->zip = $zip;
        }

        if (!is_null($additional = Arr::get($data, 'additional'))) {
            $this->additional = Arr::wrap($additional);
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