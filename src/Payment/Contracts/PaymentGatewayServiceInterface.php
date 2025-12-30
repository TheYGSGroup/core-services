<?php

namespace Ygs\CoreServices\Payment\Contracts;

/**
 * Payment Gateway Service Interface
 * 
 * Provides a contract for payment gateway registration that plugins can depend on
 * without being tied to a specific implementation.
 */
interface PaymentGatewayServiceInterface
{
    /**
     * Register a payment gateway
     *
     * @param PaymentGatewayInterface $gateway The payment gateway instance
     * @return void
     */
    public function register(PaymentGatewayInterface $gateway): void;

    /**
     * Get a payment gateway by name
     *
     * @param string $name The gateway name/identifier
     * @return PaymentGatewayInterface|null
     */
    public function get(string $name): ?PaymentGatewayInterface;

    /**
     * Get all registered payment gateways
     *
     * @return array Array of PaymentGatewayInterface instances
     */
    public function all(): array;

    /**
     * Check if a payment gateway is registered
     *
     * @param string $name The gateway name/identifier
     * @return bool
     */
    public function has(string $name): bool;
}

