<?php

namespace Ptuchik\Billing\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Interface Hostable - this contract makes model hostable
 *
 * @package Ptuchik\Billing\Contracts
 */
interface Hostable
{
    /**
     * Purchases relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function purchases() : MorphMany;
}