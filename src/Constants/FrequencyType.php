<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class FrequencyType
 * @package Ptuchik\Billing\Constants
 */
class FrequencyType extends AbstractTypes
{
    const LIFETIME = 0;
    const MONTHLY = 1;
    const YEARLY = 2;
    const MONTHS = 3;
    const YEARS = 4;
}