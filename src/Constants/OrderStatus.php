<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class OrderStatus
 * @package Ptuchik\Billing\Constants
 */
class OrderStatus extends AbstractTypes
{
    const PENDING = 0;
    const DONE = 1;
    const FAILED = 2;
}