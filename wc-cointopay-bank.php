<?php
/**
 * Plugin Name: WooCommerce Cointopay.com Bank
 * Description: Extends WooCommerce with bank payments gateway.
 * Version: 1.2.1
 * Author: Cointopay
 *
 * @package  WooCommerce
 * @author   Cointopay <info@cointopay.com>
 * @link     cointopay.com
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) === true ) {
	// Add the Gateway to WooCommerce.
	add_filter( 'woocommerce_payment_gateways', 'ctp_bank_add_gateway_class' );
	function ctp_bank_add_gateway_class( $gateways ) {
		$gateways[] = 'WC_CointopayBank_Gateway';

		return $gateways;
	}

	add_action( 'plugins_loaded', 'ctp_bank_init_gateway_class', 0 );
	function ctp_bank_init_gateway_class() {

		class WC_CointopayBank_Gateway extends WC_Payment_Gateway {
			public $msg = [];
			private $merchant_id;
			private $api_key;
			private $secret;
			public $alt_coin_id;
			public $description;
			public $title;

			public function __construct() {
				$this->id   = 'cointopay_bank';
				$this->icon = plugins_url( 'images/crypto.png', __FILE__ );

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->merchant_id = $this->get_option( 'merchant_id' );
				$this->alt_coin_id = $this->get_option( 'cointopay_bank_alt_coin' );

				$this->api_key        = '1';
				$this->secret         = $this->get_option( 'secret' );
				$this->msg['message'] = '';
				$this->msg['class']   = '';
				add_action( 'init', array( &$this, 'check_cointopay_bank_response' ) );
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					&$this,
					'process_admin_options'
				) );

				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array(
					&$this,
					'check_cointopay_bank_response'
				) );

				if ( empty( $this->settings['enabled'] ) === false
				     && empty( $this->api_key ) === false && empty( $this->secret ) === false ) {
					$this->enabled = 'yes';
				} else {
					$this->enabled = 'no';
				}
				// Checking if api key is not empty.
				if ( empty( $this->api_key ) === true ) {
					add_action( 'admin_notices', array( &$this, 'api_key_missing_message' ) );
				}

				// Checking if app_secret is not empty.
				if ( empty( $this->secret ) === true ) {
					add_action( 'admin_notices', array( &$this, 'secret_missing_message' ) );
				}

				add_action( 'admin_enqueue_scripts', array( &$this, 'ctp_include_custom_js' ) );
			}

			public function ctp_include_custom_js() {
				if ( ! did_action( 'wp_enqueue_media' ) ) {
					wp_enqueue_media();
				}
				wp_enqueue_script( 'custom-ctp-bank-js', plugins_url( 'js/ctp_bank_custom.js', __FILE__ ), array( 'jquery' ), null, false );
				wp_localize_script( 'custom-ctp-bank-js', 'ajaxurlctpbank', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ))); 
			}

			// Define init form fields function
			public function init_form_fields() {
				$this->form_fields = array(
					'enabled'     => array(
						'title'   => __( 'Enable/Disable', 'ctp-bank' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable Cointopay Bank Only', 'ctp-bank' ),
						'default' => 'yes',
					),
					'title'       => array(
						'title'       => __( 'Title', 'ctp-bank' ),
						'type'        => 'text',
						'description' => __( 'This controls the title the user can see during checkout.', 'ctp-bank' ),
						'default'     => __( 'Cointopay Bank Only', 'ctp-bank' ),
					),
					'description' => array(
						'title'       => __( 'Description', 'ctp-bank' ),
						'type'        => 'textarea',
						'description' => __( 'This controls the title the user can see during checkout.', 'ctp-bank' ),
						'default'     => __( 'You will be redirected to cointopay.com to complete your purchase.', 'ctp-bank' ),
					),
					'merchant_id' => array(
						'title'       => __( 'Your MerchantID', 'ctp-bank' ),
						'type'        => 'text',
						'description' => __( 'Please enter your Cointopay Merchant ID, You can get this information in: <a href="' . esc_url( 'https://cointopay.com' ) . '" target="_blank">Cointopay Account</a>.', 'ctp-bank' ),
						'default'     => '',
					),
					'secret'      => array(
						'title'       => __( 'Security Code', 'ctp-bank' ),
						'type'        => 'password',
						'description' => __( 'Please enter your Cointopay SecurityCode, You can get this information in: <a href="' . esc_url( 'https://cointopay.com' ) . '" target="_blank">Cointopay Account</a>.', 'ctp-bank' ),
						'default'     => '',
					),
					'cointopay_bank_alt_coin' =>  array(
						'type'          => 'select',
						'class'         => array( 'cointopay_bank_alt_coin' ),
						'title'         => __( 'Default Receive Currency', 'ctp-bank' ),
						'options'       => array(
						'blank'		=> __( 'Select Alt Coin', 'ctp-bank' ),
						)
					),
				);
			}

			public function admin_options() { ?>
                <h3><?php esc_html_e( 'Cointopay bank Only Checkout', 'ctp-bank' ); ?></h3>

                <div id="wc_get_started">
                    <span class="main"><?php esc_html_e( 'Provides a secure way to accept crypto currencies.', 'ctp-bank' ); ?></span>
                    <p><a href="https://app.cointopay.com/index.jsp?#Register" target="_blank"
                          class="button button-primary"><?php esc_html_e( 'Join free', 'ctp-bank' ); ?>
                        </a>
                        <a href="https://cointopay.com" target="_blank" class="button">
							<?php esc_html_e( 'Learn more about WooCommerce and Cointopay', 'ctp-bank' ); ?>
                        </a>
                    </p>
                </div>

                <table class="form-table">
					<?php $this->generate_settings_html(); ?>
                </table>
				<?php
			}

			public function payment_fields() {
				if ( true === $this->description ) {
					echo esc_html( $this->description );
				}
			}

			public function process_payment( $order_id ) {
				global $woocommerce;
				$order = wc_get_order( $order_id );

				$item_names = array();

				if ( count( $order->get_items() ) > 0 ) :
					foreach ( $order->get_items() as $item ) :
						if ( true === $item['qty'] ) {
							$item_names[] = $item['name'] . ' x ' . $item['qty'];
						}
					endforeach;
				endif;
				$url      = 'https://app.cointopay.com/MerchantAPI?Checkout=true';
				$params   = array(
					'body' => 'SecurityCode=' . $this->secret . '&MerchantID=' . $this->merchant_id . '&Amount=' . number_format( $order->get_total(), 8, '.', '' ) . '&AltCoinID=' . $this->alt_coin_id . '&output=json&inputCurrency=' . get_woocommerce_currency() . '&CustomerReferenceNr=' . $order_id . '&returnurl=' . rawurlencode( esc_url( $this->get_return_url( $order ) ) ) . '&transactionconfirmurl=' . site_url( '/?wc-api=WC_CointopayBank_Gateway' ) . '&transactionfailurl=' . rawurlencode( esc_url( $order->get_cancel_order_url() ) ),
				);
				$response = wp_safe_remote_post( $url, $params );
				if (( false === is_wp_error($response) ) && ( 200 === $response['response']['code'] ) && ( 'OK' === $response['response']['message'] )) {
					$results = json_decode($response['body']);
					return array(
						'result'   => 'success',
						'redirect' => $results->shortURL . "?fiat=1",
					);
				} else {
					$error_msg = str_replace('"', "", $response['body']);
					wc_add_notice($error_msg, 'error');
				}
			}

			public function check_cointopay_bank_response() {
				global $woocommerce;
				$woocommerce->cart->empty_cart();
				$order_id                = ( isset( $_REQUEST['CustomerReferenceNr'] ) ) ? intval( $_REQUEST['CustomerReferenceNr'] ) : 0;
				$order_status            = ( isset( $_REQUEST['status'] ) ) ? sanitize_text_field( $_REQUEST['status'] ) : '';
				$order_transaction_id    = ( isset( $_REQUEST['TransactionID'] ) ) ? sanitize_text_field( $_REQUEST['TransactionID'] ) : '';
				$order_confirm_code      = ( isset( $_REQUEST['ConfirmCode'] ) ) ? sanitize_text_field( $_REQUEST['ConfirmCode'] ) : '';
				$not_enough           = ( isset( $_REQUEST['notenough'] ) ) ? intval( $_REQUEST['notenough'] ) : 1;
				$order = wc_get_order( $order_id );
				$data = array(
					'mid'           => $this->merchant_id,
					'TransactionID' => $order_transaction_id,
					'ConfirmCode'   => $order_confirm_code,
				);
				$transactionData      = $this->validate_order( $data );
				if ( 200 !== $transactionData['status_code'] ) {
					get_header();
					echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="width: 100px; margin: 0 auto 20px;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">' . $transactionData['message'] . '</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
					get_footer();
					exit;
				} else {
					if ( $transactionData['data']['Security'] != $order_confirm_code ) {
						get_header();
						echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="width: 100px; margin: 0 auto 20px;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! ConfirmCode doesn\'t match</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
						get_footer();
						exit;
					} elseif ( $transactionData['data']['CustomerReferenceNr'] != $order_id ) {
						get_header();
						echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="width: 100px; margin: 0 auto 20px;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! CustomerReferenceNr doesn\'t match</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
						get_footer();
						exit;
					} elseif ( $transactionData['data']['TransactionID'] != $order_transaction_id ) {
						get_header();
						echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="width: 100px; margin: 0 auto 20px;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! TransactionID doesn\'t match</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
						get_footer();
						exit;
					} elseif ( $transactionData['data']['Status'] != $order_status ) {
						get_header();
						echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="width: 100px; margin: 0 auto 20px;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! status doesn\'t match. Your order status is ' . $transactionData['data']['Status'] . '</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
						get_footer();
						exit;
					}
				}

				if ( ( 'paid' === $order_status ) && ( 0 === $not_enough ) ) {
					// Do your magic here, and return 200 OK to Cointopay.
					if ( 'completed' === $order->get_status() ) {
						$order->update_status( 'processing', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
					} else {
						$order->payment_complete();
						$order->update_status( 'processing', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
					}
					$order->save();
					
					$order->add_order_note( __( 'IPN: Update status event for Cointopay bank to status COMPLETED:', 'woocommerce' ) . ' ' . $order_id);
					
					get_header();
					echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#0fad00">Success!</h2><img style="width: 100px; margin: 0 auto 20px;"  src="' . esc_url( plugins_url( 'images/check.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been received and confirmed successfully.</p><a href="' . esc_url( site_url() ) . '" style="background-color: #0fad00;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br><br><br></div></div></div>';
					get_footer();
					exit;
				} elseif ( 'failed' === $order_status && 1 === $not_enough ) {
					$order->update_status( 'on-hold', sprintf( __( 'IPN: Payment failed notification from Cointopay because not enough', 'woocommerce' ) ) );
					get_header();
					echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="width: 100px; margin: 0 auto 20px;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br><br><br></div></div></div>';
					get_footer();
					exit;
				} else {
					$order->update_status( 'failed', sprintf( __( 'IPN: Payment failed notification from Cointopay', 'woocommerce' ) ) );
					get_header();
					echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="width: 100px; margin: 0 auto 20px;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p><a href="' . esc_url( site_url() ) . '" style="background-color:#ff0000;border:none;color: white;padding:15px 32px;text-align: center;text-decoration:none;display:inline-block;font-size:16px;">Back</a><br><br><br><br></div></div></div>';
					get_footer();
					exit;
				}
			}

			/**
			 * Adds error message when not configured the api key.
			 */
			public function api_key_missing_message() {
				$message = '<div class="error">';
				$message .= '<p><strong>Gateway Disabled</strong> You should enter your API key in Cointopay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">Click here to configure</a></p>';
				$message .= '</div>';

				return $message;
			}

			/**
			 * Adds error message when not configured the secret.
			 */
			public function secret_missing_message() {
				$message = '<div class="error">';
				$message .= '<p><strong>Gateway Disabled</strong> You should check your SecurityCode in Cointopay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">Click here to configure!</a></p>';
				$message .= '</div>';

				return $message;
			}

			public function validate_order( $data ) {
				$params = array(
					'body'           => 'MerchantID=' . $data['mid'] . '&Call=Transactiondetail&APIKey=a&output=json&ConfirmCode=' . $data['ConfirmCode'],
					'authentication' => 1,
					'cache-control'  => 'no-cache',
				);

				$url = 'https://app.cointopay.com/v2REAPI?';

				$response = wp_safe_remote_post( $url, $params );

				return json_decode( $response['body'], true );
			}
		}
	}
	add_action( 'wp_ajax_nopriv_getCTPBankMerchantCoins', 'getCTPBankMerchantCoins' );
		add_action( 'wp_ajax_getCTPBankMerchantCoins', 'getCTPBankMerchantCoins' );
		function getCTPBankMerchantCoins()
		{
			$merchantId = 0;
			$merchantId = intval($_REQUEST['merchant']);
			if(isset($merchantId) && $merchantId !== 0)
			{
				$option = '';
				$arr = getCTPBankCoins($merchantId);
				foreach($arr as $key => $value)
				{
                    $ctpbank = new WC_CointopayBank_Gateway;
					$ctpbselect = ($key == $ctpbank->alt_coin_id) ? 'selected="selected"' : '';
					$option .= '<option value="'.$key.'" '.$ctpbselect.'>'.$value.'</option>';
				}
				
				echo $option;exit();
			}
		}

		function getCTPBankCoins($merchantId)
		{
			$params = array(
				'body' => 'MerchantID=' . $merchantId . '&output=json',
			);
			$url = 'https://cointopay.com/CloneMasterTransaction';
			$response  = wp_safe_remote_post($url, $params);
			if (( false === is_wp_error($response) ) && ( 200 === $response['response']['code'] ) && ( 'OK' === $response['response']['message'] )) {
				$php_arr = json_decode($response['body']);
				$new_php_arr = array();

				if(!empty($php_arr))
				{
					for($i=0;$i<count($php_arr)-1;$i++)
					{
						if(($i%2)==0)
						{
							$new_php_arr[$php_arr[$i+1]] = $php_arr[$i];
						}
					}
				}
				
				return $new_php_arr;
			}
		}
}