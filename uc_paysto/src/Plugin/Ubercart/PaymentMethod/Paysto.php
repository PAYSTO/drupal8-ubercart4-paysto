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
     * Display label for payment method
     * @param string $label
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
     * Return default module settengs
     * @return array
     */
    public function defaultConfiguration()
    {

        $returned = [
                'x_login' => '',
                'secret' => '',
                'vat_shipping' => '',
                'use_ip_only_from_server_list' => true,
                'server_list' => '95.213.209.218
95.213.209.219
95.213.209.220
95.213.209.221
95.213.209.222'
            ] + parent::defaultConfiguration();

        foreach (uc_product_types() as $type) {
            $returned['vat_product_' . $type] = '';
        }

        return $returned;
    }
    
    /**
     * Setup (settings) form for module
     * @param array $form
     * @param FormStateInterface $form_state
     * @return array
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['x_login'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Your Merchant ID'),
            '#description' => $this->t('Your mid from portal.'),
            '#default_value' => $this->configuration['x_login'],
            '#size' => 40,
        ];
        
        $form['secret'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Secret word for order verification'),
            '#description' => $this->t('The secret word entered in your paysto settings page.'),
            '#default_value' => $this->configuration['secret'],
            '#size' => 40,
        ];


        foreach (uc_product_types() as $type) {
            $form['vat_product_' . $type] = [
                '#type' => 'select',
                '#title' => $this->t("Vat rate for product type " . $type),
                '#description' => $this->t("Set vat rate for product " . $type),
                '#options' => [
                    'Y' => $this->t('With VAT'),
                    'N' => $this->t('WIthout VAT'),
                ],
                '#default_value' => $this->configuration['vat_product_' . $type],
                '#required' => true,
            ];
        }

        $form['vat_shipping'] = [
            '#type' => 'select',
            '#title' => $this->t("Vat rate for shipping"),
            '#description' => $this->t("Set vat rate for shipping"),
            '#options' => [
                'Y' => $this->t('With VAT'),
                'N' => $this->t('WIthout VAT'),
            ],
            '#default_value' => $this->configuration['vat_shipping'],
            '#required' => true,
        ];

        $form['use_ip_only_from_server_list'] = [
            '#type' => 'checkbox',
            '#title' => $this->t("Use server IP"),
            '#description' => $this->t("Use server IP for callback only from list"),
            '#value' => true,
            '#false_values' => [false],
            '#default_value' => $this->configuration['use_ip_only_from_server_list'],
            '#required' => true,
        ];

        $form['server_list'] = [
            '#type' => 'textarea',
            '#title' => $this->t("Acceptable server list"),
            '#description' => $this->t("Input new server IP in each new string"),
            '#default_value' => $this->configuration['server_list'],
        ];

        //todo разобраться какая функция в Ubercart отвечает за вывод статусов заказов
//        var_dump(uc_order_status_list()); die;

        return $form;

    }
    
    /**
     * Setting save submit form
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        $this->configuration['x_login'] = $form_state->getValue('x_login');
        $this->configuration['secret'] = $form_state->getValue('secret');
        foreach (uc_product_types() as $type) {
            $this->configuration['vat_product_' . $type] = $form_state->getValue('vat_product_' . $type);
        }
        $this->configuration['vat_shipping'] = $form_state->getValue('vat_shipping');
        $this->configuration['use_ip_only_from_server_list'] = $form_state->getValue('use_ip_only_from_server_list');
        $this->configuration['server_list'] = $form_state->getValue('server_list');
    }
    
    /**
     * Cart process form
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
     * Print title for payment method
     * {@inheritdoc}
     */
    public function cartReviewTitle()
    {
        return $this->t('Paysto payment');
    }
    
    /**
     *
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
