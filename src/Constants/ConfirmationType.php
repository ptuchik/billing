<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class ConfirmationType
 * @package Ptuchik\Billing\Constants
 */
class ConfirmationType extends AbstractTypes
{
    const PAID = 1;
    const TRIAL = 2;
    const FREE = 3;
}