<?php

namespace Drupal\uc_paysto\Controller;

use Drupal\uc_paysto\Plugin\Ubercart\PaymentMethod\Paysto;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_cart\CartManagerInterface;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_payment\Plugin\PaymentMethodManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller routines for uc_Paysto.
 */
class PaystoController extends ControllerBase {

  /** @var \Drupal\uc_cart\CartManagerInterface The cart manager */
  protected $cartManager;

  /** @var Store Session */
  protected $session;

  /** @var array Variable for store configuration */
  protected $configuration = array();

  /**
   * Constructs a PaystoController.
   *
   * @param \Drupal\uc_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   */
  public function __construct(CartManagerInterface $cart_manager) {
    $this->cartManager = $cart_manager;
  }


  /**
   * Create method
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @return \Drupal\uc_paysto\Controller\PaystoController
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uc_cart.manager')
    );
  }


  /**
   * Notification callback function
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   */
  public function notification(Request $request) {

    // Try to get values from request
    $orderId = $request->request->get('x_invoice_num');
    // Get first if
    if (!isset($orderId)) {
      \Drupal::messenger()->addMessage($this->t('Site can not get info from you transaction. Please return to store and perform the order'),
        'success');
      $response = new RedirectResponse('/', 302);
      $response->send();
      return;
    }
    $order = Order::load($orderId);

    // Load configuration
    $plugin = \Drupal::service('plugin.manager.uc_payment.method')
      ->createFromOrder($order);
    $this->configuration = $plugin->getConfiguration();
    $x_login = $this->configuration['x_login'];
    $secret = $this->configuration['secret'];

    $orderTotal = uc_currency_format($order->getTotal(), false, false, '.');

    $x_response_code = $request->request->get('x_response_code');
    $x_trans_id = $request->request->get('x_trans_id');
    $x_MD5_Hash = $request->request->get('x_MD5_Hash');
    $calculated_x_MD5_Hash = self::get_x_MD5_Hash($x_login, $x_trans_id, $orderTotal, $secret);

    if (!$order || $order->getStateId() != $this->configuration['order_status_after']) {
      if ($this->checkInServerList()) {
        if ($x_response_code == 1 && $calculated_x_MD5_Hash == $x_MD5_Hash) {
          $comment = $this->t('Paid by Paysto method use payment type "@type", order #@order and transaction number in Paysto system is @transaction. Also look payment callback log: @dump ', [
            '@type'  => Html::escape($request->request->get('x_method')),
            '@order' => Html::escape($request->request->get('x_invoice_num')),
            '@transaction' => Html::escape($request->request->get('x_trans_id')),
            '@dump' => Html::escape(print_r($request->request->all(),TRUE)),
          ]);
          uc_payment_enter($order->id(), 'paysto', $request->request->get('x_amount'), $order->getOwnerId(), NULL, $comment);
          $order->setStatusId($this->configuration['order_status_after'])->save();
          die('success');
        } else {
          $this->onCancel($order, $request);
          return;
        }
      } else {
        $this->onCancel($order, $request);
        return;
      }
    } else {
      \Drupal::messenger()->addMessage($this->t('Order complete! Thank you for payment'), 'success');
      return $this->cartManager->completeSale($order);
    }
  }

  /**
   * Callback order fail proceed
   * @param OrderInterface $order
   * @param Request $request
   */
  public function onCancel(Order $order, Request $request)
  {
    \Drupal::messenger()->addMessage($this->t('You have canceled checkout at Paysto but may resume the checkout process here when you are ready.'), 'error');
    uc_order_comment_save($order->id(), 0, $this->t('Order have not passed Paysto declined. Paysto make call back with data: @dump',[
      '@dump' => Html::escape(print_r($request->request->all(),TRUE))
    ]));

    $url = '/cart/checkout/';
    $response = new RedirectResponse($url, 302);
    $response->send();
  }

  /**
   * Return sign with MD5 algoritm
   *
   * @param $x_login
   * @param $x_trans_id
   * @param $x_amount
   * @param $secret
   * @return string
   */
  public static function get_x_MD5_Hash($x_login, $x_trans_id, $x_amount, $secret)
  {
    return md5($secret . $x_login . $x_trans_id . $x_amount);
  }

  /**
   * Check if IP adress in server lists
   *
   * @return bool
   */
  public function checkInServerList()
  {
    if ($this->configuration['use_ip_only_from_server_list']) {
      $clientIp = \Drupal::request()->getClientIp();
      $serverIpList = preg_split('/\r\n|[\r\n]/', $this->configuration['server_list']);
      if (in_array($clientIp, $serverIpList)) {
        return true;
      } else {
        return false;
      }
    } else {
      return true;
    }
  }

}
