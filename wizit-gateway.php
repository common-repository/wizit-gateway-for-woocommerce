<?php
/**
 * Plugin Name: Wizit Gateway for WooCommerce
 * Plugin URI: https://wizit.money/
 * Description: A payment gateway for Wizit.
 * Version Date: 10 Jul 2024
 * Version: 1.2.2
 * Author: Wizit
 * Author URI: https://wizit.money/
 * Developer: Wizit
 * Developer URI: https://github.com/cyetekmaster/wizitwoo
 * WC requires at least: 4.8.3
 * WC tested up to: 6.5
 * Copyright: © 2009-2024 wizit.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH'))
{
    exit;
} /* Exit if accessed directly */

/**
 *
 *  Define all constant values
 */
define('WIZIT_PLUGIN_ROOT', dirname(__FILE__) . '/');

register_activation_hook(__FILE__, 'wizit_activation');
function wizit_activation()
{
    global $wpdb;

    /* if ( get_option('woocommerce_wizit_settings' ) ) {
    $cst_setting = get_option('woocommerce_wizit_settings');
    $cst_setting['success_url'] = '';
    $cst_setting['fail_url'] = '';
    update_option('woocommerce_wizit_settings', $cst_setting);
    } */

    // call api and send website detail
    $wizit_register_merchant_helper = new wizit_register_merchant_class();
    $wizit_register_merchant_helper->call_register_merchant_plugin('wizit_activation');

}

register_deactivation_hook(__FILE__, 'wizit_deactivation');
function wizit_deactivation()
{
    global $wpdb;

    /* if ( get_option('woocommerce_wizit_settings' ) ) {
    $cst_setting = get_option('woocommerce_wizit_settings');
    $cst_setting['success_url'] = '';
    $cst_setting['fail_url'] = '';
    update_option('woocommerce_wizit_settings', $cst_setting);
    } */

    // call api and send website detail
    $wizit_register_merchant_helper = new wizit_register_merchant_class();
    $wizit_register_merchant_helper->call_register_merchant_plugin('wizit_deactivation');

}

// plugin uninstallation
register_uninstall_hook(__FILE__, 'wizit_uninstall');

function wizit_uninstall()
{

	// call api and send website detail
    $wizit_register_merchant_helper = new wizit_register_merchant_class();
    $wizit_register_merchant_helper->call_register_merchant_plugin('wizit_uninstall');

    delete_option('woocommerce_wizit_settings');

}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
{

    if (!class_exists('Woocommerce_Wizit_Init'))
    {

        /**
         * Localisation
         *
         */
        load_plugin_textdomain('woocommerce-wizit-gateway', false, dirname(plugin_basename(__FILE__)) . '/lang');

        final class Woocommerce_Wizit_Init
        {

            private static $instance = null;
            public static function initialize()
            {
                if (is_null(self::$instance))
                {
                    self::$instance = new self();
                }

                return self::$instance;
            }

            public function __construct()
            {

                // called after all plugins have loaded
                add_action('plugins_loaded', array(
                    $this,
                    'plugins_loaded'
                ));
                add_filter('plugin_action_links_' . plugin_basename(__FILE__) , array(
                    $this,
                    'plugin_links'
                ));
                add_action('admin_enqueue_scripts', array(
                    $this,
                    'wc_wizit_register_plugin_scripts'
                ));
                add_action('wp_ajax_get_pending_capture_amount', array(
                    $this,
                    'get_pending_capture_amount'
                ));
                add_action('wp_ajax_nopriv_get_pending_capture_amount', array(
                    $this,
                    'get_pending_capture_amount'
                ));

                add_action('wp_ajax_merchant_autherised_to_capture_amount', array(
                    $this,
                    'merchant_autherised_to_capture_amount_manually'
                ));
                add_action('wp_ajax_nopriv_merchant_autherised_to_capture_amount', array(
                    $this,
                    'merchant_autherised_to_capture_amount_manually'
                ));

                
                add_action( 'before_woocommerce_init', function() {
                    // add HPOS support
                    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
                    }

                    // Check if the required class exists
                    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                        // Declare compatibility for 'cart_checkout_blocks'
                        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
                    }
                } );


                add_action('woocommerce_blocks_loaded', function(){
                    // Check if the required class exists                    
                    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
                        require_once dirname(__FILE__) . '/class-wizit-checkout-block.php';
                        add_action(
                            'woocommerce_blocks_payment_method_type_registration',
                            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                                $payment_method_registry->register( new Wizit_Custom_Gateway_Blocks() );
                            }
                        );
                    }
                });

            }

            /**
             * Take care of anything that needs all plugins to be loaded
             */
            public function plugins_loaded()
            {

                if (!class_exists('WC_Payment_Gateway'))
                {
                    return;
                }

                /**
                 * Add the gateway to WooCommerce
                 */
                if(!class_exists('WC_Gateway_Wizit')){
                    require_once (plugin_basename('class-wizit-gateway.php'));
                }
                
                add_filter('woocommerce_payment_gateways', array(
                    $this,
                    'add_wizit_gateway'
                ) , 10, 1);

                if(!class_exists('Wizit_API')){
                     require_once dirname(__FILE__) . '/wizit/wizit_api.php';
                }
               

                if (!class_exists('wizit_hook_class'))
                {
                    require_once dirname(__FILE__) . '/wizit_hook_class.php';
                }

                $hook_class = wizit_hook_class::initialize();
                $hook_class->register_hooks();
            }

            public function add_wizit_gateway($gateways)
            {
                // array_unshift($methods, 'WC_Gateway_Wizit');
                $gateways[] = 'WC_Gateway_Wizit';
                return $gateways;
            }

            /**
             *   Register style sheet.
             */
            public function wc_wizit_register_plugin_scripts()
            {
                wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . 'assets/js/capture-payment.js', array() , '1.0');
            }

            public function get_pending_capture_amount()
            {

                if (isset($_POST['order_id']))
                {
                    $this->log = new WC_Logger();
                    $order_id = sanitize_text_field($_POST['order_id']);
                    $orderToken = get_post_meta($order_id, 'wz_token', true);
                    $merchantrefernce = get_post_meta($order_id, 'merchantrefernce', true);
                    $wzTxnId = get_post_meta($order_id, 'wz_txn_id', true);
                    $uniqid = md5(time() . $order_id);
                    $getsettings = get_option('woocommerce_wizit_settings', true);
                    $apikey = $getsettings['wz_api_key'];
                    $api_data = array(
                        'transactionId' => $wzTxnId,
                        'token' => $orderToken,
                        'merchantReference' => $merchantrefernce
                    );

                    $wzapi = new Wizit_API();
                    $wzresponse = $wzapi->get_order_payment_status_api($apikey, $api_data);
                    if (false === $wzresponse || false !== $wzapi->get_api_error())
                    {
                        $this
                            ->log
                            ->add('Wizit', sprintf('failure: %s', $wzapi->get_api_error()));

                        esc_attr_e('00.00');
                    }
                    else
                    {

                        $pending_amount = $wzresponse['pendingCaptureAmount'];
                        $min_p_amount = $pending_amount['amount'];
                        $this
                            ->log
                            ->add('Wizit', sprintf('success, pending capture amount: %s', $min_p_amount));

                        esc_attr_e($min_p_amount);
                    }
                    wp_die();
                }

            }

            public function merchant_autherised_to_capture_amount_manually()
            {

                if (isset($_POST['order_id']) && isset($_POST['captureamount']) && isset($_POST['capture_reason']) && isset($_POST['capture_avail_new']))
                {
                    $this->log = new WC_Logger();

                    $order_id = sanitize_text_field($_POST['order_id']);
                    $captureamount = sanitize_text_field($_POST['captureamount']);
                    $capture_reason = sanitize_text_field($_POST['capture_reason']);
                    $capture_avail = sanitize_text_field($_POST['capture_avail_new']);
                    $order = new WC_Order($order_id);
                    //$orderToken = get_post_meta( $order_id, 'wz_token', false );
                    $merchantrefernce = get_post_meta($order_id, 'merchantrefernce', true);
                    $wzTxnId = get_post_meta($order_id, 'wz_txn_id', true);
                    $currency = get_woocommerce_currency();
                    $uniqid = md5(time() . $order_id);
                    $getsettings = get_option('woocommerce_wizit_settings', true);
                    $apikey = $getsettings['wz_api_key'];
                    $order_status = $order->get_status();

                    if ('00.00' == $capture_avail)
                    {
                        $order->add_order_note('Order capture failed. No pending balanced left to be captured.' . PHP_EOL . 'Merchant reason for capture: ' . $capture_reason . PHP_EOL . 'Amount: $' . $captureamount);
                        esc_attr_e('00.00');
                    }
                    elseif ($captureamount > $capture_avail)
                    {

                        $order->add_order_note('Order capture failed. capture amount $' . $captureamount . ' was specified greater than the pending amount $' . $capture_avail . PHP_EOL . 'Merchant reason for capture: ' . $capture_reason);
                        esc_attr_e('00.00');
                    }
                    elseif ($captureamount <= 0)
                    {

                        $order->add_order_note('Order capture failed. capture amount $' . $captureamount . ' was specified invailid amount.' . PHP_EOL . 'Merchant reason for capture: ' . $capture_reason);
                        esc_attr_e('00.00');
                    }
                    else
                    {

                        $api_data = array(
                            'RequestId' => $uniqid,
                            'merchantReference' => $merchantrefernce,
                            'amount' => array(
                                'amount' => $captureamount,
                                'currency' => $currency
                            ) ,
                            //"paymentEventMerchantReference"=>$merchantReference
                            
                        );

                        $wzapi = new Wizit_API();
                        $wzresponse = $wzapi->order_partial_capture_api($apikey, $api_data, $wzTxnId);
                        $this
                            ->log
                            ->add('Wizit', '========= capture (Parttial Capture) API called' . PHP_EOL);
                        if (false === $wzresponse || false !== $wzapi->get_api_error())
                        {
                            $this
                                ->log
                                ->add('Wizit', '========= capture (Parttial Capture) API return failure' . PHP_EOL);

                            $order->add_order_note($wzapi->get_api_error());
                            esc_attr_e('00.00');

                        }
                        else
                        {
                            $this
                                ->log
                                ->add('Wizit', '========= capture (Parttial Capture) API return success' . PHP_EOL);

                            $pending_amount = $wzresponse['pendingCaptureAmount'];
                            $avail_p_amount = $pending_amount['amount'];

                            if ('0' == $avail_p_amount && 'on-hold' == $order_status)
                            {

                                $order->add_order_note('Wizit Payment Authorised Transaction ' . $wzTxnId . PHP_EOL . 'Merchant reason for capture: ' . $capture_reason . PHP_EOL . 'Capture Amount: $' . $captureamount);
                                $order->update_status('processing');

                            }
                            else
                            {

                                $order->add_order_note('Wizit Payment Authorised Transaction ' . $wzTxnId . PHP_EOL . 'Merchant reason for capture: ' . $capture_reason . PHP_EOL . 'Capture Amount: $' . $captureamount);
                            }

                            esc_attr_e($pending_amount);
                        }

                    }

                    wp_die();
                }

            } // function merchant_autherised_to_capture_amount_manually()
            
            /**
             * Plugin page links
             */
            public function plugin_links($links)
            {
                // $plugin_links = array(
                // 	'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wizit' ) . '">' . __( 'Settings', 'woocommerce-wizit-gateway' ) . '</a>',
                // 	'<a href="https://docs.woocommerce.com/document/">' . __( 'Docs', 'woocommerce-wizit-gateway' ) . '</a>',
                // );
                $plugin_links = array(
                    '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wizit') . '">' . __('Settings', 'woocommerce-wizit-gateway') . '</a>',
                    '<a href="https://wordpress.org/support/plugin/wizit-gateway-for-woocommerce/reviews/" target="_blank">' . __('Leave a Review', 'woocommerce-wizit-gateway') . '</a>',
                );

                return array_merge($plugin_links, $links);
            }

            public static function wc_wizit_log($message)
            {
                $thislog = new WC_Logger();
                $thislog->add('WIZIT_PLUGIN_ROOT', print_r($message, true));
            }

           


        } // final class Woocommerce_Wizit_Init
        $GLOBALS['Woocommerce_Wizit_Init'] = Woocommerce_Wizit_Init::initialize();

    } // if ( ! class_exists( 'Woocommerce_Wizit_Init' ) )
    
} // end of check woocommerce plugin activate or not
// Check checkout amount and display gatway option, under rull of Wizardpay min/max value
add_filter('woocommerce_available_payment_gateways', 'wizit_unset_gateway_by_price');
function wizit_unset_gateway_by_price($available_gateways)
{
    global $woocommerce;
    $getsettings = get_option('woocommerce_wizit_settings', true);
    $store_currency = strtoupper(get_option('woocommerce_currency'));
    if (is_admin())
    {
        return $available_gateways;
    }
    if (!is_checkout())
    {
        return $available_gateways;
    }
    $unset = false;
    $sub_totalamount = WC()
        ->cart->total;
    if (is_array($getsettings))
    {
        if (!($sub_totalamount >= $getsettings['merchant_minimum_amount'] && $sub_totalamount <= $getsettings['merchant_maximum_amount']) || 'AUD' != $store_currency)
        {
            unset($available_gateways['wizit']);
        }
        return $available_gateways;
    }

    return $available_gateways;
}

// function for register api


class wizit_register_merchant_class
{
    public function call_register_merchant_plugin($status)
    {
		$store_address = get_option('woocommerce_store_address');
		$store_address_2 = get_option('woocommerce_store_address_2');
		$store_city = get_option('woocommerce_store_city');
		$store_postcode = get_option('woocommerce_store_postcode');

		// // The country/state
		$store_raw_country = get_option('woocommerce_default_country');

		// // Split the country/state
		$split_country = explode(":", $store_raw_country);

		// // Country and state separated:
		$store_country = $split_country[0];
		$store_state = $split_country[1];

		$apidata = array(
			'merchantStoreUrl' => get_site_url() ,
			'merchantName' => get_bloginfo() ,
			'merchantAddress' => $store_address . ' ' . $store_address_2 . ' ' . $store_city . ' ' . $store_postcode . ' ' . $store_state . ' ' . $store_country,
			'merchantContactNumber' => '',
			'merchantEmail' => '',
			'storeCurrency' => get_woocommerce_currency() ,
			'dateInstalled' => date("Y-m-d") . 'T' . date("H:i:s") . '.000Z',
			'platform' => '2.0Dev ' . $status
		);

		include ('wizit/access.php');

        $actualapicall = 'RegisterMerchantPlugin';
        $finalapiurl = $this->base . $actualapicall;

        $log = new WC_Logger();

        $log->add('Wizit', '========= RegisterMerchantPlugin api called' . PHP_EOL);
		$log->add('Wizit', '========= RegisterMerchantPlugin api called url = ' . $finalapiurl . PHP_EOL);
        $log->add('Wizit', sprintf('request : %s', json_encode($apidata)) .  PHP_EOL);
        $apiresult = $this->post_to_api($finalapiurl, $apidata);
        $log->add('Wizit', sprintf('result : %s', json_encode($apiresult)) .  PHP_EOL);
    }

    private function post_to_api($url, $requestbody)
    {

        $response = wp_remote_post($url, array(
            'timeout' => 80,
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'application/json'
            ) ,
            'body' => json_encode($requestbody) ,
        ));

        if (!is_wp_error($response))
        {
            return $response;
        }
        else
        {
            return false;
        }

    }
}



// create local table for save temp order
global $wizit_db_version;
$wizit_db_version = '1.1.1';

function wizit_db_table_install() {
	global $wpdb;
	global $wizit_db_version;
    $installed_ver = get_option( "wizit_db_version" );

    if($installed_ver != $wizit_db_version){
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        // add order table
        $table_wizit_order = $wpdb->prefix . 'wizit_order';	
        $sql_table_wizit_order = "CREATE TABLE $table_wizit_order (
            `order_id` varchar(36) NOT NULL,
            `parent_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
            `num_items_sold` int(11) NOT NULL DEFAULT '0',
            `total_sales` double NOT NULL DEFAULT '0',
            `tax_total` double NOT NULL DEFAULT '0',
            `shipping_total` double NOT NULL DEFAULT '0',
            `net_total` double NOT NULL DEFAULT '0',
            `returning_customer` tinyint(1) DEFAULT NULL,
            `customer_id` bigint(20) UNSIGNED NOT NULL,
            `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            `status` int(11) NOT NULL DEFAULT '0'
        ) $charset_collate;";	
        dbDelta( $sql_table_wizit_order );


        // add sql_table_wizit_order_coupon
        $table_wizit_order_coupon = $wpdb->prefix . 'wizit_order_coupon';	
        $sql_table_wizit_order_coupon = "CREATE TABLE $table_wizit_order_coupon (
            `order_id` varchar(36) NOT NULL,
            `coupon_id` bigint(20) NOT NULL,
            `discount_amount` double NOT NULL DEFAULT '0'
        ) $charset_collate;";	
        dbDelta( $sql_table_wizit_order_coupon );


        // add wizit_order_product
        $table_wizit_order_product = $wpdb->prefix . 'wizit_order_product';	
        $sql_table_wizit_order_product = "CREATE TABLE $table_wizit_order_product (
            `order_item_id` bigint(20) UNSIGNED NOT NULL,
            `order_id` varchar(36) NOT NULL,
            `product_id` bigint(20) UNSIGNED NOT NULL,
            `variation_id` bigint(20) UNSIGNED NOT NULL,
            `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
            `product_qty` int(11) NOT NULL,
            `product_net_revenue` double NOT NULL DEFAULT '0',
            `product_gross_revenue` double NOT NULL DEFAULT '0',
            `coupon_amount` double NOT NULL DEFAULT '0',
            `tax_amount` double NOT NULL DEFAULT '0',
            `shipping_amount` double NOT NULL DEFAULT '0',
            `shipping_tax_amount` double NOT NULL DEFAULT '0'
        ) $charset_collate;";	
        dbDelta( $sql_table_wizit_order_product );


        // add wizit_order_product
        $table_wizit_order_tax = $wpdb->prefix . 'wizit_order_tax';	
        $sql_table_wizit_order_tax = "CREATE TABLE $table_wizit_order_tax (
            `order_id` varchar(36) NOT NULL,
            `tax_rate_id` bigint(20) UNSIGNED NOT NULL,
            `shipping_tax` double NOT NULL DEFAULT '0',
            `order_tax` double NOT NULL DEFAULT '0',
            `total_tax` double NOT NULL DEFAULT '0'
        ) $charset_collate;";	
        dbDelta( $sql_table_wizit_order_tax );


        add_option( 'wizit_db_version', $wizit_db_version );
    }
}


function wizit_db_table_uninstall() {
	global $wpdb;
	global $wizit_db_version;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();

    // add order table
	$table_wizit_order = $wpdb->prefix . 'wizit_order';	   
    // add sql_table_wizit_order_coupon
	$table_wizit_order_coupon = $wpdb->prefix . 'wizit_order_coupon';	
    // add wizit_order_product
    $table_wizit_order_product = $wpdb->prefix . 'wizit_order_product';	
    // add wizit_order_product
    $table_wizit_order_tax = $wpdb->prefix . 'wizit_order_tax';	


    dbDelta( 'DROP TABLE `'. $table_wizit_order .'`, `'. $table_wizit_order_coupon .'`, `'. $table_wizit_order_product .'` , `' . $table_wizit_order_tax . '`;' );

    add_option( 'wizit_db_version', '-1' );
}


register_activation_hook( __FILE__, 'wizit_db_table_install' );
register_deactivation_hook(__FILE__, 'wizit_db_table_uninstall');


