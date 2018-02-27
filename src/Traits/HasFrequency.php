<?php

namespace Ptuchik\Billing\Traits;

/**
 * Trait HasFrequency - adds period and duration to model
 * @package Ptuchik\Billing\Traits
 */
trait HasFrequency
{
    /**
     * Period attribute getter
     *
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function getPeriodAttribute()
    {
        if (empty($this->billingFrequency)) {
            return trans(config('ptuchik-billing.translation_prefixes.general').'.lifetime');
        } elseif ($this->billingFrequency == 1) {
            return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.month');
        } elseif ($this->billingFrequency == 12) {
            return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.year');
        } elseif ($this->billingFrequency % 12 == 0) {
            return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.years',
                ['years' => $this->billingFrequency / 12]);
        } else {
            return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.months',
                ['months' => $this->billingFrequency]);
        }
    }

    /**
     * Duration attribute getter
     *
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function getDurationAttribute()
    {
        if (empty($this->billingFrequency)) {
            return trans(config('ptuchik-billing.translation_prefixes.general').'.lifetime');
        } elseif ($this->billingFrequency == 1) {
            return '1 '.trans(config('ptuchik-billing.translation_prefixes.general').'.duration.month');
        } elseif ($this->billingFrequency == 12) {
            return '1 '.trans(config('ptuchik-billing.translation_prefixes.general').'.duration.year');
        } elseif ($this->billingFrequency % 12 == 0) {
            return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.years',
                ['years' => $this->billingFrequency / 12]);
        } else {
            return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.months',
                ['months' => $this->billingFrequency]);
        }
    }
}