<?php

namespace Ptuchik\Billing;

use Ptuchik\Billing\Models\Plan;
use Ptuchik\Billing\Models\Subscription;
use Ptuchik\Billing\Models\Transaction;
use ReflectionClass;
use Throwable;

/**
 * Class Event
 *
 * @package Ptuchik\Billing
 */
class Event
{
    /**
     * Trigger given event
     *
     * @param       $event
     * @param array ...$params
     */
    public static function trigger($event, ...$params)
    {
        // If event exists, try to throw it
        if (($eventClass = config('ptuchik-billing.events.'.$event)) && class_exists($eventClass)) {
            try {
                event((new ReflectionClass($eventClass))->newInstanceArgs($params));
            } catch (Throwable $exception) {

            }
        }
    }

    /**
     * Trigger failed purchase event
     *
     * @param \Ptuchik\Billing\Models\Plan        $plan
     * @param \Ptuchik\Billing\Models\Transaction $transaction
     */
    public static function purchaseFailed(Plan $plan, Transaction $transaction)
    {
        static::trigger('purchase_failed', $plan, $transaction);
    }

    /**
     * Trigger successful purchase event
     *
     * @param \Ptuchik\Billing\Models\Plan        $plan
     * @param \Ptuchik\Billing\Models\Transaction $transaction
     */
    public static function purchaseSuccess(Plan $plan, Transaction $transaction)
    {
        static::trigger('purchase_success', $plan, $transaction);
    }

    /**
     * Trigger subscription status change event
     *
     * @param \Ptuchik\Billing\Models\Subscription $subscription
     */
    public static function subscriptionStatusChange(Subscription $subscription)
    {
        static::trigger('subscription_status_change', $subscription);
    }

    /**
     * Trigger subscription autorenew reminder event
     *
     * @param \Ptuchik\Billing\Models\Subscription $subscription
     */
    public static function subscriptionAutorenewReminder(Subscription $subscription)
    {
        static::trigger('subscription_autorenew_reminder', $subscription);
    }

    /**
     * Trigger subscription expiration reminder event
     *
     * @param \Ptuchik\Billing\Models\Subscription $subscription
     */
    public static function subscriptionExpirationReminder(Subscription $subscription)
    {
        static::trigger('subscription_expiration_reminder', $subscription);
    }
}