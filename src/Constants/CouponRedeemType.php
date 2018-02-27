<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class CouponRedeemType
 * @package Ptuchik\Billing\Constants\
 */
class CouponRedeemType extends AbstractTypes
{
    const INTERNAL = 1;
    const MANUAL = 2;
    const AUTOREDEEM = 3;
}