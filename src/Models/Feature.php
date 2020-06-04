<?php

namespace Ptuchik\Billing\Models;

use App\Factory;
use Ptuchik\CoreUtilities\Models\Model;
use Ptuchik\CoreUtilities\Traits\HasParams;

/**
 * Class Features
 *
 * @package Ptuchik\Billing\Models
 */
class Feature extends Model
{
    use HasParams;

    /**
     * @var array
     */
    protected $fillable = [
        'ordering'
    ];

    /**
     * Cast following attributes
     *
     * @var array
     */
    protected $casts = [
        'params' => 'array'
    ];

    /**
     * Get group with features
     *
     * @var array
     */
    protected $with = [
        'group'
    ];

    /**
     * Make following attributes translatable
     *
     * @var array
     */
    public $translatable = [
        'title',
        'description'
    ];

    /**
     * Hide dates
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Plans relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function plans()
    {
        return $this->belongsToMany(Factory::getClass(Plan::class), 'plan_features');
    }

    /**
     * Group relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(Factory::getClass(FeatureGroup::class), 'group_id');
    }
}
