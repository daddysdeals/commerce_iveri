<?php

namespace Drupal\commerce_iveri\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;

class LiteCheckoutForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_iveri\Plugin\Commerce\PaymentGateway\LiteCheckoutInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    print_r($payment_gateway_plugin->getConfiguration());

    die;

    $extra = [
      'return_url' => $form['#return_url'],
      'cancel_url' => $form['#cancel_url'],
      'capture' => $form['#capture'],
    ];
    
    //die;

    $order = $payment->getOrder();
    $order->setData('iveri_lite_checkout', [
      'flow' => 'iveri_lite',
      'token' => '',
      'payerid' => false,
      'capture' => $extra['capture'],
    ]);
    $order->save();

    $data = [
      'Lite_Merchant_ApplicationID' => '',
      'Lite_Order_Amount' => $payment->getAmount()->getNumber(),
      'Lite_Order_Terminal' => '',
      'Lite_Order_AuthorisationCode' => '',
      'Lite_Order_BudgetPeriod' => '',
      'Lite_Website_TextColor' => '#000000',
      'Lite_Website_BGColor' => '#ffffff',
      'Lite_AutoInvoice_Ext' => '',
      'Lite_On_Error_Resume_Next' => true,
      'DC_PAYMENT_ID' => '',
      'DC_TRANSACTION_ID' => '',
    ];

    return $this->buildRedirectForm($form, $form_state, $payment_gateway_plugin->getRedirectUrl(), $data, 'get');
  }

}
