<?php



class wizit_hook_class

{





    private static $wizit_rating_notice_setting_name = 'woocommerce_wizit_settings_rating';



    private static $instance = null;

    public static function initialize() {

        if ( is_null( self::$instance ) ) {

            self::$instance = new self();

        }



        return self::$instance;

    }







    public function __construct(){

        // load hook

        // admin message - plugin page only

        add_action( 'admin_notices', array(

            $this,

            'display_plugin_rating_admin_notices'

        ) );



        add_action( 'wp_ajax_wizit_plugin_rating_did_callback', array(

            $this,

            'wizit_plugin_rating_did_callback'

        ) );





        add_action( 'woocommerce_before_add_to_cart_quantity', array(

            $this,

            'wizit_update_price_with_variation_price'

        ));


    }





    public function remove_hooks(){

        $log = new WC_Logger();

        



        // load setting

        $settings = get_option('woocommerce_wizit_settings', true);



        $payment_info_on_product_hook = is_array($settings) && array_key_exists('payment_info_on_product_hook', $settings) ? $settings['payment_info_on_product_hook'] : ""; 

        $payment_info_on_product_hook_priority = is_array($settings) && array_key_exists('payment_info_on_product_hook_priority', $settings) ? $settings['payment_info_on_product_hook_priority'] : ""; // $settings['payment_info_on_product_hook_priority']; 

        $payment_info_on_product_cat_hook = is_array($settings) && array_key_exists('payment_info_on_product_cat_hook', $settings) ? $settings['payment_info_on_product_cat_hook'] : ""; // $settings['payment_info_on_product_cat_hook']; 

        $payment_info_on_product_cat_hook_priority = is_array($settings) && array_key_exists('payment_info_on_product_cat_hook_priority', $settings) ? $settings['payment_info_on_product_cat_hook_priority'] : ""; // $settings['payment_info_on_product_cat_hook_priority']; 



        if(!isset($environments)){

            include ('wizit/wizit-default-value.php');

        }

        



        // remove old product hook

        if (isset($payment_info_on_product_hook) && !empty($payment_info_on_product_hook) && isset($payment_info_on_product_hook_priority) 

                && !empty($payment_info_on_product_hook_priority) && !is_nan($payment_info_on_product_hook_priority))

        {

            remove_action($payment_info_on_product_hook, array(

                $this,

                'remove_info_for_product_detail_page'

            ) , 

            (int) $payment_info_on_product_hook_priority , 

            0);

             

        }

        

        



        // always remove default hooks

        remove_action($def_payment_info_on_product_hook, array(

            $this,

            'remove_info_for_product_detail_page'

        ) , 

        (int) $def_payment_info_on_product_hook_priority , 

        0);

        

        





        // remove product cart hook

        if (isset($payment_info_on_product_cat_hook) && !empty($payment_info_on_product_cat_hook) && isset($payment_info_on_product_cat_hook_priority) 

                && !empty($payment_info_on_product_cat_hook_priority) && !is_nan($payment_info_on_product_cat_hook_priority))

        {

            remove_action($payment_info_on_product_cat_hook, array(

                 $this,

                'remove_info_for_product_cat_page'

            ) , 

            (int) $payment_info_on_product_cat_hook_priority , 

            0);

            

            

        }

        

        // always remove default hooks

        remove_action($def_payment_info_on_product_cat_hook, array(

            $this,

            'remove_info_for_product_cat_page'

        ) , 

        (int) $def_payment_info_on_product_cat_hook_priority , 

        0);



        



    }





    public function register_hooks(){



        $log = new WC_Logger();

        



        $settings = get_option('woocommerce_wizit_settings', true);





        $payment_info_on_product_hook = ''; 

        $payment_info_on_product_hook_priority = 15; 

        $payment_info_on_product_cat_hook = ''; 

        $payment_info_on_product_cat_hook_priority = 15; 





        if( is_array($settings) &&

            array_key_exists('payment_info_on_product_hook',$settings ) &&

            array_key_exists('payment_info_on_product_hook_priority',$settings ) &&

            array_key_exists('payment_info_on_product_cat_hook',$settings ) &&

            array_key_exists('payment_info_on_product_cat_hook_priority',$settings ) &&



            isset($settings['payment_info_on_product_hook']) && 

            isset($settings['payment_info_on_product_hook_priority']) && 

            isset($settings['payment_info_on_product_cat_hook']) && 

            isset($settings['payment_info_on_product_cat_hook_priority']))

        {

            $payment_info_on_product_hook = $settings['payment_info_on_product_hook']; 

            $payment_info_on_product_hook_priority = $settings['payment_info_on_product_hook_priority']; 

            $payment_info_on_product_cat_hook = $settings['payment_info_on_product_cat_hook']; 

            $payment_info_on_product_cat_hook_priority = $settings['payment_info_on_product_cat_hook_priority']; 

        }

        



        if(!isset($environments)){

            include ('wizit/wizit-default-value.php');

        }





        // add action for print wizit on product detail page

        if (isset($payment_info_on_product_hook) && !empty($payment_info_on_product_hook) && isset($payment_info_on_product_hook_priority) 

        && !empty($payment_info_on_product_hook_priority) && !is_nan($payment_info_on_product_hook_priority))

        {

            add_action($payment_info_on_product_hook, array(

                $this,

                'wizit_print_info_for_product_detail_page'

            ) , 

            (int) $payment_info_on_product_hook_priority , 

            0);



        }

        else

        {

            add_action($def_payment_info_on_product_hook, array(

                    $this,

                    'wizit_print_info_for_product_detail_page'

                ) , 

                (int) $def_payment_info_on_product_hook_priority, 

                0

            );



        }



        // add action for print wizit on product cat page

        if (isset($payment_info_on_product_cat_hook) && !empty($payment_info_on_product_cat_hook) && isset($payment_info_on_product_cat_hook_priority) 

        && !empty($payment_info_on_product_cat_hook_priority) && !is_nan($payment_info_on_product_cat_hook_priority))

        {

            add_action($payment_info_on_product_cat_hook, array(

                 $this,

                'wizit_print_info_for_product_cat_page'

            ) , 

            (int) $payment_info_on_product_cat_hook_priority , 

            0);

        }

        else

        {

            add_action($def_payment_info_on_product_cat_hook, array(

                 $this,

                'wizit_print_info_for_product_cat_page'

            ) , 

            (int) $def_payment_info_on_product_cat_hook_priority, 

            0);

        }



        // add check out page

        add_action('woocommerce_proceed_to_checkout', array(

             $this,

            'wizit_print_info_for_cart_page'

        ) , 15, 0);



        // add hook to new woo block
        add_filter(
            '__experimental_woocommerce_blocks_add_data_attributes_to_block',
            function ( $allowed_blocks ) {
                $allowed_blocks[] = 'wizit/checkout-totals-block';
                return $allowed_blocks;
            },
            10,
            1
        );

    }





    /*

     *  Print a paragraph of Wizit info onto the individual product pages if enabled and the product is valid.

     *  Note: Default Hooked onto the "woocommerce_single_product_summary" Action.

     *

    */

    //add_action('woocommerce_single_product_summary', 'wizit_print_info_for_product_detail_page', 15);

    public static function wizit_print_info_for_product_detail_page()

    {

        



        if (!function_exists('process_and_print_wizit_paragraph'))

        {

            include ('wizit/wizit-helper.php');

        }



        $settings = get_option('woocommerce_wizit_settings', true);



        if (!isset($settings) || !isset($settings['payment_info_on_product']) || $settings['payment_info_on_product'] != 'yes' 

        || empty($settings['payment_info_on_product_text']))

        {

            # Don't display anything on product pages unless the "Payment info on individual product pages"

            # box is ticked and there is a message to display.

            return;

        }



        global $post, $product;



        // if (is_null($product))

        // {

        //     $product = get_wizit_product_from_the_post();

        // }

        $price = $product->get_price();
        $is_on_sale = false;

        if($product->get_sale_price() != '' && is_numeric($product->get_sale_price())) {
            $price = $product->get_sale_price();
            $is_on_sale = true;
        }



        wizit_hook_class::load_required_css_js_file();

        process_and_print_wizit_paragraph(

            $settings['payment_info_on_product_text'], 
            'wizit_html_on_individual_product_pages', 
            $price, 
            plugin_dir_url(__FILE__) , 
            'wizit-logo-inline', 
            true, 
            $product->is_type('variable'),
            $product->get_id(),
            $is_on_sale
        );

    }



    public static function remove_info_for_product_detail_page()

    {



    }



    /*

     *  Print a paragraph of Wizit info onto the cart pages if enabled.

     *  Note: Default Hooked onto the "woocommerce_proceed_to_checkout" Action.

     *

    */



    public static function wizit_print_info_for_cart_page()

    {

        



        if (!function_exists('process_and_print_wizit_paragraph'))

        {

            include ('wizit/wizit-helper.php');

        }



        $settings = get_option('woocommerce_wizit_settings', true);



        if (!isset($settings) || !isset($settings['payment_info_on_cart']) || $settings['payment_info_on_cart'] != 'yes' || empty($settings['payment_info_on_cart_text']))

        {

            # Don't display anything on product pages unless the "Payment info on individual product pages"

            # box is ticked and there is a message to display.

            return;

        }



        global $woocommerce;



        if (!isset(WC()->cart))

        {

            return;

        }



        $price = WC()

            ->cart->total;



        wizit_hook_class::load_required_css_js_file();



        process_and_print_wizit_paragraph($settings['payment_info_on_cart_text'], 'wizit_html_on_cart_pages', $price, plugin_dir_url(__FILE__) , 'wizit-logo-inline');

    }



    /*

     *  Print a paragraph of Wizit info onto the product cat pages if enabled.

     *  Note: Default Hooked onto the "woocommerce_after_shop_loop_item_title" Action.

     *

    */

    //add_action('woocommerce_after_shop_loop_item_title', 'wizit_print_info_for_product_cat_page', 15);

    public static function wizit_print_info_for_product_cat_page()

    {



        



        if (!function_exists('process_and_print_wizit_paragraph'))

        {

            include ('wizit/wizit-helper.php');

        }



        $settings = get_option('woocommerce_wizit_settings', true);



        if (!isset($settings) || !isset($settings['payment_info_on_product_cat']) || $settings['payment_info_on_product_cat'] != 'yes' || empty($settings['payment_info_on_product_cat_text']))

        {

            # Don't display anything on product pages unless the "Payment info on individual product pages"

            # box is ticked and there is a message to display.

            return;

        }



        global $post, $product;



        $price = $product->get_price();



        wizit_hook_class::load_required_css_js_file();



        process_and_print_wizit_paragraph($settings['payment_info_on_product_cat_text'], 'wizit_html_on_product_cat_pages', $price, plugin_dir_url(__FILE__) , 'wizit-logo-block');

    }



    public static function remove_info_for_product_cat_page()

    {



    }





    public static function display_plugin_rating_admin_notices(){

        $html = '

        <div class="notice notice-success is-dismissible wizit-plugin-rating-admin-notices">

            <h2>Give wizit a review</h2>

            <p>Thank you for choosing wizit. We hope you love it. 

            Could you take a couple of seconds posting a nice review to share your happy experience?</p>

            <p>We will be forever grateful. Thank you in advance ;)</p>

            <p>

                <a href="https://wordpress.org/support/plugin/wizit-gateway-for-woocommerce/reviews/" target="_blank" class="button button-primary" id="wizit-review-rate-btn">Rate now</a>

                <a href="#" class="button" id="wizit-review-later-btn">Later</a>

                <a href="#" class="button" id="wizit-review-did-btn">Already did</a>

            </p>

        </div>

        ';



        $rating_setting = get_option(wizit_hook_class::$wizit_rating_notice_setting_name);

        $screen = get_current_screen(); 

        if($screen->id == "plugins" && (empty($rating_setting) || $rating_setting != 1)){

                // load admin js

                wizit_hook_class::load_required_css_js_file('admin');

                # Allow other plugins to maniplulate or replace the HTML echoed by this funtion.

                echo apply_filters("display_plugin_rating_admin_notices", $html);

         }

    }



    public static function wizit_plugin_rating_did_callback(){

        update_option(wizit_hook_class::$wizit_rating_notice_setting_name, 1 );

    }







    public static function load_required_css_js_file($type = 'public'){    

        if($type == 'admin'){

            wp_enqueue_style('wizit_admin_css', plugins_url('assets/css/admin.css', __FILE__) , array() , '1.0');

            wp_enqueue_script('wizit_admin_js', plugins_url('assets/js/admin.js', __FILE__) , array() , '1.0');

        }else {

            $settings = get_option('woocommerce_wizit_settings', true);            

            if(isset($settings) && is_array($settings) && array_key_exists('enabled', $settings)){

                $enabled = $settings['enabled'];

                if($enabled === 'yes'){

                    // load css and js

                    wp_enqueue_style('wizit_custom_css', plugins_url('assets/css/custom-popup.css', __FILE__) , array() , '1.0');

                    wp_enqueue_style('wizit_checkout_css', plugins_url('assets/css/checkout.css', __FILE__) , array() , '1.0');

                    wp_enqueue_script('wizit_custom_js', plugins_url('assets/js/custom-popup.js', __FILE__) , array() , '1.0');

                }

            }

        }

    }



    public static function wizit_update_price_with_variation_price(){

        $log = new WC_Logger(); 

        global $product;

        $price = null;

        $is_on_sale = false;

        if(!$product->is_type('variable')){
            $price = $product->get_price();
            if($product->get_sale_price() != '' && is_numeric($product->get_sale_price())) {
                $price = $product->get_sale_price();
                $is_on_sale = true;
            }
        }


        if (!function_exists('process_and_print_wizit_paragraph'))

        {

            include ('wizit/wizit-helper.php');

        }



        $settings = get_option('woocommerce_wizit_settings', true);



        if (!isset($settings) || !isset($settings['payment_info_on_product']) || $settings['payment_info_on_product'] != 'yes' 

        || empty($settings['payment_info_on_product_text']))

        {

            # Don't display anything on product pages unless the "Payment info on individual product pages"

            # box is ticked and there is a message to display.

            return;

        }





        wizit_hook_class::load_required_css_js_file();



        $html = wizit_get_display_html(

            $settings['payment_info_on_product_text'], 
            'wizit_html_on_individual_product_pages2', 
            $price, 
            plugin_dir_url(__FILE__) , 
            'wizit-logo-inline', 
            true, 
            $product->is_type('variable'),
            $product->get_id(),
            $is_on_sale        
        );

        ?>

        <script>

            jQuery(document).ready(function(){

               // jQuery('#wizit-price-range-holder').html(``);

            });

        </script>

        <?php

    }



}

