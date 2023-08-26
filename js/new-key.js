jQuery(document).ready(function($) {
	
	var errorMessage = function(msg)
	{
		if(!msg)
			msg = pdf_forms_for_woocommerce_new_key.__Unknown_error;
		jQuery('.pdf-forms-woocommerce-new-key-container').append(
			jQuery('<div class="error"/>').text(msg)
		);
	};
	
	jQuery("#pdf-forms-woocommerce-new-key-btn").on("click", function (event) {
		
		// prevent running default button click handlers
		event.stopPropagation();
		event.preventDefault();
		
		var email = jQuery('#pdf-forms-woocommerce-new-key-email').val();
		
		jQuery.ajax({
			url: pdf_forms_for_woocommerce_new_key.ajax_url,
			type: 'POST',
			data: {
				'action': 'pdf_forms_for_woocommerce_generate_pdf_ninja_key',
				'email': email,
				'nonce': pdf_forms_for_woocommerce_new_key.ajax_nonce
			},
			cache: false,
			dataType: 'json',
			
			success: function (data, textStatus, jqXHR) {
				if (!data.success)
					return errorMessage(data.error_message);
				
				location.reload(true);
			},
			
			error: function (jqXHR, textStatus, errorThrown) {
				return errorMessage(textStatus);
			},
		
			beforeSend: function() { PdfFormsFillerSpinner.show(); },
			complete: function() { PdfFormsFillerSpinner.hide(); }
		});
	});
	
});
