<?php

namespace Ptuchik\Billing\Traits;

use Ptuchik\Billing\Constants\FrequencyType;
use Ptuchik\Billing\Factory;

/**
 * Trait HasFrequency - adds period and duration to model
 *
 * @package Ptuchik\Billing\Traits
 */
trait HasFrequency
{
    protected $frequencyType;

    protected $years = 0;

    /**
     * Period attribute getter
     *
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function getPeriodAttribute()
    {
        $frequencyTypes = Factory::getClass(FrequencyType::class);
        switch ($this->parseFrequency()) {
            case $frequencyTypes::LIFETIME:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.lifetime');
            case $frequencyTypes::MONTHLY:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.month');
            case $frequencyTypes::YEARLY:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.year');
            case $frequencyTypes::YEARS:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.years',
                    ['years' => $this->years]);
            default:
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
        $frequencyTypes = Factory::getClass(FrequencyType::class);
        switch ($this->parseFrequency()) {
            case $frequencyTypes::LIFETIME:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.lifetime');
            case $frequencyTypes::MONTHLY:
                return '1 '.trans(config('ptuchik-billing.translation_prefixes.general').'.duration.month');
            case $frequencyTypes::YEARLY:
                return '1 '.trans(config('ptuchik-billing.translation_prefixes.general').'.duration.year');
            case $frequencyTypes::YEARS:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.years',
                    ['years' => $this->years]);
            default:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.months',
                    ['months' => $this->billingFrequency]);
        }
    }

    /**
     * Frequency label attribute getter
     *
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function getFrequencyLabelAttribute()
    {
        $frequencyTypes = Factory::getClass(FrequencyType::class);
        switch ($this->parseFrequency()) {
            case $frequencyTypes::LIFETIME:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.lifetime');
            case $frequencyTypes::MONTHLY:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.monthly');
            case $frequencyTypes::YEARLY:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.yearly');
            case $frequencyTypes::YEARS:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.years',
                    ['years' => $this->years]);
            default:
                return trans(config('ptuchik-billing.translation_prefixes.general').'.duration.months',
                    ['months' => $this->billingFrequency]);
        }
    }

    /**
     * Parse billing frequency
     *
     * @return mixed
     */
    protected function parseFrequency()
    {
        if (is_null($this->frequencyType)) {
            if (empty($this->billingFrequency)) {
                $this->frequencyType = Factory::getClass(FrequencyType::class)::LIFETIME;
            } elseif ($this->billingFrequency == 1) {
                $this->frequencyType = Factory::getClass(FrequencyType::class)::MONTHLY;
            } elseif ($this->billingFrequency == 12) {
                $this->frequencyType = Factory::getClass(FrequencyType::class)::YEARLY;
                $this->years = 1;
            } elseif ($this->billingFrequency % 12 == 0) {
                $this->frequencyType = Factory::getClass(FrequencyType::class)::YEARS;
                $this->years = $this->billingFrequency / 12;
            } else {
                $this->frequencyType = Factory::getClass(FrequencyType::class)::MONTHS;
            }
        }

        return $this->frequencyType;
    }
}