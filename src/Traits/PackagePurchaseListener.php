<?php

namespace Ptuchik\Billing\Traits;

use Auth;
use Carbon\Carbon;

trait PackagePurchaseListener
{
    protected $plan;

    protected $package;

    protected $transaction;

    protected $subscription;

    protected $purchase;

    protected $user;

    protected $host;

    protected $autoCharge;

    protected $trialDays = 0;

    protected function parseEvent($event)
    {
        // Set protected members
        $this->plan = $event->plan;
        $this->package = $this->plan->package;
        $this->transaction = $event->transaction;
        $this->subscription = $this->transaction->subscription;
        $this->purchase = $this->transaction->purchase;
        $this->user = $this->purchase->user;
        $this->host = $this->purchase->host;

        // If there is no user, interrupt parsing
        if (!$this->user) {
            return false;
        }

        if ($this->subscription) {
            $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->subscription->nextBillingDate);
            $creationDate = Carbon::createFromFormat('Y-m-d H:i:s', $this->subscription->createdAt);
            $this->trialDays = $this->subscription->onTrial() ? $endDate->diffInDays($creationDate) : 0;
        }

        // If there is no logged in user, that means charge was automatically
        $this->autoCharge = Auth::guest();

        return true;
    }
}