<?php

namespace Ptuchik\Billing\Models;

use Ptuchik\Billing\Factory;
use Ptuchik\CoreUtilities\Models\Model;

/**
 * Class FeatureGroup
 * @package Ptuchik\Billing\Models
 */
class FeatureGroup extends Model
{
    /**
     * Make following attributes translatable
     * @var array
     */
    public $translatable = [
        'title'
    ];

    /**
     * Hide dates
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Features relation
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function features()
    {
        return $this->hasMany(Factory::getClass(Feature::class), 'group_id');
    }
}