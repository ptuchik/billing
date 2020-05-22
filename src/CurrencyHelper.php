<?php

namespace Ptuchik\Billing;

use Carbon\Carbon;
use Currency;
use Exception;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Class CurrencyHelper
 *
 * @package Ptuchik\Billing
 */
class CurrencyHelper
{
    /**
     * Currency instance
     *
     * @var \Torann\Currency\Currency
     */
    protected $currency;

    /**
     * Currency storage instance
     *
     * @var \Torann\Currency\Contracts\DriverInterface
     */
    protected $storage;

    /**
     * All installable currencies.
     *
     * @var array
     */
    protected $currencies;

    /**
     * CurrencyHelper constructor.
     */
    public function __construct()
    {
        $this->currency = app('currency');
        $this->storage = $this->currency->getDriver();
        $this->currencies = include(base_path('vendor/torann/currency/resources/currencies.php'));
    }

    /**
     * Add currency to storage.
     *
     * @param $currency
     *
     * @return bool
     * @throws \Exception
     */
    public function add($currency)
    {
        if (Currency::getCurrency($currency)) {
            return true;
        }

        if (($data = $this->getCurrency($currency)) === null) {
            throw new Exception(trans(config('ptuchik-billing.translation_prefixes.general').'.currency_not_found'));
        }

        $data['code'] = $currency;
        $data['active'] = 1;
        unset($data['exchange_rate']);

        $result = $this->storage->create($data);

        if (is_string($result) && $result != 'exists') {
            throw new Exception(trans(config('ptuchik-billing.translation_prefixes.general').'.could_not_add_currency'));
        } else {

            try {
                return $this->updateRates($currency);
            } catch (Throwable $exception) {
                //
            }

            return $this->clearCache();
        }
    }

    /**
     * Update currency in storage.
     *
     * @param       $currency
     * @param array $data
     *
     * @return bool
     * @throws \Exception
     */
    public function update($currency, array $data)
    {
        if (is_string($result = $this->storage->update($currency, $data))) {
            throw new Exception(trans(config('ptuchik-billing.translation_prefixes.general').'.could_not_update_currency'));
        } else {
            return $this->clearCache();
        }
    }

    /**
     * Update currency rates
     *
     * @param null $currency
     *
     * @return bool
     * @throws \Exception
     */
    public function updateRates($currency = null)
    {
        $localCurrencies = $currency ? [$currency] : array_keys($this->storage->all());

        $data = [
            'base'    => config('currency.default'),
            'symbols' => implode(',', $localCurrencies)
        ];

        // Try to get currency rates from fixer.io
        try {
            $remoteCurrencies = json_decode(file_get_contents('https://api.fixer.io/latest?'.http_build_query($data)));

            $rates = $remoteCurrencies->rates;
            $date = Carbon::createFromFormat('Y-m-d', $remoteCurrencies->date);

            // Update base currency
            $this->currency->getDriver()->update(config('currency.default'), [
                'exchange_rate' => 1,
                'updated_at'    => $date->format('Y-m-d H:i:s'),
            ]);

            // Update other currencies
            foreach ($localCurrencies as $currency) {
                if (empty($rates->$currency)) {
                    continue;
                }

                $this->currency->getDriver()->update($currency, [
                    'exchange_rate' => $rates->$currency,
                    'updated_at'    => $date->format('Y-m-d H:i:s'),
                ]);
            }

            return $this->clearCache();
        } catch (Throwable $exception) {
            throw new Exception(trans(config('ptuchik-billing.translation_prefixes.general').'.could_not_update_currencies'));
        }
    }

    /**
     * Delete currency from storage.
     *
     * @param $currency
     *
     * @return bool
     * @throws \Exception
     */
    public function delete($currency)
    {
        if (is_string($result = $this->storage->delete($currency))) {
            throw new Exception(trans(config('ptuchik-billing.translation_prefixes.general').'.could_not_delete_currency'));
        } else {
            return $this->clearCache();
        }
    }

    /**
     * Get all currency rates
     */
    public function getAllRates()
    {
        $rates = [];
        foreach ($this->storage->all() as $code => $currency) {
            $rates[$code] = $currency['exchange_rate'];
        }

        return $rates;
    }

    /**
     * Get currency argument.
     *
     * @param $currency
     *
     * @return array
     */
    protected function getParseRequestedCurrency($currency)
    {
        // Get the user entered value
        $value = preg_replace('/\s+/', '', $currency);

        // Return all currencies if requested
        if ($value === 'all') {
            return array_keys($this->currencies);
        }

        return explode(',', $value);
    }

    /**
     * Get currency data.
     *
     * @param $currency
     *
     * @return mixed
     */
    protected function getCurrency($currency)
    {
        return Arr::get($this->currencies, $currency);
    }

    /**
     * Clear cached currencies
     */
    protected function clearCache()
    {
        app('cache.store')->forget('torann.currency');

        return true;
    }
}