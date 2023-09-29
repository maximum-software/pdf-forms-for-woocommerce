jQuery(document).ready(function($) {
	
	// set up 'Reset Settings' button handler
	jQuery('#pdf-forms-for-woocommerce-metabox').on('click', '.reset-settings-button', function(event) {
		
		// prevent running default button click handlers
		event.stopPropagation();
		event.preventDefault();
		
		if(!confirm(pdf_forms_for_woocommerce.__Confirm_Reset_Settings))
			return;
		
		jQuery.ajax({
			url: pdf_forms_for_woocommerce.ajax_url,
			type: 'POST',
			data: {
				'action': 'pdf_forms_for_woocommerce_reset_order_settings',
				'order_id': pdf_forms_for_woocommerce.order_id,
				'nonce': pdf_forms_for_woocommerce.ajax_nonce,
			},
			cache: false,
			dataType: 'json',
			
			success: function(data, textStatus, jqXHR) {
				
				if(!data.success)
				{
					alert(data.error_message);
					return;
				}
				
				// reload page
				location.reload();
			},
			
			error: function(jqXHR, textStatus, errorThrown) { alert(textStatus); return; },
			
			beforeSend: function() { PdfFormsFillerSpinner.show(); },
			complete: function() { PdfFormsFillerSpinner.hide(); }
		});
		
		return false;
	});
	
	// set up 'Reset PDFs' button handler
	jQuery('#pdf-forms-for-woocommerce-metabox').on('click', '.reset-pdfs-button', function(event) {
		
		// prevent running default button click handlers
		event.stopPropagation();
		event.preventDefault();
		
		if(!confirm(pdf_forms_for_woocommerce.__Confirm_Reset_PDFs))
			return;
		
		jQuery.ajax({
			url: pdf_forms_for_woocommerce.ajax_url,
			type: 'POST',
			data: {
				'action': 'pdf_forms_for_woocommerce_reset_order_pdfs',
				'order_id': pdf_forms_for_woocommerce.order_id,
				'nonce': pdf_forms_for_woocommerce.ajax_nonce,
			},
			cache: false,
			dataType: 'json',
			
			success: function(data, textStatus, jqXHR) {
				
				if(!data.success)
				{
					alert(data.error_message);
					return;
				}
				
				// reload page
				location.reload();
			},
			
			error: function(jqXHR, textStatus, errorThrown) { alert(textStatus); return; },
			
			beforeSend: function() { PdfFormsFillerSpinner.show(); },
			complete: function() { PdfFormsFillerSpinner.hide(); }
		});
		
		return false;
	});
});
