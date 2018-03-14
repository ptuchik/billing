<?php

namespace Ptuchik\Billing\Contracts;

use App\User;
use Omnipay\Common\Message\RequestInterface;

/**
 * Interface PaymentGateway
 * @package Ptuchik\Billing\Contracts
 */
interface PaymentGateway
{
    /**
     * PaymentGateway constructor.
     *
     * @param string|null $driver
     * @param bool        $forceTestMode
     */
    public function __construct(string $driver = null, bool $forceTestMode = false);

    /**
     * Create payment profile
     *
     * @param \App\User $user
     *
     * @return mixed
     */
    public function createPaymentProfile(User $user);

    /**
     * Find customer by profile
     *
     * @param string $paymentProfile
     *
     * @return mixed
     */
    public function findCustomer(string $paymentProfile);

    /**
     * Create payment method
     *
     * @param string $paymentProfile
     * @param string $token
     *
     * @return mixed
     * @throws \Exception
     */
    public function createPaymentMethod(string $paymentProfile, string $token);

    /**
     * Get payment methods
     *
     * @param string $paymentProfile
     *
     * @return array
     */
    public function getPaymentMethods(string $paymentProfile) : array;

    /**
     * Set default payment method
     *
     * @param string $token
     *
     * @return mixed
     */
    public function setDefaultPaymentMethod(string $token);

    /**
     * Delete payment method
     *
     * @param string $token
     *
     * @return mixed
     */
    public function deletePaymentMethod(string $token);

    /**
     * Get payment token
     *
     * @param string|null $paymentProfile
     *
     * @return mixed
     */
    public function getPaymentToken(string $paymentProfile = null);

    /**
     * Prepare purchase data
     *
     * @param string      $paymentProfile
     * @param string|null $description
     *
     * @return \Omnipay\Common\Message\RequestInterface
     */
    public function preparePurchaseData(string $paymentProfile, string $description = null) : RequestInterface;

    /**
     * Void transaction
     *
     * @param string $reference
     *
     * @return mixed
     */
    public function void(string $reference);

    /**
     * Refund transaction
     *
     * @param string $reference
     *
     * @return mixed
     */
    public function refund(string $reference);

}