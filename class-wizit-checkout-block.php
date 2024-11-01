<?php


class Wizit_Custom_Gateway_Blocks extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'wizit';// your payment gateway name
    
    public function initialize() {
		$this->settings = get_option( 'woocommerce_wizit_settings', [] );
		$gateways       = WC()->payment_gateways->payment_gateways();        
		$this->gateway  = $gateways[ $this->name ];        
	}


    /**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return  $this->gateway->is_available();
	}

    /**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {		
    
        $script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'assets/js/frontend/blocks.asset.php';
  
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.2'
			);
		$script_url        = untrailingslashit( plugins_url( '/', __FILE__ ) ) . $script_path;

        // echo $script_asset_path;
        // echo '-------------------------';
        // echo $script_url;
        // die();


		wp_register_script(
			'woocommerce-wizit-gateway-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		

		return [ 'woocommerce-wizit-gateway-blocks' ];
	}

    public function get_payment_method_data() {

        $order_total = WC()  ->cart->total;



        return [
            'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
            'orderTotal'  => $order_total,
			'pluginSettings' => $this->settings
        ];
    }

}
?>