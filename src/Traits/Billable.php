<?php

namespace Ptuchik\Billing\Traits;

use Auth;
use Currency;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Omnipay\Common\Message\ResponseInterface;
use Ptuchik\Billing\Constants\TransactionStatus;
use Ptuchik\Billing\Factory;
use Ptuchik\Billing\Models\Invoice;
use Ptuchik\Billing\Models\Order;
use Ptuchik\Billing\Models\Subscription;
use Ptuchik\Billing\Models\Transaction;
use Ptuchik\Billing\src\Traits\HasCoupons;
use Ptuchik\Billing\src\Traits\HasPaymentGateway;
use Ptuchik\CoreUtilities\Traits\HasParams;
use Request;
use Response;

/**
 * Trait Billable - Adds billing related methods
 *
 * @package App\Traits
 */
trait Billable
{
    use HasParams, HasPaymentGateway, HasCoupons;

    /**
     * Balance attribute getter
     *
     * @param $value
     *
     * @return string|\Torann\Currency\Currency
     */
    public function getBalanceAttribute($value)
    {
        return round(currency($value, null, null, false), 2);
    }

    /**
     * Balance attribute setter
     *
     * @param $value
     */
    public function setBalanceAttribute($value)
    {
        $this->attributes['balance'] = currency($value, Currency::getUserCurrency(), config('currency.default'), false);
    }

    /**
     * Currency attribute getter
     *
     * @param $value
     *
     * @return mixed
     */
    public function getCurrencyAttribute($value)
    {
        return empty($value) ? config('currency.default') : $value;
    }

    /**
     * Get payment customer
     *
     * @param null $gateway
     *
     * @return mixed
     */
    public function getPaymentCustomer($gateway = null)
    {
        // Get payment customer from gateway
        return $this->getPaymentGateway($gateway)->findCustomer();
    }

    /**
     * Get payment token
     *
     * @param null $gateway
     *
     * @return mixed
     */
    public function getPaymentToken($gateway = null)
    {
        // Get and return payment token for user's payment profile
        return $this->getPaymentGateway($gateway)->getPaymentToken();
    }

    /**
     * Purchase - Generic user's purchase method
     *
     * @param                                    $amount
     * @param null                               $description
     * @param \Ptuchik\Billing\Models\Order|null $order
     * @param null                               $gateway
     *
     * @return null|\Omnipay\Common\Message\ResponseInterface
     */
    public function purchase($amount, $description = null, Order $order = null, $gateway = null)
    {
        $paymentGateway = $this->getPaymentGateway($gateway);

        // Update purchase amount in order
        if ($order) {
            $order->setParam('amount', $amount);
            $order->setParam('currency', Currency::getUserCurrency());
            $order->setParam('gateway', $paymentGateway->name ?? $gateway ?? Request::input('gateway'));
            $order->setParam('request', Request::except(['nonce', 'token']));
            $order->save();
        }

        // If amount is empty, interrupt payment
        if ($amount > 0) {

            $purchase = $paymentGateway->purchase(number_format($amount, 2, '.', ''), $description, $order);

            return $this->handleRedirect($purchase, $order);
        } elseif (Request::filled('nonce')) {
            $this->createPaymentMethod(Request::input('nonce'), $gateway);
        }

        return null;
    }

    /**
     * Handle payment method redirection
     *
     * @param \Omnipay\Common\Message\ResponseInterface $payment
     * @param \Ptuchik\Billing\Models\Order|null        $order
     *
     * @return \Omnipay\Common\Message\ResponseInterface
     */
    public function handleRedirect(ResponseInterface $payment, Order $order = null)
    {
        if ($payment->isRedirect() && Auth::user()) {
            if (Request::wantsJson()) {
                if (($response = $payment->getRedirectResponse()) instanceof RedirectResponse) {
                    Response::json([
                        'order_id'     => $order->id ?? 0,
                        'redirect_url' => $payment->getRedirectUrl()
                    ])->send();
                } else {
                    Response::json([
                        'order_id' => $order->id ?? 0,
                        'form'     => $response->getContent()
                    ])->send();
                }
            } else {
                $payment->redirect();
            }
            exit;
        }

        return $payment;
    }

    /**
     * Void transaction
     *
     * @param      $reference
     * @param null $gateway
     *
     * @return mixed
     */
    public function void($reference, $gateway = null)
    {
        return $this->getPaymentGateway($gateway)->void($reference);
    }

    /**
     * Refund transaction
     *
     * @param      $reference
     * @param null $gateway
     *
     * @return mixed
     */
    public function refund($reference, $gateway = null)
    {
        return $this->getPaymentGateway($gateway)->refund($reference);
    }

    /**
     * Subscriptions
     *
     * @return mixed
     */
    public function subscriptions()
    {
        return $this->hasMany(Factory::getClass(Subscription::class))->orderBy('id', 'desc');
    }

    /**
     * Get user subscriptions
     *
     * @return mixed
     */
    public function getSubscriptions()
    {
        return $this->subscriptions()->with('user', 'host', 'purchase.package', 'purchase.reference')->get()
            ->each(function ($subscription) {
                $subscription->purchase->append('identifier');
            });
    }

    /**
     * Get paginated user subscriptions
     *
     * @param $perPage
     *
     * @return $this
     */
    public function getSubscriptionsPaginated($perPage)
    {
        return collect($this->subscriptions()->with('user', 'host', 'purchase.package', 'purchase.reference')
            ->paginate($perPage)->items())->each(function ($subscription) {
            $subscription->purchase->append('identifier');
        });
    }

    /**
     * Transactions
     *
     * @return mixed
     */
    public function transactions()
    {
        return $this->hasMany(Factory::getClass(Transaction::class))->orderBy('id', 'desc');
    }

    /**
     * Get paginated user transactions
     *
     * @param $perPage
     *
     * @return $this
     */
    public function getTransactionsPaginated($perPage)
    {
        return collect($this->transactions()->with('purchase.package', 'purchase.host', 'purchase.reference')
            ->paginate($perPage)->items())->each(function ($transaction) {
            if ($transaction->purchase) {
                $transaction->purchase->append('identifier');
            }
        });
    }

    /**
     * Create transaction
     *
     * @param \Omnipay\Common\Message\ResponseInterface $purchase
     * @param \Ptuchik\Billing\Models\Order             $order
     *
     * @return mixed|\Ptuchik\Billing\Models\Invoice
     */
    public function createTransaction(ResponseInterface $purchase, Order $order)
    {
        // Create a new transaction with collected data
        $transaction = Factory::get(Transaction::class, true);
        $transaction->setRawAttribute('name',
            trans(config('ptuchik-billing.translation_prefixes.general').'.'.$order->action));
        $transaction->user()->associate($this);
        $transaction->gateway = $order->getParam('gateway');
        $transaction->discount = $order->getParam('discount', 0);
        $transaction->summary = $order->getParam('amount', 0);
        $transaction->price = $transaction->summary + $transaction->discount;
        $transaction->currency = $order->getParam('currency');

        $transactionStatus = Factory::getClass(TransactionStatus::class);

        $transaction->data = serialize($purchase->getData() ?? '');
        $transaction->reference = $purchase->getTransactionReference();
        if ($purchase->isPending()) {
            $transaction->status = $transactionStatus::PENDING;
        } elseif ($purchase->isSuccessful()) {
            $transaction->status = $transactionStatus::SUCCESS;
            $this->balance = $this->balance + $transaction->summary;
            $this->save();
        } else {
            $transaction->status = $transactionStatus::FAILED;
        }
        $transaction->message = $purchase->getMessage();
        $transaction->save();

        // Get plan from order reference
        if ($reference = $order->reference) {
            if ($reference instanceof Subscription) {
                $plan = $reference->plan;
            } else {
                $plan = $reference;
            }
        } else {
            $plan = null;
        }

        return Factory::get(Invoice::class, true, $plan, $transaction);
    }
}