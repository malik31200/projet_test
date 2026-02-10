<?php
namespace App\Services;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;

class StripeService
{
    public function __construct(private string $stripeSecretKey)
    {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    public function createCheckoutSession(array $items, string $successUrl, string $cancelUrl, array $metadata = []): Session
    {
        $sessionData = [
            'payment_method_types' => ['card'],
            'line_items' => $items,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];
        
        if (!empty($metadata)) {
            $sessionData['metadata'] = $metadata;
        }
        
        return Session::create($sessionData);
    }

    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return PaymentIntent::retrieve($paymentIntentId);
    }

    public function retrieveCheckoutSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }

    public function createRefund(string $paymentIntentId, ?int $amount = null): \Stripe\Refund
    {
        $params = ['payment_intent' => $paymentIntentId];
        
        if ($amount !== null) {
            $params['amount'] = $amount; // Montant en centimes
        }
        
        return \Stripe\Refund::create($params);
    }
}