<?php

namespace Ptuchik\Billing\Traits;

use Currency;
use Ptuchik\Billing\Constants\CouponRedeemType;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Plan;
use Ptuchik\Billing\Models\Subscription;
use Ptuchik\Billing\Models\Transaction;
use Braintree\CreditCard;
use Braintree\Exception\NotFound as BraintreeNotFound;
use Braintree\PayPalAccount;
use Exception;
use Illuminate\Support\Collection;
use Omnipay;
use Request;
use Ptuchik\Billing\Contracts\Hostable as HostableContract;

/**
 * Trait Billable - Adds billing related methods
 * @package App\Traits
 */
trait Billable
{
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
     * Get user's default payment gateway
     * @return Omnipay\Common\AbstractGateway
     */
    public function getPaymentGateway()
    {
        $this->setPaymentGatewayConfig();

        return Omnipay::gateway($this->paymentGateway);
    }

    /**
     * Has payment method attribute getter
     * @return bool
     */
    public function getHasPaymentMethodAttribute()
    {
        return !empty($this->params['hasPaymentMethod']);
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
        if (isset($this->attributes['params']) && !isset($this->params['hasPaymentMethod'])) {
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
        return empty($value) ? config('laravel-omnipay.default') : $value;
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
        return empty($value) ? config('laravel-omnipay.currency') : $value;
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
            return $this->getPaymentGateway()->findCustomer($this->paymentProfile)->send()->getData();
        } catch (Exception $e) {

            // If it was Braintree's not found exception, recreate payment profile
            if ($e instanceof BraintreeNotFound) {
                $this->removePaymentProfile();

                return $this->getPaymentGateway()->findCustomer($this->paymentProfile)->send()->getData();
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
        return $this->getPaymentGateway()->clientToken()->setCustomerId($this->paymentProfile)->send()->getToken();
    }

    /**
     * Set payment gateway config based on user's account status
     */
    public function setPaymentGatewayConfig()
    {
        if ($this->isTester()) {
            Omnipay::gateway($this->paymentGateway)->setTestMode(true);
        } else {
            Omnipay::gateway($this->paymentGateway)
                ->setTestMode(config('laravel-omnipay.gateways.'.$this->paymentGateway.'.options.testMode'));
        }
    }

    /**
     * Create payment profile
     * @return mixed
     */
    protected function createPaymentProfile()
    {
        $profile = $this->getPaymentGateway()->createCustomer()->setCustomerData([
            'firstName' => $this->firstName,
            'lastName'  => $this->lastName,
            'email'     => $this->email
        ])->send()->getData();

        $paymentProfile = $profile->customer->id;

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
        if (!empty($this->params['coupons']) && is_array($this->params['coupons'])) {
            return $this->params['coupons'];
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
     * Prepare purchase data
     * @return \Omnipay\Common\Message\RequestInterface
     */
    protected function preparePurchaseData() : Omnipay\Common\Message\RequestInterface
    {
        // If nonce is provided, create payment method and unset nonce
        if (Request::filled('nonce')) {
            $this->createPaymentMethod(Request::input('nonce'));
            Request::offsetUnset('nonce');
        }

        // Get payment gateway and set up purchase request with customer ID
        $purchaseData = $this->getPaymentGateway()->purchase()->setCustomerId($this->paymentProfile);

        // If existing payment method's token is provided, add paymentMethodToken attribute
        // to request
        if (Request::filled('token')) {
            $purchaseData->setPaymentMethodToken(Request::input('token'));
        }

        // Return purchase data
        return $purchaseData;
    }

    /**
     * Generate transaction descriptor for payment gateway
     *
     * @param $descriptor
     *
     * @return array
     */
    protected function generateDescriptor($descriptor)
    {
        return [
            'name'  => env('TRANSACTION_DESCRIPTOR_PREFIX').'*'.strtoupper(substr($descriptor, 0,
                    21 - strlen(env('TRANSACTION_DESCRIPTOR_PREFIX')))),
            'phone' => env('TRANSACTION_DESCRIPTOR_PHONE'),
            'url'   => env('TRANSACTION_DESCRIPTOR_URL')
        ];
    }

    /**
     * Purchase - Generic user's purchase method
     *
     * @param      $amount
     * @param null $descriptor
     *
     * @return null|\Omnipay\Common\Message\ResponseInterface
     */
    public function purchase($amount, $descriptor = null)
    {
        // Prepare purchase date
        $purchaseData = $this->preparePurchaseData();

        // If amount is empty, interrupt payment
        if (empty((float) $amount)) {
            return null;
        }

        // Format the given amount
        $purchaseData->setAmount(number_format($amount, 2, '.', ''));

        // Set purchase descriptor
        $purchaseData->setDescriptor($this->generateDescriptor($descriptor));

        // Finally charge user and return the gateway purchase response
        return $purchaseData->send();
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
        $refund = $this->getPaymentGateway()->refund()->setTransactionReference($reference)->send();

        if (!$refund->isSuccessful()) {
            throw new Exception($refund->getMessage());
        }

        return $reference;
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
        $void = $this->getPaymentGateway()->void()->setTransactionReference($reference)->send();

        if (!$void->isSuccessful()) {
            throw new Exception($void->getMessage());
        }

        return $reference;
    }

    /**
     * Get payment methods
     * @return array
     */
    public function getPaymentMethods()
    {
        $paymentMethods = [];

        // Get user's all payment methods from gateway and parse the needed data to return
        foreach ($this->getPaymentCustomer()->paymentMethods as $gatewayPaymentMethod) {
            $paymentMethods[] = $this->parsePaymentMethod($gatewayPaymentMethod);
        }

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
        $setDefault = $this->getPaymentGateway()->updatePaymentMethod()->setToken($token)->setMakeDefault(true)->send();

        if (!$setDefault->isSuccessful()) {
            throw new Exception($setDefault->getMessage());
        }

        // Parse the result and return the payment method instance
        return $this->parsePaymentMethod($setDefault->getData()->paymentMethod);
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
        // Create a payment method on remote gateway
        $createPaymentMethod = $this->getPaymentGateway()->createPaymentMethod()->setToken($token)
            ->setMakeDefault(true)->setCustomerId($this->paymentProfile)->send();

        if (!$createPaymentMethod->isSuccessful()) {
            throw new Exception($createPaymentMethod->getMessage());
        }

        // Set if user has any payment methods
        $this->hasPaymentMethod = true;
        $this->save();

        // Parse the result and return the payment method instance
        return $this->parsePaymentMethod($createPaymentMethod->getData()->paymentMethod);
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
        if ($this->getPaymentGateway()->deletePaymentMethod()->setToken($token)->send()->getData()->success) {

            return $this->getPaymentMethods();
        } else {
            return false;
        }
    }

    /**
     * Parse payment method from gateways
     *
     * @param $paymentMethod
     *
     * @return mixed
     */
    public function parsePaymentMethod($paymentMethod)
    {
        // Define payment method parser
        switch (get_class($paymentMethod)) {
            case CreditCard::class:
                $parser = 'parseBraintreeCreditCard';
                break;
            case PayPalAccount::class:
                $parser = 'parseBraintreePayPalAccount';
                break;
            default:
                break;
        }

        // Return parsed result
        return $this->{$parser}($paymentMethod);
    }

    /**
     * Parse Credit Card from Braintree response
     *
     * @param $creditCard
     *
     * @return object
     */
    protected function parseBraintreeCreditCard($creditCard)
    {
        return (object) [
            'token'                  => $creditCard->token,
            'type'                   => 'credit_card',
            'default'                => $creditCard->default,
            'imageUrl'               => $creditCard->imageUrl,
            'createdAt'              => $creditCard->createdAt,
            'updatedAt'              => $creditCard->updatedAt,
            'bin'                    => $creditCard->bin,
            'last4'                  => $creditCard->last4,
            'cardType'               => $creditCard->cardType,
            'expirationMonth'        => $creditCard->expirationMonth,
            'expirationYear'         => $creditCard->expirationYear,
            'expired'                => $creditCard->expired,
            'customerLocation'       => $creditCard->customerLocation,
            'cardholderName'         => $creditCard->cardholderName,
            'uniqueNumberIdentifier' => $creditCard->uniqueNumberIdentifier,
            'prepaid'                => $creditCard->prepaid,
            'healthcare'             => $creditCard->healthcare,
            'debit'                  => $creditCard->debit,
            'durbinRegulated'        => $creditCard->durbinRegulated,
            'commercial'             => $creditCard->commercial,
            'payroll'                => $creditCard->payroll,
            'issuingBank'            => $creditCard->issuingBank,
            'countryOfIssuance'      => $creditCard->countryOfIssuance,
            'productId'              => $creditCard->productId,
            'description'            => $creditCard->cardType.' '.trans('general.ending_in').' '.$creditCard->last4
        ];
    }

    /**
     * Parse PayPal Account from Braintree response
     *
     * @param $payPalAccount
     *
     * @return object
     */
    protected function parseBraintreePayPalAccount($payPalAccount)
    {
        return (object) [
            'token'              => $payPalAccount->token,
            'type'               => 'paypal_account',
            'default'            => $payPalAccount->default,
            'imageUrl'           => $payPalAccount->imageUrl,
            'createdAt'          => $payPalAccount->createdAt,
            'updatedAtAt'        => $payPalAccount->updatedAt,
            'customerId'         => $payPalAccount->customerId,
            'email'              => $payPalAccount->email,
            'billingAgreementId' => $payPalAccount->billingAgreementId,
            'isChannelInitiated' => $payPalAccount->isChannelInitiated,
            'payerInfo'          => $payPalAccount->payerInfo,
            'limitedUseOrderId'  => $payPalAccount->limitedUseOrderId,
            'description'        => $payPalAccount->email
        ];
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