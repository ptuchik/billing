<?php

namespace Ptuchik\Billing\Gateways;

use App\User;
use Braintree\CreditCard;
use Braintree\Exception\NotFound;
use Braintree\PayPalAccount;
use Currency;
use Exception;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Omnipay;
use Ptuchik\Billing\Contracts\PaymentGateway;
use Ptuchik\Billing\Models\Order;
use Request;
use Omnipay\Common\Message\RequestInterface;

/**
 * Class Braintree
 * @package Ptuchik\Billing\Gateways
 */
class Braintree implements PaymentGateway
{
    /**
     * @var \Omnipay\Common\GatewayInterface
     */
    protected $gateway;

    /**
     * @var array
     */
    protected $config;

    /**
     * App\User
     * @var
     */
    protected $user;

    /**
     * Braintree constructor.
     *
     * @param \App\User $user
     * @param array     $config
     */
    public function __construct(User $user, array $config = [])
    {
        $this->config = $config;
        $this->user = $user;
        $this->gateway = Omnipay::create(array_get($this->config, 'driver'));
        $this->setCredentials($user->isTester() ?: !empty(array_get($this->config, 'testMode')));
    }

    /**
     * Set credentials
     *
     * @param       $testMode
     */
    protected function setCredentials($testMode)
    {
        $this->gateway->setMerchantId(array_get($this->config, $testMode ? 'sandboxMerchantId' : 'merchantId'));
        $this->gateway->setPublicKey(array_get($this->config, $testMode ? 'sandboxPublicKey' : 'publicKey'));
        $this->gateway->setPrivateKey(array_get($this->config, $testMode ? 'sandboxPrivateKey' : 'privateKey'));
        $this->gateway->setTestMode($testMode);
    }

    /**
     * Create payment profile
     * @return mixed
     */
    public function createPaymentProfile()
    {
        $profile = $this->gateway->createCustomer()->setCustomerData([
            'firstName' => $this->user->firstName,
            'lastName'  => $this->user->lastName,
            'email'     => $this->user->email
        ])->send()->getData();

        return $profile->customer->id;
    }

    /**
     * Find customer by profile
     * @return mixed
     */
    public function findCustomer()
    {
        try {
            return $this->gateway->findCustomer($this->user->paymentProfile)->send()->getData();
        } catch (NotFound $exception) {
            $this->user->removePaymentProfile();

            return $this->gateway->findCustomer($this->user->paymentProfile)->send()->getData();
        }
    }

    /**
     * Create payment method
     *
     * @param string $token
     *
     * @return mixed
     * @throws \Exception
     */
    public function createPaymentMethod(string $token)
    {
        // Create a payment method on remote gateway
        $paymentMethod = $this->gateway->createPaymentMethod()->setToken($token)
            ->setMakeDefault(true)->setCustomerId($this->user->paymentProfile)->send();

        if (!$paymentMethod->isSuccessful()) {
            throw new Exception($paymentMethod->getMessage());
        }

        // Parse the result and return the payment method instance
        return $this->parsePaymentMethod($paymentMethod->getData()->paymentMethod);
    }

    /**
     * Get payment methods
     * @return array
     */
    public function getPaymentMethods() : array
    {
        $paymentMethods = [];

        // Get user's all payment methods from gateway and parse the needed data to return
        foreach ($this->findCustomer()->paymentMethods as $gatewayPaymentMethod) {
            $paymentMethods[] = $this->parsePaymentMethod($gatewayPaymentMethod);
        }

        return $paymentMethods;
    }

    /**
     * Set default payment method
     *
     * @param string $token
     *
     * @return mixed
     * @throws \Exception
     */
    public function setDefaultPaymentMethod(string $token)
    {
        $setDefault = $this->gateway->updatePaymentMethod()->setToken($token)->setMakeDefault(true)->send();

        if (!$setDefault->isSuccessful()) {
            throw new Exception($setDefault->getMessage());
        }

        // Parse the result and return the payment method instance
        return $this->parsePaymentMethod($setDefault->getData()->paymentMethod);
    }

    /**
     * Delete payment method
     *
     * @param string $token
     *
     * @return mixed
     */
    public function deletePaymentMethod(string $token)
    {
        // Delete payment method from remote gateway
        return $this->gateway->deletePaymentMethod()->setToken($token)->send()->getData()->success;
    }

    /**
     * Get payment token
     * @return mixed
     */
    public function getPaymentToken()
    {
        // Get and return payment token for user's payment profile
        return $this->gateway->clientToken()->setCustomerId($this->user->paymentProfile)->send()->getToken();
    }

    /**
     * Purchase
     *
     * @param                                    $amount
     * @param string|null                        $description
     * @param \Ptuchik\Billing\Models\Order|null $order
     *
     * @return \Omnipay\Common\Message\ResponseInterface
     */
    public function purchase($amount, string $description = null, Order $order = null) : ResponseInterface
    {
        // If nonce is provided, create payment method and unset nonce
        if (Request::filled('nonce')) {
            $this->createPaymentMethod(Request::input('nonce'));
            Request::offsetUnset('nonce');
        }

        // Get payment gateway and set up purchase request with customer ID
        $purchaseData = $this->gateway->purchase()->setCustomerId($this->user->paymentProfile);

        // If existing payment method's token is provided, add paymentMethodToken attribute
        // to request
        if (Request::filled('token')) {
            $purchaseData->setPaymentMethodToken(Request::input('token'));
        }

        // Set purchase descriptor
        if ($description) {
            $purchaseData->setDescriptor($this->generateDescriptor($description));
        }

        // Set currency account if any
        if ($merchantId = array_get($this->config, 'currencies.'.Currency::getUserCurrency())) {
            $purchaseData->setMerchantAccountId($merchantId);
        }

        // Set transaction ID from $order if provided
        if ($order) {
            $purchaseData->setTransactionId($order->id);
        }

        // Set amount
        $purchaseData->setAmount($amount);

        // Finally charge user and return the gateway purchase response
        return $purchaseData->send();
    }

    /**
     * Void transaction
     *
     * @param string $reference
     *
     * @return mixed|string
     * @throws \Exception
     */
    public function void(string $reference)
    {
        $void = $this->gateway->void()->setTransactionReference($reference)->send();

        if (!$void->isSuccessful()) {
            throw new Exception($void->getMessage());
        }

        return $reference;
    }

    /**
     * Refund transaction
     *
     * @param string $reference
     *
     * @return mixed|string
     * @throws \Exception
     */
    public function refund(string $reference)
    {
        $refund = $this->gateway->refund()->setTransactionReference($reference)->send();

        if (!$refund->isSuccessful()) {
            throw new Exception($refund->getMessage());
        }

        return $reference;
    }

    /**
     * Generate transaction descriptor for payment gateway
     *
     * @param $descriptor
     *
     * @return array
     */
    protected function generateDescriptor($descriptor)
    {
        return [
            'name'  => env('TRANSACTION_DESCRIPTOR_PREFIX').'*'.strtoupper(substr($descriptor, 0,
                    21 - strlen(env('TRANSACTION_DESCRIPTOR_PREFIX')))),
            'phone' => env('TRANSACTION_DESCRIPTOR_PHONE'),
            'url'   => env('TRANSACTION_DESCRIPTOR_URL')
        ];
    }

    /**
     * Parse payment method from gateways
     *
     * @param $paymentMethod
     *
     * @return mixed
     */
    protected function parsePaymentMethod($paymentMethod)
    {
        // Define payment method parser
        switch (get_class($paymentMethod)) {
            case CreditCard::class:
                $parser = 'parseBraintreeCreditCard';
                break;
            case PayPalAccount::class:
                $parser = 'parseBraintreePayPalAccount';
                break;
            default:
                break;
        }

        // Return parsed result
        return $this->{$parser}($paymentMethod);
    }

    /**
     * Parse Credit Card from Braintree response
     *
     * @param $creditCard
     *
     * @return object
     */
    protected function parseBraintreeCreditCard($creditCard)
    {
        return (object) [
            'token'                  => $creditCard->token,
            'type'                   => 'credit_card',
            'default'                => $creditCard->default,
            'imageUrl'               => $creditCard->imageUrl,
            'createdAt'              => $creditCard->createdAt,
            'updatedAt'              => $creditCard->updatedAt,
            'bin'                    => $creditCard->bin,
            'last4'                  => $creditCard->last4,
            'cardType'               => $creditCard->cardType,
            'expirationMonth'        => $creditCard->expirationMonth,
            'expirationYear'         => $creditCard->expirationYear,
            'expired'                => $creditCard->expired,
            'customerLocation'       => $creditCard->customerLocation,
            'cardholderName'         => $creditCard->cardholderName,
            'uniqueNumberIdentifier' => $creditCard->uniqueNumberIdentifier,
            'prepaid'                => $creditCard->prepaid,
            'healthcare'             => $creditCard->healthcare,
            'debit'                  => $creditCard->debit,
            'durbinRegulated'        => $creditCard->durbinRegulated,
            'commercial'             => $creditCard->commercial,
            'payroll'                => $creditCard->payroll,
            'issuingBank'            => $creditCard->issuingBank,
            'countryOfIssuance'      => $creditCard->countryOfIssuance,
            'productId'              => $creditCard->productId,
            'description'            => $creditCard->cardType.' '.trans(config('ptuchik-billing.translation_prefixes.general').'.ending_in').' '.$creditCard->last4
        ];
    }

    /**
     * Parse PayPal Account from Braintree response
     *
     * @param $payPalAccount
     *
     * @return object
     */
    protected function parseBraintreePayPalAccount($payPalAccount)
    {
        return (object) [
            'token'              => $payPalAccount->token,
            'type'               => 'paypal_account',
            'default'            => $payPalAccount->default,
            'imageUrl'           => $payPalAccount->imageUrl,
            'createdAt'          => $payPalAccount->createdAt,
            'updatedAtAt'        => $payPalAccount->updatedAt,
            'customerId'         => $payPalAccount->customerId,
            'email'              => $payPalAccount->email,
            'billingAgreementId' => $payPalAccount->billingAgreementId,
            'isChannelInitiated' => $payPalAccount->isChannelInitiated,
            'payerInfo'          => $payPalAccount->payerInfo,
            'limitedUseOrderId'  => $payPalAccount->limitedUseOrderId,
            'description'        => $payPalAccount->email
        ];
    }
}