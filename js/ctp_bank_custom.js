jQuery(document).ready(function ($) {
    if($('input[id="woocommerce_cointopay_bank_merchant_id"]').length>0){
		var merchant_idd = $('input[id="woocommerce_cointopay_bank_merchant_id"]').val();
		if(merchant_idd != ''){
		var length_idd = merchant_idd.length;
		
			$.ajax ({
				url: ajaxurlctpbank.ajaxurl,
				showLoader: true,
				data: {merchant: merchant_idd, action: "getCTPBankMerchantCoins"},
				type: "POST",
				success: function(result) {
					$('select[id="woocommerce_cointopay_bank_cointopay_bank_alt_coin"]').html('');
					if (result.length) {
							$('select[id="woocommerce_cointopay_bank_cointopay_bank_alt_coin"]').html(result);
						
					} else {
					}
				}
			});
	
	$('input[id="woocommerce_cointopay_bank_merchant_id"]').on('change', function () {
		var merchant_id = $(this).val();
		var length_id = merchant_id.length;
		
			$.ajax ({
				url: ajaxurlctpbank.ajaxurl,
				showLoader: true,
				data: {merchant: merchant_id, action: "getCTPBankMerchantCoins"},
				type: "POST",
				success: function(result) {
					$('select[id="woocommerce_cointopay_bank_cointopay_bank_alt_coin"]').html('');
					if (result.length) {
						$('select[id="woocommerce_cointopay_bank_cointopay_bank_alt_coin"]').html(result);
					} else {
					}
				}
			});
		
	});
	}
	}
});