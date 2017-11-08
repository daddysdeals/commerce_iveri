<?php

namespace Drupal\commerce_iveri\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_iveri\Event\LiteCheckoutRequestEvent;
use Drupal\commerce_iveri\Event\iVeriEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides the Paypal Express Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "iveri_lite_checkout",
 *   label = @Translation("iVeri (Lite Checkout)"),
 *   display_label = @Translation("iVeri Lite"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_iveri\PluginForm\LiteCheckoutForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "discover", "mastercard", "visa",
 *   },
 * )
 */
class LiteCheckout extends OffsitePaymentGatewayBase implements LiteCheckoutInterface {

  const IVERI_LITE_SUBMISSION_ENDPOINT = 'https://backoffice.nedsecure.co.za/Lite/Transactions/New/EasyAuthorise.aspx';
  const IVERI_LITE_AUTH_INFO_ENDPOINT = 'https://backoffice.iveri.co.za/Lite/Transactions/New/AuthoriseInfo.aspx';
  const IVERI_CURRENCY_CODE = 'ZAR';

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The price rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new PaymentGatewayBase object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, LoggerChannelFactoryInterface $logger_channel_factory, ClientInterface $client, RounderInterface $rounder, ModuleHandlerInterface $module_handler, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->logger = $logger_channel_factory->get('commerce_iveri');
    $this->httpClient = $client;
    $this->rounder = $rounder;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('logger.factory'),
      $container->get('http_client'),
      $container->get('commerce_price.rounder'),
      $container->get('module_handler'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'test_key' => '',
      'live_key' => '',
      'submission_endpoint' => self::IVERI_LITE_SUBMISSION_ENDPOINT,
      'auth_info_endpoint' => self::IVERI_LITE_AUTH_INFO_ENDPOINT,
      'redirect_validation_hash' => $this->_commerce_iveri_lite_randomstring(16),
      'transaction_mode' => 'test',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['test_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API Test Key'),
      '#description' => $this->t('Your test application key.'),
      '#default_value' => $this->configuration['test_key'],
    );

    $form['live_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API Live Key'),
      '#description' => $this->t('Your live application key.'),
      '#default_value' => $this->configuration['live_key'],
    );

    $form['submission_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API Submission Endpoint'),
      '#description' => $this->t('Submission Endpoint (You\'ll probably not want to mess with this)'),
      '#default_value' => $this->configuration['submission_endpoint'],
    );

    $form['auth_info_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API Authorization Endpoint'),
      '#description' => $this->t('Authorization Endpoint (You\'ll probably not want to mess with this either)'),
      '#default_value' => $this->configuration['auth_info_endpoint'],
    );

    $form['redirect_validation_hash'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Redirect validation key'),
      '#default_value' => $this->configuration['redirect_validation_hash'],
      '#description' => $this->t('An MD5 hash key for validating redirect responses.  A random key has been generated for your convenience.  Do not leave this blank!'),
    );

    $form['transaction_mode'] = array(
      '#type' => 'select',
      '#title' => $this->t('Transaction mode'),
      '#description' => $this->t('Test is development server, live will process transactions'),
      '#options' => array(
        'test' => $this->t('Test - Development Mode'),
        'live' => $this->t('Live - Production Mode'),
      ),
      '#multiple' => FALSE,
      '#default_value' => $this->configuration['transaction_mode'],
    );
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration['test_key'] = $values['test_key'];
      $this->configuration['live_key'] = $values['live_key'];
      $this->configuration['submission_endpoint'] = $values['submission_endpoint'];
      $this->configuration['auth_info_endpoint'] = $values['auth_info_endpoint'];
      $this->configuration['redirect_validation_hash'] = $values['redirect_validation_hash'];
      $this->configuration['transaction_mode'] = $values['transaction_mode'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    print_r($_POST);

    $order_checkout_data = $order->getData('iveri_lite_checkout');
    
    if (empty($order_checkout_data['token'])) {
      throw new PaymentGatewayException('Token data missing for this PayPal Express Checkout transaction.');
    }

    // GetExpressCheckoutDetails API Operation (NVP).
    // Shows information about an Express Checkout transaction.
    $paypal_response = $this->getExpressCheckoutDetails($order);

    // If the request failed, exit now with a failure message.
    if ($paypal_response['ACK'] == 'Failure') {
      throw new PaymentGatewayException($paypal_response['PAYMENTREQUESTINFO_0_LONGMESSAGE'], $paypal_response['PAYMENTREQUESTINFO_n_ERRORCODE']);
    }

    // Set the Payer ID used to finalize payment.
    $order_express_checkout_data['payerid'] = $paypal_response['PAYERID'];
    $order->setData('paypal_express_checkout', $order_express_checkout_data);

    // If the user is anonymous, add their PayPal e-mail to the order.
    if (empty($order->mail)) {
      $order->setEmail($paypal_response['EMAIL']);
    }
    $order->save();

    // DoExpressCheckoutPayment API Operation (NVP).
    // Completes an Express Checkout transaction.
    $paypal_response = $this->doExpressCheckoutDetails($order);

    // Nothing to do for failures for now - no payment saved.
    // @todo - more about the failures.
    if ($paypal_response['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Failed') {
      throw new PaymentGatewayException($paypal_response['PAYMENTINFO_0_LONGMESSAGE'], $paypal_response['PAYMENTINFO_0_ERRORCODE']);
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state' => 'authorization',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $this->entityId,
      'order_id' => $order->id(),
      'remote_id' => $paypal_response['PAYMENTINFO_0_TRANSACTIONID'],
      'remote_state' => $paypal_response['PAYMENTINFO_0_PAYMENTSTATUS'],
    ]);

    // Process payment status received.
    // @todo payment updates if needed.
    // If we didn't get an approval response code...
    switch ($paypal_response['PAYMENTINFO_0_PAYMENTSTATUS']) {
      case 'Voided':
        $payment->state = 'authorization_voided';
        break;

      case 'Pending':
        $payment->state = 'authorization';
        break;

      case 'Completed':
      case 'Processed':
        $payment->state = 'completed';
        break;

      case 'Refunded':
        $payment->state = 'refunded';
        break;

      case 'Partially-Refunded':
        $payment->state = 'partially_refunded';
        break;

      case 'Expired':
        $payment->state = 'authorization_expired';
        break;
    }

    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $amount = $this->rounder->round($amount);

    // GetExpressCheckoutDetails API Operation (NVP).
    // Shows information about an Express Checkout transaction.
    $paypal_response = $this->doCapture($payment, $amount->getNumber());

    if ($paypal_response['ACK'] == 'Failure') {
      $message = $paypal_response['L_LONGMESSAGE0'];
      throw new PaymentGatewayException($message, $paypal_response['L_ERRORCODE0']);
    }

    $payment->setState('completed');
    $payment->setAmount($amount);
    // Update the remote id for the captured transaction.
    $payment->setRemoteId($paypal_response['TRANSACTIONID']);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);

    // GetExpressCheckoutDetails API Operation (NVP).
    // Shows information about an Express Checkout transaction.
    $paypal_response = $this->doVoid($payment);
    if ($paypal_response['ACK'] == 'Failure') {
      $message = $paypal_response['L_LONGMESSAGE0'];
      throw new PaymentGatewayException($message, $paypal_response['L_ERRORCODE0']);
    }

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);
    $amount = $this->rounder->round($amount);

    $extra['amount'] = $amount->getNumber();
    // Check if the Refund is partial or full.
    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
      $extra['refund_type'] = 'Partial';
    }
    else {
      $payment->setState('refunded');
      if ($amount->lessThan($payment->getAmount())) {
        $extra['refund_type'] = 'Partial';
      }
      else {
        $extra['refund_type'] = 'Full';
      }
    }

    // RefundTransaction API Operation (NVP).
    // Refund (full or partial) an Express Checkout transaction.
    $paypal_response = $this->doRefundTransaction($payment, $extra);

    if ($paypal_response['ACK'] == 'Failure') {
      $message = $paypal_response['L_LONGMESSAGE0'];
      throw new PaymentGatewayException($message, $paypal_response['L_ERRORCODE0']);
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    // Get IPN request data and basic processing for the IPN request.
    $ipn_data = $this->ipnHandler->process($request);

    // Do not perform any processing on EC transactions here that do not have
    // transaction IDs, indicating they are non-payment IPNs such as those used
    // for subscription signup requests.
    if (empty($ipn_data['txn_id'])) {
      $this->logger->alert('The IPN request does not have a transaction id. Ignored.');
      return FALSE;
    }
    // Exit when we don't get a payment status we recognize.
    if (!in_array($ipn_data['payment_status'], ['Failed', 'Voided', 'Pending', 'Completed', 'Refunded'])) {
      throw new BadRequestHttpException('Invalid payment status');
    }
    // If this is a prior authorization capture IPN...
    if (in_array($ipn_data['payment_status'], ['Voided', 'Pending', 'Completed']) && !empty($ipn_data['auth_id'])) {
      // Ensure we can load the existing corresponding transaction.
      $payment = $this->loadPaymentByRemoteId($ipn_data['auth_id']);
      // If not, bail now because authorization transactions should be created
      // by the Express Checkout API request itself.
      if (!$payment) {
        $this->logger->warning('IPN for Order @order_number ignored: authorization transaction already created.', ['@order_number' => $ipn_data['invoice']]);
        return FALSE;
      }
      $amount = new Price($ipn_data['mc_gross'], $ipn_data['mc_currency']);
      $payment->setAmount($amount);
      // Update the payment state.
      switch ($ipn_data['payment_status']) {
        case 'Voided':
          $payment->state = 'authorization_voided';
          break;

        case 'Pending':
          $payment->state = 'authorization';
          break;

        case 'Completed':
          $payment->state = 'completed';
          break;
      }
      // Update the remote id.
      $payment->remote_id = $ipn_data['txn_id'];
    }
    elseif ($ipn_data['payment_status'] == 'Refunded') {
      // Get the corresponding parent transaction and refund it.
      $payment = $this->loadPaymentByRemoteId($ipn_data['parent_txn_id']);
      if (!$payment) {
        $this->logger->warning('IPN for Order @order_number ignored: the transaction to be refunded does not exist.', ['@order_number' => $ipn_data['invoice']]);
        return FALSE;
      }
      elseif ($payment->getState() == 'refunded') {
        $this->logger->warning('IPN for Order @order_number ignored: the transaction is already refunded.', ['@order_number' => $ipn_data['invoice']]);
        return FALSE;
      }
      $amount = new Price((string) $ipn_data['mc_gross'], $ipn_data['mc_currency']);
      // Check if the Refund is partial or full.
      $old_refunded_amount = $payment->getRefundedAmount();
      $new_refunded_amount = $old_refunded_amount->add($amount);
      if ($new_refunded_amount->lessThan($payment->getAmount())) {
        $payment->setState('partially_refunded');
      }
      else {
        $payment->setState('refunded');
      }
      $payment->setRefundedAmount($new_refunded_amount);
    }
    elseif ($ipn_data['payment_status'] == 'Failed') {
      // ToDo - to check and report existing payments???
    }
    else {
      // In other circumstances, exit the processing, because we handle those
      // cases directly during API response processing.
      $this->logger->notice('IPN for Order @order_number ignored: this operation was accommodated in the direct API response.', ['@order_number' => $ipn_data['invoice']]);
      return FALSE;
    }
    if (isset($payment)) {
      $payment->currency_code = $ipn_data['mc_currency'];
      // Set the transaction's statuses based on the IPN's payment_status.
      $payment->setRemoteState($ipn_data['payment_status']);
      // Save the transaction information.
      $payment->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl() {
    return $this->configuration['submission_endpoint'];
  }

  /**
   * Generate a random string (from DrupalTestCase::randomString).
   */
  public function _commerce_iveri_lite_randomstring($length = 8) {
    $str = '';

    for ($i = 0; $i < $length; $i++) {
      $str .= chr(mt_rand(32, 126));
    }

    return $str;
  }
}
