<?php

namespace Drupal\commerce_iveri\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the Express Checkout request event.
 *
 * @see \Drupal\commerce_iveri\Event\CommerceiVeriEvents
 */
class LiteCheckoutRequestEvent extends Event {

  /**
   * The order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Constructs a new ExpressCheckoutRequestEvent object.
   *
   * @param array $nvp_data
   *   The NVP API data array as documented.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity, or null.
   */
  public function __construct(OrderInterface $order = NULL) {
    $this->order = $order;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   The order, or NULL if unknown.
   */
  public function getOrder() {
    return $this->order;
  }
}
