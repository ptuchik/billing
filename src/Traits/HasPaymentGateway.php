<?php

namespace Ptuchik\Billing\src\Traits;

use Currency;
use Illuminate\Support\Arr;
use Ptuchik\Billing\Contracts\PaymentGateway;
use Ptuchik\Billing\Exceptions\BillingException;
use Request;

/**
 * Trait HasPaymentGateway
 *
 * @package Ptuchik\Billing\src\Traits
 */
trait HasPaymentGateway
{
    use HasPaymentProfiles, HasPaymentMethods;

    /**
     * Payment gateway
     *
     * @var \Ptuchik\Billing\Contracts\PaymentGateway
     */
    protected $gateway;

    /**
     * Payment gateway name
     *
     * @var string
     */
    protected $gatewayName;

    /**
     * Payment gateway setter
     *
     * @param $value
     */
    public function setPaymentGatewayAttribute($value)
    {
        $this->attributes['payment_gateway'] = $this->gatewayName = $value;
    }

    /**
     * Payment gateway attribute getter
     *
     * @param      $value
     *
     * @return string
     */
    public function getPaymentGatewayAttribute($value)
    {
        if (is_null($this->gatewayName)) {

            // If user has no gateway, get default gateway
            $value = empty($value) ? config('ptuchik-billing.default_gateway') : $value;

            $this->paymentGateway = $this->checkGatewayAvailability($value);
        }

        return $this->gatewayName;
    }

    /**
     * Get user's payment gateway
     *
     * @param null $gateway
     * @param bool $checkAvailability
     *
     * @return \Ptuchik\Billing\Contracts\PaymentGateway
     * @throws \Ptuchik\Billing\Exceptions\BillingException
     */
    public function getPaymentGateway($gateway = null, $checkAvailability = true)
    {
        // If gateway is not set yet, get it from user and instantiate
        if (is_null($this->gateway)) {

            // If gateway is not provided, get user's payment gateway
            if ($gateway || $gateway = Request::input('gateway')) {
                $paymentGateway = $checkAvailability ? $this->checkGatewayAvailability($gateway) : $gateway;
            } else {
                $paymentGateway = $this->paymentGateway;
            }

            // Get trimmed class name from config
            $gatewayClass = config('ptuchik-billing.gateways.'.$paymentGateway.'.class');
            $gatewayClass = $gatewayClass ? '\\'.ltrim($gatewayClass, '\\') : null;

            // If class from config exists initialize and set as current gateway
            if (class_exists($gatewayClass)) {
                $this->gateway = new $gatewayClass($this, config('ptuchik-billing.gateways.'.$paymentGateway, []));

                // Set current payment gateway
                $this->paymentGateway = $paymentGateway;

            } else {
                throw new BillingException(trans(config('ptuchik-billing.translation_prefixes.general').'.invalid_gateway'));
            }
        }

        // Return gateway instance
        return $this->gateway;
    }

    /**
     * Set payment gateway
     *
     * @param \Ptuchik\Billing\Contracts\PaymentGateway|null $gateway
     *
     * @return \Ptuchik\Billing\Contracts\PaymentGateway|null
     */
    public function setPaymentGateway(?PaymentGateway $gateway)
    {
        $this->gatewayName = $gateway ? $gateway->name : null;
        return $this->gateway = $gateway;
    }

    /**
     * Check payment gateway availability
     *
     * @param $gateway
     *
     * @return mixed
     */
    protected function checkGatewayAvailability($gateway)
    {
        // If current currency has limited gateways
        if ($availableGateways = env(Currency::getUserCurrency().'_GATEWAYS')) {
            $gateways = explode(',', $availableGateways);
        } else {
            $gateways = array_keys(config('ptuchik-billing.gateways'));
        }

        // If user's gateway exists among currency limited gateways, return it,
        // otherwise return the first gateway from the list
        return in_array($gateway, $gateways) ? $gateway : Arr::first($gateways);
    }
}