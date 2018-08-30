<?php

namespace Ptuchik\Billing\Traits;

use Braintree\Exception\NotFound;
use Currency;
use Ptuchik\Billing\Constants\CouponRedeemType;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Order;
use Ptuchik\Billing\Models\Plan;
use Ptuchik\Billing\Models\Subscription;
use Ptuchik\Billing\Models\Transaction;
use Exception;
use Illuminate\Support\Collection;
use Ptuchik\CoreUtilities\Traits\HasParams;
use Ptuchik\Billing\Contracts\Hostable as HostableContract;

/**
 * Trait Billable - Adds billing related methods
 * @package App\Traits
 */
trait Billable
{
    use HasParams;

    /**
     * Payment gateway
     * @var \Ptuchik\Billing\Contracts\PaymentGateway
     */
    protected $gateway;

    /**
     * Payment gateway name
     * @var string
     */
    protected $gatewayName;

    /**
     * Balance attribute getter
     *
     * @param $value
     *
     * @return string|\Torann\Currency\Currency
     */
    public function getBalanceAttribute($value)
    {
        return currency($value, null, null, false);
    }

    /**
     * Balance attribute setter
     *
     * @param $value
     */
    public function setBalanceAttribute($value)
    {
        $this->attributes['balance'] = currency($value, Currency::getUserCurrency(), config('currency.default'), false);
    }

    /**
     * Get user's payment gateway
     *
     * @param null $gateway
     *
     * @return \Ptuchik\Billing\Contracts\PaymentGateway
     * @throws \Exception
     */
    public function getPaymentGateway($gateway = null)
    {
        // If gateway is not set yet, get it from user and instantiate
        if (is_null($this->gateway)) {

            // If gateway is not provided, get user's payment gateway
            $paymentGateway = $gateway ?: $this->paymentGateway;

            // Get trimmed class name from config
            $gatewayClass = config('ptuchik-billing.gateways.'.$paymentGateway.'.class');
            $gatewayClass = $gatewayClass ? '\\'.ltrim($gatewayClass, '\\') : null;

            // If class from config exists initialize and set as current gateway
            if (class_exists($gatewayClass)) {
                $this->gateway = new $gatewayClass(config('ptuchik-billing.gateways.'.$paymentGateway, []),
                    $this->isTester());

                // Set current payment gateway
                $this->paymentGateway = $paymentGateway;

                // If does not exist and gateway was not provided call this method by passing default gateway
            } elseif (!$gateway) {
                return $this->getPaymentGateway(config('ptuchik-billing.default_gateway'));

                // In all other cases, throw an exception
            } else {
                throw new Exception(trans(config('ptuchik-billing.translation_prefixes.general').'.invalid_gateway'));
            }
        }

        // Return gateway instance
        return $this->gateway;
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
     * Payment gateway attribute getter
     *
     * @param $value
     *
     * @return mixed
     */
    public function getPaymentGatewayAttribute($value)
    {
        if (is_null($this->gatewayName)) {

            // If user has no gateway, get default gateway
            $value = empty($value) ? config('ptuchik-billing.default_gateway') : $value;

            // If current currency has limited gateways
            if ($gateways = array_wrap(config('ptuchik-billing.currency_limited_gateways.'.Currency::getUserCurrency()))) {

                // If user's gateway exists among currency limited gateways, return it
                if (in_array($value, $gateways)) {
                    $this->gatewayName = $value;

                    // Otherwise return the first gateway from the list
                } else {
                    $this->gatewayName = array_first($gateways);
                }

                // Otherwise return user's gateway
            } else {
                $this->gatewayName = $value;
            }
        }

        return $this->gatewayName;
    }

    /**
     * Payment gateway setter
     *
     * @param $value
     */
    public function setPaymentGatewayAttribute($value)
    {
        $this->attributes['payment_gateway'] = $this->gatewayName = $value;
    }

    /**
     * Currency attribute getter
     *
     * @param $value
     *
     * @return mixed
     */
    public function getCurrencyAttribute($value)
    {
        return empty($value) ? config('currency.default') : $value;
    }

    /**
     * Payment profile attribute setter
     * @return null
     */
    public function setPaymentProfileAttribute($value)
    {
        // If user is not tester, save his payment profile
        if (!$this->isTester()) {
            $paymentProfiles = $this->paymentProfiles;
            $paymentProfiles[$this->paymentGateway] = $value;
            $this->paymentProfiles = $paymentProfiles;
        }
    }

    /**
     * Payment profile attribute getter
     * @return mixed
     */
    public function getPaymentProfileAttribute()
    {
        // If user is tester, return sandbox profile
        if ($this->isTester()) {
            return env('TEST_PAYMENT_PROFILE', 'testing');
        }

        // Try to get user's payment profile
        if (is_array($this->paymentProfiles) && !empty($this->paymentProfiles[$this->paymentGateway])) {
            return $this->paymentProfiles[$this->paymentGateway];

            // If user has no payment profile, create it to continue
        } else {
            return $this->createPaymentProfile();
        }
    }

    /**
     * Remove customer's payment profile
     * @return mixed
     */
    public function removePaymentProfile()
    {
        $paymentProfiles = $this->paymentProfiles;
        unset($paymentProfiles[$this->paymentGateway]);
        $this->paymentProfiles = $paymentProfiles;
        $this->save();

        return $this->paymentProfiles;
    }

    /**
     * Get payment customer
     * @return mixed
     * @throws Exception
     */
    public function getPaymentCustomer()
    {
        try {

            // Try to get payment customer from gateway
            return $this->getPaymentGateway()->findCustomer($this->paymentProfile);

        } catch (Exception $e) {

            // If there was a not found exception, try to recreate payment profile
            if ($e instanceof NotFound) {
                $this->removePaymentProfile();

                return $this->getPaymentGateway()->findCustomer($this->paymentProfile);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Get payment token
     * @return mixed
     */
    public function getPaymentToken()
    {
        // Get and return payment token for user's payment profile
        return $this->getPaymentGateway()->getPaymentToken($this->paymentProfile);
    }

    /**
     * Create payment profile
     * @return mixed
     */
    protected function createPaymentProfile()
    {
        // Create payment profile on gateway
        $paymentProfile = $this->getPaymentGateway()->createPaymentProfile($this);

        $this->paymentProfile = $paymentProfile;
        $this->save();

        return $paymentProfile;
    }

    /**
     * Get user coupons
     * @return array
     */
    public function getCoupons()
    {
        if (is_array($coupons = $this->getParam('coupons'))) {
            return $coupons;
        }

        return [];
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

    /**
     * Purchase - Generic user's purchase method
     *
     * @param                                    $amount
     * @param null                               $description
     * @param \Ptuchik\Billing\Models\Order|null $order
     *
     * @return null
     */
    public function purchase($amount, $description = null, Order $order = null)
    {
        // If amount is empty, interrupt payment
        if (empty((float) $amount)) {
            return null;
        }

        // Prepare purchase data
        $purchaseData = $this->getPaymentGateway()->preparePurchaseData($this->paymentProfile, $description);

        // Set transaction ID from $order if provided
        if ($order) {
            $purchaseData->setTransactionId($order->id);
        }

        // Format the given amount
        $purchaseData->setAmount(number_format($amount, 2, '.', ''));

        // Finally charge user and return the gateway purchase response
        return $purchaseData->send();
    }

    /**
     * Void transaction
     *
     * @param $reference
     *
     * @return mixed
     * @throws Exception
     */
    public function void($reference)
    {
        return $this->getPaymentGateway()->void($reference);
    }

    /**
     * Refund transaction
     *
     * @param $reference
     *
     * @return mixed
     * @throws \Exception
     */
    public function refund($reference)
    {
        return $this->getPaymentGateway()->refund($reference);
    }

    /**
     * Get payment methods
     * @return array
     */
    public function getPaymentMethods()
    {
        // Get payment methods from gateway
        try {
            $paymentMethods = $this->getPaymentGateway()->getPaymentMethods($this->paymentProfile);
        } catch (Exception $e) {

            // If there was a not found exception, try to recreate payment profile
            if ($e instanceof NotFound) {
                $this->removePaymentProfile();

                $paymentMethods = $this->getPaymentGateway()->getPaymentMethods($this->paymentProfile);
            } else {
                $paymentMethods = [];
            }
        }

        // If array is not empty, set user's hasPaymentMethod = true
        $this->hasPaymentMethod = !empty($paymentMethods);
        $this->save();

        // Finally return payment methods
        return $paymentMethods;
    }

    /**
     * Get default payment method
     * @return bool
     */
    public function getDefaultPaymentMethod()
    {
        // Loop through user's payment methods and find the default to return
        foreach ($this->getPaymentMethods() as $paymentMethod) {
            if ($paymentMethod->default) {
                return $paymentMethod;
            }
        }

        return false;
    }

    /**
     * Set default payment method
     *
     * @param $token
     *
     * @return mixed
     * @throws \Exception
     */
    public function setDefaultPaymentMethod($token)
    {
        return $this->getPaymentGateway()->setDefaultPaymentMethod($token);
    }

    /**
     * Create payment method
     *
     * @param $token
     *
     * @return mixed
     * @throws \Exception
     */
    public function createPaymentMethod($token)
    {
        // Create payment method
        $paymentMethod = $this->getPaymentGateway()->createPaymentMethod($this->paymentProfile, $token);

        // Set user's hasPaymentMethod = true
        $this->hasPaymentMethod = true;
        $this->save();

        // Return payment method
        return $paymentMethod;
    }

    /**
     * Delete payment method
     *
     * @param $token
     *
     * @return array|bool
     */
    public function deletePaymentMethod($token)
    {
        // Delete payment method from remote gateway
        if ($this->getPaymentGateway()->deletePaymentMethod($token)) {

            return $this->getPaymentMethods();
        } else {
            return false;
        }
    }

    /**
     * Subscriptions
     * @return mixed
     */
    public function subscriptions()
    {
        return $this->hasMany(Factory::getClass(Subscription::class))->orderBy('id', 'desc');
    }

    /**
     * Get user subscriptions
     * @return mixed
     */
    public function getSubscriptions()
    {
        return $this->subscriptions()->with('user', 'host', 'purchase.package', 'purchase.reference')->get()
            ->each(function ($subscription) {
                $subscription->purchase->append('identifier');
            });
    }

    /**
     * Get paginated user subscriptions
     *
     * @param $perPage
     *
     * @return $this
     */
    public function getSubscriptionsPaginated($perPage)
    {
        return collect($this->subscriptions()->with('user', 'host', 'purchase.package', 'purchase.reference')
            ->paginate($perPage)->items())->each(function ($subscription) {
            $subscription->purchase->append('identifier');
        });
    }

    /**
     * Transactions
     * @return mixed
     */
    public function transactions()
    {
        return $this->hasMany(Factory::getClass(Transaction::class))->orderBy('id', 'desc');
    }

    /**
     * Get paginated user transactions
     *
     * @param $perPage
     *
     * @return $this
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
}