<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class OrderAction
 *
 * @package Ptuchik\Billing\Constants
 */
class OrderAction extends AbstractTypes
{
    const CHECKOUT = 'checkout';
    const UPGRADE = 'upgrade';
    const RENEW = 'renew';
    const ADD_PAYMENT_METHOD = 'add_payment_method';
}