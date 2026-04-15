<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if (!defined('ABSPATH')) exit;

class WC_Cointopay_Bank_Block_support extends AbstractPaymentMethodType {

    private $gateway;

    protected $name = 'cointopay_bank';

    public function initialize() {

        $this->settings = get_option('woocommerce_cointopay_bank_settings', []);

        // Safer initialization (avoid double loading issues)
        if (!class_exists('WC_CointopayBank_Gateway')) {
            return;
        }

        $this->gateway = new WC_CointopayBank_Gateway();
    }

    public function is_active() {

        if (!$this->gateway) {
            return false;
        }

        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

        $script_path = '/assets/js/frontend/blocks.js';

        $script_asset_path = WC_Cointopay_Bank_Payments::plugin_abspath()
            . 'assets/js/frontend/blocks.asset.php';

        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => [],
                'version'      => file_exists(WC_Cointopay_Bank_Payments::plugin_abspath() . 'assets/js/frontend/blocks.js')
    ? filemtime(WC_Cointopay_Bank_Payments::plugin_abspath() . 'assets/js/frontend/blocks.js')
    : '1.0.0'
            ];

        $script_url = WC_Cointopay_Bank_Payments::plugin_url() . $script_path;

        wp_register_script(
            'wc-cointopay-bank-payments-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'wc-cointopay-bank-payments-blocks',
                'cointopay-com-bank-only',
                WC_Cointopay_Bank_Payments::plugin_abspath() . 'languages/'
            );
        }

        return ['wc-cointopay-bank-payments-blocks'];
    }

    public function get_payment_method_data() {

        return [
            'title'       => $this->gateway ? $this->gateway->get_title() : '',
            'description' => $this->gateway ? $this->gateway->get_description() : '',
            'supports'    => $this->gateway && isset($this->gateway->supports)
                ? array_values($this->gateway->supports)
                : []
        ];
    }
}