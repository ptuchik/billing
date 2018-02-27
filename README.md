# Billing
## Sell anything for everything...

Billing package for Laravel 5.5+ supporting packages, plans, coupons, addons, payments and subscriptions with multi currencies

---

## Structure:
The structure is the following:
- *Billable* - Model that will pay for everything
- *Hostable* - Model for which can be purchased everything
- *Package* - Model that can be purchased
- *Plan* - Model that will sell the *Package*
- *Reference* - Can be any model, that will be purchased with *Package*
- *Coupon* - Discount, which can be applied on *Plan* purchase

---

## Concept
The concept is the following:
To be able to use this package, firstly you need to add `Billable` trait to your billable model (usually it is *User* model).
Hostable models have to implement `Hostable` interface and use `Hostable` trait, which will add *Purchases* relation to model.

P. S. Everything is overridable from configuration, provided by package

### Usage

Coming soon...
