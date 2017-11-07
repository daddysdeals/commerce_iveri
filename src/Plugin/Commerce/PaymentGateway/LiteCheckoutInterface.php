<?php

namespace Drupal\commerce_iveri\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

/**
 * Provides the interface for the Lite Checkout payment gateway.
 */
interface LiteCheckoutInterface extends SupportsAuthorizationsInterface, SupportsRefundsInterface {
  
}
