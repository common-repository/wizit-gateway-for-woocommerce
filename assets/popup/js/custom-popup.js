jQuery(document).ready(function (){
    jQuery(".wizit-popup-open").click(function (){
        jQuery(".wizit-pop-outer").fadeIn("slow");
    });
    jQuery(".wizit-popup-close").click(function (){
        jQuery(".wizit-pop-outer").fadeOut("slow");
    });
});

jQuery(document.body).on('removed_from_cart updated_cart_totals', function () {
    jQuery(".wizit-popup-open").click(function (){
        jQuery(".wizit-pop-outer").fadeIn("slow");
    });
    jQuery(".wizit-popup-close").click(function (){
        jQuery(".wizit-pop-outer").fadeOut("slow");
    });
});
