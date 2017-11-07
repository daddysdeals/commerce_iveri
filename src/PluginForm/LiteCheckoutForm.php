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
    $conf = $payment_gateway_plugin->getConfiguration();

    $extra = [
      'return_url' => $form['#return_url'],
      'cancel_url' => $form['#cancel_url'],
      'capture' => $form['#capture'],
    ];
    
    $order = $payment->getOrder();
    $order->save();

    $data = [
      'Lite_Merchant_ApplicationID' => ($conf['transaction_mode'] == 'live') ? $conf['live_key'] : $conf['test_key'],
      'Lite_Order_Amount' => $payment->getAmount()->getNumber(),
      'Lite_Order_Terminal' => '',
      'Lite_Order_AuthorisationCode' => '',
      'Lite_Order_BudgetPeriod' => '',
      'Lite_Website_TextColor' => '#000000',
      'Lite_Website_BGColor' => '#ffffff',
      'Lite_AutoInvoice_Ext' => 'AUT',
      'Lite_On_Error_Resume_Next' => true,

      'DC_PAYMENT_ID' => '',
      'DC_TRANSACTION_ID' => '',

      'Lite_Order_DiscountAmount' => 0,
      'Lite_Website_Successful_url' => $extra['return_url'],
      'Lite_Website_Fail_url' => $extra['cancel_url'],
      'Lite_Website_TryLater_url' => $extra['cancel_url'],
      'Lite_Website_Error_url' => $extra['cancel_url'],
      'Ecom_ShipTo_Postal_Name_Prefix' => '',
      'Ecom_ShipTo_Postal_Name_First' => '',
      'Ecom_ShipTo_Postal_Name_Middle' => '',
      'Ecom_ShipTo_Postal_Name_Last' => '',
      'Ecom_ShipTo_Postal_Name_Suffix' => '',
      'Ecom_ShipTo_Postal_Street_Line1' => '',
      'Ecom_ShipTo_Postal_Street_Line2' => '',
      'Ecom_ShipTo_Postal_Street_Line3' => '',
      'Ecom_ShipTo_Postal_City' => '',
      'Ecom_ShipTo_Postal_StateProv' => '',
      'Ecom_ShipTo_Postal_PostalCode' => '',
      'Ecom_ShipTo_Postal_CountryCode' => '',
      'Ecom_ShipTo_Telecom_Phone_Number' => '',
      'Ecom_ShipTo_Online_Email' => '',
      'Ecom_BillTo_Postal_Name_Prefix' => '',
      'Ecom_BillTo_Postal_Name_First' => '',
      'Ecom_BillTo_Postal_Name_Middle' => '',
      'Ecom_BillTo_Postal_Name_Last' => '',
      'Ecom_BillTo_Postal_Name_Suffix' => '',
      'Ecom_BillTo_Postal_Street_Line1' => '',
      'Ecom_BillTo_Postal_Street_Line2' => '',
      'Ecom_BillTo_Postal_Street_Line3' => '',
      'Ecom_BillTo_Postal_City' => '',
      'Ecom_BillTo_Postal_StateProv' => '',
      'Ecom_BillTo_Postal_PostalCode' => '',
      'Ecom_BillTo_Postal_CountryCode' => '',
      'Ecom_BillTo_Telecom_Phone_Number' => '',
      'Ecom_BillTo_Online_Email' => '',
      
      'Ecom_Payment_Card_Name' => '',
      'Ecom_Payment_Card_Type' => '',
      'Ecom_Payment_Card_Number' => '',
      'Ecom_Payment_Card_Verification' => '',
      'Ecom_Payment_Card_Protocols' => '',
      'Ecom_Payment_Card_StartDate_Day' => '',
      'Ecom_Payment_Card_StartDate_Month' => '',
      'Ecom_Payment_Card_StartDate_Year' => '',
      'Ecom_Payment_Card_ExpDate_Day' => '',
      'Ecom_Payment_Card_ExpDate_Month' => '',
      'Ecom_Payment_Card_ExpDate_Year' => '',

      'Ecom_ConsumerOrderID' => $order->id(),
      'LITE_CONSUMERORDERID_PREFIX' => 'DC',
      'Ecom_SchemaVersion' => '',
      'Ecom_TransactionComplete' => false,
      'Lite_Payment_Card_PreAuthMode' => false,
    ];

    //die;

    return $this->buildRedirectForm($form, $form_state, $payment_gateway_plugin->getRedirectUrl(), $data, 'post');
  }

}
