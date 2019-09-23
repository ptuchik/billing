<?php

namespace Ptuchik\Billing\Constants;

use Ptuchik\CoreUtilities\AbstractClasses\AbstractTypes;

/**
 * Class PaymentMethods
 * @package Ptuchik\Billing\Constants
 */
class PaymentMethods extends AbstractTypes
{
    const PAYPAL_ACCOUNT = 'paypal_account';
    const CREDIT_CARD = 'credit_card';
    const VISA = 'visa';
    const MASTER_CARD = 'master_card';
    const AMEX = 'amex';
    const ARCA = 'arca';
    const DISCOVER = 'discover';
    const DINERS_CLUB = 'diners_club';
}