<?php

namespace Ygs\CoreServices\Payment\Contracts;

use Lunar\Models\Order;

/**
 * Payment Gateway Interface
 * 
 * All payment gateways must implement this interface.
 */
interface PaymentGatewayInterface
{
    /**
     * Get the payment gateway identifier (e.g., 'authorize-net', 'stripe')
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Get the payment gateway display name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if the payment gateway is configured and enabled
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Process a payment for an order
     *
     * @param Order $order The order to process payment for
     * @param array $paymentData Payment data (varies by gateway)
     * @param array $billingAddress Billing address data
     * @return array Result array with 'success' boolean and additional data
     */
    public function processPayment(Order $order, array $paymentData, array $billingAddress = []): array;

    /**
     * Get payment gateway configuration for frontend
     * This is used to configure the payment form on the frontend
     *
     * @return array Configuration array (e.g., client keys, API endpoints)
     */
    public function getConfig(): array;

    /**
     * Get available payment methods for this gateway
     *
     * @return array Array of payment method identifiers (e.g., ['credit_card', 'pay_by_check'])
     */
    public function getPaymentMethods(): array;
}

