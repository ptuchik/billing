<?php

namespace Ptuchik\Billing\Gateways;

use Braintree\CreditCard;
use Braintree\Exception\NotFound;
use Braintree\PayPalAccount;
use Braintree\Transaction\CreditCardDetails;
use Braintree\Transaction\PayPalDetails;
use Currency;
use Illuminate\Support\Arr;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Omnipay;
use Ptuchik\Billing\Constants\PaymentMethods;
use Ptuchik\Billing\Contracts\Billable;
use Ptuchik\Billing\Contracts\PaymentGateway;
use Ptuchik\Billing\Exceptions\BillingException;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Order;
use Ptuchik\Billing\Models\PaymentMethod;
use Ptuchik\CoreUtilities\Helpers\DataStorage;
use Throwable;

use function app;

/**
 * Class Braintree
 *
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
     *
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
        $this->gateway = Omnipay::create(Arr::get($this->config, 'driver'));
        $this->setCredentials($user->isTester() ? : !empty(Arr::get($this->config, 'testMode')));
    }

    /**
     * Set credentials
     *
     * @param       $testMode
     */
    protected function setCredentials($testMode)
    {
        $this->gateway->setMerchantId(Arr::get($this->config, $testMode ? 'sandboxMerchantId' : 'merchantId'));
        $this->gateway->setPublicKey(Arr::get($this->config, $testMode ? 'sandboxPublicKey' : 'publicKey'));
        $this->gateway->setPrivateKey(Arr::get($this->config, $testMode ? 'sandboxPrivateKey' : 'privateKey'));
        $this->gateway->setTestMode($testMode);
    }

    /**
     * Create payment profile
     *
     * @return mixed
     */
    public function createPaymentProfile()
    {
        $profile = $this->gateway->createCustomer()->setCustomerData($this->getCustomerData(false))->send()->getData();

        return $profile->customer->id;
    }

    /**
     * Update payment profile
     *
     * @return mixed
     */
    public function updatePaymentProfile()
    {
        return $this->gateway->updateCustomer()->setCustomerId($this->user->paymentProfile)
            ->setCustomerData($this->getCustomerData())->send()->getData();
    }

    /**
     * Find customer by profile
     *
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
     * @throws \Ptuchik\Billing\Exceptions\BillingException
     */
    public function createPaymentMethod(string $nonce, Order $order = null)
    {
        // Check if user can add payment method
        $this->user->canAddPaymentMethod();

        // Create a payment method on remote gateway
        $paymentMethod = $this->gateway->createPaymentMethod()
            ->setToken($nonce)
            ->setMakeDefault(true)
            ->setCustomerId($this->user->paymentProfile)
            ->send();

        if (!$paymentMethod->isSuccessful()) {
            throw new BillingException($paymentMethod->getMessage());
        }

        // Parse the result and return the payment method instance
        return $this->parsePaymentMethod($paymentMethod->getData());
    }

    /**
     * Get payment methods
     *
     * @return array
     */
    public function getPaymentMethods(): array
    {
        $paymentMethods = [];

        // Get user's all payment methods from gateway and parse the needed data to return
        foreach ($this->findCustomer()->paymentMethods as $gatewayPaymentMethod) {
            $paymentMethods[] = $this->parsePaymentMethod((object)['paymentMethod' => $gatewayPaymentMethod]);
        }

        return $paymentMethods;
    }

    /**
     * Set default payment method
     *
     * @param string $token
     *
     * @return mixed
     * @throws \Ptuchik\Billing\Exceptions\BillingException
     */
    public function setDefaultPaymentMethod(string $token)
    {
        $setDefault = $this->gateway->updatePaymentMethod()->setToken($token)->setMakeDefault(true)->send();

        if (!$setDefault->isSuccessful()) {
            throw new BillingException($setDefault->getMessage());
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
     *
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
     * @param string|null                        $descriptor
     * @param \Ptuchik\Billing\Models\Order|null $order
     *
     * @return \Omnipay\Common\Message\ResponseInterface
     */
    public function purchase($amount, string $descriptor = null, Order $order = null): ResponseInterface
    {
        /** @var DataStorage $dataStorage */
        $dataStorage = app(DataStorage::class);

        // If nonce is provided, create payment method and unset nonce
        if ($nonce = $dataStorage->get('nonce')) {
            $this->user->createPaymentMethod($nonce);
            $dataStorage->unset('nonce');
        }

        // Update customer profile
        $this->updatePaymentProfile();

        // Get payment gateway and set up purchase request with customer ID
        $purchaseData = $this->gateway->purchase()->setCustomerId($this->user->paymentProfile);

        // If existing payment method's token is provided, add paymentMethodToken attribute
        // to request
        if ($token = $dataStorage->get('token')) {
            $purchaseData->setPaymentMethodToken($token);
        }

        // Set purchase descriptor
        if ($descriptor) {
            $purchaseData->setDescriptor($this->generateDescriptor($descriptor));
        }

        // Set currency account if any
        if ($merchantId = Arr::get($this->config, 'currencies.'.Currency::getUserCurrency())) {
            $purchaseData->setMerchantAccountId($merchantId);
        }

        // Set transaction ID from $order if provided
        if ($order) {
            $purchaseData->setTransactionId($order->id);

            // If there is a plan, use plan name as purchase description
            if ($plan = $order->getPlan()) {
                $purchaseData->setDescription($plan->name);
            }
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
     * @throws \Ptuchik\Billing\Exceptions\BillingException
     */
    public function void(string $reference)
    {
        $void = $this->gateway->void()->setTransactionReference($reference)->send();

        if (!$void->isSuccessful()) {
            throw new BillingException($void->getMessage());
        }

        return $reference;
    }

    /**
     * Refund transaction
     *
     * @param string $reference
     *
     * @return mixed|string
     * @throws \Ptuchik\Billing\Exceptions\BillingException
     */
    public function refund(string $reference)
    {
        $refund = $this->gateway->refund()->setTransactionReference($reference)->send();

        if (!$refund->isSuccessful()) {
            throw new BillingException($refund->getMessage());
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
            'name'  => env('TRANSACTION_DESCRIPTOR_PREFIX').'*'.strtoupper(
                    substr(
                        $descriptor,
                        0,
                        21 - strlen(env('TRANSACTION_DESCRIPTOR_PREFIX'))
                    )
                ),
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
     *
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
            } elseif ($address = Arr::first($customer->addresses)) {
                $this->updateAddress($address->id, $billingDetails);
            }
        }

        return [
            'firstName' => $this->user->firstName,
            'lastName'  => $this->user->lastName,
            'email'     => $this->user->email,
            'company'   => $company = Arr::get($billingDetails, 'companyName', '')
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
            'company'       => Arr::get($billingDetails, 'companyName', ''),
            'streetAddress' => Arr::get($billingDetails, 'street', ''),
            'postalCode'    => Arr::get($billingDetails, 'zipCode', ''),
            'locality'      => Arr::get($billingDetails, 'city', ''),
        ];

        if ($country = Arr::get($billingDetails, 'country')) {
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
     */
    protected function parseType($type)
    {
        switch ($type) {
            case 'visa':
                return Factory::getClass(PaymentMethods::class)::VISA;
                break;
            case 'mastercard':
                return Factory::getClass(PaymentMethods::class)::MASTER_CARD;
                break;
            case 'american express':
                return Factory::getClass(PaymentMethods::class)::AMEX;
                break;
            case 'discover':
                return Factory::getClass(PaymentMethods::class)::DISCOVER;
                break;
            case 'diners club':
                return Factory::getClass(PaymentMethods::class)::DINERS_CLUB;
                break;
            default:
                return Factory::getClass(PaymentMethods::class)::CREDIT_CARD;
        }
    }
}
