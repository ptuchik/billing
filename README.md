# Billing
## Sell anything for everything...

Billing package for Laravel 5.5+ supporting packages, plans, coupons, addons, payments and subscriptions with multi currencies

---

### Structure:
The structure is the following:
- *Billable* - Model that will pay for everything
- *Hostable* - Model for which can be purchased everything
- *Package* - Model that can be purchased
- *Plan* - Model that will sell the *Package*
- *Reference* - Can be any model, that will be purchased with *Package*
- *Coupon* - Discount, which can be applied on *Plan* purchase

---

### Concept
The concept is the following:
To be able to use this package, firstly you need to add `Billable` trait to your billable model (usually it is *User* model).
Hostable models have to implement `Hostable` interface and use `Hostable` trait, which will add *Purchases* relation to model.
All packages have to be extended from *PackageModel* abstract class.
That's it!

P. S. Everything is overridable from configuration, provided by package

---

### Installation
```
composer require ptuchik/billing
```

After composer installation, just run `php artisan migrate` as usual, to have the additional tables added to your database and publish configurations by executing:

```
php artisan vendor:publish --provider="Torann\Currency\CurrencyServiceProvider" --tag=config
php artisan vendor:publish --provider="Ptuchik\Billing\Providers\BillingServiceProvider" --tag=config
php artisan vendor:publish --provider="Ptuchik\CoreUtilities\Providers\CoreUtilitiesServiceProvider" --tag=config
```

**IMPORTANT!**
Your billable model has to have the following attributes:
- `balance` - cast: _numeric_
- `paymentGateway` - cast: _string_
- `paymentProfiles` - cast: _array_
- `currency` - cast: _string_
- `params` - cast: _array_

and `isTester()` method, which will indicate if your billable model is tester or customer, to set the correct mode of payment gateway environment. If you don't need testers, you can just create the following method in your billable model:

```php
public function isTester() {
    return false;
}
```

---

### Usage

To get the plan details, with trial days calculation and summary (all coupons and available balance discounts applied) for current user on current host, just call:

```php
$plan->prepare($hostable); // Will return plan with all calculations applied for logged in user
```

To purchase the plan, just call:

```php
$plan->purchase($hostable); // It will do the rest automagically
```

---

### Documentation

Coming soon...

---

### Special thanks to

- [Taylor Otwell](mailto:taylor@laravel.com) for the best framework -  [Laravel](https://laravel.com/)
- [Daniel Stainback aka Torann](mailto:torann@gmail.com) for [Currencies](http://lyften.com/projects/laravel-currency/)
- [Spatie](mailto:info@spatie.be) for [Translatable package](https://github.com/spatie/laravel-translatable)
- [The League of Extraordinary Packages](http://thephpleague.com) for [Omnipay - multi-gateway payment processing library](https://omnipay.thephpleague.com/) (waiting for v3.0 ) ;)
