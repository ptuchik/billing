<?php

namespace Ptuchik\Billing\Models;

use Auth;
use Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Ptuchik\Billing\Constants\CouponRedeemType;
use Ptuchik\Billing\Event;
use Ptuchik\Billing\Exceptions\BillingException;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Traits\HasFrequency;
use Ptuchik\Billing\Traits\PurchaseLogic;
use Ptuchik\CoreUtilities\Models\Model;
use Ptuchik\CoreUtilities\Traits\HasIcon;
use Ptuchik\CoreUtilities\Traits\HasParams;
use Request;

/**
 * Class Plan
 *
 * @package Ptuchik\Billing\Models
 */
class Plan extends Model
{
    /**
     * Use icon and add purchase logic to model
     */
    use HasIcon, HasParams, PurchaseLogic, HasFrequency;

    /**
     * Indicates if the plan is upgradable or not
     *
     * @var bool
     */
    protected $isUpgradable = true;

    /**
     * @var array
     */
    protected $fillable = [
        'ordering'
    ];

    /**
     * Exclude following attributes from sanitizing
     *
     * @var array
     */
    protected $unsanitized = [
        'agreement',
        'features_header',
        'features',
        'description',
    ];

    /**
     * Make following attributes translatable
     *
     * @var array
     */
    public $translatable = [
        'name',
        'agreement',
        'description',
        'features_header',
        'features'
    ];

    /**
     * Cast following attributes
     *
     * @var array
     */
    protected $casts = [
        'features' => 'array',
        'params'   => 'array'
    ];

    /**
     * Append following attributes
     *
     * @var array
     */
    protected $appends = [
        'discount',
        'deductedFromBalance',
        'summary',
        'currency',
        'currencySymbol',
        'period',
        'duration',
        'frequencyLabel',
        'isFree',
        'hasTrial',
        'isRecurring',
        'agreementText',
        'hasCoupons',
        'moneyback',
        'recommended',
        'popular',
        'cardRequired',
        'comingSoon',
        'error',
        'upgradable'
    ];

    /**
     * Current error container
     *
     * @var
     */
    protected $currentError;

    /**
     * Eager load coupons
     *
     * @var array
     */
    protected $with = ['coupons'];

    /**
     * Hide coupons
     *
     * @var array
     */
    protected $hidden = [
        'coupons',
    ];

    /**
     * Discounts collection, which still needs to be calculated
     *
     * @var
     */
    protected $currentDiscounts;

    /**
     * Calculated discount, which will be applied on checkout
     *
     * @var
     */
    protected $calculatedDiscount;

    /**
     * Addons collection
     *
     * @var
     */
    protected $currentAddons;

    /**
     * The fallback subscription, who's owner will get the price difference on his balance
     *
     * @var
     */
    protected $previousSubscription = false;

    /**
     * Current user, who is going to purchase this plan
     *
     * @var
     */
    public $user;

    /**
     * Optional billing admin, who will be set to subscriptions' bill to
     *
     * @var
     */
    public $billingAdmin;

    /**
     * Current host, for which this plan is being purchased
     *
     * @var
     */
    public $host;

    /**
     * Subscription, associated with this plan
     *
     * @var
     */
    public $subscription;

    /**
     * Payment made for this plan
     *
     * @var
     */
    public $payment;

    /**
     * Order of purchase
     *
     * @var
     */
    public $order;

    /**
     * Plan constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set default current user and default host
        $this->host = $this->user = Auth::user();
    }

    /**
     * Save the model to the database.
     *
     * @param array $options
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        if (($saved = parent::save($options)) && env('WL_MODE', false)) {
            Event::wlUpdatePlanPackageData(1, Arr::except($this->toArray(), ['package', 'params']));
        }

        return $saved;
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'alias';
    }

    /**
     * Package relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function package()
    {
        return $this->morphTo();
    }

    /**
     * Coupons relation, which will be calculated and applied on checkout
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function coupons()
    {
        return $this->belongsToMany(Factory::getClass(Coupon::class), 'plan_coupons');
    }

    /**
     * Addons relation, which will be gifted to to user after successful checkout
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function addons()
    {
        return $this->belongsToMany(Factory::getClass(Coupon::class), 'plan_addons')
            ->where('coupons.redeem', Factory::getClass(CouponRedeemType::class)::INTERNAL);
    }

    /**
     * Error attribute setter
     *
     * @param $value
     */
    public function setErrorAttribute($value)
    {
        $this->currentError = $value;
    }

    /**
     * Error attribute getter
     *
     * @return mixed
     */
    public function getErrorAttribute()
    {
        return $this->currentError;
    }

    /**
     * Card required attribute setter
     *
     * @param $value
     */
    public function setCardRequiredAttribute($value)
    {
        $this->setParam('cardRequired', !empty($value));
    }

    /**
     * Card required attribute getter
     *
     * @return bool
     */
    public function getCardRequiredAttribute()
    {
        return !empty($this->getParam('cardRequired'));
    }

    /**
     * Coming soon attribute setter
     *
     * @param $value
     */
    public function setComingSoonAttribute($value)
    {
        $this->setParam('comingSoon', !empty($value));
    }

    /**
     * Coming soon attribute getter
     *
     * @return bool
     */
    public function getComingSoonAttribute()
    {
        return !empty($this->getParam('comingSoon'));
    }

    /**
     * Upgradable attribute setter
     *
     * @param $value
     */
    public function setUpgradableAttribute($value)
    {
        $this->isUpgradable = !empty($value);
    }

    /**
     * Upgradable attribute getter
     *
     * @return bool
     */
    public function getUpgradableAttribute()
    {
        return $this->isUpgradable;
    }

    /**
     * Recommended attribute setter
     *
     * @param $value
     */
    public function setRecommendedAttribute($value)
    {
        $this->setParam('recommended', !empty($value));
    }

    /**
     * Recommended attribute getter
     *
     * @return bool
     */
    public function getRecommendedAttribute()
    {
        return $this->getParam('recommended', false);
    }

    /**
     * Popular attribute setter
     *
     * @param $value
     */
    public function setPopularAttribute($value)
    {
        $this->setParam('popular', !empty($value));
    }

    /**
     * Popular attribute getter
     *
     * @return bool
     */
    public function getPopularAttribute()
    {
        return $this->getParam('popular', false);
    }

    /**
     * Moneyback attribute setter
     *
     * @param $value
     */
    public function setMoneybackAttribute($value)
    {
        $this->setParam('moneyback', !empty($value));
    }

    /**
     * Moneyback attribute setter
     *
     * @return bool
     */
    public function getMoneybackAttribute()
    {
        return $this->getParam('moneyback', false);
    }

    /**
     * Active features relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function activeFeatures()
    {
        return $this->belongsToMany(Factory::getClass(Feature::class), 'plan_features');
    }

    /**
     * All features relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function allFeatures()
    {
        return $this->hasMany(Factory::getClass(Feature::class), 'package_type', 'package_type')->orderBy('ordering');
    }

    /**
     * Price attribute getter
     *
     * @param $value
     *
     * @return string
     */
    public function getPriceAttribute($value)
    {
        // Get price from attributes and decode
        $price = $this->fromJson($value);

        // If user currency has no value, try to get default currency value, convert and return,
        // if it also does not exist, return 0
        return $price[Currency::getUserCurrency()] ?? currency($price[config('currency.default')] ?? 0, null, null,
                false);
    }

    /**
     * Price attribute setter
     *
     * @param $value
     */
    public function setPriceAttribute($value)
    {
        // Get price from attributes and decode
        $price = is_array($price = $this->fromJson($this->attributes['price'] ?? '')) ? $price : [];

        // Set user's currency value
        $price[Currency::getUserCurrency()] = $value;

        // If default currency has no value, convert amount and set as default
        $price[config('currency.default')] = $price[config('currency.default')] ??
            currency($value, Currency::getUserCurrency(), config('currency.default'), false);

        // Encode and put back price
        $this->attributes['price'] = json_encode($price);
    }

    /**
     * Currency attribute getter
     *
     * @return mixed
     */
    public function getCurrencyAttribute()
    {
        return Arr::get(Currency::getCurrency(), 'code');
    }

    /**
     * Currency symbol attribute getter
     *
     * @return mixed
     */
    public function getCurrencySymbolAttribute()
    {
        return Arr::get(Currency::getCurrency(), 'symbol');
    }

    /**
     * Additional plans relation, which will be automatically purchased
     * with purchase of this plan
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function additionalPlans()
    {
        return $this->belongsToMany(static::class, 'additional_plans', 'plan_id', 'additional_plan_id');
    }

    /**
     * Has coupons attribute getter - checks if plan has manual coupons
     *
     * @return bool
     */
    public function getHasCouponsAttribute()
    {
        foreach ($this->coupons as $coupon) {
            if ($coupon->redeem == Factory::getClass(CouponRedeemType::class)::MANUAL) {
                return true;
            }
        }

        return false;
    }

    /**
     * Discounts attribute setter
     *
     * @param \Illuminate\Support\Collection $discounts
     */
    public function setDiscountsAttribute(Collection $discounts)
    {
        $this->currentDiscounts = $discounts;
    }

    /**
     * Addon coupons attribute setter
     *
     * @param \Illuminate\Support\Collection $addons
     */
    public function setAddonCouponsAttribute(Collection $addons)
    {
        $this->currentAddons = $addons;
    }

    /**
     * Agreement text attribute getter
     *
     * @return mixed
     */
    public function getAgreementTextAttribute()
    {
        // Get the plan's and package's agreement text
        $overrideText = $this->agreement ?: $this->package->agreement;

        // Get the default text from translations
        $defaultText = $this->isRecurring ?
            trans(config('ptuchik-billing.translation_prefixes.plan').'.agreement_recurring') :
            trans(config('ptuchik-billing.translation_prefixes.plan').'.agreement_onetime');

        // If there is no override, return default text
        return $overrideText ?: $defaultText;
    }

    /**
     * Discount attribute getter, collecting all possible discounts,
     * calculating and setting as current discount
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDiscountsAttribute()
    {
        // If discounts already collected, just return
        if ($this->currentDiscounts) {

            // If there is no additional coupon input, return current discounts
            if (!Request::input('coupon')) {
                return $this->currentDiscounts;
            }

            // Create an empty discounts collection
        } else {
            $this->currentDiscounts = collect([]);
        }

        // Check if the coupon exists in the plan coupons
        if (($code = Request::input('coupon')) && !$this->coupons
                ->where('redeem', Factory::getClass(CouponRedeemType::class)::MANUAL)
                ->contains('code', $code)) {

            $this->error = trans(config('ptuchik-billing.translation_prefixes.general').'.coupon_is_invalid');
        }

        foreach ($this->coupons as $coupon) {

            // If coupon is not added to discounts collection yet and is applicate, add to collection
            if (!$this->currentDiscounts->contains('id', $coupon->id) && $coupon = $this->analizeCoupon($coupon)) {
                $this->currentDiscounts->push($coupon);
            }
        }

        return $this->currentDiscounts;
    }

    /**
     * Addon coupons attribute getter
     *
     * @return Collection
     */
    public function getAddonCouponsAttribute()
    {
        // If addons already collected, just return
        if ($this->currentAddons) {
            return $this->currentAddons;
        }

        // Create an empty addons collection, loop throught available addons and add to them
        $this->currentAddons = collect([]);
        foreach ($this->addons as $addon) {

            // If addon is not added yet, add it
            if (!$this->currentAddons->contains('id', $addon->id)) {
                $this->currentAddons->push($addon);
            }
        }

        return $this->currentAddons;
    }

    /**
     * Get previous discount for host
     *
     * @return mixed
     * @throws \Ptuchik\Billing\Exceptions\BillingException
     */
    public function getPreviousSubscription()
    {
        if ($this->previousSubscription === false) {

            // If there is a previous subscription and the current plan is recurring,
            // calculate the monthly price difference and determine
            // if it is going to be upgraded or downgraded
            if ($this->previousSubscription = $this->package->getPreviousSubscription($this->host)) {

                // If current plan is recurring
                if ($this->isRecurring) {
                    $previousPlan = $this->previousSubscription->plan;
                    $previousPrice = $previousPlan->price / $previousPlan->billingFrequency;
                    $currentPrice = $this->price / $this->billingFrequency;

                    // If current price is lower than previous price, that means it is going to be
                    // downgraded, so we have to check if it is allowed or not
                    if ($currentPrice < $previousPrice && !config('ptuchik-billing.downgrade_allowed')) {
                        throw new BillingException(trans(config('ptuchik-billing.translation_prefixes.plan').'.no_downgrade',
                            ['newpackage' => $this->package->name, 'oldpackage' => $previousPlan->package->name]));
                    }

                    // If current plan is not recurring and there is a previous subscription,
                    // but switching from recurring to lifetime is not allowed, interrupt the process
                } elseif (!config('ptuchik-billing.switch_recurring_to_lifetime_allowed')) {
                    throw new BillingException(trans(config('ptuchik-billing.translation_prefixes.plan').'.no_switch_to_lifetime'));
                }

            } elseif (!$this->isRecurring) {
                $this->previousSubscription = $this->package->setPurchase($this->host)->subscription;
            }
        }

        return $this->previousSubscription;
    }

    /**
     * Subscription balance discount attribute getter, trying to get
     * existing subscription's left balance if any, to add as discount
     *
     * @return int|mixed
     */
    public function getSubscriptionBalanceDiscountAttribute()
    {
        // If there is actual previous subscription and it is not in trial,
        // set to current plan and return it's balance
        if ($this->host) {

            $previousSubscription = $this->getPreviousSubscription();

            // If there is a previous subscription and it is not in trial, get left balance
            if ($previousSubscription && !$previousSubscription->onTrial()) {
                return $previousSubscription->balanceLeft;
            }
        }

        return 0;
    }

    /**
     * User balance discount attribute getter
     * Getting current user's balance to add to discounts
     *
     * @return int|mixed
     */
    public function getUserBalanceDiscountAttribute()
    {
        return $this->user->balance ?? 0;
    }

    /**
     * Deducted from balance attribute getter
     * Calculates how much deducted from user's balance
     *
     * @return mixed
     */
    public function getDeductedFromBalanceAttribute()
    {
        return $this->discount - $this->couponDiscount;
    }

    /**
     * Coupon discount attribute getter
     * Getting discount from coupons
     *
     * @return float|int
     */
    public function getCouponDiscountAttribute()
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
     * Discount attribute getter, getting calculated discount, based on all discounts
     *
     * @return int
     */
    public function getDiscountAttribute()
    {
        if (is_null($this->calculatedDiscount)) {

            // Get previous subscription balance as discount
            $this->calculatedDiscount = $this->subscriptionBalanceDiscount;

            // Add coupons as discount
            $this->calculatedDiscount += $this->couponDiscount;

            // Add balance as discount
            $this->calculatedDiscount += $this->userBalanceDiscount;

            if ($this->calculatedDiscount > $this->price) {
                $this->calculatedDiscount = $this->price;
            }
        }

        return $this->calculatedDiscount;
    }

    /**
     * Summary attribute getter
     *
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
     * Is recurring attribute getter
     *
     * @return bool
     */
    public function getIsRecurringAttribute()
    {
        return !empty($this->billingFrequency);
    }

    /**
     * In renew mode attribute getter
     *
     * @return bool
     */
    public function getInRenewModeAttribute()
    {
        return !$this->exists;
    }

    /**
     * Is free attribute getter
     *
     * @return string
     */
    public function getIsFreeAttribute()
    {
        return ($this->price - $this->discount) <= 0;
    }

    /**
     * Public attribute getter
     *
     * @return string
     */
    public function getPublicAttribute()
    {
        return !is_null($this->packageType);
    }

    /**
     * Has trial attribute getter
     *
     * @return bool
     */
    public function getHasTrialAttribute()
    {
        // Check if plan has trial days for current package on current host
        return !!$this->trialDays;
    }

    /**
     * Trial days attribute getter
     *
     * @param $value
     *
     * @return int
     */
    public function getTrialDaysAttribute($value)
    {
        // If plan is recurring, return trial days, otherwise return 0
        return $this->isRecurring ? $value : 0;
    }
}