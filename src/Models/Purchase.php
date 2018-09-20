<?php

namespace Ptuchik\Billing\Models;

use Auth;
use Carbon\Carbon;
use Currency;
use Ptuchik\Billing\Factory;
use Ptuchik\CoreUtilities\Models\Model;

/**
 * Class Purchase
 * @package App
 */
class Purchase extends Model
{
    /**
     * Make purchase name translatable
     * @var array
     */
    public $translatable = ['name'];

    /**
     * Cast following attributes
     * @var array
     */
    protected $casts = [
        'id'           => 'integer',
        'host_id'      => 'integer',
        'package_id'   => 'integer',
        'reference_id' => 'integer',
        'active'       => 'boolean',
        'data'         => 'array'
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
        } elseif (!empty($this->data['name'])) {
            return $this->data['name'];
        } elseif ($package = $this->package) {
            return $package->name;
        }
    }

    /**
     * Identifier attribute getter
     * @return mixed
     */
    public function getIdentifierAttribute()
    {
        return $this->package->getPurchaseIdentifier($this);
    }

    /**
     * Package relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function package()
    {
        return $this->morphTo();
    }

    /**
     * Reference relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Host relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function host()
    {
        return $this->morphTo();
    }

    /**
     * Transations relation
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Factory::getClass(Transaction::class));
    }

    /**
     * Subscriptions relation
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Factory::getClass(Subscription::class))->orderBy('id', 'desc');
    }

    /**
     * Subscription relation
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function subscription()
    {
        return $this->hasOne(Factory::getClass(Subscription::class))->where('active', 1);
    }

    /**
     * Create or update subscription
     *
     * @param \Ptuchik\Billing\Models\Plan $plan
     *
     * @return mixed|\Ptuchik\Billing\Models\Subscription
     */
    public function subscribe(Plan $plan)
    {
        // If plan has no subscription, get the subscription for current package
        $subscription = $plan->subscription ?: $this->subscription;

        // If there was no active subscription start a new one
        if (!$subscription) {
            $subscription = Factory::get(Subscription::class, true);
            $subscription->purchase()->associate($this);
            $subscription->user()->associate(Auth::user() ?: $plan->user);
            $subscription->setParamsFromPlan($plan);
            $subscription->active = true;
            $date = Carbon::today()->endOfDay();

            // Else if there is a subscription but it is on trial set the starting date
            // today to count the next billing date
        } elseif ($subscription->onTrial()) {
            $date = Carbon::today()->endOfDay();

            // If there is an active subscription, set the starting date to it's next
            // billing date
        } else {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $subscription->nextBillingDate);
        }

        $subscription->setRawAttribute('name', $plan->package->getRawAttribute('name'));
        $subscription->alias = $plan->alias;
        $subscription->user()->associate(Auth::user() ?: $subscription->user);
        $subscription->price = $plan->price;
        $subscription->currency = Currency::getUserCurrency();
        $subscription->coupons = $plan->discounts;
        $subscription->billingFrequency = $plan->billingFrequency;

        // If there are trial days that have to be used, add them to next billing date and set
        // subscription as trial
        if ($plan->hasTrial) {
            $subscription->trialEndsAt = $date->addDays($plan->trialDays);
            $subscription->nextBillingDate = $subscription->trialEndsAt;

            // Otherwise set it as active and prolong the next billing date with billing frequency
            // of current package
        } else {
            $subscription->removeNonProrateCoupons();
            $subscription->trialEndsAt = null;
            $subscription->nextBillingDate = $date->addMonths($subscription->billingFrequency);
        }

        // If user has no payment methods, start non-recurring subscription
        if (!$subscription->exists && $plan->price > 0 && !$subscription->user->hasPaymentMethod) {
            $subscription->endsAt = $subscription->nextBillingDate;
        }

        $subscription->endsAt = $subscription->endsAt ? $subscription->nextBillingDate : null;
        if (!$plan->isFree && !$plan->hasTrial && (!$plan->payment || $plan->payment->isSuccessful())) {
            $subscription->addons = [];
        } else {
            $subscription->addons = $plan->addonCoupons;
        }

        // Save and return subscription instance
        $subscription->save();

        return $subscription;
    }

    /**
     * Unsubscribe
     * @return bool
     */
    public function unsubscribe()
    {
        // If there is a subscription, complete it's billings
        if ($subscription = $this->subscription) {
            $subscription->deactivate();
        }

        return true;
    }
}