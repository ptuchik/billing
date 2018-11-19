<?php

namespace Ptuchik\Billing\src\Traits;

use Throwable;

/**
 * Trait HasPaymentMethods
 * @package Ptuchik\Billing\src\Traits
 */
trait HasPaymentMethods
{
    /**
     * Has payment method attribute setter
     *
     * @param $value
     *
     * @return mixed
     */
    public function setHasPaymentMethodAttribute($value)
    {
        $this->setParam('hasPaymentMethod', !empty($value));
    }

    /**
     * Has payment method attribute getter
     * @return bool
     */
    public function getHasPaymentMethodAttribute()
    {
        return !empty($this->getParam('hasPaymentMethod'));
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
            $method['default'] = $method['token'] == $defaultPaymentMethod['token'];
            $paymentMethods[] = $method;
        }

        return $paymentMethods;
    }

    /**
     * Get payment methods
     * @return array
     */
    public function getPaymentMethods()
    {
        // If user has no saved payment methods
        if (!$paymentMethods = array_get($this->paymentProfiles, 'methods')) {

            // Get payment methods from gateway
            try {
                $paymentMethods = [];
                foreach ($this->getPaymentGateway()->getPaymentMethods() as $method) {
                    $method['gateway'] = $this->paymentGateway;
                    $paymentMethods[] = $method;
                }
            } catch (Throwable $e) {
                $paymentMethods = [];
            }

            $this->paymentMethods = $paymentMethods;
        }

        // If array is not empty, set user's hasPaymentMethod = true
        $this->hasPaymentMethod = !empty($paymentMethods);
        $this->save();

        // Finally return payment methods
        return $paymentMethods;
    }

    /**
     * Check and save payment method existance
     * @return mixed
     */
    public function checkPaymentMethod()
    {
        // If payment methods never checked yet, check and save result
        if (isset($this->attributes['params']) && is_null($this->getParam('hasPaymentMethod'))) {
            $this->getPaymentMethods();
        }

        return $this->hasPaymentMethod;
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
            if ($paymentMethod['token'] == $this->defaultToken) {
                return $paymentMethod;
            }
        }

        return array_last($paymentMethods);
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
            if ($paymentMethod['token'] == $token) {
                $this->getPaymentGateway($paymentMethod['gateway'])->setDefaultPaymentMethod($token);
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
                if ($method['token'] == $paymentMethod['token'] && $method['gateway'] == $this->paymentGateway) {
                    return $paymentMethod;
                }
            }

            // If there was no such payment method, add it
            $paymentMethod['gateway'] = $this->paymentGateway;
            $paymentMethods[] = $paymentMethod;
            $this->paymentMethods = $paymentMethods;

            // Set the new payment method as default
            $this->defaultToken = $paymentMethod['token'];
            $paymentMethod['default'] = true;

            // Set user's hasPaymentMethod = true
            $this->hasPaymentMethod = true;
            $this->save();

            // Return payment method
            return $paymentMethod;
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
            if ($paymentMethod['token'] == $token) {
                try {
                    $this->getPaymentGateway($paymentMethod['gateway'])->deletePaymentMethod($token);
                } catch (Throwable $exception) {

                }
            } else {
                $paymentMethods[] = $paymentMethod;
            }
        }

        if (empty($paymentMethods)) {
            $this->hasPaymentMethod = false;
            $this->defaultToken = null;
        }

        $this->paymentMethods = $paymentMethods;
        $this->save();
    }
}