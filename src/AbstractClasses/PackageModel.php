<?php

namespace Ptuchik\Billing\AbstractClassess;

use Agent;
use Ptuchik\Billing\Constants\ConfirmationType;
use Ptuchik\Billing\Constants\PlanVisibility;
use Ptuchik\Billing\Contracts\Hostable;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Confirmation;
use Ptuchik\Billing\Models\Feature;
use Ptuchik\Billing\Models\Plan;
use Ptuchik\Billing\Models\Purchase;
use Ptuchik\Billing\Models\Subscription;
use Ptuchik\Billing\Models\Transaction;
use Ptuchik\CoreUtilities\Constants\DeviceType;
use Ptuchik\CoreUtilities\Models\Model;
use Ptuchik\CoreUtilities\Traits\HasIcon;
use Ptuchik\CoreUtilities\Traits\HasParams;
use Request;

/**
 * Class PackageModel - all package models have to extend this model
 * @package Ptuchik\Billing\Models
 */
abstract class PackageModel extends Model
{
    // Use icon
    use HasIcon, HasParams;

    /**
     * Exclude following attributes from sanitizing
     * @var array
     */
    protected $unsanitized = [
        'agreement',
        'features',
        'description',
        'no_access_message',
    ];

    /**
     * Make following attributes translatable
     * @var array
     */
    public $translatable = [
        'name',
        'agreement',
        'description',
        'features'
    ];

    /**
     * Cast following attributes
     * @var array
     */
    protected $casts = [
        'id'       => 'integer',
        'public'   => 'boolean',
        'features' => 'array',
        'params'   => 'array'
    ];

    /**
     * Append following attributes
     * @var array
     */
    protected $appends = [
        'type',
        'typeName'
    ];

    /**
     * Hide following attributes and relations
     * @var array
     */
    protected $hidden = [
        'purchases',
        'permissions',
        'created_at',
        'updated_at'
    ];

    /**
     * Optional additional casts to use in child models
     * @var array
     */
    protected $additionalCasts = [];

    /**
     * Optional additional translatable attributes to use in child models
     * @var array
     */
    protected $additionalTranslatable = [];

    /**
     * Package specific validations
     * @var array
     */
    protected $validations = [];

    /**
     * Referenced purchase
     * @var
     */
    public $purchase;

    /**
     * Get the route key for the model.
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'alias';
    }

    /**
     * Get the casts array.
     * @return array
     */
    public function getCasts() : array
    {
        $this->translatable = array_merge($this->translatable, $this->additionalTranslatable);
        $this->casts = array_merge($this->casts, $this->additionalCasts);

        return parent::getCasts();
    }

    /**
     * Get package descriptor for purchase to show on transaction statement
     * @return mixed
     */
    public function getDescriptorAttribute()
    {
        return str_replace('_', '-', $this->alias);
    }

    /**
     * Get validations
     * @return array
     */
    public function getValidationsAttribute()
    {
        return $this->validations;
    }

    /**
     * Validate requested package against host to process
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     * @param bool                                $forPurchase
     *
     * @return \Ptuchik\Billing\Contracts\Hostable
     */
    public function validate(Hostable $host, $forPurchase = false)
    {
        return $host;
    }

    /**
     * Optionally prepare package to process
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     * @param \Ptuchik\Billing\Models\Plan        $plan
     *
     * @return mixed
     */
    public function prepare(Hostable $host, Plan $plan)
    {
        return $this->addModifier($plan);
    }

    /**
     * All plans relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function allPlans()
    {
        return $this->morphMany(Factory::getClass(Plan::class), 'package')
            ->orderBy('plans.id', 'asc');
    }

    /**
     * Plans relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function plans()
    {
        // Get all visible plans for package
        $query = $this->allPlans()->where(function ($query) {
            $query->where('plans.visibility', Factory::getClass(PlanVisibility::class)::VISIBLE);

            // If additional plans are requested, include them also
            if (Request::filled('additional')) {
                $query->orWhere(function ($query) {
                    $query->whereIn('plans.alias', explode(',', Request::input('additional')));
                    $query->where('plans.visibility', Factory::getClass(PlanVisibility::class)::HIDDEN);
                });
            }
        });

        // If frequncy is requested, get only plans with matching frequencies
        if (Request::filled('frequency')) {
            $query->where('plans.billing_frequency', Request::input('frequency'));
        }

        // Order by ordering and return result query
        return $query->orderBy('plans.ordering', 'asc')
            ->orderBy('plans.billing_frequency', 'desc');
    }

    /**
     * Get purchase identifier, to show on front end
     *
     * @param \Ptuchik\Billing\Models\Purchase $purchase
     *
     * @return mixed
     */
    public function getPurchaseIdentifier(Purchase $purchase)
    {
        return $purchase->host ? $purchase->host->getRouteKey() : $this->name;
    }

    /**
     * Calculate and set current attribute on plans. Needs to call purchases
     * and subscriptions relations on this package for desired host
     * to have correct results here
     * @return $this
     */
    public function setCurrentPlan()
    {
        // Loop through each plan and check if there is a current one
        $this->plans->each(function ($plan) {

            // Set current to false
            $current = false;

            // If there is an active subscription for current package and it is activated with current plan,
            // set it as current
            if (($purchase = $this->purchases->first()) && $subscription = $purchase->subscription) {

                if ($subscription->alias == $plan->alias) {

                    // If current plan's subscription is in trial, set left trial days,
                    // otherwise just set true
                    $current = $subscription->onTrial() ? $subscription->daysLeft : true;
                }
            }
            $plan->setAttribute('current', $current);
        });

        return $this;
    }

    /**
     * Has permissions attribute getter
     * @return bool
     */
    public function getHasPermissionsAttribute()
    {
        return method_exists($this, 'permissions');
    }

    /**
     * Type attribute getter - getting package snake_cased type from class
     * @return \Illuminate\Contracts\Translation\Translator|mixed|string
     */
    public function getTypeAttribute()
    {
        return snake_case(class_basename(static::class));
    }

    /**
     * Type name attribute getter - getting type name translated
     * @return \Illuminate\Contracts\Translation\Translator|mixed|string
     */
    public function getTypeNameAttribute()
    {
        return trans(config('ptuchik-billing.translation_prefixes.general').'.'.$this->type);
    }

    /**
     * Calculate package trial and apply to plans
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return $this
     */
    public function calculateTrial(Hostable $host)
    {
        // Check if trial consumed on given host
        if ($this->trialConsumed($host)) {

            // Loop through all related plans and set their trial days to 0
            $this->plans->each(function ($plan) {
                $plan->trialDays = 0;
            });
        }

        return $this;
    }

    /**
     * Get only publics
     *
     * @param $query
     *
     * @return mixed
     */
    public function scopePublics($query)
    {
        return $query->where('public', 1);
    }

    /**
     * Purchases relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function purchases()
    {
        return $this->morphMany(Factory::getClass(Purchase::class), 'package')->orderBy('id', 'desc');
    }

    /**
     * Subscriptions relation
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function subscriptions()
    {
        return $this->hasManyThrough(Factory::getClass(Subscription::class), Factory::getClass(Purchase::class),
            'package_id')->where('package_type', $this->getMorphClass())->where('subscriptions.active', 1)
            ->orderBy('id', 'desc');
    }

    /**
     * Check if trial consumed
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return bool
     */
    public function trialConsumed(Hostable $host)
    {
        // Check if purchase exists for current package on current host
        return $this->setPurchase($host)->exists;
    }

    /**
     * Optionally add modifier to package
     *
     * @param \Ptuchik\Billing\Models\Plan $plan
     *
     * @return $this
     */
    protected function addModifier(Plan $plan)
    {
        $this->setRawAttribute('name', $plan->getRawAttribute('name'));

        return $this;
    }

    /**
     * Check if current package is in use
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return bool
     */
    public function isInUse(Hostable $host)
    {
        return true;
    }

    /**
     * Try to get fallback purchase for host, on main package's purchase deactivation
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return mixed
     */
    public function getFallbackPurchase(Hostable $host)
    {
        return null;
    }

    /**
     * Try to get previous subscription, in case of upgrade to another package of current type
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return \Ptuchik\Billing\Models\Subscription|null
     */
    public function getPreviousSubscription(Hostable $host)
    {
        // If there is a fallback purchase, return it's active
        // subscription if any, otherwise return null
        return ($purchase = $this->getFallbackPurchase($host)) ? $purchase->subscription : null;
    }

    /**
     * Get purchase
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return mixed|\Ptuchik\Billing\Models\Purchase
     */
    public function getPurchase(Hostable $host)
    {
        // Try to get an existing purchase for current package on current host,
        // and if not found create one
        if (!$host || !$purchase = $host->purchases()->where('package_id', $this->id)
                ->where('package_type', $this->getMorphClass())->first()) {

            $purchase = Factory::get(Purchase::class, true);
            $purchase->setRawAttribute('name', $this->getRawAttribute('name'));
            $purchase->data = $this;
            $purchase->active = false;
            $purchase->host()->associate($host);
            $purchase->package()->associate($this);
        }

        return $purchase;
    }

    /**
     * Set current purchase
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     * @param bool                                $save
     *
     * @return $this
     */
    public function setPurchase(Hostable $host, $save = false)
    {
        // Set the purchase for current package on current host if it is not set yet
        if (!$this->purchase) {
            $this->purchase = $this->getPurchase($host);
        }

        // Optionally save it and return for further use
        if ($save) {
            $this->purchase->save();
        }

        return $this->purchase;
    }

    /**
     * Activate purchase
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return mixed
     */
    protected function activatePurchase(Hostable $host)
    {
        // Set purchase
        $this->setPurchase($host);

        // Activate it and return
        $this->purchase->active = true;
        $this->purchase->save();

        return $this->purchase;
    }

    /**
     * Expire purchase
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return mixed
     */
    protected function expirePurchase(Hostable $host)
    {
        // Set purchase
        $this->setPurchase($host);

        // Deactivate it and return
        $this->purchase->active = false;
        $this->purchase->save();

        // Get latest subscription of the purchase and complete it's billings
        if ($subscription = $this->purchase->subscription) {
            $subscription->deactivate();
        }

        return $this->purchase;
    }

    /**
     * If there is a payment, refund the user
     *
     * @param \Ptuchik\Billing\Models\Plan $plan
     *
     * @return mixed
     */
    protected function refund(Plan $plan)
    {
        if ($plan->payment) {
            switch ($plan->payment->getCode()) {
                case 'authorized':
                case 'submitted_for_settlement':
                case 'settlement_pending':
                    return $plan->user->void($plan->payment->getTransactionReference());
                default:
                    return $plan->user->refund($plan->payment->getTransactionReference());
            }
        }
    }

    /**
     * Activate package
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     * @param \Ptuchik\Billing\Models\Plan        $plan
     *
     * @return mixed
     */
    public function activate(Hostable $host, Plan $plan)
    {
        // Generic activation method for creating purchase
        return $this->activatePurchase($host);
    }

    /**
     * Activate package if it is in use by host
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     */
    protected function activateInUsePackage(Hostable $host)
    {

    }

    /**
     * Activate package if it is not in use by host
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     */
    protected function activateNotInUsePackage(Hostable $host)
    {

    }

    /**
     * Deactivate package
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     *
     * @return mixed
     */
    public function deactivate(Hostable $host)
    {
        // Generic deactivation method for expiring purchase
        return $this->expirePurchase($host);
    }

    /**
     * Deactivate package if it is in use by host
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     */
    protected function deactivateInUsePackage(Hostable $host)
    {

    }

    /**
     * Deactivate package if it is not in use by host
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     */
    protected function deactivateNotInUsePackage(Hostable $host)
    {

    }

    /**
     * Has many confirmations
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function confirmations()
    {
        return $this->morphMany(Factory::getClass(Confirmation::class), 'package');
    }

    /**
     * Get purchase confirmation
     *
     * @param \Ptuchik\Billing\Models\Transaction $transaction
     * @param                                     $type
     * @param bool                                $trialDays
     *
     * @return $this
     */
    protected function getConfirmation(Transaction $transaction, $type, $trialDays = false)
    {
        $device = Agent::isMobile() ? DeviceType::MOBILE : DeviceType::DESKTOP;

        // Try to get confirmation override
        $confirmations = $this->confirmations()->where('type', $type)->where(function ($query) use ($device) {
            $query->where('device', $device);
            $query->orWhere('device', DeviceType::ALL);
        })->get();

        // If there is no override, get the global confirmation
        if ($confirmations->isEmpty()) {
            $confirmation = Factory::getClass(Confirmation::class)::whereNull('package_type')->whereNull('package_id')
                ->where('type', $type)->first();

            // Otherwise try to get override for specific device or fallback to override for all devices
        } elseif (!$confirmation = $confirmations->where('device', $device)->first()) {
            $confirmation = $confirmations->where('device', DeviceType::ALL)->first();
        }

        // Parse and return
        return $confirmation->parse($this->getConfirmationReplacements($transaction, $trialDays));
    }

    /**
     * Get trial confirmation for package
     *
     * @param \Ptuchik\Billing\Models\Transaction $transaction
     * @param                                     $trialDays
     *
     * @return \Ptuchik\Billing\AbstractClassess\PackageModel
     */
    public function getTrialConfirmation(Transaction $transaction, $trialDays)
    {
        return $this->getConfirmation($transaction, Factory::getClass(ConfirmationType::class)::TRIAL, $trialDays);
    }

    /**
     * Get free confirmation for package
     *
     * @param \Ptuchik\Billing\Models\Transaction $transaction
     *
     * @return \Ptuchik\Billing\AbstractClassess\PackageModel
     */
    public function getFreeConfirmation(Transaction $transaction)
    {
        return $this->getConfirmation($transaction, Factory::getClass(ConfirmationType::class)::FREE);
    }

    /**
     * Get paid confirmation for package
     *
     * @param \Ptuchik\Billing\Models\Transaction $transaction
     *
     * @return \Ptuchik\Billing\AbstractClassess\PackageModel
     */
    public function getPaidConfirmation(Transaction $transaction)
    {
        return $this->getConfirmation($transaction, Factory::getClass(ConfirmationType::class)::PAID);
    }

    /**
     * Get variables key => value pair array for confirmation to be replaced before rendering
     *
     * @param \Ptuchik\Billing\Models\Transaction $transaction
     * @param int                                 $trialDays
     *
     * @return array
     */
    public function getConfirmationReplacements(Transaction $transaction, $trialDays = 0)
    {
        // Define replacements and return
        return [
            'host'      => $this->purchase->host ? $this->purchase->host->getRouteKey() : $this->name,
            'amount'    => $transaction->amount,
            'package'   => $this->name,
            'reference' => $this->purchase->reference ? $this->purchase->reference->getRouteKey() : $this->name,
            'days'      => $trialDays
        ];
    }

    /**
     * Delete package
     * @return bool|null
     */
    public function delete()
    {
        // Delete all associated plans
        Factory::getClass(Plan::class)::where('package_id', $this->id)
            ->where('package_type', $this->getMorphClass())->delete();

        // Delete all associated confirmations
        Factory::getClass(Confirmation::class)::where('package_id', $this->id)
            ->where('package_type', $this->getMorphClass())->delete();

        return parent::delete();
    }

    /**
     * Package Features relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\morphToMany
     */
    public function features()
    {
        return $this->morphToMany(Factory::getClass(Feature::class), 'package', 'package_features');
    }
}
