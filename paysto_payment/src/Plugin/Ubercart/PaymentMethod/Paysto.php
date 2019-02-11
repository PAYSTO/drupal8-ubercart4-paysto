<?php

namespace Drupal\uc_paysto\Plugin\Ubercart\PaymentMethod;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;

/**
 * Defines the paysto payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "paysto",
 *   name = @Translation("paysto"),
 *   redirect = "\Drupal\uc_paysto\Form\paystoForm",
 * )
 */
class Paysto extends PaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface
{
    
    /**
     * @var string payment url
     */
    protected $url = 'https://paysto.com/ru/pay/AuthorizeNet';
    /**
     * @var string
     */
    public static $signature_separator = '|';
    /**
     * @var string
     */
    public static $order_separator = '#';
    
    /**
     * @param string $label
     *
     * @return mixed
     */
    public function getDisplayLabel($label)
    {
        $build['label'] = [
            '#prefix' => '<div class="uc-paysto">',
            '#plain_text' => $label,
            '#suffix' => '</div>',
        ];
        $build['image'] = [
            '#theme' => 'image',
            '#uri' => drupal_get_path('module', 'uc_paysto') . '/images/logo.png',
            '#alt' => $this->t('paysto'),
            '#attributes' => ['class' => ['uc-paysto-logo']]
        ];
        
        return $build;
    }
    
    /**
     * @return array
     */
    public function defaultConfiguration()
    {
        return [
            'currency' => 'EUR',
            'use_ip_only_from_server_list' => false,
            'language' => 'en',
            'back_url' => '',
            'secret' => 'test',
            'x_login' => ''
        ];
    }
    
    /**
     * @param array $form
     * @param FormStateInterface $form_state
     *
     * @return array
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['x_login'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Your Merchant ID'),
            '#description' => $this->t('Your mid from portal.'),
            '#default_value' => $this->configuration['x_login'],
            '#size' => 16,
        ];
        
        $form['secret'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Secret word for order verification'),
            '#description' => $this->t('The secret word entered in your paysto settings page.'),
            '#default_value' => $this->configuration['secret'],
            '#size' => 256,
        ];
        
        $form['use_ip_only_from_server_list'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable use_ip_only_from_server_list.'),
            '#default_value' => $this->configuration['use_ip_only_from_server_list'],
        ];
        
        $form['back_url'] = [
            '#type' => 'url',
            '#title' => $this->t('Instant notification settings URL'),
            '#description' => $this->t('Back/notify url. Example (http://{your_site}/paysto/back_url)'),
            '#default_value' => Url::fromRoute('uc_paysto.notification', [], ['absolute' => true])->toString(),
            '#attributes' => ['readonly' => 'readony'],
        ];
        
        return $form;
    }
    
    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        $this->configuration['use_ip_only_from_server_list'] = $form_state->getValue('use_ip_only_from_server_list');
        $this->configuration['checkout_type'] = $form_state->getValue('checkout_type');
        $this->configuration['currency'] = $form_state->getValue('currency');
        $this->configuration['language'] = $form_state->getValue('language');
        $this->configuration['back_url'] = $form_state->getValue('back_url');
        $this->configuration['secret'] = $form_state->getValue('secret');
        $this->configuration['x_login'] = $form_state->getValue('x_login');
    }
    
    /**
     * {@inheritdoc}
     */
    public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state)
    {
        $session = \Drupal::service('session');
        if (null != $form_state->getValue(['panes', 'payment', 'details', 'pay_method'])) {
            $session->set('pay_method', $form_state->getValue(['panes', 'payment', 'details', 'pay_method']));
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function cartReviewTitle()
    {
        return $this->t('Credit card Paysto');
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = null)
    {
        
        $paystoSettings = $this->configuration;
        $amount = round(uc_currency_format($order->getTotal(), false, false, '.') * 100);
        
        $dataSettings = [
            'order_id' => $order->id() . self::$order_separator . time(),
            'x_login' => $paystoSettings['x_login'],
            'order_desc' => $this->t('Order Pay #') . $order->id(),
            'amount' => $amount,
            'use_ip_only_from_server_list' => $paystoSettings['use_ip_only_from_server_list'] ? 'Y' : 'N',
            'currency' => $paystoSettings['currency'] != '' ? $paystoSettings['currency'] : $order->getCurrency(),
            'server_callback_url' => $paystoSettings['back_url'] == '' ? Url::fromRoute('uc_paysto.notification', [],
                ['absolute' => true])->toString() : $paystoSettings['back_url'],
            'response_url' => Url::fromRoute('uc_paysto.complete', [], ['absolute' => true])->toString(),
            'lang' => $paystoSettings['language'],
            'sender_email' => Unicode::substr($order->getEmail(), 0, 64)
        ];
        
        $dataSettings['signature'] = self::getSignature($dataSettings, $paystoSettings['secret']);
        
        return $this->generateForm($dataSettings, $this->url);
    }
    
    /**
     * @param $data
     * @param string $url
     *
     * @return mixed
     */
    public function generateForm($data, $url)
    {
        $form['#action'] = $url;
        foreach ($data as $k => $v) {
            if (!is_array($v)) {
                $form[$k] = [
                    '#type' => 'hidden',
                    '#value' => $v
                ];
            } else {
                $i = 0;
                foreach ($v as $val) {
                    $form[$k . '[' . $i++ . ']'] = [
                        '#type' => 'hidden',
                        '#value' => $val
                    ];
                }
            }
        }
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit order'),
        ];
        
        return $form;
    }
    
    /**
     * @param $data
     * @param $password
     * @param bool $encoded
     *
     * @return string
     */
    public static function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);
        
        $str = $password;
        foreach ($data as $k => $v) {
            $str .= self::$signature_separator . $v;
        }
        
        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }
}
