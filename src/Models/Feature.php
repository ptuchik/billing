<?php
namespace Ptuchik\Billing\Models;

use App\Factory;
use Ptuchik\CoreUtilities\Models\Model;

/**
 * Class Features
 * @package Ptuchik\Billing\Models
 */
class Feature extends Model
{
    /**
     * Cast following attributes
     * @var array
     */
    protected $casts = [
        'id' => 'integer'
    ];

    /**
     * Make following attributes translatable
     * @var array
     */
    public $translatable = [
        'title',
        'description'
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
     * Plans relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function plans()
    {
        return $this->belongsToMany(Factory::getClass(Feature::class), 'plan_features');
    }
}
