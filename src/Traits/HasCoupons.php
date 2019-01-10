<?php

namespace Ptuchik\Billing\src\Traits;

use Illuminate\Support\Collection;
use Ptuchik\Billing\Constants\CouponRedeemType;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Plan;
use Ptuchik\Billing\Contracts\Hostable as HostableContract;

/**
 * Trait HasCoupons
 * @package Ptuchik\Billing\src\Traits
 */
trait HasCoupons
{
    /**
     * Get user coupons
     * @return array
     */
    public function getCoupons()
    {
        return is_array($coupons = $this->getParam('coupons')) ? $coupons : [];
    }

    /**
     * Add coupon codes from provided collection to user's coupons array
     *
     * @param \Illuminate\Support\Collection      $addons
     * @param \Ptuchik\Billing\Models\Plan        $plan
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return $this
     */
    public function addCoupons(Collection $addons, Plan $plan, HostableContract $host)
    {
        // Get user coupons
        $coupons = $this->getCoupons();

        // Loop through coupons and add internal coupons to user's coupons
        foreach ($addons as $coupon) {
            if ($coupon->redeem == Factory::getClass(CouponRedeemType::class)::INTERNAL) {

                // If coupon is not gifted for current host yet, gift it and mark as gifted
                if (!$coupon->isGifted($plan, $host)) {
                    $coupons[] = $coupon->code;

                    // Mark as gifted
                    $coupon->markAsGifted($plan, $host);
                }
            }
        }

        // Get user params, overwrite coupons key and set back
        $this->setParam('coupons', $coupons);
        $this->save();

        return $this;
    }

    /**
     * Remove coupon code from user's coupons array
     *
     * @param \Illuminate\Support\Collection $discounts
     *
     * @return $this
     */
    public function removeCoupons(Collection $discounts)
    {
        // Get user coupons
        $coupons = $this->getCoupons();

        // Loop through discounts and remove used coupons from user's coupons
        foreach ($discounts as $coupon) {
            if (($key = array_search($coupon->code, $coupons)) !== false) {
                unset($coupons[$key]);
            }
        }

        // Get user params, overwrite coupons key and set back
        $this->setParam('coupons', array_values($coupons));
        $this->save();

        return $this;
    }
}