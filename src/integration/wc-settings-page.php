<?php
	
	if( ! defined( 'ABSPATH' ) )
		return;
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Settings_Page' ) )
	{
		class Pdf_Forms_For_WooCommerce_Settings_Page extends WC_Settings_Page
		{
			private static $instance = null;
			private $sections = array();
			
			public function __construct()
			{
				$this->id = 'pdf-forms-for-woocommerce-settings';
				$this->label = __( 'PDF Forms', 'pdf-forms-for-woocommerce' );
				
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
			 * @return array( key => array( id => id, title => title, settings => settings ) )
			 */
			public function add_section( $section )
			{
				$id = $section['id'];
				if( count( $this->sections ) == 0 )
					$id = '';
				$this->sections[$id] = $section;
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
			 * Output the sections list
			 */
			public function output_sections()
			{
				global $current_section;
				
				$sections = $this->get_sections();
				
				if( empty( $sections ) || 1 === sizeof( $sections ) )
					return;
				
				$array_keys = array_keys( $sections );
				
				echo '<ul class="subsubsub">';
				foreach ( $sections as $id => $label )
					echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
				echo '</ul><br class="clear" />';
			}
			
			/**
			 * Get settings array
			 *
			 * @return array
			 */
			public function get_settings()
			{
				global $current_section;
				
				if( count( $this->sections ) == 0 )
					return array();
				
				if( isset( $this->sections[$current_section] ) )
					$settings = $this->sections[$current_section];
				else
					$settings = reset( $this->sections );
				$settings = $settings['settings'];
				
				return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
			}
			
			/**
			 * Output the settings
			 */
			public function output()
			{
				$settings = $this->get_settings();
				WC_Admin_Settings::output_fields( $settings );
			}
			
			public function output_custom_html( $value )
			{
				print( $value['html'] );
			}
			
			public function save()
			{
				$settings = $this->get_settings();
				WC_Admin_Settings::save_fields( $settings );
			}
		}
	}
