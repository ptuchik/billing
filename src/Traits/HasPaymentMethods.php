<?php

namespace Ptuchik\Billing\src\Traits;

use File;
use Ptuchik\Billing\Constants\PaymentMethods;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\PaymentMethod;
use Throwable;

/**
 * Trait HasPaymentMethods
 * @package Ptuchik\Billing\src\Traits
 */
trait HasPaymentMethods
{
    /**
     * Has payment method attribute getter
     * @return bool
     */
    public function getHasPaymentMethodAttribute()
    {
        return !empty($this->paymentMethods);
    }

    /**
     * Default payment method token attribute setter
     * @return mixed
     */
    public function setDefaultTokenAttribute($value)
    {
        $paymentProfiles = $this->paymentProfiles;
        $paymentProfiles['default'] = $value;
        $this->paymentProfiles = $paymentProfiles;
    }

    /**
     * Default payment method token attribute getter
     * @return mixed
     */
    public function getDefaultTokenAttribute()
    {
        return array_get($this->paymentProfiles, 'default');
    }

    /**
     * Payment methods attribute setter
     *
     * @param array $value
     */
    public function setPaymentMethodsAttribute(array $value)
    {
        $paymentProfiles = $this->paymentProfiles;
        $paymentProfiles['methods'] = $value;
        $this->paymentProfiles = $paymentProfiles;
    }

    /**
     * Payment methods attribute getter
     * @return mixed
     */
    public function getPaymentMethodsAttribute()
    {
        $defaultPaymentMethod = $this->getDefaultPaymentMethod($methods = $this->getPaymentMethods());
        $paymentMethods = [];
        foreach ($methods as $method) {
            if ($method->gateway == $this->checkGatewayAvailability($method->gateway)) {
                $paymentMethods[] = $this->parsePaymentMethod($method, $method->token == $defaultPaymentMethod->token);
            }
        }

        return $paymentMethods;
    }

    /**
     * Get payment methods
     * @return array
     */
    public function getPaymentMethods()
    {
        $paymentMethods = [];

        // If user has no saved payment methods
        if ($methods = array_get($this->paymentProfiles, 'methods', [])) {
            foreach ($methods as $method) {
                $paymentMethods[] = Factory::get(PaymentMethod::class, true, $method);
            }
        } else {

            // Get current payment gateway
            $currentGateway = $this->paymentGateway;

            // Loop through each payment profile and get payment methods
            foreach (array_get($this->paymentProfiles, 'profiles', []) as $gateway => $profile) {
                // Get payment methods from gateway
                try {
                    foreach ($this->getPaymentGateway($gateway, false)->getPaymentMethods() as $method) {
                        $paymentMethods[] = $method;
                    }
                } catch (Throwable $e) {
                }
            }

            // Set back the current payment gateway
            $this->paymentGateway = $currentGateway;
        }

        // If array is not empty, set user's hasPaymentMethod = true
        $this->paymentMethods = $paymentMethods;
        $this->save();

        // Finally return payment methods
        return $paymentMethods;
    }

    /**
     * Get default payment method
     *
     * @param null $paymentMethods
     *
     * @return mixed
     */
    public function getDefaultPaymentMethod($paymentMethods = null)
    {
        if (!$paymentMethods || !is_array($paymentMethods)) {
            $paymentMethods = $this->getPaymentMethods();
        }

        // Loop through user's payment methods and find the default to return
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->token == $this->defaultToken) {
                return $this->parsePaymentMethod($paymentMethod, true);
            }
        }

        if ($paymentMethod = array_last($paymentMethods)) {
            return $this->parsePaymentMethod($paymentMethod, true);
        }
    }

    /**
     * Set default payment method
     *
     * @param      $token
     *
     * @return mixed
     */
    public function setDefaultPaymentMethod($token)
    {
        foreach ($this->getPaymentMethods() as $paymentMethod) {
            if ($paymentMethod->token == $token) {
                $this->getPaymentGateway($paymentMethod->gateway, false)->setDefaultPaymentMethod($token);
                $this->defaultToken = $token;
                $this->save();
            }
        }
    }

    /**
     * Create payment method
     *
     * @param      $nonce
     * @param null $gateway
     *
     * @return mixed
     */
    public function createPaymentMethod($nonce, $gateway = null)
    {
        // Try to create payment method on payment gateway, if success add to saved payment methods
        if ($paymentMethod = $this->getPaymentGateway($gateway)->createPaymentMethod($nonce)) {

            // Check if payment method already exists
            foreach ($paymentMethods = $this->getPaymentMethods() as $method) {
                if ($method->token == $paymentMethod->token && ($method->gateway) == $this->paymentGateway) {
                    return $paymentMethod;
                }
            }

            // If there was no such payment method, add it
            $paymentMethods[] = $paymentMethod;
            $this->paymentMethods = $paymentMethods;

            // Set the new payment method as default
            $this->defaultToken = $paymentMethod->token;
            $this->save();

            // Return payment method
            return $this->parsePaymentMethod($paymentMethod, true);
        }
    }

    /**
     * Delete payment method
     *
     * @param      $token
     */
    public function deletePaymentMethod($token)
    {
        $paymentMethods = [];
        foreach ($this->getPaymentMethods() as $paymentMethod) {
            if ($paymentMethod->token == $token) {
                try {
                    $this->getPaymentGateway($paymentMethod->gateway)->deletePaymentMethod($token);
                } catch (Throwable $exception) {

                }
            } else {
                $paymentMethods[] = $paymentMethod;
            }
        }

        if (empty($paymentMethods)) {
            $this->defaultToken = null;
        }

        $this->paymentMethods = $paymentMethods;
        $this->save();
    }

    /**
     * Parse payment method
     *
     * @param \Ptuchik\Billing\Models\PaymentMethod $method
     * @param null                                  $default
     *
     * @return \Ptuchik\Billing\Models\PaymentMethod
     */
    public function parsePaymentMethod(PaymentMethod $method, $default = null)
    {
        $method->description = $this->getPaymentMethodDescription($method);
        $method->imageUrl = $this->getPaymentMethodImageUrl($method);
        if (!is_null($default)) {
            $method->default = (bool) $default;
        }

        return $method;
    }

    /**
     * Get payment method description
     *
     * @param \Ptuchik\Billing\Models\PaymentMethod $method
     *
     * @return mixed|string
     */
    protected function getPaymentMethodDescription(PaymentMethod $method)
    {
        switch ($method->type) {
            case Factory::getClass(PaymentMethods::class)::PAYPAL_ACCOUNT:
                $description = $method->holder;
                break;
            default:
                $description = trans(config('ptuchik-billing.translation_prefixes.general').'.'.$method->type);

                if ($method->last4) {
                    $description .= ' '
                        .trans(config('ptuchik-billing.translation_prefixes.general').'.ending_in').' '.$method->last4;
                }
                break;
        }

        return $description;
    }

    /**
     * Get payment method image URL
     *
     * @param \Ptuchik\Billing\Models\PaymentMethod $method
     *
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    protected function getPaymentMethodImageUrl(PaymentMethod $method)
    {
        $path = config('ptuchik-billing.payment_method_images_location').'/'.$method->type.'.png';

        if (File::exists(public_path($path))) {
            return url($path);
        } else {
            return url(config('ptuchik-billing.default_payment_method_image_location'));
        }
    }
}