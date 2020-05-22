<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class TransactionStatus
 *
 * @package Ptuchik\Billing\Constants
 */
class TransactionStatus extends AbstractTypes
{
    const FAILED = 0;
    const SUCCESS = 1;
    const REFUNDED = 2;
    const VOIDED = 3;
    const PENDING = 4;
}