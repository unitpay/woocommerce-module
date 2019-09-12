<?php
/*
  Plugin Name: Unitpay
  Plugin URI:  http://unitpay.ru/
  Description: Unitpay Plugin for WooCommerce
  Version: 1.0.1
  Author: unitpay.ru
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_unitpay', 0);
function woocommerce_unitpay(){
    load_plugin_textdomain( 'unitpay', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if(class_exists('WC_UNITPAY'))
        return;
    class WC_UNITPAY extends WC_Payment_Gateway{
        public function __construct(){

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'unitpay';
            $this->icon = apply_filters('woocommerce_unitpay_icon', ''.$plugin_dir.'unitpay.png');
            $this->has_fields = false;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->public_key = $this->get_option('public_key');
            $this->secret_key = $this->get_option('secret_key');
            $this->title = 'Unitpay';
            $this->description = __('Payment system Unitpay', 'unitpay');

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Payment listener/API hook
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'callback'));


        }

        public function admin_options() {
            ?>
            <h3><?php _e('UNITPAY', 'unitpay'); ?></h3>
            <p><?php _e('Setup payments parameters.', 'unitpay'); ?></p>

            <table class="form-table">

                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->

            <?php
        } // End admin_options()

        function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'unitpay'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'unitpay'),
                    'default' => 'yes'
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
                )

            );
        }

        /**
         * Generate form
         **/
        public function generate_form($order_id){
            $order = new WC_Order( $order_id );

            $sum = number_format($order->order_total, 2, '.', '');
            $account = $order_id;
            $desc = __('Payment for Order №', 'unitpay') . $order_id;
            $currency = $order->get_order_currency();
            $cur_locale = get_locale();
            $locale = $cur_locale == 'ru_RU'?'ru':'en';
            $signature = hash('sha256', join('{up}', array(
                $account,
                $currency,
                $desc,
                $sum,
                $this->secret_key
            )));

            return
                '<form action="https://unitpay.ru/pay/' . $this->public_key . '" method="POST" id="unitpay_form">'.
                '<input type="hidden" name="sum" value="' . $sum . '" />'.
                '<input type="hidden" name="account" value="' . $account . '" />'.
                '<input type="hidden" name="desc" value="' . $desc . '" />'.
                '<input type="hidden" name="currency" value="' . $currency . '" />'.
                '<input type="hidden" name="locale" value="' . $locale . '" />'.
                '<input type="hidden" name="signature" value="' . $signature . '" />'.
                '<input type="submit" class="button alt" id="submit_unitpay_form" value="'.__('Pay', 'unitpay').'" />
			 <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel payment and return back to card', 'unitpay').'</a>'."\n".
                '</form>';
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        function receipt_page($order){
            echo '<p>'.__('Thank you for your order, press button to pay.', 'unitpay').'</p>';
            echo $this->generate_form($order);
        }

        function callback(){
            header('Content-type:application/json;  charset=utf-8');

            $method = '';
            $params = array();

            if ((isset($_GET['params'])) && (isset($_GET['method'])) && (isset($_GET['params']['signature']))){
                $params = $_GET['params'];
                $method = $_GET['method'];
                $signature = $params['signature'];


                if (empty($signature)){
                    $status_sign = false;
                }else{
                    $secret_key = $this->secret_key;
                    $status_sign = $this->verifySignature($params, $method, $secret_key);
                }
            }else{
                $status_sign = false;
            }

//    $status_sign = true;

            if ($status_sign){
                switch ($method) {
                    case 'check':
                        $result = $this->check( $params );
                        break;
                    case 'pay':
                        $result = $this->payment( $params );
                        break;
                    case 'error':
                        $result = $this->error( $params );
                        break;
                    default:
                        $result = array('error' =>
                            array('message' => __('Wrong method', 'unitpay'))
                        );
                        break;
                }
            }else{
                $result = array('error' =>
                    array('message' => __('Wrong signature', 'unitpay'))
                );
            }

            echo json_encode($result);
            die();
        }


        function verifySignature($params, $method, $secret)
        {
            return $params['signature'] == $this->getSignature($method, $params, $secret);
        }
        function getSignature($method, array $params, $secretKey)
        {
            ksort($params);
            unset($params['sign']);
            unset($params['signature']);
            array_push($params, $secretKey);
            array_unshift($params, $method);
            return hash('sha256', join('{up}', $params));
        }

        function check( $params )
        {
            $order = new WC_Order( $params['account'] );

            if (!$order->id){
                $result = array('error' =>
                    array('message' => __('Order not created', 'unitpay'))
                );
            }else{

                $sum = number_format($order->order_total, 2, '.','');
                $currency = $order->get_order_currency();

                if ((float)$sum != (float)$params['orderSum']) {
                    $result = array('error' =>
                        array('message' => __('Wrong order sum', 'unitpay'))
                    );
                }elseif ($currency != $params['orderCurrency']) {
                    $result = array('error' =>
                        array('message' => __('Wrong order currency', 'unitpay'))
                    );
                }
                else{
                    $result = array('result' =>
                        array('message' => __('Request successfully', 'unitpay'))
                    );
                }
            }

            return $result;
        }

        function payment( $params )
        {

            $order = new WC_Order( $params['account'] );

            if (!$order->id){
                $result = array('error' =>
                    array('message' => __('Order not created', 'unitpay'))
                );
            }else{

                $sum = number_format($order->order_total, 2, '.','');
                $currency = $order->get_order_currency();

                if ((float)$sum != (float)$params['orderSum']) {
                    $result = array('error' =>
                        array('message' => __('Wrong order sum', 'unitpay'))
                    );
                }elseif ($currency != $params['orderCurrency']) {
                    $result = array('error' =>
                        array('message' => __('Wrong order currency', 'unitpay'))
                    );
                }
                else{

                    $order->payment_complete();

                    $result = array('result' =>
                        array('message' => __('Request successfully', 'unitpay'))
                    );
                }
            }

            return $result;
        }

        function error( $params )
        {
            $order = new WC_Order( $params['account'] );

            if (!$order){
                $result = array('error' =>
                    array('message' => __('Order not created', 'unitpay'))
                );
            }
            else{
                $order->update_status('failed', __('Payment error', 'unitpay'));
                $result = array('result' =>
                    array('message' => __('Request successfully', 'unitpay'))
                );
            }
            return $result;
        }

    }

    /**
     * Add the gateway to WooCommerce
     **/
    function add_unitpay_gateway($methods){
        $methods[] = 'WC_UNITPAY';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_unitpay_gateway');
}
?>