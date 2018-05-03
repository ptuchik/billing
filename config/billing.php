<?php

return [

    /**
     * Check gifted coupons
     */
    'check_gifted_coupons' => [

        // Indicates by which column to check coupon, available values are 'code', 'id'
        'by'   => env('CHECK_GIFTED_COUPONS_BY', 'code'),

        // Indicates by which entity to check coupon, available values are 'coupon', 'plan'
        'with' => env('CHECK_GIFTED_COUPONS_WITH', 'coupon'),
    ],

    /**
     * Prefixes to put before translation strings in trans() usage
     */
    'translation_prefixes' => [
        'general' => 'general',
        'plan'    => 'plan',
    ],

    /**
     * Allow plan downgrade or not
     */
    'downgrade_allowed'    => env('DOWNGRADE_ALLOWED', true),

    /**
     * Here you can override the package classes to add your custom functionality and logic
     * Leave the left part unattended and change the right part of overrides to your overriding class name
     */
    'class_overrides'      => [

        // Confirmation type constants
        \Ptuchik\Billing\Constants\ConfirmationType::class   => \Ptuchik\Billing\Constants\ConfirmationType::class,

        // Coupon's redeem type constants
        \Ptuchik\Billing\Constants\CouponRedeemType::class   => \Ptuchik\Billing\Constants\CouponRedeemType::class,

        // Plan visibility constants
        \Ptuchik\Billing\Constants\PlanVisibility::class     => \Ptuchik\Billing\Constants\PlanVisibility::class,

        // Subscription status constants
        \Ptuchik\Billing\Constants\SubscriptionStatus::class => \Ptuchik\Billing\Constants\SubscriptionStatus::class,

        // Transaction status constants
        \Ptuchik\Billing\Constants\TransactionStatus::class  => \Ptuchik\Billing\Constants\TransactionStatus::class,

        // Confirmation model
        \Ptuchik\Billing\Models\Confirmation::class          => \Ptuchik\Billing\Models\Confirmation::class,

        // Coupon model
        \Ptuchik\Billing\Models\Coupon::class                => \Ptuchik\Billing\Models\Coupon::class,

        // Gifted coupon model
        \Ptuchik\Billing\Models\GiftedCoupon::class          => \Ptuchik\Billing\Models\GiftedCoupon::class,

        // Invoice model
        \Ptuchik\Billing\Models\Invoice::class               => \Ptuchik\Billing\Models\Invoice::class,

        // Plan model
        \Ptuchik\Billing\Models\Plan::class                  => \Ptuchik\Billing\Models\Plan::class,

        // Purchase model
        \Ptuchik\Billing\Models\Purchase::class              => \Ptuchik\Billing\Models\Purchase::class,

        // Subscription model
        \Ptuchik\Billing\Models\Subscription::class          => \Ptuchik\Billing\Models\Subscription::class,

        // Transaction model
        \Ptuchik\Billing\Models\Transaction::class           => \Ptuchik\Billing\Models\Transaction::class,
    ],

    /**
     * Here you can define your event classes
     */
    'events'               => [

        // Will receive Plan and Transaction instances
        'purchase_failed'                  => null,

        // Will receive Plan and Transaction instances
        'purchase_success'                 => null,

        // Will receive Subscription instance
        'subscription_status_change'       => null,

        // Will receive Subscription instance
        'subscription_expiration_reminder' => null,
    ],

    // Default gateway if user's gateway is empty or invalid
    'default_gateway'      => 'braintree',

    /**
     * Add here gateway mappings to Omnipay gateways
     * @class  -   Payment Gateway class fully qualified name,
     *             which has to implement \Ptuchik\Billing\Contracts\PaymentGateway interface
     * @driver -   Omnipay driver class fully qualified name
     */
    'gateways'             => [
        'braintree' => [
            'class'  => \Ptuchik\Billing\Gateways\Braintree::class,
            'driver' => 'Braintree'
        ],
    ]
];
