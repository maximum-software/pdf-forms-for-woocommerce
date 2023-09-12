<?php
	
	// TODO: change 'variable' to 'placeholder' as it is a more widely used term
	
	if( ! defined( 'ABSPATH' ) )
		return;
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Variable_Processor', false ) )
	{
		class Pdf_Forms_For_WooCommerce_Variable_Processor
		{
			private $email;
			private $order;
			private $order_item;
			private $product_id;
			private static $checkout_fields;
			private static $variables;
			
			/**
			 * Sets current email
			 */
			public function set_email( $email )
			{
				$this->email = $email;
				return $this;
			}
			
			/**
			 * Returns current email
			 */
			public function get_email()
			{
				return $this->email;
			}
			
			/**
			 * Sets current order
			 */
			public function set_order( $order )
			{
				$this->order = $order;
				return $this;
			}
			
			/**
			 * Returns current order
			 */
			public function get_order()
			{
				return $this->order;
			}
			
			/**
			 * Sets current product ID
			 */
			public function set_product_id( $product_id )
			{
				$this->product_id = $product_id;
				return $this;
			}
			
			/**
			 * Returns current product ID
			 */
			public function get_product_id()
			{
				return $this->product_id;
			}
						
			/**
			 * Sets current order item
			 */
			public function set_order_item( $order_item )
			{
				$this->order_item = $order_item;
				return $this;
			}
			
			/**
			 * Returns current order item
			 */
			public function get_order_item()
			{
				return $this->order_item;
			}
			
			/**
			 * Returns checkout fields after caching them
			 */
			private function get_checkout_fields()
			{
				if( ! self::$checkout_fields )
					self::$checkout_fields = WC()->checkout()->get_checkout_fields();
				return self::$checkout_fields;
			}
			
			/**
			 * Returns possible woocommerce variables after caching them
			 */
			public function get_variables()
			{
				if( ! self::$variables )
				{
					self::$variables = array(
						array( 'key' => 'blogname' ),
						array( 'key' => 'site_title' ),
						array( 'key' => 'site_address' ),
						array( 'key' => 'site_url' ),
						
						array( 'key' => 'order_number' ),
						array( 'key' => 'order_date' ),
						array( 'key' => 'order_billing_full_name', 'email_templates' => ['cancelled_order']),
					);
					
					foreach( self::get_checkout_fields() as $section )
						foreach( $section as $field_key => $field_args )
							self::$variables[] = array( 'key' => $field_key, 'label' => $field_args['label'] );
					
					// gather meta keys of items in database
					global $wpdb;
					$meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->prefix}woocommerce_order_itemmeta" );
					// check output for errors
					if( empty( $wpdb->last_error ) )
						foreach( $meta_keys as $meta_key )
							self::$variables[] = array( 'key' => 'order_item_meta:' . $meta_key );
				}
				
				return self::$variables;
			}
			
			/**
			 * Replaces variables in content with their values
			 */
			public function process( $content )
			{
				$content = $this->process_email_variables( $content );
				$content = $this->process_order_variables( $content );
				$content = $this->process_order_item_meta( $content );
				return $content;
			}
			
			/**
			 * Replaces email variables in content with their values
			 */
			private function process_email_variables( $content )
			{
				if( is_a( $this->email, 'WC_Email' ) )
					// this will process {blogname} (hardcoded in format_string),
					// {site_title}, {site_address}, {site_url} (hardcoded in class WC_Email)
					// and tags from woocommerce_email_format_string, woocommerce_email_format_string_find and woocommerce_email_format_string_replace filters
					return $this->email->format_string( $content );
				
				if( is_a( $this->order, 'WC_Order' ) )
				{
					// determine $email_class
					$status = $this->order->get_status();
					// TODO: switch to a better way to determine the email class
					$email_class = 'WC_Email_New_Order';
					switch($status)
					{
						case 'pending':
							$email_class = 'WC_Email_Customer_Pending_Order';
							break;
						case 'processing':
							$email_class = 'WC_Email_Customer_Processing_Order';
							break;
						case 'on-hold':
							$email_class = 'WC_Email_Customer_On_Hold_Order';
							break;
						case 'completed':
							$email_class = 'WC_Email_Customer_Completed_Order';
							break;
						case 'cancelled':
							$email_class = 'WC_Email_Cancelled_Order';
							break;
						case 'refunded':
							$email_class = 'WC_Email_Customer_Refunded_Order';
							break;
						case 'failed':
							$email_class = 'WC_Email_Customer_Failed_Order';
							break;
						case 'checkout-draft':
							$email_class = 'WC_Email_New_Order';
							break;
					}
					
					// create email template corresponds to order status based on $email_id
					$emails = &WC()->mailer()->emails;
					if( isset( $emails[$email_class] ) && is_a( ( $email = $emails[$email_class] ), 'WC_Email' ) )
					{
						$email->object = $this->order;
						$email->recipient = $this->order->get_billing_email();
						
						// we can't trigger() because that will send the email message, so we have to manually set the placeholders that are made available with trigger()
						$placeholders = array(
							'{order_date}' => wc_format_datetime( $this->order->get_date_created() ),
							'{order_number}' => $this->order->get_order_number(),
							'{order_billing_full_name}' => $this->order->get_formatted_billing_full_name(),
						);
						
						$find = array_keys( $placeholders );
						$replace = array_values( $placeholders );
						
						$email->find = array_merge( (array) $email->find, $find );
						$email->replace = array_merge( (array) $email->replace, $replace );
						
						return $email->format_string( $content );
					}
				}
				
				// if the email and order instances are not available, try to replace some placeholders that are possible to replace
				$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
				$blogurl = wp_parse_url( home_url(), PHP_URL_HOST );
				$placeholders = array(
					'{blogname}' => $blogname,
					'{site_title}' => $blogname,
					'{site_address}' => $blogurl,
					'{site_url}' => $blogurl,
				);
				
				$find    = array_keys( $placeholders );
				$replace = array_values( $placeholders );
				
				// TODO: figure out how to run the following filters without having the $email instance
				
				// trigger woocommerce_email_format_string_find and woocommerce_email_format_string_replace filters
				// $find = apply_filters( 'woocommerce_email_format_string_find', $find, $email );
				// $replace = apply_filters( 'woocommerce_email_format_string_replace', $replace, $email );
				
				$content = str_replace( $find, $replace, $content );
				
				// trigger woocommerce_email_format_string filter
				// $content = apply_filters( 'woocommerce_email_format_string', $content, $email );
				
				return $content;
			}
			
			/**
			 * Replaces order variables in content with their values
			 */
			private function process_order_variables( $content )
			{
				if( is_a( $this->order, 'WC_Order' ) )
				{
					$search = array();
					foreach( self::get_checkout_fields() as $section )
						foreach( $section as $field_key => $field_args )
							$search[] = '/{(' . preg_quote( $field_key, '/' ) . ')}/u';
					
					$content = preg_replace_callback(
					 	$search,
					 	function( $matches )
					 	{
							return $this->order->get_meta( '_' . $matches[1], true );
					 	},
					 	$content
					);
				}
				
				return $content;
			}
			
			private static function array_to_string( $value, $separator = ', ' )
			{
				if( is_array( $value ) )
				{
					$result = '';
					
					foreach( $value as $k => $v )
						$result .= self::array_to_string( $v, $separator ) . $separator;
					
					return '[' . rtrim( $result, $separator ) . ']';
				}
				
				return $value;
			}
			
			private function process_order_item_meta( $content )
			{
				if( is_a( $this->order_item, 'WC_Order_Item_Product' ) )
				{
					$content = preg_replace_callback(
						// TODO: make this work with escape sequences for curly braces in meta key
						'/{order_item_meta:([^}]+)}/',
						function( $matches )
						{
							$key = trim( $matches[1] );
							$value = wc_get_order_item_meta( $this->order_item->get_id(), $key, true );
							
							// convert multidimentional arrays to a string
							if( is_array( $value ) )
								$value = self::array_to_string( $value );
							
							return $value;
						},
						$content
					);
				}
				
				return $content;
			}
		}
	}
