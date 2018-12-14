<?php

namespace Ptuchik\Billing\Models;

use Ptuchik\Billing\Event;

/**
 * Class Invoice
 * @package App
 */
class Invoice
{
    public $id;
    public $price = 0.00;
    public $discount = 0.00;
    public $summary = 0.00;
    public $transactionId;
    public $old = false;
    public $confirmation;
    public $additionalInvoices = [];

    /**
     * Invoice constructor.
     *
     * @param \Ptuchik\Billing\Models\Plan|null   $plan
     * @param \Ptuchik\Billing\Models\Transaction $transaction
     */
    public function __construct(Plan $plan = null, Transaction $transaction)
    {
        $this->id = $transaction->reference;
        $this->price = $transaction->price;
        $this->discount = $transaction->discount;
        $this->summary = $transaction->summary;
        $this->transactionId = $transaction->id;

        if ($plan) {
            $this->old = !empty($plan->old);

            if($plan->subscription->isPending()){
                $this->confirmation = $plan->package->getPendingConfirmation($transaction);
            }

            if ($this->summary > 0) {
                $this->confirmation = $plan->package->getPaidConfirmation($transaction);
            } elseif ($plan->hasTrial) {
                $this->confirmation = $plan->package->getTrialConfirmation($transaction, $plan->trialDays);
            } else {
                $this->confirmation = $plan->package->getFreeConfirmation($transaction);
            }

            // If it was not an old invoice, fire new successful purchase event
            if (!$this->old) {
                Event::purchaseSuccess($plan, $transaction);
            }
        }
    }
}