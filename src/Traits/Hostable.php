<?php

namespace Ptuchik\Billing\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Purchase;

/**
 * Trait Hostable
 *
 * @package Ptuchik\Billing\Traits
 */
trait Hostable
{
    /**
     * Purchases relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function purchases() : MorphMany
    {
        return $this->morphMany(Factory::getClass(Purchase::class), 'host');
    }
}