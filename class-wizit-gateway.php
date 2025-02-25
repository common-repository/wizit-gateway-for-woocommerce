<?php

/* Exit if accessed directly */

if (!defined('ABSPATH'))

{

    exit;

}



spl_autoload_register('WC_Gateway_Wizit::autoload');





require_once dirname(__FILE__) . '/wizit_hook_class.php';

/**

 * WC_Gateway_Wizit

 *

 * @class       WC_Gateway_Wizit

 * @extends     WC_Payment_Gateway

 * @version     1.2.2

 */



class WC_Gateway_Wizit extends WC_Payment_Gateway

{



    public $wizit;

    protected $paymentURL = false; /* where to redirect browser for payment */

    protected $errorMessage = false; /* last transaction error message */

    protected static $instance = null;

    public $checkresponse = array();



    public function __construct()

    {

        global $woocommerce;



        $this->id = 'wizit';

        $this->icon = 'https://www.wizit.money/img/plugin/wizit.png';// esc_url(plugin_dir_url(__FILE__) . 'images/Group.png');

        $this->has_fields = true;



        /*adding support for subscription to the payment gateway*/

        $this->supports = array(

            'products',

            'refunds',

        );



        $this->method_title = __('Wizit', 'woocommerce-wizit-gateway');

        $this->method_description = __('Give your customer the option to buy now and pay later with 4 x interest free fortnightly instalments', 'woocommerce-wizit-gateway');



        /* Load the form fields. */

        $this->init_form_fields();



        /* Load the settings. */

        $this->init_settings();



        /* Define user set variables */

        include ('wizit/access.php');

        $this->wizit_base_url = $this->base . $this->version . $this->intermediate;



        $this->title = $this->get_option('title');

        $this->description = $this->get_option('description');

        $this->wz_api_key = $this->get_option('wz_api_key');

        $this->wz_minimum_amount = $this->get_option('wz_minimum_amount');

        $this->wz_maximum_amount = $this->get_option('wz_maximum_amount');

        $this->merchant_minimum_amount = $this->get_option('merchant_minimum_amount');

        $this->merchant_maximum_amount = $this->get_option('merchant_maximum_amount');

        $this->access_userid = $this->get_option('access_userid');

        $this->success_url = $this->get_option('success_url');

        $this->fail_url = $this->get_option('fail_url');

        $this->statement_descriptor = $this->get_option('statement_descriptor', wp_specialchars_decode(get_bloginfo('name') , ENT_QUOTES));

        $this->capture = true;// $this->get_option('capture', 'yes') === 'yes' ? true : false;



        $this->supported_currencies = array(

            'AUD'

        );



        // check environment_mode

        if ($this->get_option('environment_mode', 'production') === 'sandbox' ? true : false)

        {

            $this->wizit_base_url = $this->baseSandbox . $this->version . $this->intermediate;

            $this->wz_api_key = $this->get_option('wz_api_key_test');

        }



        add_action('woocommerce_init', array(

            $this,

            'get_order_status_failed_error_notice'

        ));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(

            $this,

            'process_admin_options'

        ));

        //add_action( 'admin_notices', array( $this,'wizit_admin_notice' ));

        add_action('wp_enqueue_scripts', array(

            $this,

            'payment_scripts'

        ));



        add_action('woocommerce_api_wc_gateway_' . $this->id, array(

            $this,

            'handle_checkout_redirecturl_response'

        ));

        //add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'process_cancel' ) );

        //add_action( 'woocommerce_order_status_processing_to_cancelled', array( $this, 'process_cancel' ) );

        // add_action('woocommerce_order_item_add_action_buttons', array(

        //     $this,

        //     'wc_order_add_capture_buttons_callback'

        // ) , 10, 1);



        add_action('woocommerce_order_status_changed', array(

            $this,

            'process_cancel'

        ) , 99, 4);



        //add_action( 'woocommerce_order_status_cancelled', array( $this, 'process_cancel', 10, 1 ) );

        //add_action( 'woocommerce_order_status_changed', array( $this, 'process_cancel' ), 99, 3 );

        //add_action( 'woocommerce_order_actions', array( $this, 'capture_amount_action') );

        add_action('admin_enqueue_scripts', array(

            $this,

            'init_admin_assets'

        ) , 10, 0);



        /* initiation of logging instance */

        $this->log = new WC_Logger();



        // register action for product, cart, product cat

        // $this->register_action_for_prod_cart_prodCat(

        //     $this->get_option('payment_info_on_product_hook') , $this->get_option('payment_info_on_product_hook_priority') , 

        //     $this->get_option('payment_info_on_product_cat_hook') , $this->get_option('payment_info_on_product_cat_hook_priority'));



    }



    public function process_admin_options()

    {

        //parent::process_admin_options();

        $error = false;

        $mmin = 0;

        $mmax = 0;



        $wmin = 0;

        $wmax = 0;



        if (isset($_POST['woocommerce_wizit_wz_api_key']))

        {

            $apikey = trim(sanitize_text_field($_POST['woocommerce_wizit_wz_api_key']));

        }

        if (isset($_POST['woocommerce_wizit_merchant_minimum_amount']))

        {

            $mmin = trim(sanitize_text_field($_POST['woocommerce_wizit_merchant_minimum_amount']));

        }

        if (isset($_POST['woocommerce_wizit_merchant_maximum_amount']))

        {

            $mmax = trim(sanitize_text_field($_POST['woocommerce_wizit_merchant_maximum_amount']));

        }



        // check environment

        include ('wizit/access.php');

        $api_url = $this->base . $this->version . $this->intermediate;

        if (isset($_POST['woocommerce_wizit_environment_mode']) && $_POST['woocommerce_wizit_environment_mode'] === 'sandbox')

        {

            $api_url = $this->baseSandbox . $this->version . $this->intermediate;

            $apikey = trim(sanitize_text_field($_POST['woocommerce_wizit_wz_api_key_test']));

        }



        if (empty($apikey))

        {

            $error = true;

            WC_Admin_Settings::add_error('Error: Please enter a valid Wizit API Key');

            return false;

        }



        //$this->set_wz_api_key($apikey);

        $wzapi = new Wizit_API();

        $wzresponse = $wzapi->call_limit_api($apikey, $api_url);

        

        if (false === $wzresponse || false !== $wzapi->get_api_error())

        {

            $error = true;

            WC_Admin_Settings::add_error($wzapi->get_api_error());

            return false;

        }

        else

        {



            $wmin = $wzresponse['minimumAmount'];

            $wmax = $wzresponse['maximumAmount'];



            if (!empty($mmin) && !empty($mmax))

            {



                if ($mmin < $wmin)

                {

                    $error = true;

                    WC_Admin_Settings::add_error('Error: Merchant Minimum Payment Amount can not be less than Wizit Minimum Payment Amount.');

                }



                if ($mmax > $wmax)

                {

                    $error = true;

                    WC_Admin_Settings::add_error('Error: Merchant Maximum Payment Amount can not be more than Wizit Maximum Payment Amount.');

                }



                if ($mmax < $mmin)

                {

                    $error = true;

                    WC_Admin_Settings::add_error('Error: Merchant Maximum Payment Amount can not be less than Merchant Minimum Payment Amount.');

                }



            }

            else

            {

                $mmin = $wmin;

                $mmax = $wmax;

            }



            if ($error)

            {

                return false;

            }



            // $_POST['woocommerce_wizit_wz_minimum_amount'] = $wmin;

            // $_POST['woocommerce_wizit_wz_maximum_amount'] = $wmax;

            // $_POST['woocommerce_wizit_merchant_minimum_amount'] = $mmin;

            // $_POST['woocommerce_wizit_merchant_maximum_amount'] = $mmax;

            delete_option('admin_error_msg_01', true);



        }



        

        $hook_class = wizit_hook_class::initialize();

        $hook_class->remove_hooks();

        



        global $wp_version;

        // post all setting to api

        $plugin_config_api_data = array(

            'merchantUrl' => get_site_url() ,

            'maxMerchantLimit' =>  $mmax, //trim(sanitize_text_field($_POST['woocommerce_wizit_merchant_maximum_amount'])),

            'minMerchantLimit' => $mmin, //trim(sanitize_text_field($_POST['woocommerce_wizit_merchant_minimum_amount'])),

            'isEnable' =>  trim(sanitize_text_field(array_key_exists('woocommerce_wizit_enabled', $_POST) && $_POST['woocommerce_wizit_enabled'])) == '1' ? true : false,

            'isEnableProduct' => trim(sanitize_text_field(array_key_exists('woocommerce_wizit_payment_info_on_product', $_POST) && $_POST['woocommerce_wizit_payment_info_on_product'])) == '1' ? true : false,

            'isEnableCategory' => trim(sanitize_text_field(array_key_exists('woocommerce_wizit_payment_info_on_product_cat', $_POST) && $_POST['woocommerce_wizit_payment_info_on_product_cat'])) == '1' ? true : false,

            'isEnableCart' => trim(sanitize_text_field(array_key_exists('woocommerce_wizit_payment_info_on_cart', $_POST) && $_POST['woocommerce_wizit_payment_info_on_cart'])) == '1' ? true : false,

            'isInstalled' => true,

            'pluginversion' => $this->plugin_version,

            'platformversion' => $wp_version ?? 'unknown',

            'apikey' => $apikey,

            'platform' => 'Wordpress'

        );



        $plugin_config_api_response = $wzapi->call_configur_merchant_plugin($apikey, $api_url, $plugin_config_api_data);







        // save all data

        $saved = parent::process_admin_options();



        // update option

        $settings = get_option('woocommerce_wizit_settings', true);

        $settings['wz_minimum_amount'] = $wmin;

        $settings['wz_maximum_amount'] = $wmax;



        $settings['merchant_minimum_amount'] = $mmin;

        $settings['merchant_maximum_amount'] = $mmax;



        update_option('woocommerce_wizit_settings', $settings);



        return $saved;

    }



    public function get_statement_descriptor()

    {

        return $this->statement_descriptor;

    }



    public function get_wizit_api_url()

    {



        return $this->wizit_base_url;

    }



    public function get_wz_api_key()

    {

        return $this->wz_api_key;

    }



    public function set_wz_api_key($apikey)

    {

        $this->wz_api_key = $apikey;

    }



    public function get_capture_setting()

    {

        return $this->capture;

    }



    /**

     * Initialise Gateway Settings Form Fields

     */

    public function init_form_fields()

    {



        include ('wizit/wizit-default-value.php');



        $this->form_fields = array(

            'enabled' => array(

                'title' => __('Enable/Disable', 'woocommerce-wizit-gateway') ,

                'type' => 'checkbox',

                'label' => __('Enable Wizit', 'woocommerce-wizit-gateway') ,

                'default' => 'yes',

            ) ,

            'title' => array(

                'title' => __('Title', 'woocommerce-wizit-gateway') ,

                'type' => 'text',

                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-wizit-gateway') ,

                'default' => __('Wizit', 'woocommerce-wizit-gateway') ,

                'css' => 'width: 400px;',

                'custom_attributes' => array(

                    'readonly' => 'readonly'

                ) ,

            ) ,

            'description' => array(

                'title' => __('Description', 'woocommerce-wizit-gateway') ,

                'type' => 'textarea',

                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-gateway-payment-express-pxhybrid') ,

                'default' => __('Give your customer the option to buy now and pay later with 4 x interest free fortnightly instalments', 'woocommerce-wizit-gateway') ,

                'css' => 'width: 400px;',

                'custom_attributes' => array(

                    'readonly' => 'readonly'

                ) ,

            ) ,

            'group_title_wizit_settings' => array(

                'title' => __('<h3>Wizit Settings</h3><hr/>', 'woocommerce-wizit-gateway') ,

                'type' => 'title',

                'description' => ''

            ) ,



            'environment_mode' => array(

                'title' => __('Environment', 'woocommerce-wizit-gateway') ,

                'type' => 'select',

                'options' => wp_list_pluck($environments, 'name') ,

                'default' => 'production',

                'description' => ''

            ) ,



            'wz_api_key' => array(

                'title' => __('<span class="wizit-enviroment-model">Wizit API Key</span>', 'woocommerce-wizit-gateway') ,

                'type' => 'password',

                'default' => '',

                'description' => __('<span class="wizit-enviroment-model">Enter API key provided by Wizit into the "Wizit API key"</span>', 'woocommerce-wizit-gateway') ,

                'css' => 'width: 400px;',

                'class' => 'wizit-enviroment-model',

                //'custom_attributes' => array( 'required' => 'required' ),

                

            ) ,



            'wz_api_key_test' => array(

                'title' => __('<span class="wizit-enviroment-model-test">Wizit Sandbox API Key</span>', 'woocommerce-wizit-gateway') ,

                'type' => 'password',

                'default' => '',

                'description' => __('<span class="wizit-enviroment-model-test">Enter Sandbox API key provided by Wizit into the "Wizit Sandbox API key"</span>', 'woocommerce-wizit-gateway') ,

                'css' => 'width: 400px;',

                'class' => 'wizit-enviroment-model-test',

                //'custom_attributes' => array( 'required' => 'required' ),

                

            ) ,



            'success_url' => array(

                'title' => __('Success URL', 'woocommerce-wizit-gateway') ,

                'description' => __('User will be returned to this page after successful transaction on Wizit payment page.', 'woocommerce-wizit-gateway') ,

                'type' => 'text',

                'default' => '',

                'css' => 'width: 400px;'

            ) ,

            'fail_url' => array(

                'title' => __('Failed URL', 'woocommerce-wizit-gateway') ,

                'description' => __('User will be returned to this page after failed transaction on Wizit payment page.<br/>', 'wwoocommerce-wizit-gateway') ,

                'type' => 'text',

                'default' => '',

                'css' => 'width: 400px;'

            ) ,

            'statement_descriptor' => array(

                'title' => __('Statement Descriptor', 'wc-authnet') ,

                'type' => 'text',

                'description' => __('Extra information about a charge. This will appear in your order description. Defaults to site name.', 'wc-authnet') ,

                'default' => '',

                'desc_tip' => true,

            ) ,

            // 'capture' => array(

            //  'title'       => __( 'Capture', 'wc-authnet' ),

            //  'label'       => __( 'Capture charge immediately', 'wc-authnet' ),

            //  'type'        => 'checkbox',

            //  'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later.', 'wc-authnet' ),

            //  'default'     => 'yes'

            // ),

            



            'wizit_customisation_title' => array(

                'title' => __('<h3>Website Customisation</h3><hr/>', 'woocommerce-wizit-gateway') ,

                'type' => 'title',

                'description' => __('<p>The following options are configurable and provide the flexibility to display the Wizit plugin to suit the individual needs of your site</p><p>Customisations may require the support of an IT professional or a developer. If you get stuck or you are unhappy with your customisations, you can reset the default settings - <button type="button" id="wizitCustRestoreBtn">Restore Defaults</button></p>', 'woocommerce-wizit-gateway')

            ) ,



            'payment_info_on_product' => array(

                'title' => __('Payment Info on Product Pages', 'woocommerce-wizit-gateway') ,

                'label' => __('Enable', 'woocommerce-wizit-gateway') ,

                'type' => 'checkbox',

                'description' => __('Enabling this section will display the Wizit elements on individual product pages of your site', 'woocommerce-wizit-gateway') ,

                'default' => 'yes',

            ) ,



            'payment_info_on_product_text' => array(

                'type' => 'wysiwyg',

                'default' => $def_payment_info_on_product_text,

                'description' => __('<p>Pro tips:</p><p>Use the [OF_OR_FROM] function if the product price is variable</p><p>Use the [OF] function if the product price is fixed or static</p>', 'woocommerce-wizit-gateway') ,

                'custom_attributes' => array(

                    'required' => 'required'

                ) ,

            ) ,



            'payment_info_on_product_hook' => array(

                'type' => 'text',

                'default' => $def_payment_info_on_product_hook,

                'description' => __('You can set the hook that will be used for the product pages here', 'woocommerce-wizit-gateway') ,

                'custom_attributes' => array(

                    'required' => 'required'

                ) ,

            ) ,



            'payment_info_on_product_hook_priority' => array(

                'type' => 'number',

                'default' => $def_payment_info_on_product_hook_priority,

                'description' => __('You can set the hook priority that will be used for individual product pages here', 'woocommerce-wizit-gateway') ,

                'custom_attributes' => array(

                    'required' => 'required'

                ) ,

            ) ,



            'payment_info_on_cart' => array(

                'title' => __('Payment Info on Cart Pages', 'woocommerce-wizit-gateway') ,

                'label' => __('Enable', 'woocommerce-wizit-gateway') ,

                'type' => 'checkbox',

                'description' => __('Enabling this section will display the Wizit elements on the cart page of your site', 'woocommerce-wizit-gateway') ,

                'default' => 'yes',

            ) ,



            'payment_info_on_cart_text' => array(

                'type' => 'textarea',

                'default' => $def_payment_info_on_cart_text,

                'description' => __('<p>Pro tips:</p><p>Use the [OF_OR_FROM] function if the product price is variable</p><p>Use the [OF] function if the product price is fixed or static</p>', 'woocommerce-wizit-gateway') ,

                'custom_attributes' => array(

                    'required' => 'required'

                ) ,

            ) ,



            'payment_info_on_product_cat' => array(

                'title' => __('Payment Info on Category Pages', 'woocommerce-wizit-gateway') ,

                'label' => __('Enable', 'woocommerce-wizit-gateway') ,

                'type' => 'checkbox',

                'description' => __('Enabling this section will display the Wizit elements on the product category pages of your site', 'woocommerce-wizit-gateway') ,

                'default' => 'no',

            ) ,



            'payment_info_on_product_cat_text' => array(

                'type' => 'wysiwyg',

                'default' => $def_payment_info_on_product_cat_text,

                'description' => __('', 'woocommerce-wizit-gateway') ,

                'custom_attributes' => array(

                    'required' => 'required'

                ) ,

            ) ,



            'payment_info_on_product_cat_hook' => array(

                'type' => 'text',

                'default' => $def_payment_info_on_product_cat_hook,

                'description' => __('You can set the hook that will be used for the product category pages here', 'woocommerce-wizit-gateway') ,

                'custom_attributes' => array(

                    'required' => 'required'

                ) ,

            ) ,



            'payment_info_on_product_cat_hook_priority' => array(

                'type' => 'number',

                'default' => $def_payment_info_on_product_cat_hook_priority,

                'description' => __('You can set the hook priority that will be used for category pages here', 'woocommerce-wizit-gateway') ,

                'custom_attributes' => array(

                    'required' => 'required'

                ) ,

            ) ,



            'title_amount_settings' => array(

                'title' => __('<h3>Minimum/Maximum Amount Settings</h3>', 'woocommerce-wizit-gateway') ,

                'type' => 'title',

                'description' => __('Upon a successful save of the Wizit credentials, the "Wizit Minimum Payment Amount" and "Wizit Maximum Payment Amount" values will be updated.<hr/>') ,

            ) ,

            'wz_minimum_amount' => array(

                'title' => __('Wizit Minimum Payment Amount', 'woocommerce-wizit-gateway') ,

                'type' => 'number',

                'default' => '',

                'css' => 'width: 400px;',

                'custom_attributes' => array(

                    'disabled' => 'disabled'

                ) ,

                'description' => __('This information is supplied by Wizit and cannot be edited.') ,

            ) ,

            'wz_maximum_amount' => array(

                'title' => __('Wizit Maximum Payment Amount', 'woocommerce-wizit-gateway') ,

                'type' => 'number',

                'default' => '',

                'css' => 'width: 400px;',

                'custom_attributes' => array(

                    'disabled' => 'disabled'

                ) ,

                'description' => __('This information is supplied by Wizit and cannot be edited.') ,

            ) ,

            'merchant_minimum_amount' => array(

                'title' => __('Merchant Minimum Payment Amount', 'woocommerce-wizit-gateway') ,

                'type' => 'number',

                'default' => '',

                'css' => 'width: 400px,disable;',

                'description' => __('The minimum order amount which merchant finds eligible to be processed by Wizit') ,

            ) ,

            'merchant_maximum_amount' => array(

                'title' => __('Merchant Maximum Payment Amount', 'woocommerce-wizit-gateway') ,

                'type' => 'number',

                'default' => '',

                'css' => 'width: 400px, disable;',

                'description' => __('The maximum order amount which merchant finds eligible to be processed by Wizit') ,

            ) ,



        );



    } /* End init_form_fields() */



    /**

     * Admin Panel Options

     */

    public function admin_options()

    {



?>

        <h3><?php esc_html_e('Wizit Payment Gateway', 'woocommerce-wizit-gateway'); ?></h3>

        <p><?php esc_html_e('Allows your customers to pay via Wizit. (App V 1.2.2)', 'woocommerce-wizit-gateway'); ?></p><hr/>

        <table class="form-table">

        <?php

        /* Generate the HTML For the settings form. */

        $this->generate_settings_html();

?>

        </table><!-- form-table -->     





        <?php include ('wizit/wizit-default-value.php'); ?>



        <script>

            wizitSetDefaultValue(

                '<?php echo wp_kses_post($def_payment_info_on_product_text); ?>',

                '<?php echo wp_kses_post( $def_payment_info_on_product_cat_text); ?>',

                '<?php echo wp_kses_post( $def_payment_info_on_cart_text); ?>',

                '<?php echo wp_kses_post( $def_payment_info_on_product_hook); ?>',

                '<?php echo wp_kses_post( $def_payment_info_on_product_hook_priority); ?>',

                '<?php echo wp_kses_post( $def_payment_info_on_product_cat_hook); ?>',

                '<?php echo wp_kses_post( $def_payment_info_on_product_cat_hook_priority); ?>'

            );

        </script>





        <?php

    } /* End admin_options() */



    /** 

     * load js & css files for admin

     */

    public function init_admin_assets()

    {

        // load js & css files for admin

        wp_enqueue_editor();

        

        wizit_hook_class::load_required_css_js_file('admin');

    }



    /**

     * Generate WYSIWYG input field. This is a pseudo-magic method, called for each form field with a type of "wysiwyg".

     *

     * @since   2.0.0

     * @see     WC_Settings_API::generate_settings_html()   For where this method is called from.

     * @param   mixed       $key

     * @param   mixed       $data

     * @uses    esc_attr()                                  Available in WordPress core since 2.8.0.

     * @uses    wp_editor()                                 Available in WordPress core since 3.3.0.

     * @return  string                                      The HTML for the table row containing the WYSIWYG input field.

     */

    public function generate_wysiwyg_html($key, $data)

    {

        $html = '';



        $id = str_replace('-', '', $key);

        $class = array_key_exists('class', $data) ? $data['class'] : '';

        $css = array_key_exists('css', $data) ? ('<style>' . $data['css'] . '</style>') : '';

        $name = "{$this->plugin_id}{$this->id}_{$key}";

        $title = array_key_exists('title', $data) ? $data['title'] : '';

        $value = array_key_exists($key, $this->settings) ? esc_attr($this->settings[$key]) : '';

        $description = array_key_exists('description', $data) ? $data['description'] : '';



        ob_start();



        include ('wizit/wysiwyg.html.php');



        $html = ob_get_clean();



        return $html;

    }



    /**

     * Fields to show on payment page - here it is only displaying description. To show form or other components include them below.

     *

     */

    public function payment_fields()

    {

        // ok, let's display some description before the payment form
        if( $this->description ) {
            // you can instructions for test mode, I mean test card numbers etc.
            // if( $this->testmode ) {
            //     $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
            //     $this->description  = trim( $this->description );
            // }
            // display the description with <p> tags etc.
            echo wpautop( wp_kses_post( $this->description ) );
        }




        global $woocommerce;

        $currency_symbol = get_woocommerce_currency_symbol();

        //echo "Hello from payment fields!!";

        $order_total = WC()

            ->cart->total;

        //$sub_totalamount = WC()->cart->get_total();

        $installments = number_format($order_total / 4, 2);



        wizit_hook_class::load_required_css_js_file();





        if (!function_exists('get_wizit_popup_window'))

        {

            include ('wizit/wizit-helper.php');

        }



        
        echo '<fieldset id="wizit-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
    
        
        do_action( 'woocommerce_credit_card_form_start', $this->id );



?>
       
        <div id="wizit-<?php echo esc_attr($this->id); ?>-payment-schedule-container" class="wizit-form-row wizit-form-row-wide">
               
        </div>
            <script>Wizit_Widgets_PaymentSchedule("wizit-<?php echo esc_attr($this->id); ?>-payment-schedule-container", <?php echo esc_attr($order_total); ?>,<?php echo esc_attr($installments); ?>)</script>
        
        <?php
            do_action( 'woocommerce_credit_card_form_end', $this->id );

            echo '<div class="clear"></div></fieldset>';
    }



    public function payment_scripts()

    {

        wizit_hook_class::load_required_css_js_file();

    }



    public function process_payment($order_id)

    {

        global $woocommerce;

        $forapi = 'checkout';

        $store_currency = strtoupper(get_option('woocommerce_currency'));

        if (!$this->is_currency_supported())

        {



            $return = array(

                'result' => 'failure',

                'messages' => "<ul class='woocommerce-error' role='alert'><li>" . $this->statement_descriptor . ': Order cannot be processed through Wizit because the store currency is not supported. Store currency: ' . $store_currency . '</li></ul>'

            );

            $this

                ->log

                ->add('Wizit', sprintf('Store currency: %s', $store_currency) . PHP_EOL);

            wp_send_json($return);

            wp_die();



        }

        else

        {



            // is_currency_supported()

            $merchantrefernce = get_post_meta($order_id, 'merchantrefernce', true);

            $wzapi = new Wizit_API();

            $dataresponse = $wzapi->prepare_api_input($order_id, $forapi);

            // Your API interaction could be built with wp_remote_post()

            $wzresponse = $wzapi->call_checkouts_redirect_api($this->wz_api_key, $dataresponse);

            $this

                ->log

                ->add('Wizit', '========= initiating transaction request' . PHP_EOL);



            if (false === $wzresponse || false !== $wzapi->get_api_error())

            {

                $return = array(

                    'result' => 'failure',

                    'messages' => "<ul class='woocommerce-error' role='alert'><li>" . $this->statement_descriptor . ': Something went wrong while finalising your payment. Wizit Checkout Redirect Error: ' . $wzapi->get_api_error() . '</li></ul>'

                );

                $this

                    ->log

                    ->add('Wizit', '========= checkout redirect failed' . PHP_EOL);

                $this

                    ->log

                    ->add('Wizit', sprintf('failure message: %s', json_encode($return)) . PHP_EOL);



                wp_send_json($return);

                wp_die();



            }

            else

            {

                // API return success



                // hide wc-order

                global $wpdb;
                if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
                    // HPOS usage is enabled.
                    $table_wc_order = $wpdb->prefix . 'wc_orders';	
                    $wpdb->update(
                        //$table, 
                        $table_wc_order,
                        //$data, 
                        array(
                            'type' => 'wizit_order',	// string
                        ),                        
                        //$where
                        array( 'id' => $order_id ),
                        //$format
                        array("%s"),
                        //$where_format
                        array("%d")
                    );
                } else {
                    // Traditional CPT-based orders are in use.                    
                    $wpdb->update( $wpdb->posts, array("post_type" => "wizit_order"), array("ID" => $order_id), array("%s"), array("%d") );
                }

                $this

                    ->log

                    ->add('Wizit', '========= successfully redirect' . PHP_EOL);

                $token = $wzresponse['token'];

                $wzTxnId = $wzresponse['transactionId'];

                update_post_meta($order_id, 'wz_token', $token);

                update_post_meta($order_id, 'wz_txn_id', $wzTxnId);

                $redirect_url = $wzresponse['redirectCheckoutUrl'];

                return array(

                    'result' => 'success',

                    'redirect' => $redirect_url

                );

            }



        } // if(!$this->is_currency_supported()

        

    } // End of process_payment()

    private function is_currency_supported()

    {



        $store_currency = strtoupper(get_option('woocommerce_currency'));



        return in_array($store_currency, $this->supported_currencies);



    }



    public function get_order_status_failed_error_notice($wzapi)

    {



        // if (!function_exists('wc_add_notice'))

        // {

        //     require_once '/includes/wc-notice-functions.php';

        // }



        // if (function_exists('wc_add_notice') && isset($wzapi))

        // {

        //     wc_add_notice($wzapi->get_api_error() , 'error');

        // }

        // else

        // {

        //     wc_add_notice('Wizit API Error', 'error');

        // }



        if(function_exists('wc_add_notice')){

            wc_add_notice('Wizit init', 'success');

        }



    }



    /**

     * Server callback was valid, process callback (update order as passed/failed etc).

     *

     */



    public function handle_checkout_redirecturl_response($response)

    {

        global $woocommerce;

        $this

            ->log

            ->add('Wizit', '========= Wizit API callback function start' . PHP_EOL);



        $this

            ->log

            ->add('Wizit', '========= Wizit API callback URL = ' . $_SERVER['REQUEST_URI'] . PHP_EOL);





        if (isset($_REQUEST['orderid'])){

            $order_id = sanitize_text_field($_REQUEST['orderid']);

            if($order_id != null){

                // switch unreadable order to woo order
                global $wpdb;
                if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
                    // HPOS usage is enabled.
                    $table_wc_order = $wpdb->prefix . 'wc_orders';	
                    $wpdb->update(
                        //$table, 
                        $table_wc_order,
                        //$data, 
                        array(
                            'type' => 'shop_order',	// string
                        ),                        
                        //$where
                        array( 'id' => $order_id ),
                        //$format
                        array("%s"),
                        //$where_format
                        array("%d")
                    );
                } else {
                    // Traditional CPT-based orders are in use.                    
                    $wpdb->update( $wpdb->posts, array("post_type" => "shop_order"), array("ID" => $order_id), array("%s"), array("%d") );
                }

            }

        }



        //sleep for 10 seconds to waiting for other cron worker to finish
        sleep(10);






        if (isset($_REQUEST['orderid']) && isset($_REQUEST['target']))

        {



            $this

                        ->log

                        ->add('Wizit', '========= target = .' . $_REQUEST['target'] . PHP_EOL);



            $this

                        ->log

                        ->add('Wizit', '========= orderid = .' . $_REQUEST['orderid'] . PHP_EOL);





            $order_id = sanitize_text_field($_REQUEST['orderid']);

            $order = new WC_Order($order_id);



            $this->log->add('Wizit', 'target = ' . $_REQUEST['target'] . PHP_EOL);

            $this->log->add('Wizit', 'order_id = ' . $order_id . PHP_EOL);



            if ($order)

            {



                if (isset($_REQUEST['target']) && 'fail' == $_REQUEST['target'])

                {



                    $this

                        ->log

                        ->add('Wizit', '========= target = fail was returned and hence need to cancel the woo order.' . PHP_EOL);

                    update_post_meta($order_id, 'wz_txn_cancelled_reason', "abandon");

                    $order->update_status('cancelled', sprintf(__('Your payment through Wizit has been cancelled.', 'woocommerce-wizit-gateway.')));

                    if(function_exists('wc_add_notice'))

                    {

                        wc_add_notice('Your payment through Wizit has been cancelled.', 'error');

                    }   

                    $return_url = wc_get_checkout_url();

                    $this->redirect_to_fail_url($return_url);

                    // $this

                    //     ->log

                    //     ->add('Wizit', '========= target = fail Do nothing to keep app runing.' . PHP_EOL);

                    

                    //     $return_url = $order->get_checkout_order_received_url();

                    //     $this->redirect_to_success_url($return_url);



                }

                elseif (isset($_REQUEST['target']) && 'cart' == $_REQUEST['target'])

                {



                    $this

                        ->log

                        ->add('Wizit', '========= target = cart was returned.' . PHP_EOL);

                    $order->add_order_note('Your payment through Wizit has been cancelled.');

                    if(function_exists('wc_add_notice')){

                        wc_add_notice('Your payment through Wizit has been cancelled.', 'error');

                    }

                      

                    $return_url = wc_get_cart_url();

                    $this->redirect_to_fail_url($return_url);

                }

                elseif (isset($_REQUEST['target']) && 'checkout' == $_REQUEST['target'])

                {



                    $this

                        ->log

                        ->add('Wizit', '========= target = checkout was returned.' . PHP_EOL);

                    $order->add_order_note('Your payment through Wizit has been cancelled.');

                    if(function_exists('wc_add_notice')){

                        wc_add_notice('Your payment through Wizit has been cancelled.', 'error');

                    }

                    

                    $return_url = wc_get_checkout_url();

                    $this->redirect_to_fail_url($return_url);



                }

                elseif (isset($_REQUEST['target']) && 'limitexceeded' == $_REQUEST['target'])

                {



                    $limitamount = (!empty(sanitize_text_field($_GET['limitamount'])) ? sanitize_text_field($_GET['limitamount']) : 500);

                    $this

                        ->log

                        ->add('Wizit', '========= target = limitexceeded was returned with limitamount = ' . $limitamount . '.' . PHP_EOL);



                    if(function_exists('wc_add_notice')){

                       wc_add_notice('It looks like this is your first time using Wizit. For first time customers, the maximum purchase amount is $' . sanitize_text_field($limitamount) . '. Please revise the value of your order before continuing.', 'error');

                    }

                    $order->add_order_note('It looks like this is your first time using Wizit. For first time customers, the maximum purchase amount is $' . sanitize_text_field($limitamount) . '. Please revise the value of your order before continuing.');



                    $return_url = wc_get_cart_url();

                    $this->redirect_to_fail_url($return_url);

                }

            }



        }

        elseif (isset($_REQUEST['orderid']) && isset($_REQUEST['mref']))

        {



            $order_id = sanitize_text_field($_REQUEST['orderid']);

            $merchantReference = sanitize_text_field($_REQUEST['mref']);



            $order = new WC_Order($order_id);

            if ($order)

            {

                $this

                    ->log

                    ->add('Wizit', '========= order details retrive' . PHP_EOL);

                $orderToken = get_post_meta($order_id, 'wz_token', true);

                $wzTxnId = get_post_meta($order_id, 'wz_txn_id', true);

                $uniqid = md5(time() . $order_id);



                $api_data = array(

                    'transactionId' => $wzTxnId,

                    'token' => $orderToken,

                    'merchantReference' => $merchantReference

                );



                $wzapi = new Wizit_API();



                $wzresponse = $wzapi->get_order_payment_status_api($this->wz_api_key, $api_data);



                if (false === $wzresponse || false !== $wzapi->get_api_error())

                {



                    $this

                        ->log

                        ->add('Wizit', '========= status api call return failed' . PHP_EOL);

                    $this

                        ->log

                        ->add('Wizit', sprintf('failure: %s', $wzapi->get_api_error()) . PHP_EOL);

                    $order->update_status('failed', sprintf(__($wzapi->get_api_error() , 'woocommerce-wizit-gateway')));

                    if(function_exists('wc_add_notice')){

                       wc_add_notice('Wizit Payment Failed. Wizit Transaction ' . $wzTxnId . ' has been Declined', 'error');

                    }

                    

                    $return_url = wc_get_checkout_url();

                    $this->redirect_to_fail_url($return_url);



                }

                else

                {

                    $this

                        ->log

                        ->add('Wizit', '========= status api call return success');

                    $orderStatus = $wzresponse['transactionStatus'];

                    $paymentStatus = $wzresponse['paymentStatus'];

                    $apiOrderId = $wzresponse['transactionId'];



                    //update_post_meta($order_id, 'wz_txn_id', $apiOrderId);

                    $captureSetting = $this->capture;



                    if ('APPROVED' == $orderStatus && 'AUTH_APPROVED' == $paymentStatus)

                    {

                        // Here order checkout process has successfully completed.

                        // Now we have to decide whether to capture the payment or not.

                        if (true == $captureSetting)

                        {



                            $wz_order_status = $this->check_wz_order_status($api_data, $order);

                            if (true == $wz_order_status)

                            {



                                // get order item out of stock data

                                $all_items = array();

                                $product_out_stocks = array();

                                $price_total = array();

                                $inStockitems = array();



                                foreach ($order->get_items() as $item_id => $item)

                                {

                                    //Get the product ID

                                    $product_id = $item->get_product_id();

                                    $total = floatval($item->get_total()); // Total without tax (discounted)

                                    $product_title = substr($item->get_name() , 0, 4);

                                    $total_tax = floatval($item->get_total_tax());

                                    $product = wc_get_product($product_id);

                                    $product_out_stock = get_post_meta($product_id, '_stock', true);

                                    $in_stock_status = get_post_meta($product_id, '_stock_status', true);

                                    if ('instock' == $in_stock_status)

                                    {



                                        $inStockitems[] = $in_stock_status;

                                    }



                                    if (!empty($product_out_stock) && $product_out_stock <= 0)

                                    {



                                        //$product_ids[] = $product_id;

                                        $product_out_stocks[] = $product_out_stock;

                                        $price_total[] = $total + $total_tax;

                                        $all_items[] = 'Item #' . $product_id . '- ' . $product_title . '...';



                                    }

                                }



                                $price_total_sum = array_sum($price_total);

                                $out_of_stock_p_details = implode(', ', $all_items);

                                

                                {



                                    // order items inStocks Call immediate_payment_capture()

                                    $api_data = array(

                                        'token' => $orderToken,

                                        'merchantReference' => $merchantReference

                                    );



                                    $wzresponse = $wzapi->immediate_payment_capture($this->wz_api_key, $api_data);

                                    $this

                                        ->log

                                        ->add('Wizit', '========= immediate capture API called' . PHP_EOL);

                                    if (false === $wzresponse || false !== $wzapi->get_api_error())

                                    {



                                        $this

                                            ->log

                                            ->add('Wizit', '========= immediate capture API return failed' . PHP_EOL);

                                        $this

                                            ->log

                                            ->add('Wizit', sprintf('failure: %s', $wzapi->get_api_error()) . PHP_EOL);

                                        $order->update_status('failed', sprintf(__($wzapi->get_api_error() , 'woocommerce-wizit-gateway')));

                                        if(function_exists('wc_add_notice')){

                                            wc_add_notice('Wizit Payment Failed. Wizit Transaction ' . $wzTxnId . ' has been Declined', 'error');

                                        }

                                        

                                        $return_url = $order->get_checkout_payment_url();

                                        $this->redirect_to_fail_url($return_url);



                                    }

                                    else

                                    {



                                        if ('CAPTURE_DECLINED' == $wzresponse['paymentStatus'])

                                        {



                                            $this

                                                ->log

                                                ->add('Wizit', '========= immediate capture API return success' . PHP_EOL);

                                            $apicaptureOrderId = $wzresponse['transactionId'];

                                            $woocommerce

                                                ->cart

                                                ->empty_cart();

                                            $order->update_status('on-hold', sprintf(__('Wizit Payment Authorised ' . $apiOrderId . ' . In order to capture this transaction, please make the partial capture manually.', 'woocommerce-wizit-gateway')));

                                            $this

                                                ->log

                                                ->add('Wizit', sprintf('Wizit Payment Authorised (Transaction ID: %s', $apiOrderId) . PHP_EOL);

                                            $return_url = $order->get_checkout_order_received_url();

                                            $this

                                                ->log

                                                ->add('Wizit', 'succ_return_url=' . $return_url  . PHP_EOL);

                                            $this->redirect_to_success_url($return_url);



                                        }

                                        else

                                        {



                                            $this

                                                ->log

                                                ->add('Wizit', '========= immediate capture API return success' . PHP_EOL);

                                            $apicaptureOrderId = $wzresponse['transactionId'];

                                            $order->payment_complete();

                                            $woocommerce

                                                ->cart

                                                ->empty_cart();

                                            //$order->add_order_note('Wizit Payment charge authorised (Charge ID: '.$apicaptureOrderId.')');

                                            $this

                                                ->log

                                                ->add('Wizit', sprintf('Wizit Payment Authorised (Transaction ID: %s', $apiOrderId) . PHP_EOL);

                                            $return_url = $order->get_checkout_order_received_url();

                                            $this

                                                ->log

                                                ->add('Wizit', 'succ_return_url=' . $return_url  . PHP_EOL);

                                            $this->redirect_to_success_url($return_url);



                                        }



                                    } // API response check

                                    

                                } // End check if(!empty( $product_out_stocks ))

                                

                            }

                            else

                            {

                                // wz_order_status not true, mark this order as cancel

                                $this

                                    ->log

                                    ->add('Wizit', '========= wz_order_status() return false' . PHP_EOL);

                                $order->update_status('failed', sprintf(__($wzapi->get_api_error() , 'woocommerce-wizit-gateway')));

                                if(function_exists('wc_add_notice')){

                                    wc_add_notice('Wizit Payment Failed. Wizit Transaction ' . $wzTxnId . ' has been Declined', 'error');

                                }

                                

                                $return_url = $order->get_checkout_payment_url();

                                $this->redirect_to_fail_url($return_url);



                            } // End if check ( $captureResult == true )

                            

                        }

                        else

                        {



                            // captureSetting not enaable/true, mark this order as on hold

                            $this

                                ->log

                                ->add('Wizit', '========= captureSetting not enable, mark this order as on hold' . PHP_EOL);

                            $woocommerce

                                ->cart

                                ->empty_cart();

                            $order->update_status('on-hold', sprintf(__('Wizit Payment Authorised ' . $apiOrderId . ' . In order to capture this transaction, please make the partial capture manually.', 'woocommerce-wizit-gateway')));

                            $this

                                ->log

                                ->add('Wizit', sprintf('Wizit Payment charge authorised (Transaction : %s', $apiOrderId) . PHP_EOL);



                            $return_url = $order->get_checkout_order_received_url();

                            $this->redirect_to_success_url($return_url);



                        }



                    } // End of [ if ($orderStatus == 'APPROVED' && $paymentStatus == 'AUTH_APPROVED')]

                    if ('APPROVED' != $orderStatus && 'COMPLETED' != $orderStatus)

                    {



                        $order->update_status('failed', sprintf(__('Wizit Payment Failed. Wizit Transaction ' . $apiOrderId . ' has been Declined', 'woocommerce-wizit-gateway')));

                        $return_url = $order->get_checkout_payment_url();

                        $this->redirect_to_fail_url($return_url);



                    }



                    if ('AUTH_APPROVED' != $paymentStatus && 'CAPTURED' != $paymentStatus)

                    {



                        $orderMessage = '';

                        if ('AUTH_DECLINED' == $paymentStatus)

                        {

                            $orderMessage = 'Wizit Payment Failed. Wizit Order ID (' . $apiOrderId . ') has been Declined!';

                            /* } elseif ('CAPTURE_DECLINED' == $paymentStatus) {

                            //$orderMessage = 'Wizit Transaction ID (' . $apiOrderId . ') Capture Attempt has been declined!';

                            */

                        }

                        elseif ('VOIDED' == $paymentStatus)

                        {

                            $orderMessage = 'Wizit Transaction ID (' . $apiOrderId . ') VOID!';

                        }

                        else

                        {

                            $orderMessage = 'Wizit Transaction ID (' . $apiOrderId . ') Payment Failed. Reason: ' . $paymentStatus;

                        }



                        $order->update_status('failed', sprintf(__($orderMessage, 'woocommerce-wizit-gateway')));

                        $return_url = $order->get_checkout_payment_url();

                        $this->redirect_to_fail_url($return_url);



                    } //if($paymentStatus != 'AUTH_APPROVED')

                    

                } //End of [ else ( $wzresponse === false || $wzapi->get_api_error() !== false )]

                

            } // End of [if($order)]

            

        } //  elseif (isset($_REQUEST['orderid']) && isset($_REQUEST['mref'] ) )

        



        $this

            ->log

            ->add('Wizit', '========= Wizit API callback function end' . PHP_EOL);



    } // End handle_checkout_redirecturl_response()

    public function send_alert_email_to_admin($out_of_stock_p_id, $order_id)

    {



        $this

            ->log

            ->add('Wizit', '========= send_alert_email_to_admin() function called' . PHP_EOL);

        $message = $this->statement_descriptor . ': ' . $out_of_stock_p_id . ' from the order are not in stock, so payment was not captured. You need to capture the payment manually after it is back in stock.';

        $to = get_bloginfo('admin_email');

        $subject = 'New Order #' . $order_id . ' Placed With Out Of Stock Items';

        $sent = wp_mail($to, $subject, $message);



    }



    public function check_wz_order_status($data, $order)

    {

        $wzapi = new Wizit_API();

        $wzresponse = $wzapi->get_order_payment_status_api($this->wz_api_key, $data);



        if (false === $wzresponse || false !== $wzapi->get_api_error())

        {



            $order->add_order_note('Wizit Payment Authorised failed.');

            return false;



        }

        else

        {



            $orderStatus = $wzresponse['transactionStatus'];

            $paymentStatus = $wzresponse['paymentStatus'];

            $apiOrderId = $wzresponse['transactionId'];

            if ('APPROVED' == $orderStatus && 'AUTH_APPROVED' == $paymentStatus)

            {



                // get order item out of stock data

                /* $order_id = $order->get_id();

                $product_out_stocks = array();

                //$price_total = array();

                $all_items = array();

                foreach( $order->get_items() as $item_id => $item ) {

                //Get the product ID

                $product_id = $item->get_product_id();

                $product_title = substr($item->get_name(), 0, 4 );

                //$total     = floatval($item->get_total()); // Total without tax (discounted)

                //$total_tax = floatval($item->get_total_tax());

                $product = wc_get_product($product_id);

                $product_out_stock = get_post_meta( $product_id, '_stock', true );

                

                if(!empty($product_out_stock) && $product_out_stock <= 0) {

                

                $product_out_stocks[] = $product_out_stock;

                $all_items[] = "#".$product_id."- " . $product_title . "...";

                //$price_total[] = $total+$total_tax;

                }

                }

                //$price_total_sum = array_sum($price_total);

                if(!empty( $product_out_stocks )) {

                $out_of_stock_p_details = implode(', ', $all_items);

                $order->add_order_note('This items ('.$out_of_stock_p_details.') from the order is currently not in stock, and hence the payment was not captured. You need to capture the payment manually after it is back in stock.');

                $this->send_alert_email_to_admin($out_of_stock_p_details, $order_id);

                } else {*/



                $order->add_order_note('Wizit Payment Authorised Transaction ' . $apiOrderId);



                return true;



            }



        }



        /*if($orderStatus != 'APPROVED') {

        $order->update_status('failed', sprintf(__('Wizit Payment Failed. Wizit Transaction ID ('.$apiOrderId.') has been Declined!', 'woocommerce-wizit-gateway') ) );

        return false;

        }

        

        if($paymentStatus != 'AUTH_APPROVED') {

        $orderMessage = "";

        if($paymentStatus == 'AUTH_DECLINED')

        $orderMessage = 'Wizit Payment Failed. Wizit Transaction ID ('.$apiOrderId.') has been Declined!';

        elseif ($paymentStatus == 'CAPTURE_DECLINED')

        $orderMessage = 'Wizit Transaction ID ('.$apiOrderId.') Capture Attempt has been declined!';

        elseif ($paymentStatus == 'VOIDED')

        $orderMessage = 'Wizit Transaction ID ('.$apiOrderId.') VOID!';

        else

        $orderMessage = 'Wizit Transaction ID ('.$apiOrderId.') Payment Failed. Reason: '.$paymentStatus;

        $order->update_status('failed', sprintf(__($orderMessage, 'woocommerce-wizit-gateway') ) );

        return false;

        

        } // if($paymentStatus != 'AUTH_APPROVED')*/



    } // End process_order_capture()

    public function redirect_to_fail_url($return_url)

    {

        if ('' != $this->fail_url || null != $this->fail_url)

        {

            $return_url = $this->fail_url;

        }

        wp_redirect($return_url);

        exit();

    }



    public function redirect_to_success_url($return_url)

    {

        if ('' != $this->success_url || null != $this->success_url)

        {

            $return_url = $this->success_url;

        }

        wp_redirect($return_url);

        exit();

    }



    /**

     *  Manually Payment Capture via Wizit Function

     */



    public function wc_order_add_capture_buttons_callback($order)

    {

        $payment_methode = $order->get_payment_method();

        if ('wizit' == $payment_methode)

        {

            $label = esc_html_e('Capture Charge', 'woocommerce');

            $slug = 'capture_charge';

            $id = $order->get_id();

            $totalamount = floatval($order->get_total());

?>

            <button type="button" id="capture_charge" class="button <?php echo esc_attr($slug); ?>-items"><?php echo esc_attr($label); ?></button>

            <div class="wc-order-data-row wc-order-capture-items wc-order-data-row-toggle" style="display: none;">

                <table class="wc-order-totals">

                    <tbody>

                        <!-- <tr>

                            <td class="label"><label for="restock_capture_items">Restock capture items:</label></td>

                            <td class="total"><input type="checkbox" id="restock_capture_items" name="restock_refunded_items" checked="checked"></td>

                        </tr> -->

                        <tr>

                            <td class="label">Amount Already Captured:</td>

                            <input type="hidden" id="capture_all_amount" name="capture_all_amount" value="<?php echo esc_attr($totalamount); ?>">

                            <td class="total">-<span class="woocommerce-Price-amount amount"><bdi id="already_capture"><span class="woocommerce-Price-currencySymbol">$</span></bdi></span></td>

                        </tr>

                        <tr>

                            <td class="label">Pending to Capture:</td>

                            <td class="total-capture">

                                <span class="woocommerce-Price-amount amount"><bdi id="capture_avail"><span class="woocommerce-Price-currencySymbol">$</span></bdi></span>

                            </td>

                        </tr>

                        <tr>

                            <td class="label">

                                <label for="capture_amount">

                                    <!-- <span class="woocommerce-help-tip"></span> -->                  

                                    Capture amount:

                                </label>

                            </td>

                            <td class="total">

                                <input type="text" id="capture_amount" name="capture_amount" class="wc_input_price">

                                <div class="clear"></div>

                            </td>

                            

                        </tr>

                        <tr>

                            <td colspan="2">

                               <p id="capture_error" style="color: red;"> </p> 

                            </td>

                        </tr>

                        <tr>

                            <td class="label">

                                <label for="capture_reason">

                                    <!-- <span class="woocommerce-help-tip"></span> -->

                                         Reason for Capture (optional):               

                                </label>

                            </td>

                            <td class="total">

                                <div class="clear"></div>

                                <input type="text" id="capture_reason" name="capture_reason">

                                <div class="clear"></div>

                            </td>

                        </tr>

                    </tbody>

                </table>

                <div class="clear"></div>

                <div class="capture-actions" style="margin: 0.4%;">

                    <div class="clear"></div>              

                    <button type="button" id="cancelaction" class="button cancel-action">Cancel</button>

                    <button type="button" id="priceapi" class="button button-primary do-api-capture">Capture Charge</button>  

                    <input type="hidden" id="capture_amount" name="capture_amount" value="0">

                    <!-- <div class="clear"></div> -->

                </div>

            </div>



            <?php

        }



    } //wc_order_item_add_action_buttons_callback( $order )

    



    

    /**

     *  Refund process function recommended by Woo

     */

    public function process_refund($order_id, $amount = null, $reason = '')

    {

        global $woocommerce;

        $order = new WC_Order($order_id);

        $wz_txn_id = get_post_meta($order_id, 'wz_txn_id', true);

        $available_gateways = $woocommerce

            ->payment_gateways

            ->get_available_payment_gateways();

        $payment_method = $available_gateways['wizit'];

        $merchantrefernce = get_post_meta($order_id, 'merchantrefernce', true);

        $paymentEventMerchantReference = 'REF-' . $order_id;

        $orderToken = get_post_meta($order_id, 'wz_token', false);

        $uniqid = md5(time() . $order_id);

        $amount = number_format($amount, 2);

        $currency = get_woocommerce_currency();

        $api_data = array(

            'RequestId' => $uniqid,

            'merchantReference' => $merchantrefernce,

            'amount' => array(

                'amount' => $amount,

                'currency' => $currency

            ) ,

            'paymentEventMerchantReference' => $paymentEventMerchantReference

        );



        $wzapi = new Wizit_API();

        $wzresponse = $wzapi->order_refund_api($this->wz_api_key, $api_data, $wz_txn_id);

        if (false === $wzresponse || false !== $wzapi->get_api_error())

        {



            $order->add_order_note(__('Refund ' . $wzapi->get_api_error() , 'woocommerce-wizit-gateway') . PHP_EOL . 'Amount- $' . $amount, 'error');

            $this

                ->log

                ->add('Wizit', sprintf('failure: %s', $wzapi->get_api_error() . PHP_EOL));

            return false;

        }

        else

        {

            $result = $wzresponse;

        }



        if ('APPROVED' == $wzresponse['transactionStatus'] || 'COMPLETED' == $wzresponse['transactionStatus'])

        {

            $order->add_order_note('Wizit Payment Refund Authorised Wizit Transaction ID (' . $wzresponse['transactionId'] . ')' . PHP_EOL . 'Amount: $' . $amount);

            return true;

        }

        return false;



    } // End process_refund()

    

    /**

     *  Cancel process function recommended by Wizardpay

     */

    public function process_cancel($order_id, $status_from, $status_to, $order)

    {

        global $woocommerce;

        $order_status = $order->get_status();

        $old_status = $status_from;

        $payment_methode = $order->get_payment_method();

        if ('wizit' == $payment_methode)

        {

            $wz_txn_id = get_post_meta($order_id, 'wz_txn_id', true);

            $available_gateways = $woocommerce

                ->payment_gateways

                ->get_available_payment_gateways();

            $payment_method = @$available_gateways['wizit'];

            $wzapi = new Wizit_API();



            if ('cancelled' == $order_status)

            {



                $wzCancelReason = get_post_meta($order_id, 'wz_txn_cancelled_reason', true);



                if ($wzCancelReason != "abandon")

                {



                    // $wzresponse = $wzapi->order_voided_api($this->wz_api_key, $wz_txn_id);



                    // if (false === $wzresponse || false !== $wzapi->get_api_error())

                    // {

                    //     $order->update_status($old_status, sprintf(__('Cancel ' . $wzapi->get_api_error() . PHP_EOL, 'woocommerce-wizit-gateway')));

                    //     return false;

                    // }



                    // if ('VOIDED' == @$wzresponse['paymentStatus'] || 'CAPTURED' == @$wzresponse['paymentStatus'])

                    // {



                    //     $order->add_order_note('Wizit Payment Cancel Authorised Wizit Transaction ID (' . @$wzresponse['transactionId'] . ')');

                        // $order->add_order_note('Order Canceled.');

                        return true;

                    // }



                }

                else

                { // if($wzCancelReason != "abandon")

                    delete_post_meta($order_id, 'wz_txn_cancelled_reason');



                }



            } // if ('cancelled' == $order_status )

            return false;

        }



    } // End process_cancel()

    



    public function wizit_admin_notice()

    {

        /* Check transient, if available display notice */

        if (get_transient('wp-admin-notice-wizit'))

        {

?>

            <div class="error notice is-dismissible">

                <p>You have entered the wrong Wizit API Key!</p>

            </div>

            <?php

            /* Delete transient, only display this notice once. */

            delete_transient('wp-admin-notice-wizit');

        }



    } /* End wizit_admin_notice() */



    /**

     * Autoload classes as/when needed

     *

     * @param string $class_name name of class to attempt to load

     */

    public static function autoload($class_name)

    {



    }



    public static function getInstance()

    {

        if (is_null(self::$instance))

        {

            self::$instance = new self();

        }

        return self::$instance;

    }



    private function remove_action_for_prod_cart_prodCat($payment_info_on_product_hook, $payment_info_on_product_hook_priority, $payment_info_on_product_cat_hook, $payment_info_on_product_cat_hook_priority)

    {

        if (!isset($payment_info_on_product_hook) && !empty($payment_info_on_product_hook) && !isset($payment_info_on_product_hook_priority) && !empty($payment_info_on_product_hook_priority) && !is_nan($payment_info_on_product_hook_priority))

        {

            remove_action($payment_info_on_product_hook, 'remove_info_for_product_detail_page');

        }



        if (!isset($payment_info_on_product_cat_hook) && !empty($payment_info_on_product_cat_hook) && !isset($payment_info_on_product_cat_hook_priority) && !empty($payment_info_on_product_cat_hook_priority) && !is_nan($payment_info_on_product_cat_hook_priority))

        {

            add_action($payment_info_on_product_cat_hook, 'remove_info_for_product_cat_page');

        }

    }





} // class WC_Gateway_Wizit extends WC_Payment_Gateway





//define custom time for cron job

//add_filter('cron_schedules', 'wizit_cron_schedule_limit_api_schedule');

// cron job integrations

//add_action('wp', 'wizit_cron_schedule_check_limits');

//cron job peerform action

//add_action('wp_wizit_call_limit_api', 'wizit_limits_api_hook_function');



//add_action('admin_notices', 'wizit_cron_schedule_error');



function wizit_cron_schedule_error()

{

 

}



function wizit_cron_schedule_limit_api_schedule($schedules)

{

    $schedules['wc_wizit_call_api_limit'] = array(

        'interval' => 3600,

        'display' => __('Every One Hour', 'textdomain')

    );

    return $schedules;

}



function wizit_cron_schedule_check_limits()

{

    if (!wp_next_scheduled('wp_wizit_call_limit_api'))

    {

        wp_schedule_event(time() , 'wc_wizit_call_api_limit', 'wp_wizit_call_limit_api');

    }

}



function wizit_limits_api_hook_function()

{

    $error = false;

    $getsettings = get_option('woocommerce_wizit_settings', true);

    $enabled = $getsettings['enabled'];

    $title = $getsettings['title'];

    $description = $getsettings['description'];

    $group_title_wizit_settings = $getsettings['group_title_wizit_settings'];

    $apikey = $getsettings['wz_api_key'];

    $apikey_sandbox = $getsettings['wz_api_key_test'];

    $success_url = $getsettings['success_url'];

    $fail_url = $getsettings['fail_url'];

    $title_amount_settings = $getsettings['title_amount_settings'];

    $capture = true;//$getsettings['capture'];

    $statement_descriptor = $getsettings['statement_descriptor'];

    $oldwmin = $getsettings['wz_minimum_amount'];

    $oldwmax = $getsettings['wz_maximum_amount'];



    if (!empty($oldwmin) && !empty($oldwmax))

    {



        $wzapi = new Wizit_API();

        $wzresponse = $wzapi->call_limit_api($apikey);



        if (false === $wzresponse || false !== $wzapi->get_api_error())

        {

            $error = true;



            WC_Admin_Settings::add_error($wzapi->get_api_error());

            return false;

        }

        else

        {



            $merchant_minimum_amount = $getsettings['merchant_minimum_amount'];

            $merchant_maximum_amount = $getsettings['merchant_maximum_amount'];

            $merchant_min_old = $getsettings['merchant_minimum_amount'];

            $merchant_max_old = $getsettings['merchant_maximum_amount'];

            $wmin = $wzresponse['minimumAmount'];

            $wmax = $wzresponse['maximumAmount'];



            if ($oldwmin < $wmin && $wmin < $oldwmax || $merchant_min_old < $wmin && $wmin < $merchant_max_old)

            {



                $merchant_minimum_amount = $wmin;

            }



            if ($oldwmax > $wmax && $wmax > $oldwmin || $merchant_max_old > $wmax && $wmax > $merchant_min_old)

            {



                $merchant_maximum_amount = $wmax;

            }



            if (($oldwmin != $wmin || $oldwmax != $wmax) || ($merchant_min_old != $merchant_minimum_amount || $merchant_max_old != $merchant_maximum_amount))

            {



                include ('wizit/wizit-default-value.php');



                $new_options = array(

                    'enabled' => $enabled,

                    'title' => $title,

                    'description' => $description,

                    'group_title_wizit_settings' => $group_title_wizit_settings,

                    'wz_api_key' => $apikey,

                    'wz_api_key_test' => $apikey_sandbox,

                    'success_url' => $success_url,

                    'fail_url' => $fail_url,

                    'statement_descriptor' => $statement_descriptor,

                    'capture' => $capture,

                    'wizit_customisation_title' => $getsettings['wizit_customisation_title'],

                    'payment_info_on_product' => $getsettings['payment_info_on_product'],

                    'payment_info_on_product_text' => $getsettings['payment_info_on_product_text'],

                    'payment_info_on_product_hook' => $getsettings['payment_info_on_product_hook'],

                    'payment_info_on_product_hook_priority' => $getsettings['payment_info_on_product_hook_priority'],

                    'payment_info_on_cart' => $getsettings['payment_info_on_cart'],

                    'payment_info_on_cart_text' => $getsettings['payment_info_on_cart_text'],

                    'payment_info_on_product_cat' => $getsettings['payment_info_on_product_cat'],

                    'payment_info_on_product_cat_text' => $getsettings['payment_info_on_product_cat_text'],

                    'payment_info_on_product_cat_hook' => $getsettings['payment_info_on_product_cat_hook'],

                    'payment_info_on_product_cat_hook_priority' => $getsettings['payment_info_on_product_cat_hook_priority'],

                    'title_amount_settings' => $title_amount_settings,

                    'wz_minimum_amount' => $wmin,

                    'wz_maximum_amount' => $wmax,

                    'merchant_minimum_amount' => $merchant_minimum_amount,

                    'merchant_maximum_amount' => $merchant_maximum_amount,



                    'environment_mode' => $getsettings['environment_mode']

                );



                update_option('woocommerce_wizit_settings', $new_options);

                $message = 'Warning: Wizit minimum and maximum order amount limits have been changed.';

                update_option('admin_error_msg_01', $message);

            }



        } // if ( $wzresponse === false || $wzapi->get_api_error() !== false )

        

    } // if ( !empty($oldwmin) && !empty($oldwmax) )

    

} // function wizit_limits_api_hook_function(





add_action('wp_footer', 'wizit_info_popup_window');



function wizit_info_popup_window()

{    

    $url_popup = 'https://info.wizit.com.au/HowItWorks/HowItWorks.html'; //plugin_dir_url(__FILE__) . 'assets/wizit-how-it-works-popup.html';    

}



/* Disable Payment Gateway by Billing/Shipping Country */

function wizit_payment_gateway_disable_country($available_gateways)

{



    global $woocommerce;

    if (is_admin())

    {

        return;

    }



    try

    {

        if (isset($available_gateways['wizit']) && isset($woocommerce) 

                && isset($woocommerce->customer) 

                && ($woocommerce->customer->get_billing_country() != 'AU' || $woocommerce->customer->get_shipping_country() != 'AU'))

        {

            unset($available_gateways['wizit']);

        }



        // and check payment currency

        $selected_currency = strtoupper(get_woocommerce_currency());

        if($selected_currency != 'AUD'){

            unset($available_gateways['wizit']);

        }

    }

    catch(Exception $e)

    {

        unset($available_gateways['wizit']);

    }

    return $available_gateways;

}



add_filter('woocommerce_available_payment_gateways', 'wizit_payment_gateway_disable_country');



function filter_wizit_woocommerce_no_available_payment_methods_message($var)

{

    $getsettings = get_option('woocommerce_wizit_settings', true);



    if (isset($getsettings) && !empty($var))

    {



        $enabled = $getsettings['enabled'];

        $display_on_cart = $getsettings['payment_info_on_cart'];

        wizit_hook_class::load_required_css_js_file();

        // global $post, $product;

        $install = 0;



        $wzwmin = $getsettings['wz_minimum_amount'];

        $wzwmax = $getsettings['wz_maximum_amount'];

        $merchant_minimum_amount = $getsettings['merchant_minimum_amount'];

        $merchant_maximum_amount = $getsettings['merchant_maximum_amount'];



        $store_currency = strtoupper(get_option('woocommerce_currency'));

        $url = 'https://www.wizit.money/img/plugin/wizit.png';// plugin_dir_url(__FILE__) . 'images/Group.png';



        if (empty($merchant_minimum_amount) || empty($merchant_maximum_amount))

        {



            $merchant_minimum_amount = $wzwmin;

            $merchant_maximum_amount = $wzwmax;

        }



        if ($enabled === 'yes' && $display_on_cart === 'yes')

        {

            // display out of range message

            

?>



            <li class="woocommerce-notice woocommerce-notice--warning woocommerce-warning">';

                <div>

                    <img class="wizit-payment-logo" style="width: 50px ;display: inline-block !important;" src="<?php echo esc_url($url); ?>">

                <span  style="vertical-align: super;font-size: 16px;font-weight: normal;padding-left:20px;"> is available on purchases between  $<?php echo esc_attr(number_format($merchant_minimum_amount, 0)) . ' and $' . esc_attr(number_format($merchant_maximum_amount, 0)); ?>

                <a target="_blank" class="wizit-popup-open" style="font-size: 12px;text-decoration: underline;">learn more</a></span>

                </div>

            </li>



            <?php

        }

        else

        {

            return $var;

        }

    }

}



add_filter('woocommerce_no_available_payment_methods_message', 'filter_wizit_woocommerce_no_available_payment_methods_message', 10, 1);



