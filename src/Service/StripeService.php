<?php
namespace App\Service;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Customer;
use Stripe\PaymentMethod;

class StripeService
{
    public function __construct(string $stripeSecretKey)
    {
        Stripe::setApiKey($stripeSecretKey);
    }

    public function createCustomer(string $email): Customer
    {
        return Customer::create(['email' => $email]);
    }

    public function createPaymentIntent(float $amount, string $currency, string $customerId, string $paymentMethodId): PaymentIntent
    {
        return PaymentIntent::create([
            'amount' => $amount * 100, // Conversion en cents
            'currency' => strtolower($currency),
            'customer' => $customerId,
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'off_session' => true, // Important pour un paiement sans que le user saisisse son CVV à chaque fois
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],
        ]);
    }
}