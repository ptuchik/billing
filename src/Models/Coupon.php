<?php

namespace Ptuchik\Billing\Models;

use Currency;
use Ptuchik\Billing\Constants\CouponRedeemType;
use Ptuchik\Billing\Contracts\Hostable;
use Ptuchik\Billing\Factory;
use Ptuchik\CoreUtilities\Models\Model;
use Ptuchik\CoreUtilities\Traits\HasParams;

/**
 * Class Coupon
 * @package App
 */
class Coupon extends Model
{
    use HasParams;

    protected $casts = [
        'id'      => 'integer',
        'percent' => 'boolean',
        'redeem'  => 'integer',
        'prorate' => 'boolean',
        'params'  => 'array'
    ];

    protected $hidden = [
        'pivot'
    ];

    protected $fillable = [
        'id',
        'name',
        'code',
        'amount',
        'percent',
        'redeem',
        'prorate',
        'params',
        'created_at',
        'updated_at'
    ];

    protected $appends = [
        'connectedToReferralSystem',
        'numberOfCoupons',
        'usedCoupons'
    ];

    /**
     * Get the route key for the model.
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'code';
    }

    /**
     * Connected to referral system attribute getter
     * @return bool
     */
    public function getConnectedToReferralSystemAttribute()
    {
        return !empty($this->getParam('connectedToReferralSystem'));
    }

    /**
     * Connected to referral system attribute getter
     *
     * @param $value
     */
    public function setConnectedToReferralSystemAttribute($value)
    {
        $this->setParam('connectedToReferralSystem', !empty($value));
    }

    /**
     * Number Of Coupons attribute getter
     * @return null|int
     */
    public function getNumberOfCouponsAttribute()
    {
        return $this->getParam('numberOfCoupons');
    }

    /**
     * Number Of Coupons attribute setter
     *
     * @param $value
     */
    public function setNumberOfCouponsAttribute($value)
    {
        $this->setParam('numberOfCoupons', $value);
    }

    /**
     * Used Coupons attribute getter
     * @return int
     */
    public function getUsedCouponsAttribute()
    {
        return $this->getParam('usedCoupons', 0);
    }

    /** Used Coupons attribute setter
     *
     * @param $value
     */
    public function setUsedCouponsAttribute($value)
    {
        $this->setParam('usedCoupons', $value);
    }

    /**
     * Plan relations
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     * @throws \Exception
     */
    public function plans()
    {
        return $this->belongsToMany(Factory::getClass(Plan::class), 'plan_coupons');
    }

    /**
     * Used coupons relation
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function usedCoupons()
    {
        $checkCouponsBy = config('ptuchik-billing.check_used_coupons.by');

        return $this->hasMany(Factory::getClass(UsedCoupon::class), 'coupon_'.$checkCouponsBy, $checkCouponsBy);
    }

    /**
     * Gifted coupons relation
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function giftedCoupons()
    {
        $checkCouponsBy = config('ptuchik-billing.check_gifted_coupons.by');

        return $this->hasMany(Factory::getClass(GiftedCoupon::class), 'coupon_'.$checkCouponsBy, $checkCouponsBy);
    }

    /**
     * Check if coupon is already used for host
     *
     * @param \Ptuchik\Billing\Models\Plan        $plan
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return bool
     */
    public function isUsed(Plan $plan, Hostable $host)
    {
        // Build query for used coupons for provided host
        $query = $this->usedCoupons()->where('used_coupons.host_id', $host->id)
            ->where('used_coupons.host_type', $host->getMorphClass());

        // If set to check with plan, add plan condition
        if (config('ptuchik-billing.check_used_coupons.with') == 'plan') {
            $query->where('used_coupons.plan_alias', $plan->alias);
        }

        // Check for existance and return
        return !empty($query->first());
    }

    /**
     * Mark coupon as used for given plan and host
     *
     * @param \Ptuchik\Billing\Models\Plan        $plan
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     */
    public function markAsUsed(Plan $plan, Hostable $host)
    {
        $used = Factory::get(UsedCoupon::class, true);
        $used->couponId = $this->id;
        $used->couponCode = $this->code;
        $used->planAlias = $plan->alias;
        $used->host()->associate($host);
        $used->save();
    }

    /**
     * Check if coupon is already gifted to host
     *
     * @param \Ptuchik\Billing\Models\Plan        $plan
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return bool
     */
    public function isGifted(Plan $plan, Hostable $host)
    {
        // Build query for gifted coupons for provided host
        $query = $this->giftedCoupons()->where('gifted_coupons.host_id', $host->id)
            ->where('gifted_coupons.host_type', $host->getMorphClass());

        // If set to check with plan, add plan condition
        if (config('ptuchik-billing.check_gifted_coupons.with') == 'plan') {
            $query->where('gifted_coupons.plan_alias', $plan->alias);
        }

        // Check for existance and return
        return !empty($query->first());
    }

    /**
     * Mark coupon as gifted for given plan and host
     *
     * @param \Ptuchik\Billing\Models\Plan        $plan
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     */
    public function markAsGifted(Plan $plan, Hostable $host)
    {
        if ($this->redeem == Factory::getClass(CouponRedeemType::class)::INTERNAL) {
            $gifted = Factory::get(GiftedCoupon::class, true);
            $gifted->couponId = $this->id;
            $gifted->couponCode = $this->code;
            $gifted->planAlias = $plan->alias;
            $gifted->host()->associate($host);
            $gifted->save();
        }
    }

    /**
     * Get coupon by code
     *
     * @param $code
     *
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getByCode($code)
    {
        return static::where('code', $code)->first();
    }

    /**
     * Amount attribute getter
     *
     * @param $value
     *
     * @return string
     */
    public function getAmountAttribute($value)
    {
        // Get amount from attributes and decode
        $amount = $this->fromJson($value);

        // If user currency has value, format and return value
        if (isset($amount[Currency::getUserCurrency()])) {
            return $amount[Currency::getUserCurrency()];
        }

        // If it has no value try to get default currency value or 0
        // Check if coupon is in percents, just return, otherwise
        // convert to user currency and return
        return $this->percent ? ($amount[config('currency.default')] ?? 0) :
            currency($amount[config('currency.default')] ?? 0, null, null, false);
    }

    /**
     * Amount attribute setter
     *
     * @param $value
     */
    public function setAmountAttribute($value)
    {
        // Get amount from attributes and decode
        $amount = is_array($amount = $this->fromJson($this->attributes['amount'] ?? '')) ? $amount : [];

        // Set user's currency value
        $amount[Currency::getUserCurrency()] = $value;

        // If default currency has no value, convert amount and set as default
        if (!isset($amount[config('currency.default')])) {
            $amount[config('currency.default')] = $this->percent ? $value :
                currency($value, Currency::getUserCurrency(), config('currency.default'), false);
        }

        // Encode and put back amount
        $this->attributes['amount'] = json_encode($amount);
    }
}