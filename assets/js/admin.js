jQuery(document).ready(function () {

    wizitHideProductionEnviromentModelDescription();


    jQuery("#woocommerce_wizit_environment_mode").change(function () {
        wizitHideProductionEnviromentModelDescription();
    });

    jQuery("#wizitCustRestoreBtn").click(function () {
        if (window.confirm('Customisations have now been reset to defaults. Please review and click "Save Changes" to accept the new values.')) {
            if (tinymce.get('payment_info_on_product_text')) {
                tinymce.get('payment_info_on_product_text').setContent(wizit_def_payment_info_on_product_text);
            }

            jQuery('#payment_info_on_product_text').val(wizit_def_payment_info_on_product_text);

            jQuery('#woocommerce_wizit_payment_info_on_product_hook').val(wizit_def_payment_info_on_product_hook);
            jQuery('#woocommerce_wizit_payment_info_on_product_hook_priority').val(wizit_def_payment_info_on_product_hook_priority);

            jQuery('#woocommerce_wizit_payment_info_on_cart_text').val(wizit_def_payment_info_on_cart_text);

            if (tinymce.get("payment_info_on_product_cat_text")) {
                tinymce.get("payment_info_on_product_cat_text").setContent(wizit_def_payment_info_on_product_cat_text);
            }

            jQuery("#payment_info_on_product_cat_text").val(wizit_def_payment_info_on_product_cat_text);

            jQuery("#woocommerce_wizit_payment_info_on_product_cat_hook").val(wizit_def_payment_info_on_product_cat_hook);
            jQuery('#woocommerce_wizit_payment_info_on_product_cat_hook_priority').val(wizit_def_payment_info_on_product_cat_hook_priority);
        }
    });



    // for admin notice function
    jQuery('#wizit-review-later-btn').click(function () {
        jQuery('.wizit-plugin-rating-admin-notices').hide();
    });
    jQuery("#wizit-review-did-btn").click(function () {
        jQuery.ajax({
            url: ajaxurl,
            data: {
                action: 'wizit_plugin_rating_did_callback'
            }
        });

        jQuery('.wizit-plugin-rating-admin-notices').hide();
    });

});

var wizit_def_payment_info_on_product_text = 'or 4 payments [OF_OR_FROM] <span id="wizit-price-range-holder">[AMOUNT]</span> with Wizit';
var wizit_def_payment_info_on_product_cat_text = 'or 4 payments [OF_OR_FROM] <span id="wizit-price-range-holder">[AMOUNT]</span> with Wizit';
var wizit_def_payment_info_on_cart_text = '<div class="wizit-cart-custom-message">[wizit_logo] <span style="vertical-align: super;font-size: 16px;font-weight: normal;padding-left: 5px;" > 4 x fortnightly payments of $ <? php echo esc_attr(number_format($install, 2)); ?> <a target="_blank" class="wizit-popup-open" style="font-size: 12px;text-decoration: underline;">learn more</a></span></div>';


var wizit_def_payment_info_on_product_hook = 'woocommerce_single_product_summary';
var wizit_def_payment_info_on_product_hook_priority = '15';
var wizit_def_payment_info_on_product_cat_hook = 'woocommerce_after_shop_loop_item';
var wizit_def_payment_info_on_product_cat_hook_priority = '99';

function wizitHideProductionEnviromentModelDescription() {
    if (jQuery("#woocommerce_wizit_environment_mode").val() === 'production') {
        jQuery('.wizit-enviroment-model-test').hide();
        jQuery('.wizit-enviroment-model-test').parent().parent().parent().hide();
        jQuery('.wizit-enviroment-model').show();
        jQuery('.wizit-enviroment-model').parent().parent().parent().show();
    } else {
        jQuery('.wizit-enviroment-model-test').parent().parent().parent().show();
        jQuery('.wizit-enviroment-model-test').show();
        jQuery('.wizit-enviroment-model').hide();
        jQuery('.wizit-enviroment-model').parent().parent().parent().hide();
    }
}







function wizitSetDefaultValue(
    payment_info_on_product_text,
    payment_info_on_product_cat_text,
    payment_info_on_cart_text,
    payment_info_on_product_hook,
    payment_info_on_product_hook_priority,
    payment_info_on_product_cat_hook,
    payment_info_on_product_cat_hook_priority
) {
    wizit_def_payment_info_on_product_text = payment_info_on_product_text;
    wizit_def_payment_info_on_product_cat_text = payment_info_on_product_cat_text;
    wizit_def_payment_info_on_cart_text = payment_info_on_cart_text;
    wizit_def_payment_info_on_product_hook = payment_info_on_product_hook;
    wizit_def_payment_info_on_product_hook_priority = payment_info_on_product_hook_priority;
    wizit_def_payment_info_on_product_cat_hook = payment_info_on_product_cat_hook;
    wizit_def_payment_info_on_product_cat_hook_priority = payment_info_on_product_cat_hook_priority;
}