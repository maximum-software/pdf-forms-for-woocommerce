<?php
	
	if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Service', false ) )
	{
		abstract class Pdf_Forms_For_WooCommerce_Service
		{
			public function plugin_init() { }
			
			public function register_settings() { }
			
			public function admin_enqueue_scripts( $hook ) { }
			
			public function admin_notices() { }
			
			public function settings_notices() { }
			
			public function api_get_fields( $attachment_id )
			{
				throw new Exception( __( "Missing feature", 'pdf-forms-for-woocommerce' ) );
			}
			
			public function api_get_info( $attachment_id )
			{
				throw new Exception( __( "Missing feature", 'pdf-forms-for-woocommerce' ) );
			}
			
			public function api_image( $destfile, $attachment_id, $page )
			{
				throw new Exception( __( "Missing feature", 'pdf-forms-for-woocommerce' ) );
			}
			
			public function api_fill( $destfile, $attachment_id, $data, $options = array() )
			{
				throw new Exception( __( "Missing feature", 'pdf-forms-for-woocommerce' ) );
			}
			
			public function api_fill_embed( $destfile, $attachment_id, $data, $embeds, $options = array() )
			{
				throw new Exception( __( "Missing feature", 'pdf-forms-for-woocommerce' ) );
			}
		}
	}
