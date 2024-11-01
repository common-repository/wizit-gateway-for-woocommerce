
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';



const settings = getSetting( 'wizit_data', {} );

const handleLearnMoreClick = () => {
    const htmlElements = document.getElementsByClassName('wizit-pop-outer');
  
    if(htmlElements && htmlElements.length > 0){
        const wizitPopup = htmlElements[0];
        wizitPopup.style.display = 'block';
    }
    
};


/**
 * Content component
 */

const Content = () => {
    const orderTotal = Number(settings.orderTotal);
    const installments = orderTotal / 4;

    const displayData = {
        orderTotal: orderTotal.toFixed(2),
        installments: installments.toFixed(2)
    };

    return (
        <div>
            <p class='content' >4 x interest free fortnightly instalments totalling  
                            ${displayData.orderTotal}
                 &nbsp;&nbsp;&nbsp;   <a target="_blank" class="wizit-popup-open" onClick={handleLearnMoreClick}>learn more</a>
            </p>
            <div class="clear"></div>
            <div class="wizit-custom-payfields">
                <div class="wizit-row">
                    <div class="wizit-col-3 wizit-col-sm-6">
                        <p class="wizit-installment1">
                            ${displayData.installments}
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
                        <p class="wizit-installment2">
                             ${displayData.installments}
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
                        <p class="wizit-installment3" >
                            ${displayData.installments}
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
                        <p class="wizit-installment4">
                            ${displayData.installments}
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
        </div>
    );
};


/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	//const { PaymentMethodLabel } = props.components;
	return (
        <div style={{width: '100%'}}>
            <span style={{float:'left', display: 'inline-block',width:'50%'}}>
                Wizit            
            </span>
            <span style={{float:'right', display: 'inline-block',width:'50%', textAlign:'right'}}>
                <img style={{paddingRight : '15px'}} src="https://www.wizit.money/img/plugin/wizit.png"></img>
            </span>
        </div>
        
    );
};


/**
 * Wizit payment method config object.
 */

const Wizit_Block_Gateway = {
    name: 'wizit',
    label: <Label />,
	content: <Content />,
	edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: 'Wizit',
    supports: {
        features: settings.supports,
    },
};

registerPaymentMethod( Wizit_Block_Gateway );





// register a filter
// Global import

// https://github.com/woocommerce/woocommerce-blocks/blob/trunk/docs/third-party-developers/extensibility/checkout-block/available-slot-fills.md
const { registerPlugin } = window.wp.plugins;
const { ExperimentalOrderMeta } = window.wc.blocksCheckout;

const WizitCheckoutBlockComponent = ( { cart, extensions, context } ) => {

    const orderTotal = Number(settings.orderTotal);
    const installments = orderTotal / 4;
    const merchantMaxAmount = Number(settings.pluginSettings.merchant_maximum_amount ?? 0);
    const merchantMinAmount = Number(settings.pluginSettings.merchant_minimum_amount ?? 0);

    const displayData = {
        orderTotal: orderTotal.toFixed(2),
        installments: installments.toFixed(2),
        maxAmount: merchantMaxAmount.toFixed(2),
        minAmount: merchantMinAmount.toFixed(2)
    };

   

    const isDisplayAble = merchantMinAmount <= orderTotal && orderTotal <= merchantMaxAmount;


    if(context === 'woocommerce/cart'){
        if(isDisplayAble) {
            return (
                <div class="wc-block-components-totals-wrapper">
                            <p>
                                <img style={{width:'40px', height:'20px'}} class="wizit-payment-logo" src="https://www.wizit.money/img/plugin/wizit.png"></img>
                                &nbsp; 4 x fortnightly payments of ${displayData.installments} 
                                &nbsp; &nbsp; <a target="_blank" class="wizit-popup-open" onClick={handleLearnMoreClick}>learn more</a>
                            </p>
                </div>
            );
        }else{
            return (
                <div class="wc-block-components-totals-wrapper">
                            <p>
                                <img style={{width:'40px', height:'20px'}} class="wizit-payment-logo" src="https://www.wizit.money/img/plugin/wizit.png"></img>
                                &nbsp; is available on purchases between ${displayData.minAmount} 
                                &nbsp;and ${displayData.maxAmount} 
                                &nbsp; &nbsp; <a target="_blank" class="wizit-popup-open" onClick={handleLearnMoreClick}>learn more</a>
                            </p>
                </div>
            );
        }
        
    }else{
        return <div></div>;
    }
};



const render = () => {

    if(settings.pluginSettings.payment_info_on_cart === 'yes'){
        return (
            <ExperimentalOrderMeta>
                <WizitCheckoutBlockComponent />
            </ExperimentalOrderMeta>
        );
    }else{
        return (
            <ExperimentalOrderMeta>
                
            </ExperimentalOrderMeta>
        );
    }
	
};

registerPlugin( 'wizit-woo-checkout-block', {
	render,
	scope: 'woocommerce-checkout',
} );