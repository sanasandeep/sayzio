<?php

namespace App\Services\Billing;

/**
 * Thrown by stub gateway adapters (razorpay/stripe/paypal/cashfree)
 * while their real implementations are out of scope for task-193.
 * The checkout flow catches this and surfaces a friendly "gateway
 * not available yet" message to the user.
 */
class NotImplementedException extends \RuntimeException {}
