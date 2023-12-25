<?php
	
	if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Settings_Page', false ) )
	{
		class Pdf_Forms_For_WooCommerce_Settings_Page extends WC_Settings_Page
		{
			private static $instance = null;
			private $sections = array();
			
			public function __construct()
			{
				$this->id = 'pdf-forms-for-woocommerce-settings';
				$this->label = __( 'PDF Forms Filler', 'pdf-forms-for-woocommerce' );
				
				parent::__construct();
				
				add_action( 'woocommerce_admin_field_pdf-forms-for-woocommerce-setting-html', array( $this, 'output_custom_html' ) );
			}
			
			/**
			* Returns a global instance of this class
			*/
			public static function get_instance()
			{
				if ( empty( self::$instance ) )
					self::$instance = new self;
				
				return self::$instance;
			}
			
			/**
			 * Add section to the settings page
			 * 
			 * @param array( key => array( id => id, title => title, settings => settings ) ) $section Section data
			 */
			public function add_section( $section )
			{
				$this->sections[$section['id']] = $section;
			}
			
			/**
			 * Get sections
			 * 
			 * @return array(key => title)
			 */
			public function get_sections()
			{
				$sections = array();
				foreach( $this->sections as $key => $section )
					$sections[$key] = $section['title'];
				return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
			}
			
			/**
			 * Get settings array
			 * 
			 * @param string $section Section ID
			 * @return array
			 */
			public function get_settings( $section = '' )
			{
				if( isset( $this->sections[$section] ) )
					$settings = $this->sections[$section];
				else
					$settings = reset( $this->sections );
				$settings = $settings['settings'];
				
				return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $section );
			}
			
			/**
			 * Used to output raw html options
			 * 
			 * @param array
			 */
			public function output_custom_html( $value )
			{
				if( is_array( $value ) && isset( $value['callback'] ) && is_callable( $value['callback'] ) )
					call_user_func( $value['callback'] );
			}
		}
	}
