<?php
	
	if( ! defined( 'ABSPATH' ) )
		return;
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Variable_Processor' ) )
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
				if( ! $this->email )
					return $content;
				
				// this will process {blogname} (hardcoded in format_string),
				// {site_title}, {site_address}, {site_url} (hardcoded in class WC_Email)
				// and tags from woocommerce_email_format_string_find and woocommerce_email_format_string_replace filters
				return $this->email->format_string( $content );
			}
			
			/**
			 * Replaces order variables in content with their values
			 */
			private function process_order_variables( $content )
			{
				if( $this->order )
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
				if( $this->order_item )
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
