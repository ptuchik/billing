<?php

namespace Ptuchik\Billing\Models;

use Ptuchik\CoreUtilities\Models\Model;

/**
 * Class PlanFeature
 *
 * @package App
 */
class PlanFeature extends Model
{
    /**
     * Make following attributes translatable
     *
     * @var array
     */
    public $translatable = [
        'limit'
    ];

    public $timestamps = false;

    public $primaryKey = 'limit';
}