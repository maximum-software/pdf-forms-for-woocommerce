jQuery(document).ready(function($) {
	
	var data_tag = jQuery('input[name="pdf-forms-for-woocommerce-data"]');
	var preload_data_tag = jQuery('div[class="preload-data"]');
	var post_id = data_tag.closest('form').find('input[name=post_ID]').val();
	
	// https://github.com/uxitten/polyfill/blob/master/string.polyfill.js
	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/padEnd
	if (!String.prototype.padEnd) {
		String.prototype.padEnd = function padEnd(targetLength,padString) {
			targetLength = targetLength>>0; //floor if number or convert non-number to 0;
			padString = String((typeof padString !== 'undefined' ? padString : ' '));
			if (this.length > targetLength) {
				return String(this);
			}
			else {
				targetLength = targetLength-this.length;
				if (targetLength > padString.length) {
					padString += padString.repeat(targetLength/padString.length); //append to original to ensure we are longer than needed
				}
				return String(this) + padString.slice(0,targetLength);
			}
		};
	}
	
	// Object assign polyfill, courtesy of https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/assign
	if (typeof Object.assign !== 'function')
	{
		// Must be writable: true, enumerable: false, configurable: true
		Object.defineProperty(Object, "assign", {
			value: function assign(target, varArgs) { // .length of function is 2
				'use strict';
				if (target === null || target === undefined) {
					throw new TypeError('Cannot convert undefined or null to object');
				}
				
				var to = Object(target);
				
				for (var index = 1; index < arguments.length; index++) {
					var nextSource = arguments[index];
					
					if (nextSource !== null && nextSource !== undefined) {
						for (var nextKey in nextSource) {
							// Avoid bugs when hasOwnProperty is shadowed
							if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
							  to[nextKey] = nextSource[nextKey];
							}
						}
					}
				}
				return to;
			},
			writable: true,
			configurable: true
		});
	}
	
	jQuery.fn.select2.amd.define("pdf-forms-for-woocommerce-shared-data-adapter", 
	['select2/data/array','select2/utils'],
		function (ArrayData, Utils) {
			function CustomData($element, options) {
				CustomData.__super__.constructor.call(this, $element, options);
			}
			
			Utils.Extend(CustomData, ArrayData);
			
			CustomData.prototype.query = function (params, callback) {
				
				var options = this.options.options;
				var items = select2SharedData[options.sharedDataElement];
				if(options.hasOwnProperty('sharedDataElementId'))
					items = items[options.sharedDataElementId];
				
				// if items is an object, convert to array
				if(typeof items === 'object' && !Array.isArray(items))
					items = Object.entries(items).map(function(item) { return item[1]; });
				
				var pageSize = 20;
				if(!("page" in params))
					params.page = 1;
				
				var totalNeeded = params.page * pageSize;
				
				if(params.term && params.term !== '')
				{
					var upperTerm = params.term.toLowerCase();
					var count = 0;
					
					items = items.filter(function(item) {
						
						// don't filter any more items if we have collected enough
						if(count > totalNeeded)
							return false;
						
						if(!item.hasOwnProperty("lowerText"))
							item.lowerText = item.text.toLowerCase();
						
						var counts = item.lowerText.indexOf(upperTerm) >= 0;
						
						if(counts)
							count++;
						
						return counts;
					});
				}
				
				if(options.tags === true)
				{
					var currentValue = this.$element.val();
					var tag = params.term && params.term != '' ? params.term : currentValue ? currentValue : "";
					var lowerTag = String(tag).toLowerCase();
					
					var exists = false;
					jQuery.each(items, function(index, item)
					{
						if(!item.hasOwnProperty("lowerText"))
							item.lowerText = String(item.text).toLowerCase();
						if(item.id == tag || item.lowerText == lowerTag)
						{
							exists = true;
							return false; // break
						}
					});
					if(!exists)
					{
						items = Object.assign([], items); // shallow copy
						items.unshift({id: tag, text: tag, lowerText: lowerTag});
					}
				}
				
				var more = items.length > totalNeeded;
				
				items = items.slice((params.page - 1) * pageSize, totalNeeded); // paginate
				
				callback({
					results: items,
					pagination: { more: more }
				});
			};
			
			return CustomData;
		}
	);
	
	jQuery.fn.initializeMultipleSelect2Field = function(shared_data_element, selected_options) {
		
		if(typeof selected_options !== 'object' || !Array.isArray(selected_options))
			selected_options = [];
		
		var class_name = this[0].className;
		var attachment_id = jQuery(this).data('attachment_id');
		
		if(attachment_id === undefined || !class_name)
			return;
		
		jQuery(this)
			.removeClass(class_name).addClass(class_name + '--' + attachment_id)
			.select2({
				ajax: {},
				width: '100%',
				sharedDataElement: shared_data_element,
				dropdownParent: jQuery('#pdf-forms-for-woocommerce-product-settings'),
				dataAdapter: jQuery.fn.select2.amd.require("pdf-forms-for-woocommerce-shared-data-adapter")
			});
		
		var select2Data = select2SharedData[shared_data_element];
		for(var j = 0; j < select2Data.length; ++j)
			if(selected_options.indexOf(String(select2Data[j].id)) != -1)
			{
				var text = select2Data[j].text, id = String(select2Data[j].id);
				jQuery(this).append( new Option(text, id, true, true) );
			}
		
		jQuery(this).val(selected_options).trigger('change');
	}
	
	jQuery.fn.resetSelect2Field = function(id) {
		
		if(typeof id == 'undefined')
			id = null;
		
		if(!jQuery(this).data('select2'))
			return;
		
		jQuery(this).empty();
		
		var options = this.data().select2.options.options;
		var select2Data = select2SharedData[options.sharedDataElement];
		if(options.hasOwnProperty('sharedDataElementId'))
			select2Data = select2Data[options.sharedDataElementId];
		if(typeof select2Data === 'object' && (
			   (Array.isArray(select2Data) && select2Data.length > 0))
			|| (!Array.isArray(select2Data) && Object.keys(select2Data).length > 0)
		)
		{
			var optionInfo = select2Data[id !== null ? id : 0];
			if(typeof optionInfo == 'undefined')
				optionInfo = Array.isArray(select2Data) ? select2Data[0] : Object.values(select2Data)[0];
			var option = new Option(optionInfo.text, optionInfo.id, true, true);
			jQuery(this).append(option).val(optionInfo.id);
			
			// TODO fix
			jQuery(this).trigger('change');
			jQuery(this).trigger({
				type: 'select2:select',
				params: {
					data: optionInfo
				}
			});
		}
		else
			jQuery(this).trigger('change');
		
		return this;
	}
	
	var pdfFields = [],
		attachmentData = {},
		defaultPdfOptions = {};
	
	var pluginData = {
		attachments: [],
		mappings: [],
		value_mappings: [],
		embeds: []
	};
	
	var select2SharedData = {
		unmappedPdfFields: [],
		pdfSelect2Files: [{id: 0, text: pdf_forms_for_woocommerce.__All_PDFs, lowerText: String(pdf_forms_for_woocommerce.__All_PDFs).toLowerCase()}],
		pageList: [],
		emailTemplates: [],
		woocommercePlaceholders: [],
		downloads: []
	};
	
	jQuery('.pdf-forms-for-woocommerce-admin .woo-placeholder-list').select2({
		ajax: {},
		width: '100%',
		sharedDataElement: "woocommercePlaceholders",
		dropdownParent: jQuery('#pdf-forms-for-woocommerce-product-settings'),
		dataAdapter: jQuery.fn.select2.amd.require("pdf-forms-for-woocommerce-shared-data-adapter")
	}).on('select2:select', function (e) {
		var data = e.params.data;
		jQuery(this).find('option:selected').attr('data-placeholders', data['placeholder']);
	});
	
	jQuery('.pdf-forms-for-woocommerce-admin .pdf-field-list').select2({
		ajax: {},
		width: '100%',
		sharedDataElement: "unmappedPdfFields",
		dropdownParent: jQuery('#pdf-forms-for-woocommerce-product-settings'),
		dataAdapter: jQuery.fn.select2.amd.require("pdf-forms-for-woocommerce-shared-data-adapter")
	});
	
	jQuery('.pdf-forms-for-woocommerce-admin .pdf-files-list').select2({
		ajax: {},
		width: '100%',
		sharedDataElement: "pdfSelect2Files",
		dropdownParent: jQuery('#pdf-forms-for-woocommerce-product-settings'),
		dataAdapter: jQuery.fn.select2.amd.require("pdf-forms-for-woocommerce-shared-data-adapter")
	});
	jQuery('.pdf-forms-for-woocommerce-admin .page-list').select2({
		ajax: {},
		width: '100%',
		sharedDataElement: "pageList",
		dropdownParent: jQuery('#pdf-forms-for-woocommerce-product-settings'),
		dataAdapter: jQuery.fn.select2.amd.require("pdf-forms-for-woocommerce-shared-data-adapter")
	});
	
	var clearMessages = function()
	{
		jQuery('.pdf-forms-for-woocommerce-admin .messages').empty();
	};
	
	var errorMessage = function(msg)
	{
		if(!msg)
			msg = pdf_forms_for_woocommerce.__Unknown_error;
		jQuery('.pdf-forms-for-woocommerce-admin .messages').append(
			jQuery('<div class="error"/>').text(msg)
		);
		location.href = '#pdf-forms-for-woocommerce-messages';
	};
	
	var warningMessage = function(msg)
	{
		jQuery('.pdf-forms-for-woocommerce-admin .messages').append(
			jQuery('<div class="warning"/>').text(msg)
		);
		location.href = '#pdf-forms-for-woocommerce-messages';
	};
	
	var successMessage = function(msg)
	{
		jQuery('.pdf-forms-for-woocommerce-admin .messages').append(
			jQuery('<div class="updated"/>').text(msg)
		);
		location.href = '#pdf-forms-for-woocommerce-messages';
	};
	
	var strtr = function(str, replacements)
	{
		for(i in replacements)
			if(replacements.hasOwnProperty(i))
				str = str.replace(i, replacements[i]);
		return str;
	}
	
	var utf8atob = function(str)
	{
		// see https://developer.mozilla.org/en-US/docs/Glossary/Base64#the_unicode_problem
		return (new TextDecoder()).decode(Uint8Array.from(atob(str), c => c.charCodeAt(0)));
	};
	
	var base64urldecode = function(data)
	{
		return utf8atob(strtr(data, {'.': '+', '_': '/'}).padEnd(data.length % 4, '='));
	}
	
	var getPdfFieldData = function(id)
	{
		for (var i = 0, l = pdfFields.length; i < l; ++i)
			if (pdfFields[i].id == id)
				return pdfFields[i];
		
		return null;
	};
	
	var getUnmappedPdfFields = function()
	{
		var pdf_fields = [];
		var mappings = getMappings();
		
		jQuery.each(pdfFields, function(f, field) {
			
			var field_pdf_field = String(field.id);
			var field_attachment_id = field_pdf_field.substr(0, field_pdf_field.indexOf('-'));
			var field_pdf_field_name = field_pdf_field.substr(field_pdf_field.indexOf('-')+1);
			
			for(var i=0, l=mappings.length; i<l; i++)
			{
				var mapping_pdf_field = String(mappings[i].pdf_field);
				var mapping_attachment_id = mapping_pdf_field.substr(0, mapping_pdf_field.indexOf('-'));
				var mapping_pdf_field_name = mapping_pdf_field.substr(mapping_pdf_field.indexOf('-')+1);
				
				if( (mapping_attachment_id == 'all' || field_attachment_id == 'all' || mapping_attachment_id == field_attachment_id)
					&& mapping_pdf_field_name == field_pdf_field_name)
					return;
			}
			
			pdf_fields.push(field);
		});
		
		return pdf_fields;
	};
	
	var reloadPdfFields = function()
	{
		var pdfFieldsA = [];
		var pdfFieldsB = [];
		
		var attachments = getAttachments();
		jQuery.each(attachments, function(a, attachment) {
			
			var info = getAttachmentInfo(attachment.attachment_id);
			if(!info || !info.fields)
				return;
			
			jQuery.each(info.fields, function(f, field) {
				
				// sanity check
				if(!field.hasOwnProperty('name') || !field.hasOwnProperty('type'))
					return;
				
				var name = String(field.name);
				var type = String(field.type);
				
				// sanity check
				if(!(type === 'text' || type === 'radio' || type === 'select' || type === 'checkbox'))
					return;
				
				var all_attachment_data = Object.assign({}, field); // shallow copy
				var current_attachment_data = Object.assign({}, field); // shallow copy
				
				all_attachment_data['id'] = 'all-' + field.id;
				all_attachment_data['text'] = name;
				all_attachment_data['attachment_id'] = 'all';
				
				current_attachment_data['id'] = attachment.attachment_id + '-' + field.id;
				current_attachment_data['text'] = '[' + attachment.attachment_id + '] ' + name;
				current_attachment_data['attachment_id'] = attachment.attachment_id;
				
				pdfFieldsA.push(all_attachment_data);
				pdfFieldsB.push(current_attachment_data);
				
			});
			
			var ids = [];
			pdfFields = [];
			
			jQuery.each(pdfFieldsA.concat(pdfFieldsB), function(f, field) {
				if(ids.indexOf(field.id) == -1)
				{
					ids.push(field.id);
					field.lowerText = String(field.text).toLowerCase();
					pdfFields.push(field);
				}
			});
			
			runWhenDone(refreshPdfFields);
		});
	};
	
	var refreshPdfFields = function()
	{
		select2SharedData.unmappedPdfFields = getUnmappedPdfFields(); // TODO: optimize this
		jQuery('.pdf-forms-for-woocommerce-admin .pdf-field-list').resetSelect2Field();
	};
	
	var getData = function(field)
	{
		return pluginData[field];
	};
	
	var setData = function(field, value)
	{
		pluginData[field] = value;
		runWhenDone(updatePluginDataField);
	};
	
	var updatePluginDataField = function()
	{
		data_tag.val(JSON.stringify(pluginData));
	}
	
	var getAttachments = function()
	{
		var attachments = getData('attachments');
		if(attachments)
			return attachments;
		else
			return [];
	};
	
	var getAttachment = function(attachment_id)
	{
		var attachments = getAttachments();
		
		for(var i=0, l=attachments.length; i<l; i++)
			if(attachments[i].attachment_id == attachment_id)
				return attachments[i];
		
		return null;
	};
	
	var setAttachments = function(attachments)
	{
		setData('attachments', attachments);
		reloadPdfFields();
	};
	
	var deleteAttachment = function(attachment_id)
	{
		var remove_ids = [];
		var mappings = getMappings();
		jQuery.each(mappings, function(index, mapping) {
			var field_attachment_id = mapping.pdf_field.substr(0, mapping.pdf_field.indexOf('-'));
			if(field_attachment_id == attachment_id)
				remove_ids.push(mapping.mapping_id);
		});
		jQuery.each(remove_ids, function(index, id) { deleteMapping(id); });
		
		remove_ids = [];
		var embeds = getEmbeds();
		jQuery.each(embeds, function(index, embed) {
			if(embed.attachment_id == attachment_id)
				remove_ids.push(embed.id);
		});
		jQuery.each(remove_ids, function(index, id) { deleteEmbed(id); });
		
		var attachments = getAttachments();
		
		for(var i=0, l=attachments.length; i<l; i++)
			if(attachments[i].attachment_id == attachment_id)
			{
				attachments.splice(i, 1);
				break;
			}
		
		setAttachments(attachments);
		
		for(var i = 0, l = select2SharedData.pdfSelect2Files.length; i < l; i++)
			if(select2SharedData.pdfSelect2Files[i].id == attachment_id)
			{
				select2SharedData.pdfSelect2Files.splice(i, 1);
				break;
			}
		
		deleteAttachmentData(attachment_id);
		
		refreshMappings();
		refreshEmbeds();
		refreshPdfFilesList();
	};
	
	var setAttachmentOption = function(attachment_id, option, value) {
		
		var attachments = getAttachments();
		
		for(var i=0, l=attachments.length; i<l; i++)
			if(attachments[i].attachment_id == attachment_id)
			{
				if(typeof attachments[i].options == 'undefined'
				|| attachments[i].options == null)
					attachments[i].options = {};
				attachments[i].options[option] = value;
				break;
			}
		
		setAttachments(attachments);
	};
	
	var getAttachmentData = function(attachment_id)
	{
		return attachmentData[attachment_id];
	}
	var getAttachmentInfo = function(attachment_id)
	{
		var data = getAttachmentData(attachment_id);
		if(!data || !data.info)
			return;
		return data.info;
	}

	var setAttachmentData = function(attachment_id, data)
	{
		attachmentData[attachment_id] = data;
	}
	var deleteAttachmentData = function(attachment_id)
	{
		delete attachmentData[attachment_id];
	}
	
	var addAttachment = function(data)
	{
		var attachment_id = data.attachment_id;
		
		var info = getAttachmentData(attachment_id);
		if(!info)
			return;
		
		var filename = info.filename;
		var options = data.options;
		
		var attachments = getAttachments();
		attachments.push( data );
		setAttachments(attachments);
		
		jQuery('.pdf-forms-for-woocommerce-admin .instructions').remove();
		
		var template = jQuery('.pdf-forms-for-woocommerce-admin .pdf-attachment-row-template');
		var tag = template.clone().removeClass('pdf-attachment-row-template').addClass('pdf-attachment-row');
		
		tag.find('.pdf-filename').text('['+attachment_id+'] '+filename);
		tag.find('.pdf-options input, .pdf-options select').data('attachment_id', attachment_id);
		
		if(typeof options != 'undefined' && options !== null)
		{
			tag.find('.pdf-options input[type=checkbox]').each(function() {
				var option = jQuery(this).data('option');
				jQuery(this)[0].checked = (options[option] !== false);
			});
			tag.find('.pdf-options input[type=text]').each(function() {
				var option = jQuery(this).data('option');
				jQuery(this).val(options[option]);
			});
			tag.find('.pdf-options select.email-templates-list').initializeMultipleSelect2Field('emailTemplates', options['email_templates']);
			tag.find('.pdf-options select.downloads-list').each(function() {
				jQuery(this).select2({
					ajax: {},
					width: '100%',
					sharedDataElement: "downloads",
					dropdownParent: jQuery('#pdf-forms-for-woocommerce-product-settings'),
					dataAdapter: jQuery.fn.select2.amd.require("pdf-forms-for-woocommerce-shared-data-adapter")
				});
				var option = jQuery(this).data('option');
				
				var index = 0;
				for(var i=0, l=select2SharedData.downloads.length; i<l; i++)
					if(select2SharedData.downloads[i].id == options[option])
					{
						index = i;
						break;
					}
				jQuery(this).resetSelect2Field(index);
			});
			
			// set unique ids
			tag.find('.pdf-option-save-directory label').attr('for', 'pdf-option-save-directory-'+attachment_id);
			tag.find('.pdf-option-save-directory input.placeholders').attr('id', 'pdf-option-save-directory-'+attachment_id);
			tag.find('.pdf-option-filename label').attr('for', 'pdf-option-filename-'+attachment_id);
			tag.find('.pdf-option-filename input.placeholders').attr('id', 'pdf-option-filename-'+attachment_id);
		}
		
		tag.find('.pdf-options input[type=checkbox]').change(function() {
			var attachment_id = jQuery(this).data('attachment_id');
			var option = jQuery(this).data('option');
			setAttachmentOption(attachment_id, option, jQuery(this)[0].checked);
		});
		tag.find('.pdf-options input[type=text], .pdf-options select').on("input change", function() {
			var attachment_id = jQuery(this).data('attachment_id');
			var option = jQuery(this).data('option');
			setAttachmentOption(attachment_id, option, jQuery(this).val());
		});
		tag.find('.pdf-options-button').click(function() {
			
			// prevent running default button click handlers
			event.stopPropagation();
			event.preventDefault();
			
			jQuery(this).closest('.pdf-attachment-row').find('.pdf-options').toggle('.pdf-options-hidden');
		});
		
		var delete_button = tag.find('.delete-button');
		delete_button.data('attachment_id', attachment_id);
		delete_button.click(function(event) {
			
			// prevent running default button click handlers
			event.stopPropagation();
			event.preventDefault();
			
			if(!confirm(pdf_forms_for_woocommerce.__Confirm_Delete_Attachment))
				return;
			
			var attachment_id = jQuery(this).data('attachment_id');
			if(!attachment_id)
				return false;
			
			deleteAttachment(attachment_id);
			
			tag.remove();
			
			jQuery('.pdf-forms-for-woocommerce-admin .pdf-files-list option[value='+attachment_id+']').remove();
			
			return false;
		});
		
		jQuery('.pdf-forms-for-woocommerce-admin .pdf-attachments tr.pdf-buttons').before(tag);
		// TODO: remove item when attachment is deleted
		// better TODO: use shared list (attachmentData)
		select2SharedData.pdfSelect2Files.push({
			id: attachment_id,
			text: '[' + attachment_id + '] ' + filename,
			lowerText: String('[' + attachment_id + '] ' + filename).toLowerCase()
		});
		
		refreshPdfFilesList();
		
		jQuery('.pdf-forms-for-woocommerce-admin .help-button').each(function() {
			var button = jQuery(this);
			var helpbox = button.parent().find('.helpbox');
			hideHelp(button, helpbox);
		});
	};
	
	var preloadData = function()
	{
		if(!post_id)
			return errorMessage(pdf_forms_for_wpforms.__No_Post_ID);
		
		// get initial form data
		var data_json = data_tag.val();
		var data = {};
		if(data_json)
		try { data = JSON.parse(data_json); }
		catch(e) { errorMessage(e.message); }
		var preload_data_json = preload_data_tag.text();
		var preload_data = {};
		if(preload_data_json)
			preload_data = JSON.parse(preload_data_json);
		
		if((typeof data != 'object' || data === null)
		|| (typeof preload_data != 'object' || preload_data === null))
			return errorMessage(pdf_forms_for_woocommerce.__No_Preload_Data);
		
		// load email templates
		if(preload_data.hasOwnProperty('email_templates'))
			select2SharedData.emailTemplates = preload_data.email_templates;
		
		// load woocommerce fields
		if(preload_data.hasOwnProperty('woocommerce_placeholders'))
		{
			select2SharedData.woocommercePlaceholders = preload_data.woocommerce_placeholders;
			jQuery('.pdf-forms-for-woocommerce-admin .woo-placeholder-list').resetSelect2Field();
		}
		
		// load information about product downloads
		if(preload_data.hasOwnProperty('downloads'))
			select2SharedData.downloads = preload_data.downloads;
		
		// load default PDF options
		if(preload_data.hasOwnProperty('default_pdf_options'))
			defaultPdfOptions = preload_data.default_pdf_options;
		
		// load information about attached PDFs
		if(preload_data.hasOwnProperty('attachments'))
			jQuery.each(preload_data.attachments, function(index, attachment) {
				setAttachmentData(attachment.attachment_id, attachment);
			});
		
		if(data.hasOwnProperty('attachments'))
			jQuery.each(data.attachments, function(index, data) {
				addAttachment(data);
			});
		
		if(data.hasOwnProperty('mappings'))
		{
			jQuery.each(data.mappings, function(index, mapping) {
				addMapping(mapping);
			});
			refreshMappings();
		}
		
		if(data.hasOwnProperty('value_mappings'))
		{
			var mappings = getMappings();
			jQuery.each(data.value_mappings, function(index, value_mapping) {
				
				// find mapping id
				for(var i=0, l=mappings.length; i<l; i++)
				{
					if(mappings[i].pdf_field == value_mapping.pdf_field)
					{
						value_mapping.mapping_id = mappings[i].mapping_id;
						break;
					}
				}
				
				if(!value_mapping.hasOwnProperty('mapping_id'))
					return;
				
				addValueMapping(value_mapping);
			});
		}
		
		if(data.hasOwnProperty('embeds'))
		{
			jQuery.each(data.embeds, function(index, embed) { if(embed.id && embed_id_autoinc < embed.id) embed_id_autoinc = embed.id; });
			jQuery.each(data.embeds, function(index, embed) { addEmbed(embed); });
		}
	};
	
	var getMappings = function()
	{
		var mappings = getData('mappings');
		if(mappings)
			return mappings;
		else
			return [];
	};
	
	var getMapping = function(id)
	{
		var mappings = getMappings();
		for(var i=0; i<mappings.length; i++)
			if(mappings[i].mapping_id == id)
				return mappings[i];
		return undefined;
	};
	
	var getValueMappings = function()
	{
		var valueMappings = getData('value_mappings');
		if(valueMappings)
			return valueMappings;
		else
			return [];
	};
	
	var runWhenDoneTimers = {};
	var runWhenDone = function(func)
	{
		if(runWhenDoneTimers[func])
			return;
		runWhenDoneTimers[func] = setTimeout(function(func){ delete runWhenDoneTimers[func]; func(); }, 0, func);
	}
	
	var setMappings = function(mappings)
	{
		setData('mappings', mappings);
		runWhenDone(refreshPdfFields);
	};
	
	var deleteMapping = function(mapping_id)
	{
		var mappings = getMappings();
		
		for(var i=0; i<mappings.length; i++)
			if(mappings[i].mapping_id == mapping_id)
			{
				mappings.splice(i, 1);
				break;
			}
		
		deleteValueMappings(mapping_id);
		setMappings(mappings);
	};
	
	var deleteAllMappings = function()
	{
		setMappings([]);
		setValueMappings([]);
		refreshMappings();
	};
	
	var setValueMappings = function(value_mappings)
	{
		setData('value_mappings', value_mappings);
	};
	
	var deleteValueMapping = function(value_mapping_id)
	{
		var value_mappings = getValueMappings();
		
		for(var i=0; i<value_mappings.length; i++)
			if(value_mappings[i].value_mapping_id == value_mapping_id)
			{
				value_mappings.splice(i, 1);
				break;
			}
		
		setValueMappings(value_mappings);
	};
	
	var deleteValueMappings = function(mapping_id)
	{
		var value_mappings = getValueMappings();
		
		for(var i=0; i<value_mappings.length; i++)
			if(value_mappings[i].mapping_id == mapping_id)
				value_mappings.splice(i, 1);
		
		setValueMappings(value_mappings);
		runWhenDone(refreshMappings);
	};
	
	var generateId = function()
	{
		return Math.random().toString(36).substring(2) + Date.now().toString();
	}
	
	var addValueMapping = function(data) {
		
		if(typeof data.mapping_id == 'undefined'
		|| typeof data.pdf_field == 'undefined'
		|| typeof data.woo_value == 'undefined'
		|| typeof data.pdf_value == 'undefined')
			return;
		
		data.value_mapping_id = generateId();
		pluginData["value_mappings"].push(data);
		
		runWhenDone(updatePluginDataField);
		
		addValueMappingEntry(data);
	};
	
	var addValueMappingEntry = function(data) {
		
		var mapping = getMapping(data.mapping_id);
		
		var pdfField = getPdfFieldData(data.pdf_field);
		
		var template = jQuery('.pdf-forms-for-woocommerce-admin .pdf-mapping-row-valuemapping-template');
		var tag = template.clone().removeClass('pdf-mapping-row-valuemapping-template').addClass('pdf-valuemapping-row');
		tag.data('mapping_id', data.mapping_id);
		
		tag.find('input').data('value_mapping_id', data.value_mapping_id);
		
		if(typeof pdfField == 'object' && pdfField !== null && pdfField.hasOwnProperty('options') && ((Array.isArray(pdfField.options) && pdfField.options.length > 0) || (typeof pdfField.options == 'object' && Object.values(pdfField.options).length > 0)))
		{
			var input = tag.find('input.pdf-value');
			var select = jQuery('<select>');
			select.insertAfter(input);
			input.hide();
			
			var options = [];
			var add_custom = true;
			jQuery.each(pdfField.options, function(i, option) {
				var text;
				if(typeof option == 'object' && option.hasOwnProperty('value'))
					text = String(option.value);
				else
					text = String(option);
				options.push({ id: text, text: text});
				if(text == data.pdf_value)
					add_custom = false;
			});
			if(add_custom && data.pdf_value != '')
				options.unshift({ id: data.pdf_value, text: data.pdf_value });
			options.unshift({ id: '', text: pdf_forms_for_woocommerce.__Null_Value_Mapping });
			
			select.select2({
				data: options,
				tags: true,
				width: '100%',
				dropdownParent: jQuery('#pdf-forms-for-woocommerce-product-settings')
			});
			
			select.val(data.pdf_value).trigger('change');
			
			select.on("input change", function() {
				jQuery(this).prev().val(jQuery(this).val()).trigger('change');
			});
		}
		
		tag.find('input.woo-value').val(data.woo_value);
		tag.find('input.pdf-value').val(data.pdf_value);
		
		var delete_button = tag.find('.delete-valuemapping-button');
		delete_button.data('value_mapping_id', data.value_mapping_id);
		delete_button.click(function(event) {
			
			// prevent running default button click handlers
			event.stopPropagation();
			event.preventDefault();
			
			if(!confirm(pdf_forms_for_woocommerce.__Confirm_Delete_Mapping))
				return;
			
			deleteValueMapping(jQuery(this).data('value_mapping_id'));
			
			jQuery(this).closest('.pdf-valuemapping-row').remove();
		});
		
		var mappingTag = jQuery('.pdf-forms-for-woocommerce-admin .pdf-mapping-row[data-mapping_id="'+data.mapping_id+'"]');
		tag.insertAfter(mappingTag);
	};
	
	var addMapping = function(data)
	{
		if(!data.hasOwnProperty('placeholders'))
			return;
		
		data.mapping_id = generateId();
		pluginData["mappings"].push(data);
		
		runWhenDone(updatePluginDataField);
		runWhenDone(refreshPdfFields);
		
		addMappingEntry(data);
		
		return data.mapping_id;
	};
	
	var addMappingEntry = function(data)
	{
		var pdf_field_data = getPdfFieldData(data.pdf_field);
		var pdf_field_caption;
		if(pdf_field_data)
			pdf_field_caption = pdf_field_data.text;
		else
		{
			var field_id = data.pdf_field.substr(data.pdf_field.indexOf('-')+1);
			pdf_field_caption = base64urldecode(field_id);
		}
		
		var template = jQuery('.pdf-forms-for-woocommerce-admin .pdf-mapping-row-template');
		var tag = template.clone().removeClass('pdf-mapping-row-template').addClass('pdf-mapping-row');
		
		// set unique id
		tag.find('label').attr('for', 'mapping-placeholder-'+data.mapping_id);
		tag.find('textarea.placeholders').attr('id', 'mapping-placeholders-'+data.mapping_id);
		
		tag.find('textarea.placeholders').val(data.placeholders).data('mapping_id', data.mapping_id);
		tag.find('.pdf-field-name').text(pdf_field_caption);
		
		tag.attr('data-mapping_id', data.mapping_id);
		
		var delete_button = tag.find('.delete-mapping-button');
		delete_button.data('mapping_id', data.mapping_id);
		delete_button.click(function(event) {
			
			// prevent running default button click handlers
			event.stopPropagation();
			event.preventDefault();
			
			if(!confirm(pdf_forms_for_woocommerce.__Confirm_Delete_Mapping))
				return;
			
			deleteMapping(jQuery(this).data('mapping_id'));
			
			jQuery(this).closest('.pdf-mapping-row').remove();
			
			var mappings = getMappings();
			if(mappings.length==0)
				jQuery('.pdf-forms-for-woocommerce-admin .delete-all-row').hide();
		});
		
		var map_value_button = tag.find('.map-value-button');
		map_value_button.data('mapping_id', data.mapping_id);
		map_value_button.click(function(event) {
			
			// prevent running default button click handlers
			event.stopPropagation();
			event.preventDefault();
			
			addValueMapping({'mapping_id': data.mapping_id, 'pdf_field': data.pdf_field, 'woo_value': "", 'pdf_value': ""});
		});
		
		tag.insertBefore(jQuery('.pdf-forms-for-woocommerce-admin .pdf-fields-mapper .delete-all-row'));
		jQuery('.pdf-forms-for-woocommerce-admin .delete-all-row').show();
	};
	
	var refreshMappings = function()
	{
		jQuery('.pdf-forms-for-woocommerce-admin .pdf-mapping-row').remove();
		jQuery('.pdf-forms-for-woocommerce-admin .pdf-valuemapping-row').remove();
		
		var mappings = getMappings();
		for(var i=0; i<mappings.length; i++)
			addMappingEntry(mappings[i]);
		
		var value_mappings = getValueMappings();
		for(var i=0; i<value_mappings.length; i++)
			addValueMappingEntry(value_mappings[i]);
		
		if(mappings.length==0)
			jQuery('.pdf-forms-for-woocommerce-admin .delete-all-row').hide();
		else
			jQuery('.pdf-forms-for-woocommerce-admin .delete-all-row').show();
	};
	
	var getEmbeds = function()
	{
		var embeds = getData('embeds');
		if(embeds)
			return embeds;
		else
			return [];
	};
	
	var setEmbeds = function(embeds)
	{
		setData('embeds', embeds);
	};
	
	var embed_id_autoinc = 0;
	var addEmbed = function(embed)
	{
		if(!embed.hasOwnProperty('placeholders'))
			return;
		
		var attachment_id = embed.attachment_id;
		var page = embed.page;
		
		if(!attachment_id || !page || (page != 'all' && page < 0))
			return;
		
		var attachment = null;
		if(attachment_id != 'all')
		{
			attachment = getAttachment(attachment_id);
			if(!attachment)
				return;
		}
		
		if(!embed.id)
			embed.id = ++embed_id_autoinc;
		
		var embeds = getEmbeds();
		embeds.push(embed);
		setEmbeds(embeds);
		
		if(embed.hasOwnProperty('placeholders'))
			addEmbedEntry({placeholders: embed.placeholders, attachment: attachment, embed: embed});
	};
	
	var refreshEmbeds = function()
	{
		jQuery('.pdf-forms-for-woocommerce-admin .image-embeds-row').remove();
		
		var embeds = getEmbeds();
		for(var i=0, l=embeds.length; i<l; i++)
		{
			var embed = embeds[i];
			
			var attachment = null;
			if(embed.attachment_id != 'all')
			{
				attachment = getAttachment(embed.attachment_id);
				if(!attachment)
					continue;
			}

			if(embed.hasOwnProperty('placeholders'))
				addEmbedEntry({placeholders: embed.placeholders, attachment: attachment, embed: embed});
		}
	};
	
	var addEmbedEntry = function(data)
	{
		var page = data.embed.page;
		
		if(data.hasOwnProperty('placeholders'))
		{
			var template = jQuery('.pdf-forms-for-woocommerce-admin .image-embeds-row-template');
			var tag = template.clone().removeClass('image-embeds-row-template').addClass('image-embeds-row');
			
			// set unique id
			tag.find('label').attr('for', 'embed-placeholders-'+data.embed.id);
			tag.find('textarea.placeholders').attr('id', 'embed-placeholders-'+data.embed.id);
			
			tag.find('textarea.placeholders').text(data.placeholders);
			tag.find('textarea.placeholders').data('embed_id', data.embed.id);
		}
		
		tag.attr('data-embed_id', data.embed.id);
		
		var delete_button = tag.find('.delete-embed-button');
		delete_button.data('embed_id', data.embed.id);
		delete_button.click(function(event) {
			
			// prevent running default button click handlers
			event.stopPropagation();
			event.preventDefault();
			
			if(!confirm(pdf_forms_for_woocommerce.__Confirm_Delete_Embed))
				return;
			
			deleteEmbed(jQuery(this).data('embed_id'));
			
			tag.remove();
			
			return false;
		});
		
		var pdf_name = pdf_forms_for_woocommerce.__All_PDFs;
		if(data.hasOwnProperty('attachment') && data.attachment)
		{
			var attachment_id = data.attachment.attachment_id;
			pdf_name = '[' + attachment_id + ']';
			var info = getAttachmentData(attachment_id);
			if(typeof info == 'object' && info.hasOwnProperty('filename'))
				pdf_name += ' ' + info.filename;
		}
		
		tag.find('.pdf-file-caption').text(pdf_name);
		tag.find('.page-caption').text(page > 0 ? page : pdf_forms_for_woocommerce.__All_Pages);
		
		if(data.hasOwnProperty('attachment') && data.attachment && page > 0)
			loadPageSnapshot(data.attachment, data.embed, tag);
		else
			tag.find('.page-selector-row').addBack('.page-selector-row').hide();
		
		jQuery('.pdf-forms-for-woocommerce-admin .image-embeds tbody').append(tag);
	};
	
	var deleteEmbed = function(embed_id)
	{
		var embeds = getEmbeds();
		
		for(var i=0, l=embeds.length; i<l; i++)
			if(embeds[i].id == embed_id)
			{
				embeds.splice(i, 1);
				break;
			}
		
		setEmbeds(embeds);
	};
	
	var loadPageSnapshot = function(attachment, embed, tag)
	{
		var info = getAttachmentInfo(attachment.attachment_id);
		if(!info)
			return;
		
		var pages = info.pages;
		var pageData = null;
		for(var p=0;p<pages.length;p++)
		{
			if(pages[p].number == embed.page)
			{
				pageData = pages[p];
				break;
			}
		}
		if(!pageData || !pageData.width || !pageData.height)
			return;
		
		jQuery.ajax({
			url: pdf_forms_for_woocommerce.ajax_url,
			type: 'POST',
			data: {
				'action': 'pdf_forms_for_woocommerce_query_page_image',
				'attachment_id': attachment.attachment_id,
				'page': embed.page,
				'nonce': pdf_forms_for_woocommerce.ajax_nonce
			},
			cache: false,
			dataType: 'json',
			
			success: function(data, textStatus, jqXHR) {
				
				if(!data.success)
					return errorMessage(data.error_message);
				
				if(data.hasOwnProperty('snapshot'))
				{
					var width = 700;
					var height = Math.round((pageData.height / pageData.width) * width);
					
					var container = tag.find('.jcrop-container');
					var image = tag.find('.jcrop-page');
					
					var widthStr = width.toString();
					var heightStr = height.toString();
					var widthCss = widthStr + 'px';
					var heightCss = heightStr + 'px';
					
					jQuery(image).attr('width', widthStr).css('width', widthCss);
					jQuery(image).attr('height', heightStr).css('height', heightCss);
					jQuery(container).css('width', widthCss);
					jQuery(container).css('height', heightCss);
					
					var xPixelsPerPoint = width / pageData.width;
					var yPixelsPerPoint = height / pageData.height;
					
					var leftInput = tag.find('input[name=left]');
					var topInput = tag.find('input[name=top]');
					var widthInput = tag.find('input[name=width]');
					var heightInput = tag.find('input[name=height]');
					
					leftInput.attr('max', width / xPixelsPerPoint);
					topInput.attr('max', height / yPixelsPerPoint);
					widthInput.attr('max', width / xPixelsPerPoint);
					heightInput.attr('max', height / yPixelsPerPoint);
					
					var updateEmbedCoordinates = function(x, y, w, h)
					{
						var embeds = getEmbeds();
						for(var i=0, l=embeds.length; i<l; i++)
							if(embeds[i].id == embed.id)
							{
								embeds[i].left = embed.left = x;
								embeds[i].top = embed.top = y;
								embeds[i].width = embed.width = w;
								embeds[i].height = embed.height = h;
								
								break;
							}
						setEmbeds(embeds);
					};
					
					var updateCoordinates = function(c)
					{
						leftInput.val(Math.round(c.x / xPixelsPerPoint));
						topInput.val(Math.round(c.y / yPixelsPerPoint));
						widthInput.val(Math.round(c.w / xPixelsPerPoint));
						heightInput.val(Math.round(c.h / yPixelsPerPoint));
						
						updateEmbedCoordinates(
							leftInput.val(),
							topInput.val(),
							widthInput.val(),
							heightInput.val()
						);
					};
					
					var jcropApi;
					
					var updateRegion = function() {
						
						var leftValue = parseFloat(leftInput.val());
						var topValue = parseFloat(topInput.val());
						var widthValue = parseFloat(widthInput.val());
						var heightValue = parseFloat(heightInput.val());
						
						if(typeof leftValue == 'number'
						&& typeof topValue == 'number'
						&& typeof widthValue == 'number'
						&& typeof heightValue == 'number')
						{
							jcropApi.setSelect([
								leftValue * xPixelsPerPoint,
								topValue * yPixelsPerPoint,
								(leftValue + widthValue) * xPixelsPerPoint,
								(topValue + heightValue) * yPixelsPerPoint
							]);
							
							updateEmbedCoordinates(
								leftValue,
								topValue,
								widthValue,
								heightValue
							);
						}
					}
					
					jQuery(image).one('load', function() {
						image.Jcrop({
							onChange: updateCoordinates,
							onSelect: updateCoordinates,
							onRelease: updateCoordinates,
							boxWidth: width,
							boxHeight: height,
							trueSize: [width, height],
							minSize: [1, 1]
						}, function() {
							
							jcropApi = this;
							
							if(!embed.left)
								embed.left = Math.round(pageData.width * 0.25);
							if(!embed.top)
								embed.top = Math.round(pageData.height * 0.25);
							if(!embed.width)
								embed.width = Math.round(pageData.width * 0.5);
							if(!embed.height)
								embed.height = Math.round(pageData.height * 0.5);
							
							updateCoordinates({
								x: Math.round(embed.left * xPixelsPerPoint),
								y: Math.round(embed.top * yPixelsPerPoint),
								w: Math.round(embed.width * xPixelsPerPoint),
								h: Math.round(embed.height * yPixelsPerPoint)
							});
							
							updateRegion();
						});
					});
					
					tag.find('input.coordinate').on("input change", updateRegion);
					
					jQuery(image).attr('src', data.snapshot);
				}
				
			},
			
			error: function(jqXHR, textStatus, errorThrown) { return errorMessage(textStatus); },
			
			beforeSend: function() { PdfFormsFillerSpinner.show(); },
			complete: function() { PdfFormsFillerSpinner.hide(); }
			
		});
	};
	
	var refreshPageList = function()
	{
		var pageList = [];
		
		pageList.push({
			id: 0,
			text: pdf_forms_for_woocommerce.__All_Pages,
			lowerText: String(pdf_forms_for_woocommerce.__All_Pages).toLowerCase()
		});
		
		var files = jQuery('.pdf-forms-for-woocommerce-admin .image-embedding-tool .pdf-files-list');
		var info = getAttachmentInfo(files.val());
		
		if(typeof info != 'undefined' && info !== null)
		{
			jQuery.each(info.pages, function(p, page){
				pageList.push({
					id: page.number,
					text: page.number,
					lowerText: String(page.number).toLowerCase()
				});
			});
		}
		
		// TODO: use a new dynamically generated data adapter for better memory efficiency
		select2SharedData.pageList = pageList;
		
		var id = typeof info != 'undefined' && info !== null && info.pages.length > 0 ? 1 : 0;
		jQuery('.pdf-forms-for-woocommerce-admin .page-list').resetSelect2Field(id);
	};
	
	var refreshPdfFilesList = function()
	{
		var id = select2SharedData.pdfSelect2Files.length > 1 ? 1 : null;
		jQuery('.pdf-forms-for-woocommerce-admin .pdf-files-list').resetSelect2Field(id);
	}
	
	var attachPdf = function(file_id)
	{
		jQuery.ajax({
			url: pdf_forms_for_woocommerce.ajax_url,
			type: 'POST',
			data: {
				'action': 'pdf_forms_for_woocommerce_get_attachment_data',
				'post_id': post_id,
				'file_id': file_id,
				'nonce': pdf_forms_for_woocommerce.ajax_nonce,
			},
			cache: false,
			dataType: 'json',
			
			success: function(data, textStatus, jqXHR) {
				
				if(!data.success)
					return errorMessage(data.error_message);
				
				delete data.success;
				
				if(data.hasOwnProperty('attachment_id') && data.hasOwnProperty('info') && data.hasOwnProperty('filename'))
				{
					if(!data.info.hasOwnProperty('fields')
					|| typeof data.info.fields !== 'object'
					|| Object.keys(data.info.fields).length == 0)
						if(!confirm(pdf_forms_for_woocommerce.__Confirm_Attach_Empty_Pdf))
							return;
					setAttachmentData(data.attachment_id, data);
					addAttachment({'attachment_id': data.attachment_id, options: defaultPdfOptions});
				}
			},
			
			error: function(jqXHR, textStatus, errorThrown) { return errorMessage(textStatus); },
			
			beforeSend: function() { PdfFormsFillerSpinner.show(); },
			complete: function() { PdfFormsFillerSpinner.hide(); }
		});
		
		return false;
	};
	
	var showHelp = function(button, helpbox)
	{
		helpbox.show();
		button.text(pdf_forms_for_woocommerce.__Hide_Help);
	}
	
	var hideHelp = function(button, helpbox)
	{
		helpbox.hide();
		button.text(pdf_forms_for_woocommerce.__Show_Help);
	}

	// set up help buttons
	jQuery('.pdf-forms-for-woocommerce-admin').on("click", '.help-button', function(event) {

		// prevent running default button click handlers
		event.stopPropagation();
		event.preventDefault();

		var button = jQuery(this);
		var helpbox = button.parent().find('.helpbox');

		if(helpbox.is(":visible"))
			hideHelp(button, helpbox);
		else
			showHelp(button, helpbox);

		return false;
	});
	
	jQuery('.pdf-forms-for-woocommerce-admin .field-mapping-tool').on("input change", 'input.woo-value', function(event) {
		
		var woo_value = jQuery(this).val();
		var value_mapping_id = jQuery(this).data('value_mapping_id');
		
		var value_mappings = getValueMappings();
		for(var i=0, l=value_mappings.length; i<l; i++)
			if(value_mappings[i].value_mapping_id == value_mapping_id)
			{
				value_mappings[i].woo_value = woo_value;
				break;
			}
		
		setValueMappings(value_mappings);
	});
	
	jQuery('.pdf-forms-for-woocommerce-admin .field-mapping-tool').on("input change", 'input.pdf-value', function(event) {
		
		var pdf_value = jQuery(this).val();
		var value_mapping_id = jQuery(this).data('value_mapping_id');
		
		var value_mappings = getValueMappings();
		for(var i=0, l=value_mappings.length; i<l; i++)
			if(value_mappings[i].value_mapping_id == value_mapping_id)
			{
				value_mappings[i].pdf_value = pdf_value;
				break;
			}
		
		setValueMappings(value_mappings);
	});
	
	jQuery('.pdf-forms-for-woocommerce-admin .image-embedding-tool').on("change", '.pdf-files-list', refreshPageList);
	
	// set up 'Attach a PDF File' button handler
	jQuery('.pdf-forms-for-woocommerce-admin').on('click', '.attach-btn', function (event) {
		
		// prevent running default button click handlers
		event.stopPropagation();
		event.preventDefault();
		
		clearMessages();
		
		// create the pdf frame
		var pdf_frame = wp.media({
			title: pdf_forms_for_woocommerce.__PDF_Frame_Title,
			multiple: false,
			library: {
				order: 'DESC',
				// we can use ['author','id','name','date','title','modified','uploadedTo','id','post__in','menuOrder']
				orderby: 'date',
				type: 'application/pdf',
				search: null,
				uploadedTo: null
			},
			button: {
				text: pdf_forms_for_woocommerce.__PDF_Frame_Button
			}
		});
		// callback on the pdf frame
		pdf_frame.on('select', function() {
			var attachment = pdf_frame.state().get('selection').first().toJSON();
			if(!getAttachmentInfo(attachment.id))
				attachPdf(attachment.id);
		});
		pdf_frame.open();
	});
	
	// set up 'Add Mapping' button handler
	jQuery('.pdf-forms-for-woocommerce-admin').on('click', '.add-mapping-button', function(event) {
		
		// prevent running default button click handlers
		event.stopPropagation();
		event.preventDefault();
		
		clearMessages();
		
		let tag = jQuery('.pdf-forms-for-woocommerce-admin .pdf-fields-mapper');
		
		let placeholder_id = tag.find('.woo-placeholder-list').val();
		let placeholder = tag.find('.woo-placeholder-list').find('option:selected').text();
		let pdf_field = tag.find('.pdf-field-list').val();
		
		if(pdf_field && placeholder)
			addMapping({
				placeholders: placeholder,
				pdf_field: pdf_field,
			});
		
		return false;
	});
	
	// set up 'Delete All Mappings' button handler
	jQuery('.pdf-forms-for-woocommerce-admin').on('click', '.delete-all-mappings-button', function(event) {
		
		// prevent running default button click handlers
		event.stopPropagation();
		event.preventDefault();
		
		clearMessages();
		
		if(!confirm(pdf_forms_for_woocommerce.__Confirm_Delete_All_Mappings))
			return;
		
		deleteAllMappings();
		
		return false;
	});
	
	// set up 'Embed Image' button handler
	jQuery('.pdf-forms-for-woocommerce-admin').on('click', '.add-woo-placeholder-embed-button', function(event) {
		
		// prevent running default button click handlers
		event.stopPropagation();
		event.preventDefault();
		
		clearMessages();
		
		let tag = jQuery('.pdf-forms-for-woocommerce-admin .image-embedding-tool');
		
		let placeholder_id = tag.find('.woo-placeholder-list').val();
		let placeholder = tag.find('.woo-placeholder-list').find('option:selected').text();
		let attachment_id = tag.find('.pdf-files-list').val();
		if(attachment_id == 0)
			attachment_id = 'all';
		let page = tag.find('.page-list').val();
		if(page == 0)
			page = 'all';
		
		if(placeholder && attachment_id && page)
		{
			addEmbed({
				placeholders: placeholder,
				attachment_id: attachment_id,
				page: page
			});
		}
		
		return false;
	});
	
	jQuery('.pdf-forms-for-woocommerce-admin .field-mapping-tool').on("input change", 'textarea.placeholders', function(event) {
		
		var placeholders = jQuery(this).val();
		var mapping_id = jQuery(this).data('mapping_id');
		
		var mappings = getMappings();
		jQuery.each(mappings, function(index, mapping) {
			if(mapping.mapping_id == mapping_id)
			{
				mappings[index].placeholders = placeholders;
				return false; // break
			}
		});
		
		setMappings(mappings);
	});
	
	jQuery('.pdf-forms-for-woocommerce-admin .image-embedding-tool').on("input change", "textarea.placeholders", function(event) {
		
		var placeholders = jQuery(this).val();
		var embed_id = jQuery(this).data('embed_id');
		
		var embeds = getEmbeds();
		jQuery.each(embeds, function(index, embed) {
			if(embed.id == embed_id)
			{
				embeds[index].placeholders = placeholders;
				return false; // break
			}
		});
		
		setEmbeds(embeds);
	});
	
	// auto-resizing textareas
	jQuery('.pdf-forms-for-woocommerce-admin').on("input change focus", "textarea.placeholders", function() {
		if(this.scrollHeight > this.clientHeight)
		{
			this.style.height = 'auto';
			this.style.height = (this.scrollHeight) + 'px';
		}
	});
	
	preloadData();
	
});
