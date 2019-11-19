<?php

namespace Ptuchik\Billing\Models;

use Currency;
use Ptuchik\Billing\Factory;
use Ptuchik\CoreUtilities\Models\Model;
use Throwable;

/**
 * Class Transaction
 * @package App
 */
class Transaction extends Model
{
    /**
     * Make transaction name translatable
     * @var array
     */
    public $translatable = ['name'];

    /**
     * Unsanitize following items
     * @var array
     */
    protected $unsanitized = ['name', 'data', 'message'];

    /**
     * Hide sensitive data
     * @var array
     */
    protected $hidden = ['data'];

    /**
     * Cast following attributes
     * @var array
     */
    protected $casts = [
        'id'              => 'integer',
        'purchase_id'     => 'integer',
        'subscription_id' => 'integer',
        'user_id'         => 'integer',
        'status'          => 'integer',
        'coupons'         => 'array',
        'params' => 'array',
    ];

    /**
     * Append following attributes
     * @var array
     */
    protected $appends = [
        'paymentMethod',
        'currencySymbol'
    ];

    /**
     * Get name attribute
     *
     * @param $value
     *
     * @return mixed
     */
    public function getNameAttribute($value)
    {
        if ($value) {
            return $value;
        } elseif ($purchase = $this->purchase) {
            return $purchase->name;
        }
    }

    /**
     * Get discounts attribute
     * @return static
     */
    public function getDiscountsAttribute()
    {
        // Convert coupons into collection
        $discounts = collect([]);
        foreach ($this->coupons as $coupon) {
            $discounts->push(Factory::get(Coupon::class, true)->forceFill($coupon));
        }

        return $discounts;
    }

    /**
     * Purchase relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @throws \Exception
     */
    public function purchase()
    {
        return $this->belongsTo(Factory::getClass(Purchase::class));
    }

    /**
     * User relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Subscription relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Factory::getClass(Subscription::class));
    }

    /**
     * Get transaction data
     * @return \Braintree\Transaction|mixed
     */
    public function getData()
    {
        if (empty($this->data)) {
            return null;
        }

        return unserialize($this->data);
    }

    /**
     * Currency attribute getter
     *
     * @param $value
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    public function getCurrencyAttribute($value)
    {
        return $value ?: config('currency.default');
    }

    /**
     * Currency symbol attribute getter
     *
     * @param $value
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    public function getCurrencySymbolAttribute()
    {
        return array_get(Currency::getCurrency($this->currency), 'symbol');
    }

    /**
     * Payment method attribute getter
     * @return object
     */
    public function getPaymentMethodAttribute()
    {
        $paymentData = $this->getData();
        if ($user = $this->user) {
            $gateway = $user->getPaymentGateway($this->gateway, false);
            try {
                return $user->parsePaymentMethod($gateway->parsePaymentMethod($paymentData));
            } catch (Throwable $exception) {

            }
        }
    }

    /**
     * Fill transaction details from subscription
     *
     * @param Subscription $subscription
     *
     * @return $this
     * @throws \Exception
     */
    public function fillFromSubscription(Subscription $subscription)
    {
        $this->purchase()->associate($subscription->purchase);
        $this->subscription()->associate($subscription);
        $this->user()->associate($subscription->user);
        $this->gateway = $subscription->user->paymentGateway;
        $this->price = $subscription->price;
        $this->currency = $subscription->currency;
        $this->discount = $subscription->discount;
        $this->summary = $subscription->summary;
        $this->coupons = $subscription->discounts;

        return $this;
    }
}