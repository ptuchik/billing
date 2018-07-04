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
     * Package relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function package()
    {
        return $this->morphTo();
    }

    public function features()
    {
        return $this->morphedByMany(Factory::getClass(Sites::class), 'package', 'packages_features');
    }
}