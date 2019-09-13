<?php

namespace Ptuchik\Billing\Gateways;

use Braintree\CreditCard;
use Braintree\Exception\NotFound;
use Braintree\PayPalAccount;
use Braintree\Transaction\CreditCardDetails;
use Braintree\Transaction\PayPalDetails;
use Currency;
use Exception;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Omnipay;
use Ptuchik\Billing\Constants\PaymentMethods;
use Ptuchik\Billing\Contracts\Billable;
use Ptuchik\Billing\Contracts\PaymentGateway;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Order;
use Ptuchik\Billing\Models\PaymentMethod;
use Request;
use Throwable;

/**
 * Class Braintree
 * @package Ptuchik\Billing\Gateways
 */
class Braintree implements PaymentGateway
{
    public $name = 'braintree';

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
     * @param \Ptuchik\Billing\Contracts\Billable $user
     * @param array                               $config
     */
    public function __construct(Billable $user, array $config = [])
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
        $profile = $this->gateway->createCustomer()->setCustomerData($this->getCustomerData(false))->send()->getData();

        return $profile->customer->id;
    }

    /**
     * Update payment profile
     * @return mixed
     */
    public function updatePaymentProfile()
    {
        return $this->gateway->updateCustomer()->setCustomerId($this->user->paymentProfile)
            ->setCustomerData($this->getCustomerData())->send()->getData();
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
            return $this->gateway->findCustomer($this->user->refreshPaymentProfile($this->name))->send()->getData();
        }
    }

    /**
     * Create payment method
     *
     * @param string                             $nonce
     * @param \Ptuchik\Billing\Models\Order|null $order
     *
     * @return mixed
     * @throws \Exception
     */
    public function createPaymentMethod(string $nonce, Order $order = null)
    {
        // Create a payment method on remote gateway
        $paymentMethod = $this->gateway->createPaymentMethod()
            ->setToken($nonce)
            ->setMakeDefault(true)
            ->setCustomerId($this->user->paymentProfile)
            ->send();

        if (!$paymentMethod->isSuccessful()) {
            throw new Exception($paymentMethod->getMessage());
        }

        // Parse the result and return the payment method instance
        return $this->parsePaymentMethod($paymentMethod->getData());
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
            $paymentMethods[] = $this->parsePaymentMethod((object) ['paymentMethod' => $gatewayPaymentMethod]);
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
        return $this->parsePaymentMethod($setDefault->getData());
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
        try {
            return $this->gateway->clientToken()->setCustomerId($this->user->paymentProfile)->send()->getToken();

            // In case the customer ID is not valid anymore, regenerate a new one
        } catch (Throwable $exception) {
            return $this->gateway->clientToken()
                ->setCustomerId($this->user->refreshPaymentProfile($this->name))->send()->getToken();
        }
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
            $this->user->createPaymentMethod(Request::input('nonce'));
            Request::offsetUnset('nonce');
        }

        // Update customer profile
        $this->updatePaymentProfile();

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
     * @param $paymentData
     *
     * @return mixed
     */
    public function parsePaymentMethod($paymentData)
    {
        if (!empty($paymentData->paymentMethod)) {
            $paymentMethod = $paymentData->paymentMethod;
        } else {
            switch ($paymentData->paymentInstrumentType ?? '') {
                case PaymentMethods::PAYPAL_ACCOUNT;
                    if (!empty($paymentData->paypalDetails)) {
                        $paymentMethod = $paymentData->paypalDetails;
                        break;
                    } else {
                        return Factory::get(PaymentMethod::class, true);
                    }
                default;
                    if (!empty($paymentData->creditCardDetails)) {
                        $paymentMethod = $paymentData->creditCardDetails;
                        break;
                    } else {
                        return Factory::get(PaymentMethod::class, true);
                    }
            }
        }

        // Define payment method parser
        switch (get_class($paymentMethod)) {
            case CreditCardDetails::class:
            case CreditCard::class:
                $parser = 'parseBraintreeCreditCard';
                break;
            case PayPalDetails::class:
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
     * @return array
     */
    protected function parseBraintreeCreditCard($creditCard)
    {
        $paymentMethod = Factory::get(PaymentMethod::class, true);
        $paymentMethod->token = $creditCard->token;
        $paymentMethod->type = $this->parseType(strtolower($creditCard->cardType));
        $paymentMethod->last4 = $creditCard->last4;
        $paymentMethod->gateway = $this->name;
        $paymentMethod->holder = $creditCard->cardholderName;

        return $paymentMethod;
    }

    /**
     * Parse PayPal Account from Braintree response
     *
     * @param $payPalAccount
     *
     * @return array
     */
    protected function parseBraintreePayPalAccount($payPalAccount)
    {
        $paymentMethod = Factory::get(PaymentMethod::class, true);
        $paymentMethod->token = $payPalAccount->token;
        $paymentMethod->type = Factory::getClass(PaymentMethods::class)::PAYPAL_ACCOUNT;
        $paymentMethod->gateway = $this->name;
        $paymentMethod->holder = $payPalAccount->payerEmail ?? $payPalAccount->email;

        return $paymentMethod;
    }

    /**
     * Create address
     *
     * @param array $billingDetails
     *
     * @return mixed
     */
    protected function createAddress(array $billingDetails)
    {
        return $address = $this->gateway->createAddress()->setCustomerId($this->user->paymentProfile)
            ->setCustomerData($this->getBillingData($billingDetails))->send();
    }

    /**
     * Update address
     *
     * @param array $billingDetails
     *
     * @return mixed
     */
    protected function updateAddress($id, array $billingDetails)
    {
        return $address = $this->gateway->updateAddress()->setCustomerId($this->user->paymentProfile)
            ->setBillingAddressId($id)->setCustomerData($this->getBillingData($billingDetails))->send();
    }

    /**
     * Get customer data
     * @return array
     */
    protected function getCustomerData($addAddress = true)
    {
        // Add billing details
        $billingDetails = $this->user->billingDetails;

        if ($addAddress) {

            $customer = $this->findCustomer();
            if (empty($customer->addresses)) {
                $this->createAddress($billingDetails);
            } elseif ($address = array_first($customer->addresses)) {
                $this->updateAddress($address->id, $billingDetails);
            }
        }

        return [
            'firstName' => $this->user->firstName,
            'lastName'  => $this->user->lastName,
            'email'     => $this->user->email,
            'company'   => $company = array_get($billingDetails, 'companyName', '')
        ];
    }

    /**
     * Get billing data
     *
     * @param array $billingDetails
     *
     * @return array
     */
    protected function getBillingData(array $billingDetails)
    {
        $data = [
            'firstName'     => $this->user->firstName,
            'lastName'      => $this->user->lastName,
            'company'       => array_get($billingDetails, 'companyName', ''),
            'streetAddress' => array_get($billingDetails, 'street', ''),
            'postalCode'    => array_get($billingDetails, 'zipCode', ''),
            'locality'      => array_get($billingDetails, 'city', ''),
        ];

        if ($country = array_get($billingDetails, 'country')) {
            if (strlen($country) == 2) {
                $data['countryCodeAlpha2'] = strtoupper($country);
            } else {
                $data['countryName'] = $country;
            }
        }

        return $data;
    }

    /**
     * @param $type
     *
     * @return mixed
     * @throws \Exception
     */
    protected function parseType($type)
    {
        switch ($type) {
            case 'visa':
                return BillingFactory::getClass(PaymentMethods::class)::VISA;
                break;
            case 'mastercard':
                return BillingFactory::getClass(PaymentMethods::class)::MASTER_CARD;
                break;
            case 'american express':
                return BillingFactory::getClass(PaymentMethods::class)::AMEX;
                break;
            case 'discover':
                return BillingFactory::getClass(PaymentMethods::class)::DISCOVER;
                break;
            case 'diners club':
                return BillingFactory::getClass(PaymentMethods::class)::DINERS_CLUB;
                break;
            default:
                return BillingFactory::getClass(PaymentMethods::class)::CREDIT_CARD;
        }
    }
}