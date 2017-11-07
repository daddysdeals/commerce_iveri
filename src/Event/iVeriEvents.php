<?php

namespace Drupal\commerce_iveri\Event;

/**
 * Defines events for the Commerce iVeri module.
 */
final class iVeriEvents {

  /**
   * Name of the event fired when performing the iVeri Lite Checkout requests.
   *
   * @Event
   *
   * @see \Drupal\commerce\Event\LiteCheckoutRequestEvent.php
   */
  const LITE_CHECKOUT_REQUEST = 'commerce_iveri.iveri_lite_checkout';

}
