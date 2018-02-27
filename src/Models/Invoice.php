<?php

namespace Ptuchik\Billing\Models;

use App\Events\PackagePurchaseSuccessEvent;
use Ptuchik\Billing\Factory;

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
     * @param Plan        $plan
     * @param Transaction $transaction
     *
     * @throws \Exception
     */
    public function __construct(Plan $plan, Transaction $transaction)
    {
        $this->old = !empty($plan->old);

        $this->id = $transaction->reference;
        $this->price = $transaction->price;
        $this->discount = $transaction->discount;
        $this->summary = $transaction->summary;
        $this->transactionId = $transaction->id;

        if ($this->summary > 0) {
            $this->confirmation = $plan->package->getPaidConfirmation($transaction);
        } elseif ($plan->hasTrial) {
            $this->confirmation = $plan->package->getTrialConfirmation($transaction, $plan->trialDays);
        } else {
            $this->confirmation = $plan->package->getFreeConfirmation($transaction);
        }

        // If it was not an old invoice, fire new successful purchase event
        if (!$this->old) {
            event(Factory::get(PackagePurchaseSuccessEvent::class, true, $plan, $transaction));
        }
    }
}