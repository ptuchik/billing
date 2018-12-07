<?php

namespace Ptuchik\Billing\Models;

use Auth;
use Carbon\Carbon;
use Currency;
use Exception;
use Omnipay\Common\Message\ResponseInterface;
use Ptuchik\Billing\Constants\PlanVisibility;
use Ptuchik\Billing\Constants\SubscriptionStatus;
use Ptuchik\Billing\Constants\TransactionStatus;
use Ptuchik\Billing\Event;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Traits\HasFrequency;
use Ptuchik\CoreUtilities\Models\Model;
use Ptuchik\CoreUtilities\Traits\HasParams;

/**
 * Class Subscription
 * @package Ptuchik\Billing\Models
 */
class Subscription extends Model
{
    /**
     * Use params and add frequency methods
     */
    use HasParams, HasFrequency;

    /**
     * Make params unsanitized
     * @var array
     */
    protected $unsanitized = ['params'];

    /**
     * Make subscription name translatable
     * @var array
     */
    public $translatable = ['name'];

    /**
     * Cast following attributes
     * @var array
     */
    protected $casts = [
        'id'                => 'integer',
        'params'            => 'array',
        'user_id'           => 'integer',
        'coupons'           => 'array',
        'addons'            => 'array',
        'billing_frequency' => 'integer',
        'active'            => 'boolean'
    ];

    /**
     * Append following attributes
     * @var array
     */
    protected $appends = [
        'discount',
        'summary',
        'currencySymbol',
        'status',
        'period',
        'duration',
        'daysLeft',
        'autoRenew',
        'expirationDateFormatted',
        'hasPaymentIssue',
        'features',
        'agreementText'
    ];

    /**
     * Hide sensitive data
     * @var array
     */
    protected $hidden = [
        'params',
        'lastTransaction'
    ];

    /**
     * Eager load last transaction to check whether subscription had payment issue
     * @var array
     */
    protected $with = ['lastTransaction'];

    /**
     * Current plan of subscription
     * @var
     */
    public $currentPlan;

    /**
     * Renew attempt count
     * @var int
     */
    public $attempt = 1;

    /**
     * Last attempt indicator
     * @var bool
     */
    public $lastAttempt = false;

    /**
     * Adds autorenew attribute getter
     * @return bool
     */
    public function getAutoRenewAttribute()
    {
        return is_null($this->endsAt);
    }

    /**
     * Check if subscription has active user
     * @return bool
     */
    public function hasActiveUser()
    {
        return $this->user && $this->user->active;
    }

    /**
     * Discounts attribute getter
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
     * Addon coupons attribute getter
     * @return static
     */
    public function getAddonCouponsAttribute()
    {
        // Convert coupons into collection
        $addons = collect([]);
        foreach ($this->addons as $addon) {
            $addons->push(Factory::get(Coupon::class, true)->forceFill($addon));
        }

        return $addons;
    }

    /**
     * Discount attribute getter
     * @return int
     */
    public function getDiscountAttribute()
    {
        $discount = 0;

        // Calculate the discount based on discounts collection
        foreach ($this->discounts as $coupon) {
            $discount += $coupon->percent ? $this->price * $coupon->amount / 100 : $coupon->amount;
        }

        if ($discount > $this->price) {
            $discount = $this->price;
        }

        return $discount;
    }

    /**
     * Summary attribute getter
     * @return mixed
     */
    public function getSummaryAttribute()
    {
        $summary = $this->price - $this->discount;
        if ($summary < 0) {
            $summary = 0;
        }

        return $summary;
    }

    /**
     * Remove non prorate coupons
     * @return $this
     */
    public function removeNonProrateCoupons()
    {
        $this->coupons = $this->discounts->reject(function ($value, $key) {
            if (!$value->prorate) {
                return $value;
            }
        });

        return $this;
    }

    /**
     * Has payment issue attribute getter, checks if subscription had payment issue on last attempt
     * @return bool
     */
    public function getHasPaymentIssueAttribute()
    {
        return $this->active && $this->lastTransaction &&
            $this->lastTransaction->status != Factory::getClass(TransactionStatus::class)::SUCCESS;
    }

    /**
     * Autorenew attribute setter
     *
     * @param $value
     */
    public function setAutoRenewAttribute($value)
    {
        $this->endsAt = $value ? null : $this->nextBillingDate;
    }

    /**
     * Adds expiration date formatted attribute to subscription
     * @return null|string
     */
    public function getExpirationDateFormattedAttribute()
    {
        if (empty($this->nextBillingDate)) {
            return null;
        } else {
            return Carbon::createFromFormat('Y-m-d H:i:s', $this->endsAt ?? $this->nextBillingDate)->format('M d Y');
        }
    }

    /**
     * Purchase relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @throws Exception
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
     * Host attribute getter
     * @return mixed
     */
    public function getHostAttribute()
    {
        return $this->purchase->host;
    }

    /**
     * Reference attribute getter
     * @return mixed
     */
    public function getReferenceAttribute()
    {
        return $this->purchase->reference;
    }

    /**
     * Price attribute setter
     *
     * @param $value
     */
    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = currency($value ?? 0, Currency::getUserCurrency(), $this->currency, false);
    }

    /**
     * Price attribute getter
     *
     * @param $value
     *
     * @return mixed
     */
    public function getPriceAttribute($value)
    {
        return currency($value ?? 0, $this->currency, null, false);
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
     * @return \Illuminate\Config\Repository|mixed
     */
    public function getCurrencySymbolAttribute()
    {
        return array_get(Currency::getCurrency($this->currency), 'symbol');
    }

    /**
     * Transactions relation
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Factory::getClass(Transaction::class))->orderBy('id', 'desc');
    }

    /**
     * Last transaction relation
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function lastTransaction()
    {
        return $this->hasOne(Factory::getClass(Transaction::class))->orderBy('id', 'desc');
    }

    /**
     * Determine if the subscription is within its trial period
     * @return bool
     */
    public function onTrial()
    {
        // If trial end column is not empty, means the subscription is on trial period
        return $this->active && !is_null($this->trialEndsAt);
    }

    /**
     * Determine if the subscription is within its grace period after cancellation
     * @return bool
     */
    public function onGracePeriod()
    {
        // If subscription end column is not empty, means that subscription will not autorenew,
        // so it is on grace period
        return $this->active && !is_null($this->endsAt);
    }

    /**
     * Mark subscription as active
     * @return bool
     */
    public function markAsActive()
    {
        // Clean up end column and activate
        $this->active = true;
        $this->endsAt = null;

        return $this->save();
    }

    /**
     * Mark the subscription as expired
     * @return bool
     */
    public function markAsExpired()
    {
        // Set subscription to end on next billing date
        $this->trialEndsAt = $this->trialEndsAt ?: $this->nextBillingDate;
        $this->endsAt = $this->nextBillingDate;

        return $this->save();
    }

    /**
     * Expire subscription
     * @return bool
     */
    public function expireNow()
    {
        $this->nextBillingDate = Carbon::today();

        return $this->markAsExpired();
    }

    /**
     * Cancel subscription
     * @return bool
     */
    public function cancelNow()
    {
        // Set subscription's ending today
        $this->trialEndsAt = $this->trialEndsAt ?: Carbon::today();
        $this->endsAt = Carbon::today();

        return $this->save();
    }

    /**
     * Cancel subscription if it's package not in use and refund it's user if needed
     *
     * @param \Ptuchik\Billing\Models\Plan $plan
     * @param int                          $price
     *
     * @return int|mixed
     */
    public function cancelAndRefund(Plan $plan, $price = 0)
    {
        // If there is a user on subscription
        if ($user = $this->user) {

            // Get left amount after coupon and subscription discount
            $subscriptionDiscount = $this->onTrial() ? 0 : $this->balanceLeft;
            $leftAmount = $subscriptionDiscount - $price;

            // If there is left some difference, add to previous user's balance and get the price after these all
            if ($leftAmount > 0) {
                $user->balance = $user->balance + $leftAmount;
                $user->save();
                $price = 0;
            } else {
                $price = 0 - $leftAmount;
            }

            // If previous user is the current user, update current user's balance
            if ($plan->user->id == $user->id) {
                $plan->user->balance = $user->balance;
            }
        }

        // Deactivate previous subscription's package if it is not the same as plan's one
        if ($this->package->id != $plan->package->id) {
            $this->package->deactivate($plan->host);
        }

        return $price;
    }

    /**
     * Determine if the subscription is active
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Status attribute getter
     * @return mixed
     */
    public function getStatusAttribute()
    {
        // If subscription is active, check if it is on trial or not
        if ($this->isActive()) {
            if ($this->onTrial()) {
                return Factory::getClass(SubscriptionStatus::class)::TRIAL_ACTIVE;
            } else {
                return Factory::getClass(SubscriptionStatus::class)::ACTIVE;
            }
        } else {

            // If ending date is before next billing date, mark as cancelled
            if (Carbon::createFromFormat('Y-m-d H:i:s', $this->endsAt ?? $this->nextBillingDate)
                ->lt(Carbon::createFromFormat('Y-m-d H:i:s', $this->nextBillingDate))) {
                return Factory::getClass(SubscriptionStatus::class)::CANCELLED;

                // Otherwise it is expired
            } else {
                return Factory::getClass(SubscriptionStatus::class)::EXPIRED;
            }
        }
    }

    /**
     * Features attribute getter
     * @return string
     */
    public function getFeaturesAttribute()
    {
        return !empty($this->params['features']) ? $this->getTranslationValue(json_encode($this->params['features'])) : [];
    }

    /**
     * Agreement text attribute getter
     * @return string
     */
    public function getAgreementTextAttribute()
    {
        if (!empty($this->params['agreement'])) {
            $agreementOverride = $this->getTranslationValue(json_encode($this->params['agreement']));
        } else {
            $agreementOverride = false;
        }

        return $agreementOverride ?: trans(config('ptuchik-billing.translation_prefixes.plan').'.agreement_recurring');
    }

    /**
     * Days left attribute getter
     * @return float|int
     */
    public function getDaysLeftAttribute()
    {
        $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->endsAt ?? $this->nextBillingDate);

        return $endDate->isPast() ? 0 : $endDate->diffInDays();
    }

    /**
     * Price per day attribute getter
     * @return float|int
     */
    public function getPricePerDayAttribute()
    {
        // Get next payment date from subscription
        $nextPaymentDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->nextBillingDate);

        // Get previous payment date based on billing frequency
        $previousPaymentDate = (clone $nextPaymentDate)->subMonths($this->billingFrequency);

        // Calculate price per day and return
        return $this->summary / $nextPaymentDate->diffInDays($previousPaymentDate);
    }

    /**
     * Balance left attribute getter
     * @return float|int
     */
    public function getBalanceLeftAttribute()
    {
        // Calculate and return left balance
        return $this->daysLeft * $this->pricePerDay;
    }

    /**
     * Get paginated user transactions
     *
     * @param $perPage
     *
     * @return array
     */
    public function getTransactionsPaginated($perPage)
    {
        return collect($this->transactions()->with('purchase.package', 'purchase.host', 'purchase.reference')
            ->paginate($perPage)->items())->each(function ($transaction) {
            if ($transaction->purchase) {
                $transaction->purchase->append('identifier');
            }
        });
    }

    /**
     * Get subscription's payment method
     * @return mixed
     */
    public function getPaymentMethod()
    {
        return $this->user->getDefaultPaymentMethod();
    }

    /**
     * Set params from given plan
     *
     * @param \Ptuchik\Billing\Models\Plan $plan
     *
     * @return $this
     */
    public function setParamsFromPlan(Plan $plan)
    {
        // Get and set raw features from plan
        $this->setParam('features', json_decode($plan->getRawAttribute('features')));

        // Set agreement override
        $this->setParam('agreement', json_decode($plan->getRawAttribute('agreement')));

        return $this;
    }

    /**
     * Package attribute getter
     * @return mixed
     */
    public function getPackageAttribute()
    {
        // Get purchase
        $purchase = $this->purchase;

        // Get current package
        $package = $purchase->package;

        // If the package does not exist, generate a new one based on purchase data
        if (!$package) {
            $packageData = $purchase->data;
            $package = $purchase->package()->getModel();
            $package->id = $purchase->packageId;
            $package->setRawAttribute('name', $package->getRawAttribute('name'));
            $package->setRawAttribute('agreement', $package->getRawAttribute('agreement'));
            $package->alias = $packageData['alias'].'-removed-'.time();
            $package->save();
        }

        // Set purchase to package
        $package->purchase = $purchase;

        return $package;
    }

    /**
     * Original plan relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function originalPlan()
    {
        return $this->belongsTo(Factory::getClass(Plan::class), 'alias', 'alias')
            ->where('plans.visibility', '<>', Factory::getClass(PlanVisibility::class)::DISABLED);
    }

    /**
     * Plan attribute getter
     * @return mixed|\Ptuchik\Billing\Models\Plan
     */
    public function getPlanAttribute()
    {
        // If there is no current plan, set it
        if (!$this->currentPlan) {

            // Generate a plan from subscription
            $plan = Factory::get(Plan::class, true);

            // Try to get some data from original plan
            if ($originalPlan = $this->originalPlan) {
                $plan->setRawAttribute('name', $originalPlan->getRawAttribute('name'));
                $plan->alias = $originalPlan->alias;
                $plan->features = $originalPlan->features;
                $plan->agreementText = $originalPlan->agreementText;
            } else {
                $plan->setRawAttribute('name', $this->getRawAttribute('name'));
                $plan->alias = $this->alias;
                $plan->features = $this->features;
                $plan->agreementText = $this->agreementText;
            }
            $plan->cardRequired = true;
            $plan->host = $this->purchase->host;
            $plan->price = $this->price;
            $plan->trialDays = 0;
            $plan->billingFrequency = $this->billingFrequency;
            $plan->package = $this->package;
            $plan->discounts = $this->discounts;
            $plan->addonCoupons = $this->addonCoupons;
            $plan->user = Auth::check() ? Auth::user() : $this->user;
            $plan->subscription = $this;

            // Prepare package to process
            $plan->package->prepare($plan->host, $plan);

            // Set current plan
            $this->currentPlan = $plan;
        }

        return $this->currentPlan;
    }

    /**
     * Renew subscription
     *
     * @param \Omnipay\Common\Message\ResponseInterface|null $payment
     * @param \Ptuchik\Billing\Models\Order|null             $order
     *
     * @return mixed
     */
    public function renew(ResponseInterface $payment = null, Order $order = null)
    {
        // Call plan's purchase to renew
        return $this->plan->purchase($this->plan->host, $payment, $order);
    }

    /**
     * Prolong subscription
     *
     * @param int $months
     *
     * @return $this|bool
     */
    public function prolong($months = 0)
    {
        // If subscription is not active, return false
        if (!$this->isActive()) {
            return false;
        }

        $this->nextBillingDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->nextBillingDate)->addMonths($months);
        $this->endsAt = is_null($this->endsAt) ? null : $this->nextBillingDate;
        $this->trialEndsAt = is_null($this->trialEndsAt) ? null : $this->nextBillingDate;

        $this->save();

        return $this;
    }

    /**
     * Switch subscription of the same package between frequencies
     *
     * @param \Ptuchik\Billing\Models\Plan $plan
     *
     * @return mixed
     */
    public function switchFrequency(Plan $plan)
    {
        $subscription = new static();
        $subscription->setRawAttribute('name', $this->getRawAttribute('name'));
        $subscription->purchase()->associate($this->purchase);
        $subscription->user()->associate(Auth::user() ?: $this->user);
        $subscription->setParamsFromPlan($plan);
        $subscription->active = $this->active;
        $subscription->alias = $plan->alias;
        $subscription->price = $plan->price;
        $subscription->currency = Currency::getUserCurrency();
        $subscription->coupons = $plan->discounts;
        $subscription->addons = $plan->addonCoupons;
        $subscription->billingFrequency = $plan->billingFrequency;
        $subscription->trialEndsAt = $this->trialEndsAt;
        $subscription->nextBillingDate = $this->nextBillingDate;

        // If user has no payment methods, start non-recurring subscription
        if ($plan->price > 0 && !$subscription->user->hasPaymentMethod) {
            $subscription->endsAt = $subscription->nextBillingDate;
        }

        // Save new subscription, deactivate current one and return new subscription
        $subscription->save();
        $this->deactivate();

        // Set plan as old, because we are not going to charge customer
        $plan->old = true;

        return Factory::get(Invoice::class, true, $plan,
            Factory::get(Transaction::class, true)->fillFromSubscription($this));
    }

    /**
     * Deactivate subscription
     * @return mixed
     */
    public function deactivate()
    {
        // Deactivate itself
        $this->active = false;

        return $this->cancelNow();
    }

    /**
     * Process billing for all active subscriptions
     *
     * @param      $date
     * @param int  $attempt
     * @param bool $lastAttempt
     */
    public static function renewActiveSubscriptions($date, $attempt = 1, $lastAttempt = false)
    {
        // Get subscriptions
        $query = static::with(['user', 'purchase.package', 'purchase.host'])->where('active', 1)->whereNull('ends_at');

        if ($lastAttempt) {
            $query->whereDate('next_billing_date', '<=', $date);
        } else {
            $query->whereDate('next_billing_date', $date);
        }

        // Loop through each active subscription and try to renew
        foreach ($query->get() as $subscription) {

            // If subscription has no active user or it is not in use just deactivate package and continue
            if (!$subscription->hasActiveUser() || !$subscription->package->isInUse($subscription->host)) {

                $subscription->package->deactivate($subscription->host);
                continue;

            }

            // Set currency
            Currency::setUserCurrency($subscription->currency);

            // Set subscription's attempt and last attempt indicators
            $subscription->attempt = $attempt;
            $subscription->lastAttempt = $lastAttempt;

            try {
                $subscription->renew();
            } catch (Exception $e) {
                continue;
            }
        }
    }

    /**
     * Expire subscriptions
     *
     * @param $date
     */
    public static function expireSubscriptions($date)
    {
        // Loop through each subscription and deactivate it
        foreach (static::with(['user', 'purchase.package', 'purchase.host'])->where('active', 1)
                     ->whereNotNull('ends_at')->whereDate('ends_at', '<=', $date)->get() as $subscription) {

            // If subscription has no active user or it is not in use, just deactivate package and continue
            if (!$subscription->hasActiveUser() || !$subscription->package->isInUse($subscription->host)) {
                $subscription->package->deactivate($subscription->host);
                continue;
            }

            // Set currency
            Currency::setUserCurrency($subscription->currency);

            // Set subscription's last attempt, to expire it
            $subscription->lastAttempt = true;

            // Create fake transaction from subscription and trigger event
            Event::purchaseFailed($subscription->plan,
                Factory::get(Transaction::class)->fillFromSubscription($subscription));
        }
    }

    /**
     * Expiration reminder
     *
     * @param $fromDate
     * @param $toDate
     */
    public static function expirationReminder($fromDate, $toDate)
    {
        // Loop through each trial subscription and send email it users
        foreach (static::with(['user', 'purchase.package', 'purchase.host'])->where('active', 1)
                     ->whereDate('next_billing_date', '>', $fromDate)
                     ->whereDate('next_billing_date', '<=', $toDate)
                     ->get() as $subscription) {

            // If subscription has no active user or it is free or it is not in use, ignore it
            if (!$subscription->hasActiveUser() ||
                empty((float) $subscription->summary) ||
                !$subscription->package->isInUse($subscription->host)) {
                continue;
            }

            // Set currency
            Currency::setUserCurrency($subscription->currency);

            // Trigger reminder event
            Event::subscriptionExpirationReminder($subscription);
        }
    }
}