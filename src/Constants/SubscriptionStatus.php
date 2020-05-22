<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class SubscriptionStatus
 *
 * @package Ptuchik\Billing\Constants
 */
class SubscriptionStatus extends AbstractTypes
{
    const NONE = 0;
    const ACTIVE = 2;
    const EXPIRED = 3;
    const CANCELLED = 5;
    const TRIAL_ACTIVE = 6;
    const TRIAL_EXPIRED = 7;
    const PENDING = 8;
}