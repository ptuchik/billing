<?php

namespace Ptuchik\Billing\src\Traits;

use Illuminate\Support\Arr;

/**
 * Trait HasPaymentProfiles
 * @package Ptuchik\Billing\src\Traits
 */
trait HasPaymentProfiles
{
    /**
     * Payment profiles attribute getter
     *
     * @param $value
     *
     * @return array
     */
    public function getPaymentProfilesAttribute($value)
    {
        return !is_array($profiles = json_decode($value, true)) ? [] : $profiles;
    }

    /**
     * Payment profile attribute setter
     *
     * @param $value
     */
    public function setPaymentProfileAttribute($value)
    {
        // If user is not tester, save his payment profile
        $paymentProfiles = $this->paymentProfiles;
        if (is_null($value)) {
            unset($paymentProfiles['profiles'][$this->paymentGateway]);
        } else {
            $paymentProfiles['profiles'][$this->paymentGateway] = $value;
        }
        $this->paymentProfiles = $paymentProfiles;
    }

    /**
     * Payment profile attribute getter
     * @return mixed
     */
    public function getPaymentProfileAttribute()
    {
        $paymentProfiles = $this->paymentProfiles;

        // Try to get user's payment profile
        if ($profile = Arr::get($paymentProfiles, 'profiles.'.$this->paymentGateway)) {
            return $profile;

            // If user has payment profile with old method, upgrade it
        } elseif ($profile = Arr::get($paymentProfiles, $this->paymentGateway)) {
            unset($paymentProfiles[$this->paymentGateway]);
            $this->paymentProfiles = $paymentProfiles;
            $this->paymentProfile = $profile;
            $this->save();

            return $profile;
            // If user has no payment profile, create it to continue
        } else {
            return $this->createPaymentProfile();
        }
    }

    /**
     * Create payment profile
     * @return mixed
     */
    protected function createPaymentProfile()
    {
        // Create payment profile on gateway
        $paymentProfile = $this->getPaymentGateway()->createPaymentProfile();

        $this->paymentProfile = $paymentProfile;
        $this->save();

        return $paymentProfile;
    }

    /**
     * Remove customer's payment profile
     * @return mixed
     */
    public function removePaymentProfile()
    {
        $paymentProfiles = $this->paymentProfiles;
        unset($paymentProfiles['profiles'][$this->paymentGateway]);
        $this->paymentProfiles = $paymentProfiles;
        $this->save();

        return $this->paymentProfiles;
    }

    /**
     * Refresh user's payment profile on for given gateway
     *
     * @param $gateway
     *
     * @return mixed
     */
    public function refreshPaymentProfile($gateway)
    {
        $this->paymentGateway = $gateway;
        $this->removePaymentProfile();
        $this->removePaymentMethods();

        return $this->createPaymentProfile();
    }
}