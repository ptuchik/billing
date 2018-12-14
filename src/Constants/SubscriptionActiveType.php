<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class TransactionStatus
 * @package Ptuchik\Billing\Constants
 */
class SubscriptionActiveType extends AbstractTypes
{
    const NONE = 0;
    const ACTIVE = 1;
    const PENDING = 2;
}