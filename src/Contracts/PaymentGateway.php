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
     * @param array $config
     * @param bool  $testMode
     */
    public function __construct(array $config = [], bool $testMode = false);

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
     * @param $paymentProfile
     *
     * @return mixed
     */
    public function findCustomer($paymentProfile);

    /**
     * Create payment method
     *
     * @param        $paymentProfile
     * @param string $token
     *
     * @return mixed
     * @throws \Exception
     */
    public function createPaymentMethod($paymentProfile, string $token);

    /**
     * Get payment methods
     *
     * @param $paymentProfile
     *
     * @return array
     */
    public function getPaymentMethods($paymentProfile) : array;

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
    public function getPaymentToken($paymentProfile = null);

    /**
     * Prepare purchase data
     *
     * @param string      $paymentProfile
     * @param string|null $description
     *
     * @return \Omnipay\Common\Message\RequestInterface
     */
    public function preparePurchaseData($paymentProfile, string $description = null) : RequestInterface;

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