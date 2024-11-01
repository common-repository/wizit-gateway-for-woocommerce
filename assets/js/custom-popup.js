jQuery(document).ready(function (){

    wizit_v_product_pricing_watcher();
    wizit_load_popup_template();
    wizit_register_popup_button();
});

jQuery(document.body).on('removed_from_cart updated_cart_totals', function () {
    wizit_register_popup_button();
});



var Wizit_Widgets_PaymentSchedule = function(containerId, amount, installments){
    var htmlElement = jQuery('#' + containerId);
    if(htmlElement){
        var innerHtml = `
            <h5 style="text-align: center;font-size: 16px;">4 x interest free fortnightly instalments totalling  
                $` + amount.toFixed(2) + `
                <a target="_blank" class="wizit-popup-open">learn more</a>
            </h5>
            <div class="clear"></div>
            <div class="wizit-custom-payfields">
                <div class="wizit-row">
                    <div class="wizit-col-3 wizit-col-sm-6">
                        <p class="wizit-installment1" style="font-size: 18px;">
                            $` + installments.toFixed(2) + `
                        </p>
                        <div class="wizit-installment1">
                            <svg width="25" height="25" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="3" cy="3" r="3" fill="#e61996"/>
                                <circle cx="13" cy="3" r="3" fill="#EDEDF4"/>
                                <circle cx="3" cy="13" r="3" fill="#EDEDF4"/>
                                <circle cx="13" cy="13" r="3" fill="#EDEDF4"/>
                            </svg>
                        </div>
                        <p class="wizit-installment-word">First Payment</p>
                    </div>
                    <div class="wizit-col-3 wizit-col-sm-6">
                        <p class="wizit-installment2" style="font-size: 18px;">
                            $` + installments.toFixed(2) + `
                        </p>
                        <div class="wizit-installment2">
                            <svg width="25" height="25" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="3" cy="3" r="3" fill="#e61996"/>
                                <circle cx="13" cy="3" r="3" fill="#e61996"/>
                                <circle cx="3" cy="13" r="3" fill="#EDEDF4"/>
                                <circle cx="13" cy="13" r="3" fill="#EDEDF4"/>
                            </svg>
                        </div>
                        <p class="wizit-installment-word">2 weeks later</p>
                    </div>
                    <div class="wizit-col-3 wizit-col-sm-6">
                        <p class="wizit-installment3" style="font-size: 18px;">
                            $` + installments.toFixed(2) + `
                        </p>
                        <div class="wizit-installment3">
                            <svg width="25" height="25" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="3" cy="3" r="3" fill="#e61996"/>
                                <circle cx="13" cy="3" r="3" fill="#e61996"/>
                                <circle cx="3" cy="13" r="3" fill="#e61996"/>
                                <circle cx="13" cy="13" r="3" fill="#EDEDF4"/>
                            </svg>
                        </div>
                        <p class="wizit-installment-word">4 weeks later</p>
                    </div>
                    <div class="wizit-col-3 wizit-col-sm-6">
                        <p class="wizit-installment4" style="font-size: 18px;">
                            $` + installments.toFixed(2) + `
                        </p>
                        <div class="wizit-installment4">
                            <svg width="25" height="25" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="3" cy="3" r="3" fill="#e61996"/>
                                <circle cx="13" cy="3" r="3" fill="#e61996"/>
                                <circle cx="3" cy="13" r="3" fill="#e61996"/>
                                <circle cx="13" cy="13" r="3" fill="#e61996"/>
                            </svg>
                        </div>
                        <p class="wizit-installment-word">6 weeks later</p>
                    </div>
                </div>
            </div>
            <div class="clear"></div>
        `;

        htmlElement.html(innerHtml);


        wizit_load_popup_template();
        wizit_register_popup_button();
    }
}


var wizit_v_product_pricing_watcher = function (){
    // do variation_price update
    // 1. watch variation_price change
    // Options for the observer (which mutations to observe)
    var config = { aattributes: true, childList: true, subtree: true, characterData:true};

    // Function to convert
    var currencyToNumber = function(currency){
        var k, temp;
        try{
            // Loop to make substring
            for(var i = 0; i < currency.length; i++){

                // Getting Unicode value
                k = currency.charCodeAt(i);

                // Checking whether the character
                // is of numeric type or not
                if(k > 47 && k < 58){

                    // Making substring
                    temp = currency.substring(i);
                    break;
                }
            }

            // If currency is in format like
            // 458, 656.75 then we used replace
            // method to replace every ', ' with ''
            temp = temp.replace(/, /, '');

            // Converting string to float
            // or double and return
            return parseFloat(temp);   
        } catch(error){
            return 0;
        }

    };

    var getPriceFromMainElement = function(priceNodeElement){
        var price = 0;
        if(priceNodeElement.childNodes && priceNodeElement.childNodes.length > 0){
            for(var i = 0; i < priceNodeElement.childNodes.length; i++){
                price = getPriceFromMainElement(priceNodeElement.childNodes[i]);
                if(price > 0){
                    return price;
                }
            }
        }else if(priceNodeElement.textContent && !isNaN(priceNodeElement.textContent.replace(/[^\d.-]/g, ''))){
            price = Number(priceNodeElement.textContent.replace(/[^\d.-]/g, ''));
        }

        return price;
    };

    var cartTotalNodes = document.getElementsByClassName('single_variation_wrap');

    var default_text_holder = document.getElementById('wizit-price-range-holder'); 

    var default_text = '';
    if(default_text_holder){
        default_text = default_text_holder.innerHTML;
    }

    var wizit_min = document.getElementById('wizit_merchant_minimum_amount');
    var wizit_max = document.getElementById('wizit_merchant_maximum_amount');

    var wizit_min_price = -1;
    var wizit_max_price = -1;

    if(wizit_min && wizit_max){
        wizit_min_price = parseFloat(wizit_min.value);
        wizit_max_price = parseFloat(wizit_max.value);
    }

    // working for variable product
    if(cartTotalNodes && cartTotalNodes.length > 0){
        var cartTotalNode = cartTotalNodes[0];
        var callbackCart = function(mutationsList) {
            for(var mutation of mutationsList) {
                var newPriceNodes = cartTotalNode.getElementsByClassName('woocommerce-Price-amount amount');
 
                if(newPriceNodes && newPriceNodes.length > 0){
                    // set default to first
                    var newPriceNode = newPriceNodes[0];                    
                    var total = getPriceFromMainElement(newPriceNode); 
                    // check all other pricing make sure we selecte min one.
                    for(var i = 0; i < newPriceNodes.length; i++){
                        // get all price and chooies min one.
                        total = Math.min(total, getPriceFromMainElement(newPriceNodes[i]));
                    }
                    
                    if(total > 0){
                        // re-calc wizit value
                        var priceElement = document.getElementById('wizit-price-range-holder');
                            
                        if(wizit_min_price > 0 && wizit_max_price > 0
                            && priceElement
                            && wizit_min_price <= total
                            && total <= wizit_max_price){
                                if(priceElement.innerHTML.indexOf('is available on purchases') >= 0
                                || priceElement.innerHTML.length > 8){
                                    priceElement.innerHTML = 'or 4 payments of $ ' + (total / 4).toFixed(2) + ' with Wizit'
                                }else{
                                    priceElement.innerHTML = '$' + (total / 4).toFixed(2);
                                }
                                
                            }
                        else if(priceElement && default_text){
                            priceElement.innerHTML = default_text;
                        }
                    }                    
                }
            }
        };

        // Create an observer instance linked to the callback function
        var observerCart = new MutationObserver(callbackCart);    
        // Start observing the target node for configured mutations
        observerCart.observe(cartTotalNode, config);  

    }
    else{
        // get wizit_is_variable_price value
        var wizit_is_variable_price = document.getElementById('wizit_is_variable_price');
        if(wizit_is_variable_price && wizit_is_variable_price.value == 'false'){
            // watch pricing changes
            var wizit_product_id = document.getElementById('wizit_product_id');
            var wizit_is_on_sale_product = document.getElementById('wizit_is_on_sale_product');

            if(wizit_product_id && !isNaN(wizit_product_id.value)){
                wizit_product_id = Number(wizit_product_id.value);
                var  priceDiv = null;  

               if(wizit_is_on_sale_product && wizit_is_on_sale_product.value == 'true'){
                    var t1 = document.getElementById('product-'+ wizit_product_id);
                    if(t1){
                        var t2 = t1.getElementsByClassName('summary');
                        if(t2 && t2.length > 0){
                            var t3 = t2[0].getElementsByTagName('ins');
                            if(t3 && t3.length > 0){
                                var t4 = t3[0].getElementsByClassName('woocommerce-Price-amount');
                                if(t4 && t4.length > 0){
                                    priceDiv = t4[0];
                                }
                            }
                        }
                    }
               }else{
                    var t1 = document.getElementById('product-'+ wizit_product_id);
                    if(t1){
                        var t2 = t1.getElementsByClassName('summary');
                        if(t2 && t2.length > 0){
                            var t3 = t2[0].getElementsByClassName('woocommerce-Price-amount');
                            if(t3 && t3.length > 0){
                                priceDiv = t3[0];
                            }
                        }
                    }
               }
               
               if(priceDiv){
                    var callbackCart = function(mutationsList) {
                        for(var mutation of mutationsList) {
                            
                                // set default to first
                                var newPriceNode = null;
                                // re-get all element
                                if(wizit_is_on_sale_product && wizit_is_on_sale_product.value == 'true'){
                                    var t1 = document.getElementById('product-'+ wizit_product_id);
                                    if(t1){
                                        var t2 = t1.getElementsByClassName('summary');
                                        if(t2 && t2.length > 0){
                                            var t3 = t2[0].getElementsByTagName('ins');
                                            if(t3 && t3.length > 0){
                                                var t4 = t3[0].getElementsByClassName('woocommerce-Price-amount');
                                                if(t4 && t4.length > 0){
                                                    newPriceNode = t4[0];
                                                }
                                            }
                                        }
                                    }
                               } else {
                                    var t1 = document.getElementById('product-'+ wizit_product_id);
                                    if(t1){
                                        var t2 = t1.getElementsByClassName('summary');
                                        if(t2 && t2.length > 0){
                                            var t3 = t2[0].getElementsByClassName('woocommerce-Price-amount');
                                            if(t3 && t3.length > 0){
                                                newPriceNode = t3[0];
                                            }
                                        }
                                    }
                               }

                                var total = getPriceFromMainElement(newPriceNode); 
                                                                
                                if(total > 0){
                                    // re-calc wizit value
                                    var priceElement = document.getElementById('wizit-price-range-holder');
                                        
                                    if(wizit_min_price > 0 && wizit_max_price > 0
                                        && priceElement
                                        && wizit_min_price <= total
                                        && total <= wizit_max_price){
                                            if(priceElement.innerHTML.indexOf('is available on purchases') >= 0
                                            || priceElement.innerHTML.length > 8){
                                                priceElement.innerHTML = 'or 4 payments of $ ' + (total / 4).toFixed(2) + ' with Wizit'
                                            }else{
                                                priceElement.innerHTML = '$' + (total / 4).toFixed(2);
                                            }
                                            
                                        }
                                    else if(priceElement && default_text){
                                        priceElement.innerHTML = default_text;
                                    }
                                }                    
                            
                        }
                    };
            
                    // Create an observer instance linked to the callback function
                    var observerCart = new MutationObserver(callbackCart);    
                    // Start observing the target node for configured mutations
                    observerCart.observe(priceDiv, config);  
               }
                        
            }
        }
    }
};



var wizit_register_popup_button = function(){
    jQuery(".wizit-popup-open").click(function (){
        jQuery(".wizit-pop-outer").fadeIn("slow");
    });
    jQuery(".wizit-popup-close").click(function (){
        jQuery(".wizit-pop-outer").fadeOut("slow");
    });
    jQuery(".wizit-pop-outer").click(function (){
        jQuery(".wizit-pop-outer").fadeOut("slow");
    });
}

var wizit_load_popup_template = function(){
    jQuery(document.body).append('<div style="display: none;" class="wizit-pop-outer"><div class="wizit-pop-inner"><button class="wizit-popup-close" type="button">X</button><a href="https://info.wizit.money/HowItWorks/HowItWorks.html" target="_blank"><img src="https://www.wizit.money/img/plugin/wizit_popup.png" title="Terms and Conditions" /></a></div></div>');
}
