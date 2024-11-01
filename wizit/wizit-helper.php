<?php
    /*
     * Display HTML on page
     *
    */
    function process_and_print_wizit_paragraph($html, $output_filter, $price, $plugin_dir, $logo_class = '', 
        $display_outside_range_message = false, $is_variable_price = false, $product_id = -1, $is_on_sale_product = false)
    {     



        // add popup windows 
        
        // $html = $html . get_popup_window();
        $html = wizit_get_display_html($html, $output_filter, $price, $plugin_dir, $logo_class, $display_outside_range_message, $is_variable_price, $product_id, $is_on_sale_product);       

        # Allow other plugins to maniplulate or replace the HTML echoed by this funtion.
        echo apply_filters($output_filter, $html, $price);
    }


    function wizit_get_display_html($html, $output_filter, $price, $plugin_dir, $logo_class = '', $display_outside_range_message = false, 
        $is_variable_price = false, $product_id = -1, $is_on_sale_product = false){
        $settings = get_option('woocommerce_wizit_settings', true);

        if($settings != null && is_array($settings) && array_key_exists('enabled', $settings ) && $settings['enabled'] != 'yes' ){
            echo apply_filters($output_filter, '', $price);
            return;
        }


        $wzwmin = $settings['wz_minimum_amount'];
        $wzwmax = $settings['wz_maximum_amount'];
        $merchant_minimum_amount = $settings['merchant_minimum_amount'];
        $merchant_maximum_amount = $settings['merchant_maximum_amount'];

        $store_currency = strtoupper(get_woocommerce_currency());// strtoupper(get_option('woocommerce_currency'));

        // only support AUD
        if($store_currency != 'AUD'){
            echo apply_filters($output_filter, '', $price);
            return;
        }



        if (empty($merchant_minimum_amount) || empty($merchant_maximum_amount))
        {

            $merchant_minimum_amount = $wzwmin;
            $merchant_maximum_amount = $wzwmax;
        }



        // add logo
        $logo_url = 'https://www.wizit.money/img/plugin/wizit.png';// $plugin_dir . 'images/Group.png';
        $logo_html = '<img class="wizit-payment-logo ' . $logo_class . '" style="width: 50px;" src="'. $logo_url .'">';

        //$html = '<p>';

        // check amount
        if (is_null($price) || $price <= 0 || $price < $merchant_minimum_amount || $price > $merchant_maximum_amount )
        {
            if($display_outside_range_message){
         
                // display outside range message
                $html = $logo_html . '<span id="wizit-price-range-holder">                        
                       is available on purchases between $' . number_format($merchant_minimum_amount, 0) . ' and $' . number_format($merchant_maximum_amount, 0) . '
                       </span><a target="_blank" class="wizit-popup-open" style="font-size: 12px;text-decoration: underline;">
                        learn more</a>
                    ';                    
            }
            else
            {
                
                return;
            }
            
        }
        elseif ($is_variable_price == true)
        {
            

            // replace key words for variable product
            $html = str_replace(array(
                '[MIN_LIMIT]',
                '[MAX_LIMIT]',
                '[AMOUNT]',
                '[OF_OR_FROM]',
                '[wizit_logo]',
                '[Learn_More]'
            ) , array(
                wizit_display_price_html(floatval($merchant_minimum_amount)) ,
                wizit_display_price_html(floatval($merchant_maximum_amount)) ,
                wizit_display_price_html(floatval($price)) ,
                '<span id="wizit-price-range-holder">' . wizit_display_price_html(floatval($price / 4)) . '</span>' ,
                $logo_html,
                '<a target="_blank" class="wizit-popup-open" style="font-size: 12px;text-decoration: underline">learn more</a>'
            ) , $html);            

            $html = $html . '<input type="hidden" id="wizit_is_variable_price" name="wizit_is_variable_price" value="true">';
        }else{

            // replace key words
            $html = str_replace(array(
                '[MIN_LIMIT]',
                '[MAX_LIMIT]',
                '[AMOUNT]',
                '[OF_OR_FROM]',
                '[wizit_logo]',
                '[Learn_More]'
            ) , array(
                wizit_display_price_html(floatval($merchant_minimum_amount)) ,
                wizit_display_price_html(floatval($merchant_maximum_amount)) ,
                wizit_display_price_html(floatval($price)) ,
                '<span id="wizit-price-range-holder">' . wizit_display_price_html(floatval($price / 4)) . '</span>' ,
                $logo_html,
                '<a target="_blank" class="wizit-popup-open" style="font-size: 12px;text-decoration: underline">learn more</a>'
            ) , $html);            
            
            $html = $html . '<input type="hidden" id="wizit_is_variable_price" name="wizit_is_variable_price" value="false">';
        }

        $html = $html . '<input type="hidden" id="wizit_merchant_minimum_amount" name="wizit_merchant_minimum_amount" value="' . $merchant_minimum_amount  . '">';
        $html = $html . '<input type="hidden" id="wizit_merchant_maximum_amount" name="wizit_merchant_maximum_amount" value="' . $merchant_maximum_amount . '">';

        $html = $html . '<input type="hidden" id="wizit_product_id" name="wizit_product_id" value="' . $product_id . '">';
        $html = $html . '<input type="hidden" id="wizit_is_on_sale_product" name="wizit_is_on_sale_product" value="' . ($is_on_sale_product ? "true" : "false") . '">';
    
        if(strpos($html, 'learn more') === false){
            $html = '<p>' . $html . '</p>';
        }else{
            // add popup windows         
            $html = '<p>' . $html . '</p>' . get_wizit_popup_window($plugin_dir);
        }

        return $html;
    }


    /**
     * Convert the global $post object to a WC_Product instance.
     *
     * @since   2.0.0
     * @global  WP_Post $post
     * @uses    wc_get_product()    Available as part of the WooCommerce core plugin since 2.2.0.
     *                              Also see:   WC()->product_factory->get_product()
     *                              Also see:   WC_Product_Factory::get_product()
     * @return  WC_Product
     */
    function get_wizit_product_from_the_post()
    {
        global $post;

        if (function_exists('wc_get_product'))
        {
            $product = wc_get_product($post->ID);
        }
        else
        {
            $product = new WC_Product($post->ID);
        }

        return $product;
    }


    function wizit_display_price_html($price)
    {
        if (function_exists('wc_price'))
        {
           // return wc_price($price);
        }
        elseif (function_exists('woocommerce_price'))
        {
           // return woocommerce_price($price);
        }
        return '$' . number_format($price, 2, '.', ',');
    }


    // move function to assets/js/custom-popup.js
    function get_wizit_popup_window($plugin_dir){
        $url_popup =  $plugin_dir . 'images/wizit_popup.png';//'https://info.wizit.money/HowItWorks/HowItWorks.html';

        return '';
    }
