<?php

namespace Ptuchik\Billing\Models;

use Ptuchik\CoreUtilities\Models\Model;
use Ptuchik\CoreUtilities\Traits\HasIcon;

/**
 * Class Confirmation
 *
 * @package Ptuchik\Billing\Models
 */
class Confirmation extends Model
{
    /**
     * Use icon
     */
    use HasIcon;

    /**
     * Make body unsamitized
     *
     * @var array
     */
    protected $unsanitized = ['body'];

    /**
     * Cast following attributes
     *
     * @var array
     */
    protected $casts = [
        'id'     => 'integer',
        'type'   => 'integer',
        'device' => 'integer'
    ];

    /**
     * Make following attributes translatable
     *
     * @var array
     */
    public $translatable = [
        'title',
        'body',
        'button',
        'url'
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
     * Package relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function package()
    {
        return $this->morphTo();
    }

    /**
     * @param array $replacements
     *
     * @return $this
     */
    public function parse(array $replacements = [])
    {
        $from = [];
        $to = [];

        // Loop through each replacement and separate them to "from" and "to" arrays
        foreach ($replacements as $key => $value) {
            $from[] = '{{'.$key.'}}';
            $to[] = $value;
        }

        // Replace title, body, button text and button URL
        $this->title = str_replace($from, $to, $this->title);
        $this->body = str_replace($from, $to, $this->body);
        $this->button = str_replace($from, $to, $this->button);
        $this->url = str_replace($from, $to, $this->url);

        return $this;
    }
}