<?php

namespace Ptuchik\Billing\Models;

use Ptuchik\Billing\Factory;
use Ptuchik\CoreUtilities\Models\Model;
use Ptuchik\CoreUtilities\Traits\HasParams;

/**
 * Class Order
 * @package Ptuchik\Billing\Models
 */
class Order extends Model
{
    use HasParams;

    /**
     * Cast following attributes
     * @var array
     */
    protected $casts = [
        'id'      => 'integer',
        'params'  => 'array',
        'user_id' => 'integer',
        'host_id' => 'integer',
        'plan_id' => 'integer',
    ];

    /**
     * User relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Plan relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(Factory::getClass(Plan::class));
    }

    /**
     * Host relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function host()
    {
        return $this->morphTo();
    }
}