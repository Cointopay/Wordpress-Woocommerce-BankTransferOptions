
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
console.log('awa');
const settings = getSetting( 'cointopay_bank_data', {} );
console.log(settings);
const defaultLabel = __(
	'WooCommerce Cointopay.com Bank',
	'woocommerce'
);

const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
	return decodeEntities( settings.description || '' );
};
/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	console.log('label');
	console.log(props);
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};

/**
 * LS payment method config object.
 */
const cointopay_bank_ctp = {
	name: "cointopay_bank",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};
//console.log(cointopay_bank_ctp);
registerPaymentMethod( cointopay_bank_ctp );