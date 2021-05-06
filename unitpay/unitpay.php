<?php
/*
  Plugin Name: Unitpay
  Description: Unitpay Plugin for WooCommerce
  Version: 2.1.1
 */
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_unitpay', 0);

function woocommerce_unitpay()
{
    load_plugin_textdomain('unitpay', false, dirname(plugin_basename(__FILE__)) . '/lang/');

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    } // if the WC payment gateway class is not available, do nothing
    if (class_exists('WC_UNITPAY')) {
        return;
    }

    class WC_UNITPAY extends WC_Payment_Gateway
    {
        public function __construct()
        {

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'unitpay';
            $this->icon = apply_filters('woocommerce_unitpay_icon', '' . $plugin_dir . 'unitpay.png');
            $this->has_fields = false;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->domain = $this->get_option('domain');
            $this->public_key = $this->get_option('public_key');
            $this->secret_key = $this->get_option('secret_key');
            $this->nds = $this->get_option('nds');
            $this->nds_delivery = $this->get_option('nds_delivery');

            $this->title = 'Unitpay';
            $this->description = __('Payment system Unitpay', 'unitpay');

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id,
                    array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'callback'));
        }

        public function admin_options()
        {
            ?>
            <h3><?php _e('UNITPAY', 'unitpay'); ?></h3>
            <p><?php _e('Setup payments parameters.', 'unitpay'); ?></p>
            <p><strong>В личном кабинете включите обработчик
                    платежей:</strong> <?= home_url('/wc-api/' . strtolower(get_class($this))); ?></p>

            <table class="form-table">

                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->

            <?php
        } // End admin_options()

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'unitpay'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'unitpay'),
                    'default' => 'yes'
                ),
                'domain' => array(
                    'title' => __('DOMAIN', 'unitpay'),
                    'type' => 'text',
                    'description' => __('Insert your working DOMAIN', 'unitpay'),
                    'default' => ''
                ),
                'public_key' => array(
                    'title' => __('PUBLIC KEY', 'unitpay'),
                    'type' => 'text',
                    'description' => __('Copy PUBLIC KEY from your account page in unitpay system', 'unitpay'),
                    'default' => ''
                ),
                'secret_key' => array(
                    'title' => __('SECRET KEY', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Copy SECRET KEY from your account page in unitpay system', 'unitpay'),
                    'default' => ''
                ),
                'nds' => array(
                    'title' => __('Ставка НДС', 'woocommerce'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('Выберите ставку НДС, которая применима к товарам в магазине.',
                            'woocommerce'),
                    'default' => 'none',
                    'desc_tip' => true,
                    'options' => array(
                        'none' => __('НДС не облагается', 'woocommerce'),
                        'vat20' => __('НДС 20%', 'woocommerce'),
                        'vat10' => __('НДС 10%', 'woocommerce'),
                        'vat0' => __('НДС 0%', 'woocommerce'),
                    ),
                ),
                'nds_delivery' => array(
                    'title' => __('Ставка НДС для доставки', 'woocommerce'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('Выберите ставку НДС, которая применима к доставке в магазине.',
                            'woocommerce'),
                    'default' => 'none',
                    'desc_tip' => true,
                    'options' => array(
                        'none' => __('НДС не облагается', 'woocommerce'),
                        'vat20' => __('НДС 20%', 'woocommerce'),
                        'vat10' => __('НДС 10%', 'woocommerce'),
                        'vat0' => __('НДС 0%', 'woocommerce'),
                    ),
                )
            );
        }

        /**
         * Generate form
         **/
        public function generate_form($order_id)
        {
            $order = new WC_Order($order_id);

            $sum = $this->priceFormat($order->order_total);
            $account = $order_id;
            $desc = __('Payment for Order №', 'unitpay') . $order_id;
            $currency = $order->get_order_currency();

            $signature = $this->generateSignature($account, $currency, $desc, $sum);

            $form =
                '<form action="' . $this->endpoint() . '" method="POST" id="unitpay_form">' .
                '<input type="hidden" name="sum" value="' . $sum . '" />' .
                '<input type="hidden" name="account" value="' . $account . '" />' .
                '<input type="hidden" name="desc" value="' . $desc . '" />' .
                '<input type="hidden" name="currency" value="' . $currency . '" />' .
                '<input type="hidden" name="signature" value="' . $signature . '" />';

            if ($order->billing_email) {
                $form .= '<input type="hidden" name="customerEmail" value="' . sanitize_email($order->billing_email) . '" />';
            }

            if ($order->billing_phone) {
                $form .= '<input type="hidden" name="customerPhone" value="' . $this->phoneFormat($order->billing_phone) . '" />';
            }

            $deliveryPrice = $order->get_total_shipping() + abs($order->get_shipping_tax());

            $items = array();

            foreach ($order->get_items() as $item) {
                $product = $order->get_product_from_item($item);

                $itemData = array(
                    'name' => $item['name'],
                    'count' => $item['quantity'],
                    'currency' => $currency,
                    'price' => $this->priceFormat($product->get_price()),
                    //'price' => $this->priceFormat($item->get_subtotal() / $item['quantity']),
                    'sum' => $this->priceFormat($item->get_total()),
                    'nds' => $this->nds,
                    'type' => "commodity",
                );

                $items[] = $itemData;
            }

            if ($deliveryPrice > 0) {
                $items[] = array(
                    'name' => 'Доставка',
                    'count' => 1,
                    'currency' => $currency,
                    'price' => $this->priceFormat($deliveryPrice),
                    'nds' => $this->nds_delivery,
                    'type' => "service",
                );
            }

            $form .= '<input type="hidden" name="cashItems" value="' . $this->cashItems($items) . '" />';

            $form .= '<input type="submit" class="button alt" id="submit_unitpay_form" value="' . __('Pay', 'unitpay') . '" />
				<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel payment and return back to card',
                        'unitpay') . '</a>' . "\n" .
                '</form>';

            return $form;
        }

        /**
         * Process the payment and return the result
         **/
        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id,
                        add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        public function receipt_page($order)
        {
            echo '<p>' . __('Thank you for your order, press button to pay.', 'unitpay') . '</p>';
            echo $this->generate_form($order);
        }

        public function callback()
        {
            header('Content-type:application/json;  charset=utf-8');

            $method = '';
            $params = array();

            if ((isset($_GET['params'])) && (isset($_GET['method'])) && (isset($_GET['params']['signature']))) {
                $params = (array)$_GET['params'];
                $method = sanitize_text_field($_GET['method']);
                $signature = sanitize_text_field($params['signature']);


                if (empty($signature)) {
                    $status_sign = false;
                } else {
                    $secret_key = $this->secret_key;
                    $status_sign = $this->verifySignature($params, $method, $secret_key);
                }
            } else {
                $status_sign = false;
            }


            if ($status_sign) {
                switch ($method) {
                    case 'check':
                        $result = $this->check($params);
                        break;
                    case 'pay':
                        $result = $this->payment($params);
                        break;
                    case 'error':
                        $result = $this->error($params);
                        break;
                    default:
                        $result = array(
                                'error' =>
                                        array('message' => __('Wrong method', 'unitpay'))
                        );
                        break;
                }
            } else {
                $result = array(
                    'error' => array('message' => __('Wrong signature', 'unitpay'))
                );
            }

            echo json_encode($result);
            die();
        }

        /**
         * @return string
         */
        public function endpoint()
        {
            return 'https://' . $this->domain . '/pay/' . $this->public_key;
        }

        /**
         * @param $items
         * @return string
         */
        public function cashItems($items)
        {
            return base64_encode(json_encode($items));
        }

        /**
         * @param $value
         * @return string
         */
        public function priceFormat($value)
        {
            return number_format($value, 2, '.', '');
        }

        /**
         * @param $value
         * @return string
         */
        public function phoneFormat($value)
        {
            return preg_replace('/\D/', '', $value);
        }

        public function verifySignature($params, $method, $secret)
        {
            return $params['signature'] == $this->getSignature($method, $params, $secret);
        }

        /**
         * @param $order_id
         * @param $currency
         * @param $desc
         * @param $sum
         * @return string
         */
        public function generateSignature($order_id, $currency, $desc, $sum)
        {
            return hash('sha256', join('{up}', array(
                $order_id,
                $currency,
                $desc,
                $sum,
                $this->secret_key
            )));
        }

        public function getSignature($method, array $params, $secretKey)
        {
            ksort($params);
            unset($params['sign']);
            unset($params['signature']);
            array_push($params, $secretKey);
            array_unshift($params, $method);

            return hash('sha256', join('{up}', $params));
        }

        public function check($params)
        {
            $order = new WC_Order(sanitize_text_field($params['account']));

            if (!$order->id) {
                $result = array(
                    'error' => array('message' => __('Order not created', 'unitpay'))
                );
            } else {
                $sum = $this->priceFormat($order->order_total);

                $currency = $order->get_order_currency();

                if ((float)$sum != (float)$this->priceFormat($params['orderSum'])) {
                    $result = array(
                        'error' => array('message' => __('Wrong order sum', 'unitpay'))
                    );
                } elseif ($currency != sanitize_text_field($params['orderCurrency'])) {
                    $result = array(
                        'error' => array('message' => __('Wrong order currency', 'unitpay'))
                    );
                } else {
                    $result = array(
                        'result' => array('message' => __('Request successfully', 'unitpay'))
                    );
                }
            }

            return $result;
        }

        public function payment($params)
        {

            $order = new WC_Order(sanitize_text_field($params['account']));

            if (!$order->id) {
                $result = array(
                    'error' => array('message' => __('Order not created', 'unitpay'))
                );
            } else {
                $sum = $this->priceFormat($order->order_total);

                $currency = $order->get_order_currency();

                if ((float)$sum != (float)$this->priceFormat($params['orderSum'])) {
                    $result = array(
                        'error' => array('message' => __('Wrong order sum', 'unitpay'))
                    );
                } elseif ($currency != sanitize_text_field($params['orderCurrency'])) {
                    $result = array(
                        'error' => array('message' => __('Wrong order currency', 'unitpay'))
                    );
                } else {

                    $order->payment_complete();

                    WC()->cart->empty_cart();

                    $result = array(
                        'result' => array('message' => __('Request successfully', 'unitpay'))
                    );
                }
            }

            return $result;
        }

        public function error($params)
        {
            $order = new WC_Order($params['account']);

            if (!$order) {
                $result = array(
                    'error' => array('message' => __('Order not created', 'unitpay'))
                );
            } else {
                $order->update_status('failed', __('Payment error', 'unitpay'));

                WC()->cart->empty_cart();

                $result = array(
                    'result' => array('message' => __('Request successfully', 'unitpay'))
                );
            }
            return $result;
        }
    }

    /**
     * Add the gateway to WooCommerce
     **/
    function add_unitpay_gateway($methods)
    {
        $methods[] = 'WC_UNITPAY';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_unitpay_gateway');
}

?>