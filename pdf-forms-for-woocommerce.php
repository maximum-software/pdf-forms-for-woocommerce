<?php
/**
 * Plugin Name: PDF Forms Filler for WooCommerce
 * Plugin URI: https://pdfformsfiller.org/
 * Description: Automatically fill PDF forms with WooCommerce orders. Attach filled PDFs to orders and order email notifications.
 * Version: 1.0.0
 * Requires at least: 5.4
 * Requires PHP: 5.5
 * Author: Maximum.Software
 * Author URI: https://maximum.software/
 * Text Domain: pdf-forms-for-woocommerce
 * Domain Path: /languages
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once untrailingslashit( dirname( __FILE__ ) ) . '/src/tgm-config.php';
require_once untrailingslashit( dirname( __FILE__ ) ) . '/src/wrapper.php';

if( ! class_exists( 'Pdf_Forms_For_WooCommerce', false ) )
{
	class Pdf_Forms_For_WooCommerce
	{
		const VERSION = '1.0.0';
		const MIN_WC_VERSION = '5.6.0';
		const MAX_WC_VERSION = '8.0.99';
		private static $BLACKLISTED_WC_VERSIONS = array();
		
		const META_KEY = '_pdf-forms-for-woocommerce-data';
		
		private static $instance = null;
		private $pdf_ninja_service = null;
		private $service = null;
		private $registered_services = false;
		private $tmp_dir = null;
		
		private function __construct()
		{
			add_action( 'admin_notices',  array( $this, 'admin_notices' ) );
			add_action( 'plugins_loaded', array( $this, 'plugin_init' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
		}
		
		/**
		 * Returns a global instance of this class
		 */
		public static function get_instance()
		{
			if( ! self::$instance )
				self::$instance = new self;
			
			return self::$instance;
		}
		
		/**
		 * Runs after all plugins have been loaded
		 */
		public function plugin_init()
		{
			load_plugin_textdomain( 'pdf-forms-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			
			if( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) )
				return;
			
			add_action( 'wp_ajax_pdf_forms_for_woocommerce_get_attachment_data', array( $this, 'wp_ajax_get_attachment_data' ) );
			add_action( 'wp_ajax_pdf_forms_for_woocommerce_query_page_image', array( $this, 'wp_ajax_query_page_image' ) );
			add_action( 'wp_ajax_pdf_forms_for_woocommerce_generate_pdf_ninja_key', array( $this, 'wp_ajax_generate_pdf_ninja_key') );
			
			add_action( 'init', array( $this, 'register_meta' ) );
			add_action( 'admin_menu', array( $this, 'register_services' ) );
			
			add_filter( 'woocommerce_order_status_changed', array( $this, 'process_order_status_change' ), 10, 4 );
			add_filter( 'woocommerce_email_attachments', array( $this, 'attach_pdfs' ), 10, 4 );
			add_action( 'woocommerce_email_sent', array( $this, 'remove_tmp_dir' ), 99, 0 ); // since WC 5.6.0
			
			add_filter( 'woocommerce_get_item_downloads', array( $this, 'woocommerce_get_item_downloads' ), 10, 3 );
			
			add_action( 'before_delete_post', array( $this, 'before_delete_post' ), 1, 2 );
			
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'print_product_data_tab_contents' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ), 99 );
			
			add_filter( 'woocommerce_get_settings_pages', array( $this, 'register_settings' ) );
			
			if( $service = $this->get_service() )
			{
				$service->plugin_init();
				if( $service != $this->pdf_ninja_service )
					$this->pdf_ninja_service->plugin_init();
			}
		}
		
		/*
		 * Registers the meta key for settings
		 */
		public function register_meta()
		{
			register_meta( 'post', self::META_KEY, array(
				'show_in_rest' => false,
				'single' => true,
				'type' => 'array',
				'auth_callback' => '__return_false',
			) );
		}
		
		/**
		 * Add PDF Forms settings page to WooCommerce settings
		 */
		public function register_settings( $settings )
		{
			require_once untrailingslashit( dirname( __FILE__ ) ) . '/src/integration/wc-settings-page.php';
			$settings[] = Pdf_Forms_For_WooCommerce_Settings_Page::get_instance();
			
			if( $service = $this->get_service() )
			{
				$service->register_settings();
				if( $service != $this->pdf_ninja_service )
					$this->pdf_ninja_service->register_settings();
			}
			
			return $settings;
		}
		
		/**
		 * Adds plugin action links
		 */
		public function action_links( $links )
		{
			$links[] = '<a target="_blank" href="https://wordpress.org/support/plugin/pdf-forms-for-woocommerce/">'.esc_html__( "Support", 'pdf-forms-for-woocommerce' ).'</a>';
			return $links;
		}
		
		/**
		 * Prints admin notices
		 */
		public function admin_notices()
		{
			if( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) )
			{
				if( current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' ) )
					echo Pdf_Forms_For_WooCommerce::render_error_notice( 'woocommerce-not-installed', array(
						'label'   => esc_html__( "PDF Forms Filler for WooCommerce Error", 'pdf-forms-for-woocommerce' ),
						'message' => esc_html__( "The required plugin 'WooCommerce' version is not installed!", 'pdf-forms-for-woocommerce' ),
					) );
				return;
			}
			
			if( ! $this->is_wc_version_supported( WC_VERSION ) )
				if( current_user_can( 'update_plugins' ) )
					echo Pdf_Forms_For_WooCommerce::render_warning_notice( 'unsupported-woocommerce-version-'.WC_VERSION, array(
						'label'   => esc_html__( "PDF Forms Filler for WooCommerce Warning", 'pdf-forms-for-woocommerce' ),
						'message' =>
							self::replace_tags(
								esc_html__( "The currently installed version of 'WooCommerce' plugin ({current-woocommerce-version}) may not be supported by the current version of 'PDF Forms Filler for WooCommerce' plugin ({current-plugin-version}), please switch to 'WooCommerce' plugin version {supported-woocommerce-version} or below to ensure that 'PDF Forms Filler for WooCommerce' plugin would work correctly.", 'pdf-forms-for-woocommerce' ),
								array(
									'current-woocommerce-version' => esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : __( "Unknown version", 'pdf-forms-for-woocommerce' ) ),
									'current-plugin-version' => esc_html( self::VERSION ),
									'supported-woocommerce-version' => esc_html( self::MAX_WC_VERSION ),
								)
							),
					) );
			
			if( $service = $this->get_service() )
			{
				$service->admin_notices();
				if( $service != $this->pdf_ninja_service )
					$this->pdf_ninja_service->admin_notices();
			}
		}
		
		/**
		 * Checks if WooCommerce version is supported
		 */
		public function is_wc_version_supported( $version )
		{
			if( version_compare( $version, self::MIN_WC_VERSION ) < 0
			||  version_compare( $version, self::MAX_WC_VERSION ) > 0 )
				return false;
			
			foreach( self::$BLACKLISTED_WC_VERSIONS as $blacklisted_version )
				if( version_compare( $version, $blacklisted_version ) == 0 )
					return false;
			
			return true;
		}
		
		/**
		 * Returns the service module instance
		 */
		public function get_service()
		{
			$this->register_services();
			
			if( ! $this->service )
				$this->set_service( $this->load_pdf_ninja_service() );
			
			return $this->service;
		}
		
		/**
		 * Sets the service module instance
		 */
		public function set_service( $service )
		{
			return $this->service = $service;
		}
		
		/**
		 * Loads and returns the storage module
		 */
		private static function get_storage()
		{
			require_once untrailingslashit( dirname( __FILE__ ) ) . '/src/filesystem/storage.php';
			return Pdf_Forms_For_WooCommerce_Storage::get_instance();
		}
		
		/**
		 * Registers PDF.Ninja service
		 */
		public function register_services()
		{
			if( $this->registered_services )
				return;
			
			require_once untrailingslashit( dirname( __FILE__ ) ) . '/src/services/service.php';
			
			$this->registered_services = true;
			$this->load_pdf_ninja_service();
		}
		
		/**
		 * Loads the Pdf.Ninja service module
		 */
		private function load_pdf_ninja_service()
		{
			if( ! $this->pdf_ninja_service )
			{
				require_once untrailingslashit( dirname( __FILE__ ) ) . '/src/services/pdf-ninja.php';
				$this->pdf_ninja_service = WooCommerce_Pdf_Ninja::get_instance();
			}
			
			return $this->pdf_ninja_service;
		}
		
		/**
		 * Creates a new instance of the variable processor
		 */
		public function get_variable_processor()
		{
			require_once untrailingslashit( dirname( __FILE__ ) ) . '/src/integration/wc-variables-processor.php';
			return new Pdf_Forms_For_WooCommerce_Variable_Processor();
		}
		
		/**
		 * Function for working with metadata
		 */
		public static function get_meta( $post_id, $key )
		{
			$value = get_post_meta( $post_id, "pdf-forms-for-woocommerce-" . $key, $single=true );
			if( $value === '' )
				return null;
			return $value;
		}
		
		/**
		 * Function for working with metadata
		 */
		public static function set_meta( $post_id, $key, $value )
		{
			$oldval = get_post_meta( $post_id, "pdf-forms-for-woocommerce-" . $key, true );
			if( $oldval !== '' && $value === null)
				delete_post_meta( $post_id, "pdf-forms-for-woocommerce-" . $key );
			else
			{
				// wp bug workaround
				// https://developer.wordpress.org/reference/functions/update_post_meta/#workaround
				$fixed_value = wp_slash( $value );
				
				update_post_meta( $post_id, "pdf-forms-for-woocommerce-" . $key, $fixed_value, $oldval );
			}
			return $value;
		}
		
		/**
		 * Function for working with metadata
		 */
		public static function unset_meta( $post_id, $key )
		{
			delete_post_meta( $post_id, "pdf-forms-for-woocommerce-" . $key );
		}
		
		/*
		 * Function for retrieving metadata
		 */
		public static function get_metadata( $post_id, $key = null )
		{
			$data = get_post_meta( $post_id, self::META_KEY, $single=true );
			if( ! is_array( $data ) )
				return null;
			
			if( $key === null)
				return $data;
			
			if( isset( $data[$key] ) )
				return $data[$key];
			else
				return null;
		}
		
		/*
		 * Function for setting metadata
		 * If $key is null, the whole metadata element is removed
		 * If $value is null, the metadata subelement with the given key is removed
		 */
		public static function set_metadata( $post_id, $key = null, $value = null )
		{
			// TODO: fix race condition
			$data = get_post_meta( $post_id, self::META_KEY, $single=true );
			if( ! is_array( $data ) )
				$data = array();
			
			if( $key === null )
				$data = null;
			else
			{
				if( $value === null )
					unset( $data[$key] );
				else
					$data[$key] = $value;
			}
			
			if( empty( $data ) )
				delete_post_meta( $post_id, self::META_KEY );
			else
			{
				// wp bug workaround
				// https://developer.wordpress.org/reference/functions/update_post_meta/#workaround
				$data = wp_slash( $data );
				
				update_post_meta( $post_id, self::META_KEY, $data );
			}
			
			return $value;
		}
		
		public static function unset_metadata( $post_id, $key = null )
		{
			return self::set_metadata( $post_id, $key, null );
		}
		
		/**
		 * Hook that runs on user click 'Get New key' button and gets new pdf-ninja key
		 */
		public function wp_ajax_generate_pdf_ninja_key()
		{
			try
			{
				if( ! check_ajax_referer( 'pdf-forms-for-woocommerce-ajax-nonce', 'nonce', false ) )
					throw new Exception( __( "Nonce mismatch", 'pdf-forms-for-woocommerce' ) );
				
				if( ! current_user_can( 'manage_woocommerce' ) )
					throw new Exception( __( "Permission denied", 'pdf-forms-for-woocommerce' ) );
				
				$email = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : null;
				$email = sanitize_email( $email );
				
				if( ! $email )
					throw new Exception( __( "Invalid email", 'pdf-forms-for-woocommerce' ) );
				
				$service = $this->get_service();
				$service->set_key( $key = $service->generate_key( $email ) );
			}
			catch ( Exception $e )
			{
				return wp_send_json( array (
					'success' => false,
					'error_message' => $e->getMessage()
				) );
			}
			
			wp_send_json_success();
		}
		
		const DEFAULT_PDF_OPTIONS = array(
			'skip_empty' => false,
			'flatten' => false,
			'email_templates' => array( "customer_completed_order" ),
			'filename' => "",
			'save_directory'=> "",
			'download_id' => "",
		);
		
		/**
		 * Returns MIME type of the file
		 */
		public static function get_mime_type( $filepath )
		{
			if( ! is_file( $filepath ) )
				return null;
			
			$mimetype = null;
			
			if( function_exists( 'finfo_open' ) )
			{
				if( version_compare( phpversion(), "5.3" ) < 0 )
				{
					$finfo = finfo_open( FILEINFO_MIME );
					if( $finfo )
					{
						$mimetype = finfo_file( $finfo, $filepath );
						$mimetype = explode( ";", $mimetype );
						$mimetype = reset( $mimetype );
						finfo_close( $finfo );
					}
				}
				else
				{
					$finfo = finfo_open( FILEINFO_MIME_TYPE );
					if( $finfo )
					{
						$mimetype = finfo_file( $finfo, $filepath );
						finfo_close( $finfo );
					}
				}
			}
			
			if( ! $mimetype && function_exists( 'mime_content_type' ) )
				$mimetype = mime_content_type( $filepath );
			
			// fallback
			if( ! $mimetype )
			{
				$type = wp_check_filetype( $filepath );
				if( isset( $type['type'] ) )
					$mimetype = $type['type'];
			}
			
			if( ! $mimetype )
				return 'application/octet-stream';
			
			return $mimetype;
		}
		
		/**
		 * Downloads a file from the given URL and saves the contents to the given filepath, returns mime type via argument
		 */
		private static function download_file( $url, $filepath, &$mimetype = null )
		{
			// if this url points to the site, copy the file directly
			$site_url = trailingslashit( get_site_url() );
			if( substr( $url, 0, strlen( $site_url ) ) == $site_url )
			{
				$path = substr( $url, strlen( $site_url ) );
				$home_path = trailingslashit( realpath( dirname(__FILE__) . '/../../../' ) );
				$sourcepath = realpath( $home_path . $path );
				if( $home_path && $sourcepath && substr( $sourcepath, 0, strlen( $home_path ) ) == $home_path )
					if( is_file( $sourcepath ) )
						if( copy($sourcepath, $filepath) )
						{
							$mimetype = self::get_mime_type( $sourcepath );
							return;
						}
			}
			
			$args = array(
				'compress'    => true,
				'decompress'  => true,
				'timeout'     => 100,
				'redirection' => 5,
				'user-agent'  => 'pdf-forms-for-woocommerce/' . self::VERSION,
			);
			
			$response = wp_remote_get( $url, $args );
			
			if( is_wp_error( $response ) )
				throw new Exception(
					self::replace_tags(
							__( "Failed to download file: {error-message}", 'pdf-forms-for-woocommerce' ),
							array( 'error-message' => $response->get_error_message() )
						)
				);
			
			$mimetype = wp_remote_retrieve_header( $response, 'content-type' );
			
			$body = wp_remote_retrieve_body( $response );
			
			$handle = @fopen( $filepath, 'w' );
			
			if( ! $handle )
				throw new Exception( __( "Failed to open file for writing", 'pdf-forms-for-woocommerce' ) );
			
			fwrite( $handle, $body );
			fclose( $handle );
			
			if( ! is_file( $filepath ) )
				throw new Exception( __( "Failed to create file", 'pdf-forms-for-woocommerce' ) );
		}
		
		/**
		 * Get temporary directory path
		 */
		public static function get_tmp_path()
		{
			$upload_dir = wp_upload_dir();
			$tmp_path = trailingslashit( empty( $upload_dir['error'] && ! empty( $upload_dir['basedir'] ) ) ? $upload_dir['basedir'] : get_temp_dir() );
			
			$dir = trailingslashit( $tmp_path . 'pdf-forms-for-woocommerce' ) . 'tmp';
			
			if( ! is_dir( $dir ) )
			{
				wp_mkdir_p( $dir );
				$index_file = path_join( $dir , 'index.php' );
				if( ! file_exists( $index_file ) )
					file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
			}
			
			return $dir;
		}
		
		/**
		 * Creates a temporary directory
		 */
		public function create_tmp_dir()
		{
			if( ! $this->tmp_dir )
			{
				$dir = trailingslashit( self::get_tmp_path() ) . wp_hash( wp_rand() . microtime() );
				wp_mkdir_p( $dir );
				$this->tmp_dir = trailingslashit( $dir );
			}
			
			return $this->tmp_dir;
		}
		
		/**
		 * Removes a temporary directory
		 */
		public function remove_tmp_dir()
		{
			if( ! $this->tmp_dir )
				return;
			
			// remove files in the directory
			$tmp_dir_slash = trailingslashit( $this->tmp_dir );
			$files = array_merge( glob( $tmp_dir_slash . '*' ), glob( $tmp_dir_slash . '.*' ) );
			while( $file = array_shift( $files ) )
				if( is_file( $file ) )
					@unlink( $file );
			
			@rmdir( $this->tmp_dir );
			$this->tmp_dir = null;
		}
		
		/**
		 * Creates a temporary file path (but not the file itself)
		 */
		private function create_tmp_filepath( $filename )
		{
			$uploads_dir = $this->create_tmp_dir();
			$filename = sanitize_file_name( $filename );
			$filename = wp_unique_filename( $uploads_dir, $filename );
			return trailingslashit( $uploads_dir ) . $filename;
		}
		
		/**
		 * Checks if the image MIME type is supported for embedding
		 */
		private function is_embed_image_format_supported( $mimetype )
		{
			$supported_mime_types = array(
					"image/jpeg",
					"image/png",
					"image/gif",
					"image/tiff",
					"image/bmp",
					"image/x-ms-bmp",
					"image/svg+xml",
					"image/webp",
				);
			
			if( $mimetype )
				foreach( $supported_mime_types as $smt )
					if( $mimetype === $smt )
						return true;
			
			return false;
		}
		
		/**
		 * Used for encoding WooCommerce form settings data
		 */
		public static function encode_form_settings( $data )
		{
			return Pdf_Forms_For_WooCommerce_Wrapper::json_encode( $data );
		}
		
		/**
		 * Used for decoding WooCommerce form settings data
		 */
		public static function decode_form_settings( $data )
		{
			$form_settings = array();
			if( ! empty( $data ) && is_array( $json_decoded = json_decode( $data, true ) ) )
				$form_settings = $json_decoded;
			return $form_settings;
		}
		
		/**
		 * Runs when order status is changed
		 */
		public function process_order_status_change( $order_id, $old_status, $new_status, $order )
		{
			if( is_a( $order, 'WC_Order' ) )
			{
				// the problem with downloadable product files is that when the order status changes, the PDF forms have to be re-filled because the data may have changed
				// but we don't want to redo it every time status changes, only as needed
				// so, we should delete the old PDF files and recreate them only when needed
				
				if( $new_status != 'new' )
					// remove order downloadable files
					self::unset_downloadable_files( $order->get_id() );
			}
		}
		
		/**
		 * Fills PDFs and attaches them to email notifications
		 */
		public function attach_pdfs( $email_attachments, $email_id, $object, $email )
		{
			if( $email_id !== null && is_a( $object, 'WC_Order' ) && is_a( $email, 'WC_Email' ) )
			{
				try
				{
					$variable_processor = $this->get_variable_processor();
					$variable_processor->set_email( $email );
					$variable_processor->set_order( $object );
					
					// compile a list of product settings
					$items = $object->get_items();
					foreach( $items as $item )
					{
						$variable_processor->set_order_item( $item );
						
						$product_id = $item->get_product_id();
						
						$variable_processor->set_product_id( $product_id );
						
						$product_data = self::get_meta( $product_id, 'data' );
						if( isset( $product_data ) )
						{
							$settings = self::decode_form_settings( $product_data );
							
							if( is_array( $settings )
							&& isset( $settings['attachments'] )
							&& is_array( $attachments = $settings['attachments'] ) )
							{
								foreach( $attachments as $attachment )
								{
									if( isset( $attachment['options'] )
									&& is_array( $options = $attachment['options'] )
									&& isset( $options['email_templates'] )
									&& is_array( $email_templates = $options['email_templates'] )
									&& in_array( $email_id, $email_templates ) )
									{
										// TODO
										//$qty = $item->get_quantity();
										//for( $i = 0; $i < $qty; $i++ )
										
										$email_attachments = array_merge( $email_attachments, $this->fill_pdfs( $settings, $variable_processor ) );
										break;
									}
								}
							}
						}
					}
				}
				catch( Exception $e )
				{
					$error_message = self::replace_tags(
						__( "Error generating PDF: {error-message} at {error-file}:{error-line}", 'pdf-forms-for-woocommerce' ),
						array( 'error-message' => $e->getMessage(), 'error-file' => wp_basename( $e->getFile() ), 'error-line' => $e->getLine() )
					);
					
					// log error in woocommerce error logging facility
					wc_get_logger()->error( $error_message, array( 'source' => 'pdf-forms-for-woocommerce' ) );
					
					// TODO: notify store owner of error
				}
			}
			
			return $email_attachments;
		}
		
		public static function get_order_storage( $order_id )
		{
			// TODO: fix race condition
			$storage = self::get_storage();
			$storage_directory = self::get_metadata( $order_id, 'storage-dir' );
			$order_directory = self::get_metadata( $order_id, 'order-dir' );
			if( $storage_directory && $order_directory )
			{
				$storage->set_site_root_relative_storage_path( $storage_directory );
				$storage->set_subpath( $order_directory );
			}
			else
			{
				$storage_directory = $storage->get_site_root_relative_storage_path();
				$order_directory = 'pdf-forms-for-woocommerce/order-files/' . $order_id . '-' . wp_hash( wp_rand() . microtime() );
				$storage->set_subpath( $order_directory );
				self::set_metadata( $order_id, 'storage-dir', $storage_directory );
				self::set_metadata( $order_id, 'order-dir', $order_directory );
			}
			
			return $storage;
		}
		
		public static function delete_order_storage( $order_id )
		{
			// TODO: fix race condition
			$storage_directory = self::get_metadata( $order_id, 'storage-dir' );
			$order_directory = self::get_metadata( $order_id, 'order-dir' );
			if( $storage_directory && $order_directory )
			{
				$storage = self::get_storage();
				$storage->set_site_root_relative_storage_path( $storage_directory );
				$storage->set_subpath( $order_directory );
				$storage->delete_subpath_recursively();
				self::unset_metadata( $order_id, 'storage-dir' );
				self::unset_metadata( $order_id, 'order-dir' );
			}
		}
		
		public static function get_downloadable_files( $order_id, $order_item_id = null )
		{
			$downloadable_files = self::get_metadata( $order_id, 'downloadable-files' );
			if( is_array( $downloadable_files ) )
			{
				if( $order_item_id === null )
					return $downloadable_files;
				
				if( isset( $downloadable_files[$order_item_id] ) )
				{
					$order_item_files = $downloadable_files[$order_item_id];
					if( is_array( $order_item_files ) )
						return $order_item_files;
				}
			}
			
			return array();
		}
		
		public static function set_downloadable_files( $order_id, $order_item_id = null, $order_item_files = null )
		{
			// TODO: fix race condition
			$downloadable_files = self::get_metadata( $order_id, 'downloadable-files' );
			if( ! is_array( $downloadable_files ) )
				$downloadable_files = array();
			
			if( $order_item_files === null )
			{
				if( $order_item_id === null )
					$downloadable_files = array();
				else
					unset( $downloadable_files[$order_item_id] );
			}
			else if( $order_item_id !== null )
				$downloadable_files[$order_item_id] = $order_item_files;
			
			self::set_metadata( $order_id, 'downloadable-files', $downloadable_files );
		}
		
		public static function get_downloadable_file( $order_id, $order_item_id, $attachment_id )
		{
			$downloadable_files = self::get_downloadable_files( $order_id, $order_item_id );
			if( isset( $downloadable_files[$attachment_id] ) )
				return $downloadable_files[$attachment_id];
			return null;
		}
		
		public static function set_downloadable_file( $order_id, $order_item_id, $attachment_id, $fileurl, $filename )
		{
			// TODO: fix race condition
			$downloadable_files = self::get_downloadable_files( $order_id, $order_item_id );
			$downloadable_files[$attachment_id] = array(
				'attachment_id' => $attachment_id,
				'file' => $fileurl,
				'filename' => $filename,
			);
			self::set_downloadable_files( $order_id, $order_item_id, $downloadable_files );
		}
		
		public static function unset_downloadable_files( $order_id, $order_item_id = null )
		{
			// TODO: fix race condition
			
			$downloadable_files = self::get_downloadable_files( $order_id, $order_item_id );
			
			if( $order_item_id !== null )
				$downloadable_files = array( $downloadable_files );
			
			// delete files
			foreach( $downloadable_files as $downloadable_file_set )
				foreach( $downloadable_file_set as $downloadable_file )
					if( isset( $downloadable_file['file'] ) )
						@unlink( trailingslashit( ABSPATH ) . $downloadable_file['file'] );
			
			self::set_downloadable_files( $order_id, $order_item_id, null );
		}
		
		public static function unset_downloadable_file( $order_id, $order_item_id, $attachment_id )
		{
			// TODO: fix race condition
			
			$downloadable_files = self::get_downloadable_files( $order_id, $order_item_id );
			if( is_array( $downloadable_files ) && isset( $downloadable_files[$attachment_id] ) )
			{
				// delete file
				if( isset( $downloadable_files[$attachment_id] )
				&& is_array( $downloadable_file = $downloadable_files[$attachment_id] )
				&& isset( $downloadable_file['file'] ) )
					@unlink( $downloadable_file['file'] );
				
				unset( $downloadable_files[$attachment_id] );
				
				self::set_downloadable_files( $order_item_id, $downloadable_files );
			}
		}
		
		public function woocommerce_get_item_downloads( $files, $order_item, $order )
		{
			if( is_array( $files ) && is_object( $order_item ) && is_object( $order ) )
			{
				$order_id = $order->get_id();
				$order_item_id = $order_item->get_id();
				$product_id = $order_item->get_product_id();
				
				foreach( $files as $download_id => &$file )
				{
					$product_data = self::get_meta( $product_id, 'data' );
					if( isset( $product_data ) )
					{
						$settings = self::decode_form_settings( $product_data );
						
						if( is_array( $settings )
						&& isset( $settings['attachments'] )
						&& is_array( $attachments = $settings['attachments'] ) )
						{
							foreach( $attachments as $attachment )
							{
								$attachment_id = $attachment['attachment_id'];
								
								if( isset( $attachment['options'] )
								&& is_array( $options = $attachment['options'] )
								&& isset( $options['download_id'] )
								&& $options['download_id'] == $download_id )
								{
									$downloadable_file = self::get_downloadable_file( $order_id, $order_item_id, $attachment_id );
									
									if( ! is_array( $downloadable_file) || ! isset( $downloadable_file['file'] ) )
									{
										// what happens if the attachment is not being attached to any emails? we need to fill the PDF
										$variable_processor = $this->get_variable_processor();
										$variable_processor->set_order( $order );
										$variable_processor->set_order_item( $order_item );
										$variable_processor->set_product_id( $product_id );
										
										// fill PDFs so that they are also saved to the order directory
										$temporary_pdfs = $this->fill_pdfs( $settings, $variable_processor );
										
										// clean up temporary files
										$this->remove_tmp_dir();
										
										$downloadable_file = self::get_downloadable_file( $order_id, $order_item_id, $attachment_id );
									}
									
									if( is_array( $downloadable_file) && isset( $downloadable_file['file'] ) )
									{
										$file['name'] = $downloadable_file['filename'];
										$file['file'] = $downloadable_file['file']; // relative to site root
										$file['download_url'] = trailingslashit( get_site_url() ) . $downloadable_file['file'];
									}
								}
							}
						}
					}
				}
			}
			
			return $files;
		}
		
		/*
		 * Runs when post is deleted to clean up files
		 */
		public function before_delete_post( $post_id )
		{
			$type = get_post_type( $post_id );
			// delete the order directory
			if($type == 'shop_order')
				$this->before_delete_order( $post_id, wc_get_order( $post_id ) );
			// delete the order item's downloadable files
			else if($type == 'line_item')
				$this->before_delete_order_item( $post_id );
		}
		
		/*
		 * Runs when order is deleted to clean up order's items' downloadable files
		 */
		public function before_delete_order( $order_id, $order )
		{
			// remove downloadable files
			self::unset_downloadable_files( $order_id );
			
			// delete order directory
			self::delete_order_storage( $order_id );
		}
		
		/*
		 * Runs when order item is deleted to clean up order item's downloadable files
		 */
		public function before_delete_order_item( $order_item_id )
		{
			// remove order item downloadable files
			self::unset_downloadable_files( wc_get_order_id_by_order_item_id( $order_item_id ), $order_item_id );
		}
		
		/**
		 * Fills PDFs
		 */
		private function fill_pdfs( $settings, $variable_processor )
		{
			try
			{
				$output_files = array();
				
				$attachments = array();
				$mappings = array();
				$embeds = array();
				$value_mappings = array();
				
				if( isset( $settings['attachments'] ) && is_array( $settings['attachments'] ) )
					$attachments = $settings['attachments'];
				if( isset( $settings['mappings'] ) && is_array( $settings['mappings'] ) )
					$mappings = $settings['mappings'];
				if( isset( $settings['embeds'] ) && is_array( $settings['embeds'] ) )
					$embeds = $settings['embeds'];
				if( isset( $settings['value_mappings'] ) && is_array( $settings['value_mappings'] ))
					$value_mappings = $settings['value_mappings'];
				
				$files = array();
				
				$service = $this->get_service();
				
				// preprocess embedded images
				$embed_files = array();
				foreach( $embeds as $id => $embed )
				{
					$filepath = null;
					$filename = null;
					$url_mimetype = null;
					
					$url = null;
					
					if( isset( $embed['variables'] ) ) 
						$url = $variable_processor->process( $embed["variables"] );
					
					if( $url != null )
					{
						if( filter_var( $url, FILTER_VALIDATE_URL ) !== FALSE )
						if( substr( $url, 0, 5 ) === 'http:' || substr( $url, 0, 6 ) === 'https:' )
						{
							$filepath = $this->create_tmp_filepath( 'img_download_'.count($embed_files).'.tmp' );
							self::download_file( $url, $filepath, $url_mimetype ); // can throw an exception
							$filename = $url;
						}
						
						if( substr( $url, 0, 5 ) === 'data:' )
						{
							$filepath = $this->create_tmp_filepath( 'img_data_'.count($embed_files).'.tmp' );
							$filename = $url;
							
							$parsed = self::parse_data_uri( $url );
							if( $parsed !== false )
							{
								$url_mimetype = $parsed['mime'];
								file_put_contents( $filepath, $parsed['data'] );
							}
						}
					}
					
					if( ! $filepath )
						continue;
					
					$file_mimetype = self::get_mime_type( $filepath );
					
					$mimetype_supported = false;
					$mimetype = 'unknown';
					
					if( $file_mimetype )
					{
						$mimetype = $file_mimetype;
						
						// check if MIME type is supported based on file contents
						$mimetype_supported = $this->is_embed_image_format_supported( $file_mimetype );
					}
					
					// if we were not able to determine MIME type based on file contents
					// then fall back to URL MIME type (if we are dealing with a URL)
					// (maybe fileinfo functions are not functional and WP fallback failed due to the ".tmp" extension)
					if( !$mimetype_supported && $url_mimetype )
					{
						$mimetype = $url_mimetype;
						$mimetype_supported = $this->is_embed_image_format_supported( $url_mimetype );
					}
					
					if( !$mimetype_supported )
						throw new Exception(
							self::replace_tags(
								__( "File type {mime-type} of {file} is unsupported for {purpose}", 'pdf-forms-for-woocommerce' ),
								array( 'mime-type' => $mimetype, 'file' => $filename, 'purpose' => __( "image embedding", 'pdf-forms-for-woocommerce') )
							)
						);
					
					$embed_files[$id] = $filepath;
				}
				
				foreach( $attachments as $attachment )
				{
					$attachment_id = $attachment["attachment_id"];
					
					$fields = $this->get_fields( $attachment_id );
					$data = array();
					
					// process mappings
					foreach( $mappings as $mapping )
					{
						$i = strpos( $mapping["pdf_field"], '-');
						if( $i === FALSE )
							continue;
						
						$aid = substr( $mapping["pdf_field"], 0, $i );
						if( $aid != $attachment_id && $aid != 'all' )
							continue;
						
						$field = substr( $mapping["pdf_field"], $i+1);
						$field = self::base64url_decode( $field );
						
						if( !isset( $fields[$field] ) )
							continue;
						
						$multiple = isset( $fields[$field]['flags'] ) && in_array( 'MultiSelect', $fields[$field]['flags'] );
						
						if( isset( $mapping["variables"] ) )
						{
							$data[$field] = $variable_processor->process( $mapping["variables"] );
							
							if( $multiple )
							{
								$data[$field] = explode( "\n" , $data[$field] );
								foreach( $data[$field] as &$value )
									$value = trim( $value );
								unset( $value );
							}
						}
					}
					
					if( count( $value_mappings ) > 0 )
					{
						// process value mappings
						$processed_value_mappings = array();
						$value_mapping_data = array();
						$existing_data_fields = array_fill_keys( array_keys( $data ), true );
						foreach( $value_mappings as $value_mapping )
						{
							$i = strpos( $value_mapping["pdf_field"], '-' );
							if( $i === FALSE )
								continue;
							
							$aid = substr( $value_mapping["pdf_field"], 0, $i );
							if( $aid != $attachment_id && $aid != 'all' )
								continue;
							
							$field = substr( $value_mapping["pdf_field"], $i+1 );
							$field = self::base64url_decode( $field );
							
							if( !isset( $existing_data_fields[$field] ) )
								continue;
							
							if( !isset( $value_mapping_data[$field] ) )
								$value_mapping_data[$field] = $data[$field];
							
							$woo_value = strval( $value_mapping['woo_value'] );
							if( ! isset( $processed_value_mappings[$field] ) )
								$processed_value_mappings[$field] = array();
							if( ! isset( $processed_value_mappings[$field][$woo_value] ) )
								$processed_value_mappings[$field][$woo_value] = array();
							$processed_value_mappings[$field][$woo_value][] = $value_mapping;
						}
						
						// convert plain text values to arrays for processing
						foreach( $value_mapping_data as $field => &$value )
							if( ! is_array( $value ) )
								$value = array( $value );
						unset( $value );
						
						// determine old and new values
						$add_data = array();
						$remove_data = array();
						foreach($processed_value_mappings as $field => $woo_mappings_list)
							foreach($woo_mappings_list as $woo_value => $list)
							{
								foreach( $value_mapping_data[$field] as $key => $value )
									if( Pdf_Forms_For_WooCommerce_Wrapper::mb_strtolower( $value ) === Pdf_Forms_For_WooCommerce_Wrapper::mb_strtolower( $woo_value ) )
									{
										if( ! isset( $remove_data[$field] ) )
											$remove_data[$field] = array();
										$remove_data[$field][] = $value;
										
										if( ! isset( $add_data[$field] ) )
											$add_data[$field] = array();
										foreach( $list as $item )
											$add_data[$field][] = $item['pdf_value'];
									}
							}
						
						// remove old values
						foreach( $value_mapping_data as $field => &$value )
							if( isset( $remove_data[$field] ) )
								$value = array_diff( $value, $remove_data[$field] );
						unset( $value );
						
						// add new values
						foreach( $value_mapping_data as $field => &$value )
							if( isset( $add_data[$field] ) )
								$value = array_unique( array_merge( $value, $add_data[$field] ) );
						unset( $value );
						
						// convert arrays back to plain text where needed
						foreach( $value_mapping_data as $field => &$value )
							if( count( $value ) < 2 )
							{
								if( count( $value ) > 0 )
									$value = reset( $value );
								else
									$value = null;
							}
						unset( $value );
						
						// update data
						foreach( $value_mapping_data as $field => &$value )
							$data[$field] = $value;
						unset( $value );
					}
					
					// filter out anything that the pdf field can't accept
					foreach( $data as $field => &$value )
					{
						$type = $fields[$field]['type'];
						
						if( $type == 'radio' || $type == 'select' || $type == 'checkbox' )
						{
							// compile a list of field options
							$pdf_field_options = null;
							if( isset( $fields[$field]['options'] ) && is_array( $fields[$field]['options'] ) )
							{
								$pdf_field_options = $fields[$field]['options'];
								
								// if options are have more information than value, extract only the value
								foreach( $pdf_field_options as &$option )
									if( is_array( $option ) && isset( $option['value'] ) )
										$option = $option['value'];
								unset( $option );
							}
							
							// if a list of options are available then filter $value
							if( $pdf_field_options !== null )
							{
								if( is_array( $value ) )
									$value = array_intersect( $value, $pdf_field_options );
								else
									$value = in_array( $value, $pdf_field_options ) ? $value : null;
							}
						}
						
						// if pdf field is not a multiselect field but value is an array then use the first element only
						$pdf_field_multiselectable = isset( $fields[$field]['flags'] ) && in_array( 'MultiSelect', $fields[$field]['flags'] );
						if( !$pdf_field_multiselectable && is_array( $value ) )
						{
							if( count( $value ) > 0 )
								$value = reset( $value );
							else
								$value = null;
						}
					}
					unset( $value );
					
					// process image embeds
					$embeds_data = array();
					foreach( $embeds as $id => $embed )
						if( $embed['attachment_id'] == $attachment_id || $embed['attachment_id'] == 'all' )
						{
							if( isset( $embed_files[$id] ) )
							{
								$embed_data = array(
									'image' => $embed_files[$id],
									'page' => $embed['page'],
								);
								
								if($embed['page'] > 0)
								{
									$embed_data['left'] = $embed['left'];
									$embed_data['top'] = $embed['top'];
									$embed_data['width'] = $embed['width'];
									$embed_data['height'] = $embed['height'];
								};
								
								$embeds_data[] = $embed_data;
							}
						}
					
					// skip file if 'skip when empty' option is enabled and form data is blank
					if($attachment['options']['skip_empty'] )
					{
						$empty_data = true;
						foreach( $data as $field => $value )
							if( !( is_null( $value ) || $value === array() || trim( $value ) === "" ) )
							{
								$empty_data = false;
								break;
							}
						
						if( $empty_data && count( $embeds_data ) == 0 )
							continue;
					}
					
					$save_directory = strval( $attachment['options']['save_directory'] );
					
					$options = array();
					
					$options['flatten'] =
						isset($attachment['options']) &&
						isset($attachment['options']['flatten']) &&
						$attachment['options']['flatten'] == true;
					
					// determine if the attachment would be changed
					$filling_data = false;
					foreach( $data as $field => $value )
					{
						if( $value === null || $value === '' )
							$value = array();
						else if( ! is_array( $value ) )
							$value = array( $value );
						
						$pdf_value = null;
						if( isset( $fields[$field]['value'] ) )
							$pdf_value = $fields[$field]['value'];
						if( $pdf_value === null || $pdf_value === '' )
							$pdf_value = array();
						else if( ! is_array( $pdf_value ) )
							$pdf_value = array( $pdf_value );
						
						// check if values don't match
						if( ! ( array_diff( $value, $pdf_value ) == array() && array_diff( $pdf_value, $value ) == array() ) )
						{
							$filling_data = true;
							break;
						}
					}
					$attachment_affected = $filling_data || count( $embeds_data ) > 0 || $options['flatten'];
					
					$filepath = get_attached_file( $attachment_id );
					
					$filename = strval( $attachment['options']['filename'] );
					if ( $filename !== "" )
						$destfilename = $variable_processor->process( $filename );
					else
						$destfilename = $filepath;
					
					$destfilename = wp_basename( empty( $destfilename ) ? $filepath : $destfilename, '.pdf' );
					$destfile = $this->create_tmp_filepath( $destfilename . '.pdf' );
					
					$filled = false;
					
					if( $service )
						// we only want to use the API when something needs to be done to the file
						if( $attachment_affected )
							$filled = $service->api_fill_embed( $destfile, $attachment_id, $data, $embeds_data, $options );
					
					if( ! $filled )
						copy( $filepath, $destfile );
					$files[] = array( 'attachment_id' => $attachment_id, 'file' => $destfile, 'filename' => $destfilename . '.pdf', 'options' => $attachment['options'] );
				}
				
				if( count( $files ) > 0 )
				{
					$storage = self::get_storage();
					foreach( $files as $id => $filedata )
					{
						$output_files[] = $filedata['file'];
						
						$save_directory = strval( $filedata['options']['save_directory'] );
						if( $save_directory !== "" )
						{
							// standardize directory separator
							$save_directory = str_replace( '\\', '/', $save_directory );
							
							// remove preceding slashes and dots and space characters
							$trim_characters = "/\\. \t\n\r\0\x0B";
							$save_directory = trim( $save_directory, $trim_characters );
							
							// replace variables in path elements
							$path_elements = explode( "/", $save_directory );
							$tag_replaced_path_elements = array();
							foreach ( $path_elements as $key => $value )
								$tag_replaced_path_elements[$key] = $variable_processor->process( $value );
							
							foreach( $tag_replaced_path_elements as $elmid => &$new_element )
							{
								// sanitize
								$new_element = trim( sanitize_file_name( $new_element ), $trim_characters );
								
								// if replaced and sanitized filename is blank then attempt to use the non-tag-replaced version
								if( $new_element === "" )
									$new_element = trim( sanitize_file_name( $path_elements[$elmid] ), $trim_characters );
							}
							unset($new_element);
							
							$save_directory = implode( "/", $tag_replaced_path_elements );
							$save_directory = preg_replace( '|/+|', '/', $save_directory ); // remove double slashes
							
							$storage->set_subpath( $save_directory );
							$storage->save( $filedata['file'], $filedata['filename'] );
						}
						
						$save_order_file = $filedata['options']['download_id'];
						if ( ! empty( $save_order_file ) )
						{
							$order = $variable_processor->get_order();
							$order_item = $variable_processor->get_order_item();
							if( $order && $order_item )
							{
								$order_id = $order->get_id();
								$order_item_id = $order_item->get_id();
								$order_storage = self::get_order_storage( $order_id );
								$dstfilename = $order_storage->save( $filedata['file'], $filedata['filename'] , $overwrite = true );
								
								// record file data in order meta
								self::set_downloadable_file(
									$order_id,
									$order_item_id,
									$attachment_id,
									trailingslashit( $order_storage->get_site_root_relative_path() ) . $dstfilename,
									$filedata['filename']
								);
							}
						}
					}
				}
				
				return $output_files;
			}
			catch( Exception $e )
			{
				// clean up
				$this->remove_tmp_dir();
				
				$error_message = self::replace_tags(
					__( "Error generating PDF: {error-message} at {error-file}:{error-line}", 'pdf-forms-for-woocommerce' ),
					array( 'error-message' => $e->getMessage(), 'error-file' => wp_basename( $e->getFile() ), 'error-line' => $e->getLine() )
				);
				
				// log error in woocommerce error logging facility
				wc_get_logger()->error( $error_message, array( 'source' => 'pdf-forms-for-woocommerce' ) );
				
				// TODO: notify store owner of error
				
				return array();
			}
		}
		
		/**
		 * Takes html template from the html folder and renders it with the given attributes
		 */
		public static function render( $template, $attributes = array() )
		{
			return self::render_file( plugin_dir_path(__FILE__) . 'html/' . $template . '.html', $attributes );
		}
		
		/**
		 * Renders a notice with the given attributes
		 */
		public static function render_notice( $notice_id, $type, $attributes = array() )
		{
			if( ! isset( $attributes['classes'] ) )
				$attributes['classes'] = "";
			$attributes['classes'] = trim( $attributes['classes'] . " notice-$type" );
			
			if( !isset( $attributes['label'] ) )
				$attributes['label'] = __( "PDF Forms Filler for WooCommerce" );
			
			if( $notice_id )
			{
				$attributes['attributes'] = 'data-notice-id="'.esc_attr( $notice_id ).'"';
				$attributes['classes'] .= ' is-dismissible';
			}
			
			return self::render( "notice", $attributes );
		}
		
		/**
		 * Renders a success notice with the given attributes
		 */
		public static function render_success_notice( $notice_id, $attributes = array() )
		{
			return self::render_notice( $notice_id, 'success', $attributes );
		}
		
		/**
		 * Renders a warning notice with the given attributes
		 */
		public static function render_warning_notice( $notice_id, $attributes = array() )
		{
			return self::render_notice( $notice_id, 'warning', $attributes );
		}
		
		/**
		 * Renders an error notice with the given attributes
		 */
		public static function render_error_notice( $notice_id, $attributes = array() )
		{
			return self::render_notice( $notice_id, 'error', $attributes );
		}
		
		/**
		 * Helper for replace_tags function
		 */
		private static function add_curly_braces($str)
		{
			return '{'.$str.'}';
		}
		
		/**
		 * Takes a string with tags and replaces tags in the string with the given values in $tags array
		 */
		public static function replace_tags( $string, $tags = array() )
		{
			return str_replace(
				array_map( array( get_class(), 'add_curly_braces' ), array_keys( $tags ) ),
				array_values( $tags ),
				$string
			);
		}
		
		/**
		 * Takes html template file and renders it with the given attributes
		 */
		public static function render_file( $template_filepath, $attributes = array() )
		{
			return self::replace_tags( file_get_contents( $template_filepath ) , $attributes );
		}
		
		/**
		 * Adds the 'PDF Forms' tab on WooCommerce product page
		 */
		public function add_product_data_tab( $tabs )
		{
			$tabs['pdf-forms-for-woocommerce'] = array(
				'label'    => __( 'PDF Forms', 'pdf-forms-for-woocommerce' ),
				'target'   => 'pdf-forms-for-woocommerce-product-settings',
				'class'    => array()
			);
			
			return $tabs;
		}
		
		/**
		 * Returns possible woocommerce fields
		 */
		private static function get_woocommerce_fields( $product_id )
		{
			$woocommerce_fields = array();
			
			$fields = WC()->checkout()->get_checkout_fields();
			foreach($fields as $section)
				foreach($section as $field_key => $field_args)
					$woocommerce_fields[] = array( 'alias' => $field_key, 'label' => $field_args['label'] );
			
			return $woocommerce_fields;
		}
		
		/**
		 * Prints 'PDF Forms' tab HTML contents
		 */
		public function print_product_data_tab_contents()
		{
			global $post;
			$product_id = $post->ID;
			
			$messages = '';
			$attachments = array();
			$email_templates = array();
			$woocommerce_variables = array();
			
			$service = $this->get_service();
			$messages .= $service->form_notices();
			
			// get attachments
			$data = self::get_meta( $product_id, 'data' );
			$form_settings = self::decode_form_settings( $data );
			
			if( is_array( $form_settings ) )
			{
				if( isset( $form_settings['attachments'] ) && is_array( $form_settings['attachments'] ) )
				foreach( $form_settings['attachments'] as $attachment )
				{
					$attachment_id = $attachment['attachment_id'];
					$info = $this->get_info( $attachment_id );
					$info['fields'] = $this->query_pdf_fields( $attachment_id );
					
					$attachments[] = array(
						'attachment_id' => $attachment_id,
						'filename' => wp_basename( get_attached_file( $attachment_id ) ),
						'info' => $info,
					);
				}
			}
			
			// get email templates
			$email_classes = WC()->mailer()->get_emails();
			foreach( $email_classes as $email_class )
				$email_templates[] = array(
					'id' => $email_class->id,
					'alias' => $email_class->id,
					'text' => $email_class->title,
					'lowerText' => Pdf_Forms_For_WooCommerce_Wrapper::mb_strtolower( $email_class->title ),
				);
			
			// prepare woocommerce variables for select2
			foreach( $this->get_variable_processor()->get_variables() as $variable )
			{
				$tag = '{'.$variable['key'].'}';
				$woocommerce_variables[] = array(
					'id' => count( $woocommerce_variables ),
					'text' => $tag,
					'lowerText' => Pdf_Forms_For_WooCommerce_Wrapper::mb_strtolower( $tag ),
				);
			}
			
			// capture function output in a variable
			ob_start();
			woocommerce_wp_hidden_input( array(
				'id'    => 'pdf-forms-for-woocommerce-data',
				'class' => 'pdf-forms-for-woocommerce-data',
				'value' => $data,
			) );
			$woocommerce_wp_data_input = ob_get_clean();
			
			// get product's downloads
			$downloads = array(
				array( 'id' => '', 'text' => __( "None", 'pdf-forms-for-woocommerce' ) ),
				array( 'id' => 'create-new-download', 'text' => __( "Create a new downloadable file", 'pdf-forms-for-woocommerce') ),
			);
			$product = wc_get_product( $product_id );
			if( $product )
			{
				$list = $product->get_downloads();
				if( is_array( $list ) )
					foreach( $list as $download )
						$downloads[] = array(
							'id' => $download->get_id(),
							'text' => $download->get_name(),
						);
			}
			
			$preload_data = array(
				'default_pdf_options' => self::DEFAULT_PDF_OPTIONS,
				'attachments' => $attachments,
				'email_templates' => $email_templates,
				'woocommerce_variables' => $woocommerce_variables,
				'downloads' => $downloads,
			);
			
			echo self::render( 'spinner' ).
				self::render( 'product-settings', array(
					'messages' => $messages,
					'data-field' => $woocommerce_wp_data_input,
					'preload-data' => esc_html( Pdf_Forms_For_WooCommerce_Wrapper::json_encode( $preload_data ) ),
					'instructions' => esc_html__( "You can use this section to attach a PDF file to your product, add new order fields to your form, and link them to fields in the PDF file. You can also embed images (from URLs or attached files) into the PDF file. Changes here are applied when the form is saved.", 'pdf-forms-for-woocommerce' ),
					'attach-pdf' => esc_html__( "Attach a PDF File", 'pdf-forms-for-woocommerce' ),
					'delete' => esc_html__( 'Delete', 'pdf-forms-for-woocommerce' ),
					'map-value' => esc_html__( 'Map Value', 'pdf-forms-for-woocommerce' ),
					'options' => esc_html__( 'Options', 'pdf-forms-for-woocommerce' ),
					'skip-when-empty' => esc_html__( 'Skip when empty', 'pdf-forms-for-woocommerce' ),
					'email-templates' => esc_html__( 'Attach the filled PDF file to the following emails:', 'pdf-forms-for-woocommerce' ),
					'flatten' => esc_html__( 'Flatten', 'pdf-forms-for-woocommerce' ),
					'filename' => esc_html__( 'New filled PDF file name (variables allowed):', 'pdf-forms-for-woocommerce' ),
					'save-directory'=> esc_html__( 'Path for saving filled PDF file (variables allowed):', 'pdf-forms-for-woocommerce' ),
					'download-link' => esc_html__( "Provide a download link to the filled PDF file via the product's downloadable file:", 'pdf-forms-for-woocommerce' ),
					'leave-blank-to-disable'=> esc_html__( '(leave blank to disable this option)', 'pdf-forms-for-woocommerce' ),
					'field-mapping' => esc_html__( 'Field Mapper Tool', 'pdf-forms-for-woocommerce' ),
					'field-mapping-help' => esc_html__( 'This tool can be used to link form fields and variables with fields in the attached PDF files. WooCommerce fields can also be generated from PDF fields. When your users submit the form, input from form fields and other variables will be inserted into the correspoinding fields in the PDF file. WooCommerce to PDF field value mappings can also be created to enable the replacement of WooCommerce data when PDF fields are filled.', 'pdf-forms-for-woocommerce' ),
					'pdf-field' => esc_html__( 'PDF field', 'pdf-forms-for-woocommerce' ),
					'woo-variable' => esc_html__( 'WooCommerce variables', 'pdf-forms-for-woocommerce' ),
					'add-mapping' => esc_html__( 'Add Mapping', 'pdf-forms-for-woocommerce' ),
					'delete-all-mappings' => esc_html__( 'Delete All', 'pdf-forms-for-woocommerce' ),
					'new-field' => esc_html__( 'New Field:', 'pdf-forms-for-woocommerce' ),
					'image-embedding' => esc_html__( 'Image Embedding Tool', 'pdf-forms-for-woocommerce' ),
					'image-embedding-help'=> esc_html__( 'This tool allows embedding images into PDF files.  Images are taken from field attachments or field values that are URLs.  You must select a PDF file, its page and draw a bounding box for image insertion.', 'pdf-forms-for-woocommerce' ),
					'add-woo-variable-embed' => esc_html__( 'Embed Image', 'pdf-forms-for-woocommerce' ),
					'delete-woo-variable-embed' => esc_html__( 'Delete', 'pdf-forms-for-woocommerce' ),
					'pdf-file' => esc_html__( 'PDF file', 'pdf-forms-for-woocommerce' ),
					'page' => esc_html__( 'Page', 'pdf-forms-for-woocommerce' ),
					'image-region-selection-hint' => esc_html__( 'Select a region where the image needs to be embeded.', 'pdf-forms-for-woocommerce' ),
					'top' => esc_html__( 'Top', 'pdf-forms-for-woocommerce' ),
					'left' => esc_html__( 'Left', 'pdf-forms-for-woocommerce' ),
					'width' => esc_html__( 'Width', 'pdf-forms-for-woocommerce' ),
					'height' => esc_html__( 'Height', 'pdf-forms-for-woocommerce' ),
					'pts' => esc_html__( 'pts', 'pdf-forms-for-woocommerce' ),
					'help-message' => self::replace_tags(
						esc_html__( "Have a question/comment/problem? Feel free to use {a-href-forum}the support forum{/a}.", 'pdf-forms-for-woocommerce' ),
						array(
							'a-href-forum' => '<a href="https://wordpress.org/support/plugin/pdf-forms-for-woocommerce/" target="_blank">',
							'/a' => '</a>',
						)
					),
					'show-help' => esc_html__( 'Show Help', 'pdf-forms-for-woocommerce' ),
					'hide-help' => esc_html__( 'Hide Help', 'pdf-forms-for-woocommerce' ),
				) );
		}
		
		/**
		 * Hook that runs on product save action to validate plugin data
		 */
		public function save_product_data( $product_id )
		{
			if( ! isset( $_POST['pdf-forms-for-woocommerce-data'] ) )
				return;
			
			try
			{
				$data = json_decode( wp_unslash( $_POST['pdf-forms-for-woocommerce-data'] ), true );
				
				if( ! is_array( $data ) )
					throw new Exception(
							__( "Failed to decode data", 'pdf-forms-for-woocommerce' ),
						);
				
				// check attachments
				$attachments = array();
				if( isset( $data['attachments'] ) && is_array( $data['attachments'] ) )
				{
					// get email templates
					$email_classes = WC()->mailer()->get_emails();
					$email_templates = array();
					foreach( $email_classes as $email_class )
						$email_templates[] = $email_class->id;
					
					foreach( $data['attachments'] as $attachment )
					{
						$attachment_id = $attachment['attachment_id'];
						
						// check permissions
						if( ! current_user_can( 'edit_post', $attachment_id ) )
							continue;
						
						// check options
						if( !isset( $attachment['options'] ) || !is_array( $attachment['options'] ) )
							$attachment['options'] = self::DEFAULT_PDF_OPTIONS;
						else
						{
							// add missing options
							foreach( self::DEFAULT_PDF_OPTIONS as $option_name => $option_value )
								if( ! isset( $attachment['options'][$option_name] ) )
									$attachment['options'][$option_name] = $option_value;
							
							// remove non-existing options
							$allowed_options = array_keys( self::DEFAULT_PDF_OPTIONS );
							foreach( $attachment['options'] as $option_name => $option_value )
								if( ! in_array( $option_name, $allowed_options ) )
									unset( $attachment['options'][$option_name] );
							
							// check skip_empty to make sure it is a boolean value
							if( isset( $attachment['options']['skip_empty'] ) && ! is_bool( $attachment['options']['skip_empty'] ) )
								$attachment['options']['skip_empty'] = boolval( $attachment['options']['skip_empty'] );
							
							// check flatten to make sure it is a boolean value
							if( isset( $attachment['options']['flatten'] ) && ! is_bool( $attachment['options']['flatten'] ) )
								$attachment['options']['flatten'] = boolval( $attachment['options']['flatten'] );
							
							// check email templates
							if( isset( $attachment['options']['email_templates'] ) )
							{
								$option_value = $attachment['options']['email_templates'];
								if( ! is_array( $option_value ) )
									$option_value = array();
								if( count( $option_value ) > 0 )
									foreach( $option_value as $etid => $email_template )
										// check to make sure this notification is valid
										if( ! in_array( $email_template, $email_templates ) )
											unset( $attachment['options']['email_templates'][$etid] );
							}
							
							// create a new downloadable file if necessary
							if( ! empty( $attachment['options']['download_id'] ) )
							{
								$product = wc_get_product( $product_id );
								
								// make sure product is downloadable
								if( ! $product->get_downloadable() )
									$product->set_downloadable( true );
								
								$downloads = $product->get_downloads();
								
								if( $attachment['options']['download_id'] == 'create-new-download' )
								{
									$attachment['options']['download_id'] = '';
									
									// create the product's downloadable file entry
									$download_id = '';
									$attachment_url = wp_get_attachment_url( $attachment_id );
									
									// check if download already exists
									foreach( $downloads as $download )
										if( $download->get_file() == $attachment_url )
											$download_id = $download->get_id();
									
									// don't create a new download if one already exists
									if( $download_id == '' && $product)
									{
										$downloads[] = array(
											'name' => __( "Filled PDF", 'pdf-forms-for-woocommerce' ),
											'file' => $attachment_url,
										);
										$product->set_downloads( $downloads );
										$product->save();
										
										$downloads = $product->get_downloads();
										
										// get download id
										$download_id = '';
										foreach( $downloads as $download )
											if( $download->get_file() == $attachment_url )
												$download_id = $download->get_id();
									}
									
									// update download id in options
									$attachment['options']['download_id'] = $download_id;
								}
								
								// make sure download id is valid
								if( ! empty( $attachment['options']['download_id'] ) )
								{
									$download_id = $attachment['options']['download_id'];
									$download_exists = false;
									foreach( $downloads as $download )
										if( $download->get_id() == $download_id )
										{
											$download_exists = true;
											break;
										}
									if( ! $download_exists )
										$attachment['options']['download_id'] = '';
								}
							}
						}
						
						$attachments[$attachment_id] = $attachment;
					}
				}
				$data['attachments'] = $attachments;
				
				// process mappings
				$mappings = array();
				if( isset( $data['mappings'] ) && is_array( $data['mappings'] ) )
				{
					foreach( $data['mappings'] as $mapping )
					{
						if( isset( $mapping['variables'] ) && isset( $mapping['pdf_field'] ) )
							$mappings[] = array( 'variables' => $mapping['variables'], 'pdf_field' => $mapping['pdf_field'] );
						
						// TODO: make sure pdf field exists
					}
				}
				$data['mappings'] = $mappings;
				
				// process embeds
				$embeds = array();
				if( isset( $data['embeds'] ) && is_array( $data['embeds'] ) )
				{
					foreach( $data['embeds'] as $embed )
					{
						if( ! isset( $embed['attachment_id'] ) )
							continue;
						
						if( ! isset( $embed['variables'] ) )
							continue;
						
						// make sure attachment exists
						if( ! isset( $data['attachments'][ $embed['attachment_id'] ] ) && $embed['attachment_id'] !== 'all' )
							continue;
						
						// TODO: make sure pdf page exists
						// TODO: check insertion position and size
						
						if( isset( $embed['variables'] ) )
							$embed['variables'] = strval( $embed['variables'] );
						
						// TODO: don't reuse user input but create a new array with checked data
						$embeds[] = $embed;
					}
				}
				$data['embeds'] = $embeds;
				
				// process value mappings
				if( !isset( $data['value_mappings'] ) || !is_array( $data['value_mappings'] ) )
					$data['value_mappings'] = array();
				else
				{
					$value_mappings = array();
					foreach( $data['value_mappings'] as $value_mapping )
						if( isset( $value_mapping['pdf_field'] ) && isset( $value_mapping['woo_value'] ) && isset( $value_mapping['pdf_value'] ) )
							$value_mappings[] = array( 'pdf_field' => $value_mapping['pdf_field'], 'woo_value' => $value_mapping['woo_value'], 'pdf_value' => $value_mapping['pdf_value'] );
					$data['value_mappings'] = $value_mappings;
				}
				
				self::set_meta( $product_id, 'data', self::encode_form_settings( $data ) );
			}
			catch( Exception $e )
			{
				$error_message = self::replace_tags(
					__( "Error saving PDF form data: {error-message} at {error-file}:{error-line}", 'pdf-forms-for-woocommerce' ),
					array( 'error-message' => $e->getMessage(), 'error-file' => wp_basename( $e->getFile() ), 'error-line' => $e->getLine() )
				);
				
				// log error in woocommerce error logging facility
				wc_get_logger()->error( $error_message, array( 'source' => 'pdf-forms-for-woocommerce' ) );
				
				// show error message in to the user
				WC_Admin_Meta_Boxes::add_error( $error_message );
			}
		}
		
		/**
		 * Adds necessary admin scripts and styles
		 */
		public function admin_enqueue_scripts( $hook )
		{
			wp_register_script( 'pdf_forms_for_woocommerce_notices_script', plugins_url( 'js/notices.js', __FILE__ ), array( 'jquery' ), self::VERSION );
			wp_enqueue_script( 'pdf_forms_for_woocommerce_notices_script' );
			
			if( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) )
				return;
			
			wp_register_script( 'pdf_forms_filler_spinner_script', plugins_url( 'js/spinner.js', __FILE__ ), array('jquery'), '1.0.0' );
			wp_register_style( 'pdf_forms_filler_spinner_style', plugins_url( 'css/spinner.css', __FILE__ ), array(), '1.0.0' );
			
			// if we are on the product edit page then load the admin scripts
			if( ( 'post.php' == $hook || 'post-new.php' == $hook ) && 'product' == get_post_type() )
			{
				wp_register_style( 'select2', plugin_dir_url( __FILE__ ) . 'css/select2.min.css', array(), '4.0.13' );
				wp_register_script( 'select2', plugin_dir_url(  __FILE__ ) . 'js/select2/select2.min.js', array( 'jquery' ), '4.0.13' );
				
				wp_register_script( 'pdf_forms_for_woocommerce_admin_script', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery', 'jcrop', 'select2' ), self::VERSION );
				wp_register_style( 'pdf_forms_for_woocommerce_admin_style', plugin_dir_url( __FILE__ ) . 'css/admin.css', array( 'jcrop', 'select2' ), self::VERSION );
				
				wp_localize_script( 'pdf_forms_for_woocommerce_admin_script', 'pdf_forms_for_woocommerce', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'pdf-forms-for-woocommerce-ajax-nonce' ),
					'__No_Form_ID' => __( "Failed to determine form ID", 'pdf-forms-for-woocommerce' ),
					'__No_Preload_Data' => __( 'Failed to load PDF form data', 'pdf-forms-for-woocommerce' ),
					'__Unknown_error' => __( 'Unknown error', 'pdf-forms-for-woocommerce' ),
					'__Confirm_Delete_Attachment' => __( 'Are you sure you want to delete this file?  This will delete field mappings and image embeds associated with this file.', 'pdf-forms-for-woocommerce' ),
					'__Confirm_Delete_Mapping' => __( 'Are you sure you want to delete this mapping?', 'pdf-forms-for-woocommerce' ),
					'__Confirm_Delete_All_Mappings' => __( 'Are you sure you want to delete all mappings?', 'pdf-forms-for-woocommerce' ),
					'__Confirm_Attach_Empty_Pdf' => __( 'Are you sure you want to attach a PDF file without any form fields?', 'pdf-forms-for-woocommerce' ),
					'__Confirm_Delete_Embed' => __( 'Are you sure you want to delete this embeded image?', 'pdf-forms-for-woocommerce' ),
					'__Show_Help' => __( 'Show Help', 'pdf-forms-for-woocommerce' ),
					'__Hide_Help' => __( 'Hide Help', 'pdf-forms-for-woocommerce' ),
					'__PDF_Frame_Title' => __( 'Select a PDF file', 'pdf-forms-for-woocommerce'),
					'__PDF_Frame_Button' => __( 'Select', 'pdf-forms-for-woocommerce'),
					'__Custom_String' => __( "Custom text string...", 'pdf-forms-for-woocommerce' ),
					'__All_PDFs' => __( 'All PDFs', 'pdf-forms-for-woocommerce' ),
					'__All_Pages' => __( 'All', 'pdf-forms-for-woocommerce' ),
					'__PDF_Field_Type_Unsupported' => __( 'PDF field type has no equivalent in WooCommerce', 'pdf-forms-for-woocommerce' ),
					'__Default_Notification' => __( 'Default Notification', 'pdf-forms-for-woocommerce' ),
					'__Default_Confirmation' => __( 'Default Confirmation', 'pdf-forms-for-woocommerce' ),
					'__Null_Value_Mapping' => __( '--- EMPTY ---', 'pdf-forms-for-woocommerce' ),
				) );
				
				wp_enqueue_media();
				
				wp_enqueue_script( 'pdf_forms_for_woocommerce_admin_script' );
				wp_enqueue_style( 'pdf_forms_for_woocommerce_admin_style' );
				
				wp_enqueue_script( 'pdf_forms_filler_spinner_script' );
				wp_enqueue_style( 'pdf_forms_filler_spinner_style' );
			}
			
			if( $service = $this->get_service() )
			{
				$service->admin_enqueue_scripts( $hook );
				if( $service != $this->pdf_ninja_service )
					$this->pdf_ninja_service->admin_enqueue_scripts( $hook );
			}
		}
		
		/**
		 * Used for retreiving PDF file information in wp-admin interface
		 */
		public function wp_ajax_get_attachment_data()
		{
			try
			{
				if( ! check_ajax_referer( 'pdf-forms-for-woocommerce-ajax-nonce', 'nonce', false ) )
					throw new Exception( __( "Nonce mismatch", 'pdf-forms-for-woocommerce' ) );
				
				$attachment_id = isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : null;
				
				if( ! $attachment_id )
					throw new Exception( __( "Invalid attachment ID", 'pdf-forms-for-woocommerce' ) );
				
				if( ! current_user_can( 'edit_post', $attachment_id ) )
					throw new Exception( __( "Permission denied", 'pdf-forms-for-woocommerce' ) );
				
				if( ( $filepath = get_attached_file( $attachment_id ) ) !== false
				&& ( $mimetype = self::get_mime_type( $filepath ) ) != null
				&& $mimetype !== 'application/pdf' )
					throw new Exception(
						self::replace_tags(
							__( "File type {mime-type} of {file} is unsupported for {purpose}", 'pdf-forms-for-woocommerce' ),
							array( 'mime-type' => $mimetype, 'file' => wp_basename( $filepath ), 'purpose' => __("PDF form filling", 'pdf-forms-for-woocommerce') )
						)
					);
				
				$info = $this->get_info( $attachment_id );
				$info['fields'] = $this->query_pdf_fields( $attachment_id );
				
				return wp_send_json( array(
					'success' => true,
					'attachment_id' => $attachment_id,
					'filename' => wp_basename( $filepath ),
					'info' => $info,
				) );
			}
			catch( Exception $e )
			{
				return wp_send_json( array(
					'success' => false,
					'error_message' => $e->getMessage(),
					'error_location' => wp_basename( $e->getFile() ) . ":" . $e->getLine(),
				) );
			}
		}
		
		/**
		 * Returns (and computes, if necessary) the md5 sum of the media file
		 */
		public static function get_attachment_md5sum( $attachment_id )
		{
			$md5sum = self::get_meta( $attachment_id, 'md5sum' );
			if( ! $md5sum )
				return self::update_attachment_md5sum( $attachment_id );
			else
				return $md5sum;
		}
		
		/**
		 * Computes, saves and returns the md5 sum of the media file
		 */
		public static function update_attachment_md5sum( $attachment_id )
		{
			// clear info cache
			self::unset_meta( $attachment_id, 'info' );
			
			// delete page snapshots
			$args = array(
				'post_parent' => $attachment_id,
				'meta_key' => 'pdf-forms-for-woocommerce-page',
				'post_type' => 'attachment',
				'post_status' => 'any',
				'posts_per_page' => -1,
			);
			foreach( get_posts( $args ) as $post )
				wp_delete_post( $post->ID, $force_delete = true );
			
			$filepath = get_attached_file( $attachment_id );
			
			if( $filepath !== false && is_readable( $filepath ) !== false )
				$md5sum = @md5_file( $filepath );
			else
			{
				$fileurl = wp_get_attachment_url( $attachment_id );
				if( $fileurl === false )
					throw new Exception( __( "Attachment file is not accessible", 'pdf-forms-for-woocommerce' ) );
				
				try
				{
					$temp_filepath = wp_tempnam();
					self::download_file( $fileurl, $temp_filepath ); // can throw an exception
					$md5sum = @md5_file( $temp_filepath );
					@unlink( $temp_filepath );
				}
				catch(Exception $e)
				{
					@unlink( $temp_filepath );
					throw $e;
				}
			}
			
			if( $md5sum === false )
				throw new Exception( __( "Could not read attached file", 'pdf-forms-for-woocommerce' ) );
			
			return self::set_meta( $attachment_id, 'md5sum', $md5sum );
		}
		
		/**
		 * Caching wrapper for $service->api_get_info()
		 */
		public function get_info( $attachment_id )
		{
			// cache
			if( ( $info = self::get_meta( $attachment_id, 'info' ) )
			&& ( $old_md5sum = self::get_meta( $attachment_id, 'md5sum' ) ) )
			{
				// use cache only if file is locally accessible
				$filepath = get_attached_file( $attachment_id );
				if( $filepath !== false && is_readable( $filepath ) !== false )
				{
					$new_md5sum = md5_file( $filepath );
					if( $new_md5sum !== false && $new_md5sum === $old_md5sum )
						return json_decode( $info, true );
					else
						self::update_attachment_md5sum( $attachment_id );
				}
			}
			
			$service = $this->get_service();
			if( !$service )
				throw new Exception( __( "No service", 'pdf-forms-for-woocommerce' ) );
			
			$info = $service->api_get_info( $attachment_id );
			
			// set up array keys so it is easier to search
			$fields = array();
			foreach( $info['fields'] as $field )
				$fields[$field['name']] = $field;
			$info['fields'] = $fields;
			
			$pages = array();
			foreach( $info['pages'] as $page )
				$pages[$page['number']] = $page;
			$info['page'] = $pages;
			
			// set fields cache
			self::set_meta( $attachment_id, 'info', Pdf_Forms_For_WooCommerce_Wrapper::json_encode( $info ) );
			
			return $info;
		}
		
		/**
		 * Caches and returns fields for an attachment
		 */
		public function get_fields( $attachment_id )
		{
			$info = $this->get_info( $attachment_id );
			return $info['fields'];
		}
		
		/**
		 * Helper function used in wp-admin interface
		 */
		private function query_pdf_fields( $attachment_id )
		{
			$fields = $this->get_fields( $attachment_id );
			
			foreach( $fields as $id => &$field )
			{
				if( !isset( $field['name'] ) )
				{
					unset( $fields[$id] );
					continue;
				}
				
				$name = strval( $field['name'] );
				$field['id'] = self::base64url_encode( $name );
			}
			
			return $fields;
		}
		
		/**
		 * Downloads and caches PDF page images, returns image attachment id
		 */
		public function get_pdf_snapshot( $attachment_id, $page )
		{
			$args = array(
				'post_parent' => $attachment_id,
				'meta_key' => 'pdf-forms-for-woocommerce-page',
				'meta_value' => $page,
				'post_type' => 'attachment',
				'post_status' => 'any',
				'posts_per_page' => 1,
			);
			$posts = get_posts( $args );
			
			if( count( $posts ) > 0 )
			{
				$old_attachment_id = reset( $posts )->ID;
				return $old_attachment_id;
			}
			
			if( ! ( ( $wp_upload_dir = wp_upload_dir() ) && false === $wp_upload_dir['error'] ) )
				throw new Exception( $wp_upload_dir['error'] );
			
			$attachment_path = get_attached_file( $attachment_id );
			
			if( $attachment_path === false )
				$attachment_path = wp_get_attachment_url( $attachment_id );
			if( $attachment_path === false )
				$attachment_path = "unknown";
			
			$filename = wp_unique_filename( $wp_upload_dir['path'], wp_basename( $attachment_path ).'.page'.intval($page).'.jpg' );
			$filepath = trailingslashit( $wp_upload_dir['path'] ) . $filename;
			
			$service = $this->get_service();
			if( $service )
				$service->api_image( $filepath, $attachment_id, $page );
			
			$mimetype = self::get_mime_type( $filepath );
			
			$attachment = array(
				'guid'           => $wp_upload_dir['url'] . '/' . $filename,
				'post_mime_type' => $mimetype,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);
			
			$new_attachment_id = wp_insert_attachment( $attachment, $filepath, $attachment_id );
			
			self::set_meta( $new_attachment_id, 'page', $page );
			
			return $new_attachment_id;
		}
		
		/**
		 * Used for getting PDF page images in wp-admin interface
		 */
		public function wp_ajax_query_page_image()
		{
			try
			{
				if ( ! check_ajax_referer( 'pdf-forms-for-woocommerce-ajax-nonce', 'nonce', false ) )
					throw new Exception( __( "Nonce mismatch", 'pdf-forms-for-woocommerce' ) );
				
				$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : null;
				$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : null;
				
				if ( $page < 1 )
					throw new Exception( __( "Invalid page number", 'pdf-forms-for-woocommerce' ) );
				
				if( ! current_user_can( 'edit_post', $attachment_id ) )
					throw new Exception( __( "Permission denied", 'pdf-forms-for-woocommerce' ) );
				
				$attachment_id = $this->get_pdf_snapshot( $attachment_id, $page );
				$snapshot = wp_get_attachment_image_src( $attachment_id, array( 500, 500 ) );
				
				if( !$snapshot || !is_array( $snapshot ) )
					throw new Exception( __( "Failed to retrieve page snapshot", 'pdf-forms-for-woocommerce' ) );
				
				return wp_send_json( array(
					'success' => true,
					'snapshot' => reset( $snapshot ),
				) );
			}
			catch( Exception $e )
			{
				return wp_send_json( array(
					'success' => false,
					'error_message' => $e->getMessage(),
					'error_location' => wp_basename( $e->getFile() ) . ":". $e->getLine(),
				) );
			}
		}
		
		/**
		 * Helper functions for encoding/decoding field names
		 */
		public static function base64url_encode( $data )
		{
			return rtrim( strtr( base64_encode( $data ), '+/', '._' ), '=' );
		}
		public static function base64url_decode( $data )
		{
			return base64_decode( str_pad( strtr( $data, '._', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) );
		}
		
		/*
		 * Parses data URI
		 */
		public static function parse_data_uri( $uri )
		{
			if( ! preg_match( '/data:([a-zA-Z-\/+.]*)((;[a-zA-Z0-9-_=.+]+)*),(.*)/', $uri, $matches ) )
				return false;
			
			$data = $matches[4];
			$mime = $matches[1] ? $matches[1] : null;
			
			$base64 = false;
			foreach( explode( ';', $matches[2] ) as $ext )
				if( $ext == "base64" )
				{
					$base64 = true; 
					if( ! ( $data = base64_decode( $data, $strict=true ) ) )
						return false;
				}
			
			if( ! $base64 )
				$data = rawurldecode( $data );
			
			return array(
				'data' => $data,
				'mime' => $mime,
			);
		}
	}
	
	Pdf_Forms_For_WooCommerce::get_instance();
}
