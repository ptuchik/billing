<?php

namespace Ptuchik\Billing\Traits;

use Auth;
use Currency;
use Exception;
use Omnipay\Common\Message\ResponseInterface;
use Ptuchik\Billing\Constants\CouponRedeemType;
use Ptuchik\Billing\Constants\TransactionStatus;
use Ptuchik\Billing\Contracts\Hostable;
use Ptuchik\Billing\Event;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Coupon;
use Ptuchik\Billing\Models\Invoice;
use Ptuchik\Billing\Models\Order;
use Ptuchik\Billing\Models\Transaction;
use Request;
use Throwable;

/**
 * Trait PurchaseLogic - to add purchase logic to plan model
 * @package Ptuchik\Billing\Traits
 */
trait PurchaseLogic
{
    /**
     * Analize and return coupon if applicable
     *
     * @param \Ptuchik\Billing\Models\Coupon $coupon
     *
     * @return \Ptuchik\Billing\Models\Coupon|void
     * @throws \Exception
     */
    protected function analizeCoupon(Coupon $coupon)
    {
        // Define redeem types
        $redeemTypes = Factory::getClass(CouponRedeemType::class);

        // Otherwise check and add to collection
        switch ($coupon->redeem) {
            case $redeemTypes::INTERNAL:

                // If redeem type is internal, check user's coupons and add if exists, return it
                if ($this->user && in_array($coupon->code, $this->user->getCoupons())) {
                    return $coupon;
                }
                break;
            case $redeemTypes::MANUAL:

                // If coupon is connected to referral system and user is enrolled in referral program,
                // ignore coupon
                if ($coupon->connectedToReferralSystem && $this->user && $this->user->referralId) {
                    break;
                }

                // If redeem type is manual, check if coupon code provided by user, return it
                if ($coupon->code == Request::input('coupon')) {
                    if ($coupon->numberOfCoupons <= $coupon->usedCoupons) {
                        throw new Exception(trans('general.coupon_limit_has_expired'));
                    } else {
                        return $coupon;
                    }
                }
                break;
            case $redeemTypes::AUTOREDEEM:

                // If redeem type is autoredeem, return it
                return $coupon;
            default:
                return;
        }
    }

    /**
     * Caclculate coupon usage
     */
    protected function calculateCouponUsage()
    {
        // Define redeem types
        $redeemTypes = Factory::getClass(CouponRedeemType::class);

        if (!$this->inRenewMode) {
            foreach ($this->discounts as $discount) {
                if ($discount->redeem == $redeemTypes::MANUAL && is_null($discount->numberOfCoupons)) {
                    $discount->usedCoupons = $discount->usedCoupons + 1;
                    $discount->save;
                }
            }
        }
    }

    /**
     * Calculate trial for given host
     * @return bool
     */
    public function calculateTrial()
    {
        // If plan has no trial days, just return false
        if (empty($this->trialDays)) {
            return false;
        }

        // If plan has trial days, but it's package was previously used for given host,
        // set the trial days to 0 and return false
        if ($this->package->trialConsumed($this->host)) {
            $this->trialDays = 0;

            return false;
        }

        // Finally return true
        return true;
    }

    /**
     * Prepare the plan for user
     *
     * @param \Ptuchik\Billing\Contracts\Hostable $host
     * @param bool                                $forPurchase
     *
     * @return $this
     */
    public function prepare(Hostable $host, $forPurchase = false)
    {
        // Validate host against required package and if it exists, set to plan
        if (($host = $this->package->validate($host, $this->user, $forPurchase))->exists) {
            $this->host = $host;
        }

        // Prepare package to process
        $this->package->prepare($this->host, $this);

        // Calculate the current plan's trial
        $this->calculateTrial();

        // Set purchase for current package on current host and try to get latest subscription
        $subscription = $this->package->setPurchase($this->host)->subscription;

        // Get previous subscription
        $this->getPreviousSubscription();

        // If preparation is for purchase, return subscription
        if ($forPurchase) {
            return $subscription;
        }

        // If there is already a valid subscription and it has the same billing frequency
        // return subscription's plan instead of this one
        if ($subscription && $subscription->billingFrequency == $this->billingFrequency) {
            return $subscription->plan;
        }

        return $this;
    }

    /**
     * Prepare plan and purchase
     *
     * @param \Ptuchik\Billing\Contracts\Hostable            $host
     * @param \Omnipay\Common\Message\ResponseInterface|null $payment
     * @param \Ptuchik\Billing\Models\Order|null             $order
     *
     * @return bool|mixed|\Ptuchik\Billing\Models\Invoice
     * @throws \Exception
     */
    public function purchase(Hostable $host, ResponseInterface $payment = null, Order $order = null)
    {
        // If plan is in renew mode, jump to make purchase
        if ($this->inRenewMode) {
            return $this->makePurchase($payment, $order);
        }

        // If there is an active subscription
        if ($subscription = $this->prepare($host, true)) {

            // If the current plan is recurring
            if ($this->isRecurring) {

                // If package is in use
                if ($this->package->isInUse($this->host)) {

                    // If subscription's billing frequency is the same as plan's billing frequnecy
                    if ($subscription->billingFrequency == $this->billingFrequency) {

                        // Call subscription's renew
                        return $subscription->renew($payment, $order);
                    } else {

                        // Otherwise switch subscriptions billing frequency and price
                        return $subscription->switchFrequency($this);
                    }
                } elseif (!$subscription->onTrial()) {

                    // If package is not in use and is not in trial, switch to it
                    return $this->useExistingPurchase();
                }

                // If current plan is not recurring and there is an active subscription,
                // but switching from recurring to lifetime is not allowed, interrupt the process
            } elseif (!config('ptuchik-billing.switch_recurring_to_lifetime_allowed')) {
                throw new Exception(trans(config('ptuchik-billing.translation_prefixes.plan').'.no_switch_to_lifetime'));
            }

            // If there is no active subscription but purchase is active, use the existing one
        } elseif ($this->package->purchase->active) {
            return $this->useExistingPurchase();
        }

        // Purchase plan, purchase additional plans and get invoice
        return $this->purchaseAdditionalPlans($this->makePurchase($payment, $order), $payment);
    }

    /**
     * Purchase additional plans if any
     *
     * @param \Ptuchik\Billing\Models\Invoice                $invoice
     * @param \Omnipay\Common\Message\ResponseInterface|null $payment
     *
     * @return \Ptuchik\Billing\Models\Invoice
     */
    protected function purchaseAdditionalPlans(Invoice $invoice, ResponseInterface $payment = null)
    {
        // Check if plan has additional plans, loop through them, and purchase them also
        if ($this->additionalPlans->isNotEmpty()) {
            foreach ($this->additionalPlans as $additionalPlan) {
                try {
                    $invoice->additionalInvoices[] = $additionalPlan->purchase($this->host, $payment);
                } catch (Throwable $exception) {
                    // Do nothing, as they are secondary plans
                }
            }
        }

        // Finaly return invoice
        return $invoice;
    }

    /**
     * Make a purchase
     *
     * @param \Omnipay\Common\Message\ResponseInterface|null $payment
     * @param \Ptuchik\Billing\Models\Order|null             $order
     *
     * @return bool|mixed|\Ptuchik\Billing\Models\Invoice
     */
    protected function makePurchase(ResponseInterface $payment = null, Order $order = null)
    {
        // Get summary to calculate discounts and summary
        $summary = $this->summary;

        // If no payment provided, charge user
        if (!$payment) {

            // If there is no current user, just return
            if (!$this->user) {
                return false;
            }

            // If plan has trial, no charge required
            $price = $this->hasTrial ? 0 : $summary;

            // Make payment and set the result as plan's payment
            $this->payment = $this->user->purchase($price, $this->package->descriptor, $order);
        } else {
            $this->payment = $payment;
        }

        // Set purchase to package
        $this->package->setPurchase($this->host, true);

        return $this->processPurchase();

    }

    /**
     * Process purchase
     * @return mixed|\Ptuchik\Billing\Models\Invoice
     */
    protected function processPurchase()
    {
        // Refund left amount to previous user's balance if needed
        $this->refundToUserBalance();

        // If there is no payment needed or the payment is successful activate the package
        if (!$this->payment || $this->payment->isSuccessful()) {

            // Activate package
            $this->package->activate($this->host, $this);

            // Remove user's coupons if needed
            $this->user->removeCoupons($this->discounts);

            // Increment coupon usage
            $this->calculateCouponUsage();
        }

        // If there is a successful payment, add plan's addon coupons to user coupons
        if ($this->payment && $this->payment->isSuccessful()) {
            $this->user->addCoupons($this->addonCoupons, $this, $this->host);
        }

        // Process subscription if needed
        $this->processSubscription();

        // Finally create a transaction and return it
        return $this->createTransaction();
    }

    /**
     * Process subscription if needed
     * @return mixed|null
     */
    protected function processSubscription()
    {
        if (!$this->payment || $this->payment->isSuccessful()) {

            // If the plan is recurring, start a subscription or prolong the existing
            if ($this->isRecurring) {
                $this->subscription = $this->subscribe();
            } else {

                // If the plan is not recurring, complete any active subscriptions for current host
                // and current package
                $this->unsubscribe();
            }

            // If the purchase was needed, but it fails, and the plan is recurring,
            // get the last subscription of current package on current host to
            // reference in transactions
        } elseif ($this->isRecurring && !$this->subscription) {
            $this->subscription = $this->package->purchase->subscription;
        }

        return $this->subscription;
    }

    /**
     * Create subscription
     * @return mixed
     */
    protected function subscribe()
    {
        // Subscribe to this purchase
        return $this->package->purchase->subscribe($this);
    }

    /**
     * Unsubscribe
     * @return mixed
     */
    protected function unsubscribe()
    {
        // Complete the current host's subscription for current purchase if any
        return $this->package->purchase->unsubscribe();
    }

    /**
     * Refund left amount to user's balance
     */
    protected function refundToUserBalance()
    {
        // If plan is in trial, do not continue
        if ($this->hasTrial) {
            return;
        }

        // Get the price after coupon discount
        $price = $this->price - $this->couponDiscount;

        // If there is previous subscription and so previous user
        if ($this->previousSubscription) {
            $price = $this->previousSubscription->cancelAndRefund($this, $price);
        }

        // Update current user's balance
        if (($this->user->balance = $this->user->balance - $price) < 0) {
            $this->user->balance = 0;
        }

        $this->user->save();
    }

    /**
     * Create transaction
     * @return mixed
     * @throws \Exception
     */
    protected function createTransaction()
    {
        // Create a new transaction with collected data
        $transaction = Factory::get(Transaction::class, true);
        $transaction->setRawAttribute('name', $this->package->getRawAttribute('name'));
        $transaction->purchase()->associate($this->package->purchase);
        $transaction->subscription()->associate($this->subscription);
        $transaction->user()->associate($this->user);
        $transaction->gateway = $this->user->paymentGateway;
        $transaction->price = $this->price;
        $transaction->discount = ($discount = $this->discount) > $this->price ? $this->price : $discount;
        $transaction->summary = 0;
        $transaction->currency = Currency::getUserCurrency();
        $transaction->coupons = $this->discounts;

        // If plan is free or it is in trial, fire an event and return empty invoice
        if ($this->isFree || $this->hasTrial) {
            return Factory::get(Invoice::class, true, $this, $transaction);
        }

        $transactionStatus = Factory::getClass(TransactionStatus::class);

        $transaction->data = serialize($this->payment->getData()->transaction ?? '');
        $transaction->reference = $this->payment->getTransactionReference();
        if ($this->payment->isPending()) {
            $transaction->status = $transactionStatus::PENDING;
        } elseif ($this->payment->isSuccessful()) {
            $transaction->status = $transactionStatus::SUCCESS;
        } else {
            $transaction->status = $transactionStatus::FAILED;
        }
        $transaction->message = $this->payment->getMessage();
        $transaction->summary = $this->summary;
        $transaction->save();

        // Finally if payment was not successful throw an exception with error message
        if (!$this->payment->isSuccessful()) {

            // If payment failed, fire a failed purchase event
            Event::purchaseFailed($this, $transaction);

            throw new Exception(trans(config('ptuchik-billing.translation_prefixes.general').'.payment_processor').': '.$this->payment->getMessage());
        }

        return Factory::get(Invoice::class, true, $this, $transaction);
    }

    /**
     * Activate existing purchase and return last invoice
     * @return mixed|\Ptuchik\Billing\Models\Invoice
     */
    protected function useExistingPurchase()
    {
        // Set old attribute to be used on confirmation message
        $this->setAttribute('old', true);

        // Activate package
        $this->package->activate($this->host, $this);

        // If there is a previous subscription, cancel and refund user
        if ($this->previousSubscription) {
            $this->previousSubscription->cancelAndRefund($this);
        }

        // Try to get the last successful transaction for current purchase
        // or create a blank transaction to attach to invoice
        $transaction = $this->package->purchase->transactions()
                ->where('status', Factory::getClass(TransactionStatus::class)::SUCCESS)
                ->orderBy('id', 'desc')->first() ?? Factory::get(Transaction::class);

        return Factory::get(Invoice::class, true, $this, $transaction);
    }
}