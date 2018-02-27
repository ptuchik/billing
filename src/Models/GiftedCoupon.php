<?php

namespace Ptuchik\Billing\Models;

use Ptuchik\Billing\Factory;
use Ptuchik\CoreUtilities\Models\Model;

/**
 * Class GiftedCoupon
 * @package Ptuchik\Billing\Models
 */
class GiftedCoupon extends Model
{
    /**
     * Coupon relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coupon()
    {
        $checkCouponsBy = config('ptuchik-billing.check_gifted_coupons.by');

        return $this->belongsTo(Factory::getClass(Coupon::class), 'coupon_'.$checkCouponsBy, $checkCouponsBy);
    }

    /**
     * Plan relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(Factory::getClass(Plan::class), 'plan_alias', 'alias');
    }

    /**
     * Host relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function host()
    {
        return $this->morphTo('host');
    }
}