<?php
	
	if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Placeholder_Processor', false ) )
	{
		class Pdf_Forms_For_WooCommerce_Placeholder_Processor
		{
			private $email;
			private $order;
			private $order_item;
			private $product_id;
			private static $placeholders;
			
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
			 * Returns email placeholders
			 */
			private static function get_email_placeholders()
			{
				return array(
					'blogname' => array( 'key' => 'blogname' ),
					'site_title' => array( 'key' => 'site_title' ),
					'site_address' => array( 'key' => 'site_address' ),
					'site_url' => array( 'key' => 'site_url' ),
					
					'order_number' => array( 'key' => 'order_number' ),
					'order_date' => array( 'key' => 'order_date' ),
					'order_billing_full_name' => array( 'key' => 'order_billing_full_name', 'email_templates' => array( 'cancelled_order' ) ),
				);
			}
			
			/**
			 * Returns order placeholders
			 */
			private static function get_order_placeholders()
			{
				$placeholders = array();
				
				foreach( WC()->checkout()->get_checkout_fields() as $section )
					foreach( $section as $field_key => $field_args )
						$placeholders[$field_key] = array( 'key' => $field_key, 'label' => $field_args['label'] );
				
				// order_comments is actually customer_note
				if( isset( $placeholders['order_comments'] ) )
					$placeholders['customer_note'] = array( 'key' => 'customer_note', 'label' => $placeholders['order_comments']['label'] );
				
				// enumerate order getters
				$order = new WC_Order();
				$methods = get_class_methods( $order );
				foreach( $methods as $method )
					if( 'get_' == substr( $method, 0, 4 ) )
						if( self::is_callable_for_placeholder( $order, $method ) )
						{
							$key = substr( $method, 4 );
							if( ! isset( $placeholders[$key] ) )
								$placeholders[$key] = array( 'key' => $key );
						}
				
				return $placeholders;
			}
			
			/**
			 * Returns order item placeholders
			 */
			private static function get_order_item_placeholders()
			{
				$placeholders = array();
				
				// gather meta keys of items in database
				global $wpdb;
				$meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->prefix}woocommerce_order_itemmeta" );
				// check output for errors
				if( empty( $wpdb->last_error ) )
					foreach( $meta_keys as $meta_key )
					{
						$key = 'order_item_meta:' . $meta_key;
						$placeholders[$key] = array( 'key' => $key );
					}
				
				return $placeholders;
			}
			
			/**
			 * Returns possible woocommerce placeholders after caching them
			 */
			public function get_placeholders()
			{
				if( ! self::$placeholders )
				{
					self::$placeholders = array_merge(
						self::get_order_item_placeholders(),
						self::get_order_placeholders(),
						self::get_email_placeholders()
					);
					ksort( self::$placeholders );
				}
				
				return self::$placeholders;
			}
			
			/**
			 * Replaces placeholders in content with their values
			 */
			public function process( $content )
			{
				$content = $this->process_email_placeholders( $content );
				$content = $this->process_order_placeholders( $content );
				$content = $this->process_order_meta( $content );
				$content = $this->process_order_item_meta( $content );
				$content = preg_replace( '/\{[^\}]+\}/', '', $content ); // replace any unmatched placeholders with an empty string
				return $content;
			}
			
			/**
			 * Replaces email placeholders in content with their values
			 */
			private function process_email_placeholders( $content )
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
			 * Replaces order placeholders in content with their values
			 */
			private function process_order_placeholders( $content )
			{
				if( is_a( $this->order, 'WC_Order' ) )
				{
					$content = preg_replace_callback(
						'/\{([^\}:]+)\}/',
					 	function( $matches )
					 	{
							$field_key = $matches[1];
							
							$value = null;
							
							// try to use a getter
							$getter = 'get_' . ltrim( $field_key, '_' );
							if( self::is_callable_for_placeholder( $this->order, $getter ) )
								$value = $this->order->$getter();
							
							// order_comments getter is actually get_customer_note
							if( $value === null && $field_key == 'order_comments' )
								$value = $this->order->get_customer_note();
							
							// try to use a meta key getter
							if( $value === null )
							{
								$value = @$this->order->get_meta( $field_key );
								// $value will be '' if the meta key does not exist, set it back to null
								if( $value === '' )
									$value = null;
							}
							
							// try to use a meta key getter with an underscore prefix
							if( $value === null )
							{
								$value = @$this->order->get_meta( '_' . $field_key );
								// $value will be '' if the meta key does not exist, set it back to null
								if( $value === '' )
									$value = null;
							}
							
							// if the value is still null, the placeholder cannot be handled by this function, so we will keep it unchanged
							if( $value === null )
								$value = $matches[0];
							
							$value = self::value_to_string( $value );
							
							return $value;
					 	},
					 	$content
					);
				}
				
				return $content;
			}
			
			private function process_order_meta( $content )
			{
				if( is_a( $this->order, 'WC_Order' ) )
				{
					$content = preg_replace_callback(
						// TODO: make this work with escape sequences for curly braces in meta key
						'/\{order_meta:([^\}]+)\}/',
						function( $matches )
						{
							$key = trim( $matches[1] );
							$value = @$this->order->get_meta( $key );
							
							// $value will be '' if the meta key does not exist, then the placeholder cannot be handled by this function, so we will keep it unchanged
							if( $value === '' )
								$value = $matches[0];
							
							$value = self::value_to_string( $value );
							
							return $value;
						},
						$content
					);
				}
				
				return $content;
			}
			
			private function process_order_item_meta( $content )
			{
				if( is_a( $this->order_item, 'WC_Order_Item_Product' ) )
				{
					$content = preg_replace_callback(
						// TODO: make this work with escape sequences for curly braces in meta key
						'/\{order_item_meta:([^\}]+)\}/',
						function( $matches )
						{
							$key = trim( $matches[1] );
							$value = wc_get_order_item_meta( $this->order_item->get_id(), $key );
							$value = self::value_to_string( $value );
							return $value;
						},
						$content
					);
				}
				
				return $content;
			}
			
			/**
			 * Converts an array to a string for placeholder replacement
			 */
			private static function array_to_string( $value, $separator = ', ' )
			{
				if( is_array( $value ) )
				{
					$result = '';
					
					foreach( $value as $k => $v )
						$result .= self::value_to_string( $v, $separator ) . $separator;
					
					return '[' . rtrim( $result, $separator ) . ']';
				}
				
				return $value;
			}
			
			/**
			 * Converts an object to a string for placeholder replacement
			 */
			private static function object_to_string( $value )
			{
				if( is_object( $value ) )
				{
					// attempt sensible conversion to string
					if( is_a( $value, 'WC_Meta_Data' ) )
						$value = self::value_to_string( $value->get_data() );
					else if( is_callable( array( $value, '__toString' ) ) )
						$value = @$value->__toString();
					else if( is_callable( array( $value, 'get_name' ) ) )
						$value = @$value->get_name();
					else
						$value = get_class( $value );
				}
				
				return $value;
			}
			
			/**
			 * Converts a value to a string for placeholder replacement
			 */
			private static function value_to_string( $value )
			{
				if( is_array( $value ) )
					return self::array_to_string( $value );
				else if( is_object( $value ) )
					return self::object_to_string( $value );
				else
					return strval( $value );
			}
			
			/**
			 * Checks if a method is callable for placeholder replacement
			 */
			private static function is_callable_for_placeholder( $object, $method )
			{
				return is_callable( array( $object, $method ) )
					// method must have no required parameters
					&& empty( array_filter( ( new ReflectionMethod( $object, $method ) )->getParameters(), function( $param ) { return !$param->isOptional(); } ) )
					// downloads will cause an infinite loop because it will cause fill_pdfs to be called, disallow it
					&& ( $method != 'get_item_downloads' && $method != 'get_downloadable_items' );
			}
		}
	}
