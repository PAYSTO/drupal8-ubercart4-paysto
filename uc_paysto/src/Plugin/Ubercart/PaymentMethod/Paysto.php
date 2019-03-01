<?php

namespace Drupal\uc_paysto\Plugin\Ubercart\PaymentMethod;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\uc_order\Entity\OrderStatus;
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
                'description' => 'Оплата заказа №',
                'vat_shipping' => 'N',
                'use_ip_only_from_server_list' => true,
                'server_list' => '95.213.209.218
95.213.209.219
95.213.209.220
95.213.209.221
95.213.209.222',
                'order_status_after' => 'processing'
            ] + parent::defaultConfiguration();

        foreach (uc_product_types() as $type) {
            $returned['vat_product_' . $type] = 'N';
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

        $form['description'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Order description"),
            '#description' => $this->t("Order description in Paysto interface"),
            '#default_value' => $this->configuration['description'],
            '#required' => true,
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

        $form['order_status_after'] = [
            '#type' => 'select',
            '#title' => $this->t("Order status after successfull payment"),
            '#description' => $this->t("Set order status after successfull payment"),
            '#options' => OrderStatus::getOptionsList(),
            '#default_value' => $this->configuration['order_status_after'],
            '#required' => true,
        ];

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
        $this->configuration['order_status_after'] = $form_state->getValue('order_status_after');
    }

    /**
     *
     * {@inheritdoc}
     */
    public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = null)
    {
        // Get config
        $configs = $this->configuration;

        // Posotion count in order
        $pos = 1;
        // line item list
        $x_line_item = '';

        // Get amount
        $x_amount = uc_currency_format($order->getTotal(), false, false, '.');

        // Get now for sign
        $now = time();

        // Set data array
        $data = [
            'x_description' => $configs['description'] . $order->id(),
            'x_login' => $configs['x_login'],
            'x_amount' => $x_amount,
            'x_currency_code' => $order->getCurrency(),
            'x_fp_sequence' => $order->id(),
            'x_fp_timestamp' => $now,
            'x_fp_hash' => self::get_x_fp_hash($configs['x_login'], $order->id(), $now, $x_amount,
                $order->getCurrency(), $configs['secret']),
            'x_invoice_num' => $order->id(),
            'x_relay_response' => "TRUE",
            'x_relay_url' => Url::fromRoute('uc_paysto.notification', [], ['absolute' => true])->toString(),
        ];

        // Get customer (order) email
        $customerEmail = $order->getEmail();
        // if isset email
        if ($customerEmail) {
            $data['x_email'] = $customerEmail;
        }


        foreach ($order->products as $product) {
            $lineArr = array();
            $lineArr[] = '№' . $pos . " ";


            $nid = $product->nid->first()->getValue()['target_id'];
            $node = Node::load($nid);
            $type = $node->getType();

            $lineArr[] = substr($product->model->value, 0, 30);
            $lineArr[] = substr($product->title->value, 0, 254);
            $lineArr[] = $product->qty->value;
            $lineArr[] = uc_currency_format($product->price->value, FALSE, FALSE, '.');
            $lineArr[] = $configs['vat_product_' . $type];
            $x_line_item .= implode('<|>', $lineArr) . "0<|>\n";
            $pos++;
        }

        // add delivery
        foreach ($order->getLineItems() as $item) {
            if ($item['type'] == 'shipping') {
                $lineArr = array();
                $lineArr[] = '№' . $pos . " ";
                $lineArr[] = 'shipping';
                $lineArr[] = substr($item['title'], 0, 254);
                $lineArr[] = '1';
                $lineArr[] = uc_currency_format($item['amount'], FALSE, FALSE, '.');
                $lineArr[] = $configs['vat_shipping'];
                $x_line_item .= implode('<|>', $lineArr) . "0<|>\n";
            }
        }

        $data['x_line_item'] = $x_line_item;

        return $this->generateForm($data, $this->url);
    }

    /**
     * Get sign for send order
     * @param $x_login
     * @param $x_fp_sequence
     * @param $x_fp_timestamp
     * @param $x_amount
     * @param $x_currency_code
     * @param $secret
     * @return string
     */
    public static function get_x_fp_hash($x_login, $x_fp_sequence, $x_fp_timestamp,
        $x_amount, $x_currency_code, $secret)
    {
        $arr = [$x_login, $x_fp_sequence, $x_fp_timestamp, $x_amount, $x_currency_code];
        $str = implode('^', $arr);
        return hash_hmac('md5', $str, $secret);
    }

    /**
     * Generate payment form
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
}
