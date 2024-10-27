<?php
	
	if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
	
	require_once( untrailingslashit( __DIR__ ) . '/service.php' );
	require_once( untrailingslashit( __DIR__ ) . '/../wrapper.php' );
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Pdf_Ninja', false ) )
	{
		class Pdf_Forms_For_WooCommerce_Pdf_Ninja extends Pdf_Forms_For_WooCommerce_Service
		{
			private static $instance = null;
			
			private $key = null;
			private $error = null;
			private $api_url = null;
			private $api_version = null;
			private $ignore_ssl_errors = null;
			
			const API_URL = 'https://pdf.ninja';
			
			/*
			* Runs after all plugins have been loaded
			*/
			public function plugin_init()
			{
				
			}
			
			/**
			 * Add Pdf.Ninja to settings page
			 */
			public function register_settings()
			{
				try { $key = self::get_instance()->get_key(); }
				catch(Exception $e) { } // ignore errors
				
				$settings = array(
					'id' => 'pdf-ninja',
					'title' => __( 'Pdf.Ninja API', 'pdf-forms-for-woocommerce' ),
					'settings' =>
						array(
							array(
								'title' => __( 'Pdf.Ninja API', 'pdf-forms-for-woocommerce' ),
								'type' => 'title',
								'desc' =>  __( 'The following form allows you to edit your API settings.', 'pdf-forms-for-woocommerce' ),
								'is_option' => false,
							),
							array(
								'title' => __( 'API Key', 'pdf-forms-for-woocommerce' ),
								'type' => 'text',
								'desc' => Pdf_Forms_For_WooCommerce::render( 'copy-key',
									array(
										'key-copy-btn-label' => esc_html__( 'copy key', 'pdf-forms-for-woocommerce' ),
									)
								),
								'default' => $key,
								'class' => 'pdf-ninja-key',
								'id' => 'pdf-forms-for-woocommerce-settings-pdf-ninja-api-key'
							),
							array(
								'title' => __( 'Get new API key', 'pdf-forms-for-woocommerce' ),
								'type' => 'pdf-forms-for-woocommerce-setting-html',
								'callback' => function()
									{
										print(
											Pdf_Forms_For_WooCommerce::render( 'spinner' ) .
											Pdf_Forms_For_WooCommerce::render( 'new-key',
												array(
													'title' => esc_html__( 'Get new API key', 'pdf-forms-for-woocommerce' ),
													'admin-email' => esc_html( self::get_instance()->get_admin_email() ),
													'get-new-key-label' => esc_html__( 'Get New Key', 'pdf-forms-for-woocommerce' ),
												)
											)
										);
									},
								'is_option' => false,
							),
							array(
								'title' => __( 'API URL', 'pdf-forms-for-woocommerce' ),
								'type' => 'text',
								'desc' =>  __( 'Enter your API URL', 'pdf-forms-for-woocommerce' ),
								'desc_tip' =>  true,
								'default' => self::API_URL,
								'id' => 'pdf-forms-for-woocommerce-settings-pdf-ninja-api-url'
							),
							array(
								'title' => __( 'API version', 'pdf-forms-for-woocommerce' ),
								'type' => 'radio',
								'desc' =>  __( 'Select API version', 'pdf-forms-for-woocommerce' ),
								'desc_tip' =>  true,
								'options' => array(
									'1' => 'Version 1',
									'2' => 'Version 2'
								),
								'default' => '2',
								'id' => 'pdf-forms-for-woocommerce-settings-pdf-ninja-api-version'
							),
							array(
								'title' => __( 'Data Security', 'pdf-forms-for-woocommerce' ),
								'type' => 'checkbox',
								'desc' =>  __( 'Ignore SSL errors', 'pdf-forms-for-woocommerce' ),
								'default' => 'no',
								'id' => 'pdf-forms-for-woocommerce-settings-pdf-ninja-ignore-ssl-errors'
							),
							array(
								'type' => 'sectionend',
								'is_option' => false,
							)
						)
					);
				
				Pdf_Forms_For_WooCommerce_Settings_Page::get_instance()->add_section( $settings );
			}
			
			/**
			 * Adds necessary admin scripts and styles
			 */
			public function admin_enqueue_scripts( $hook )
			{
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The following code does not do anything requiring security checks
				if( false !== strpos( $hook, 'wc-settings' ) && isset( $_GET['tab'] ) && $_GET['tab'] == "pdf-forms-for-woocommerce-settings" )
				{
					wp_register_script( 'pdf_forms_for_woocommerce_copy_key_script', plugins_url( '../../js/copy-key.js', __FILE__ ), array( 'jquery' ), Pdf_Forms_For_WooCommerce::VERSION );
					wp_localize_script( 'pdf_forms_for_woocommerce_copy_key_script', 'pdf_forms_for_woocommerce_copy_key', array(
						'__key_copy_btn_label' => esc_html__( 'copy key', 'pdf-forms-for-woocommerce' ),
						'__key_copied_btn_label' => esc_html__( 'copied!', 'pdf-forms-for-woocommerce' ),
					) );
					wp_enqueue_script( 'pdf_forms_for_woocommerce_copy_key_script' );
					
					wp_enqueue_script( 'pdf_forms_filler_spinner_script' );
					wp_enqueue_style( 'pdf_forms_filler_spinner_style' );
					
					wp_register_script( 'pdf_forms_for_woocommerce_new_key_script', plugins_url( '../../js/new-key.js', __FILE__ ), array( 'jquery', 'pdf_forms_filler_spinner_script' ), Pdf_Forms_For_WooCommerce::VERSION );
					wp_localize_script( 'pdf_forms_for_woocommerce_new_key_script', 'pdf_forms_for_woocommerce_new_key', array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'ajax_nonce' => wp_create_nonce( 'pdf-forms-for-woocommerce-ajax-nonce' ),
						'__Unknown_error' => __( 'Unknown error', 'pdf-forms-for-woocommerce' ),
					) );
					wp_enqueue_script( 'pdf_forms_for_woocommerce_new_key_script' );
				}
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
			 * Returns (and initializes, if necessary) the current API key
			 */
			public function get_key()
			{
				if( $this->key )
					return $this->key;
				
				if( ! $this->key )
					$this->key = get_option( 'pdf-forms-for-woocommerce-settings-pdf-ninja-api-key', null );
				
				if( ! $this->key )
				{
					// attempt to get the key from another plugin
					$key = $this->get_external_key();
					if( $key )
						$this->set_key( $key );
				}
				
				if( ! $this->key )
				{
					// don't try to get the key from the API on every page load!
					$fail = get_transient( 'pdf_forms_for_woocommerce_pdfninja_key_failure' );
					if( $fail )
						throw new Exception( __( "Failed to get the Pdf.Ninja API key on last attempt.", 'pdf-forms-for-woocommerce' ) );
					
					// create new key if it hasn't yet been set
					try { $key = $this->generate_key(); }
					catch (Exception $e)
					{
						set_transient( 'pdf_forms_for_woocommerce_pdfninja_key_failure', true, 12 * HOUR_IN_SECOND );
						throw $e;
					}
					
					$this->set_key( $key );
				}
				
				return $this->key;
			}
			
			/**
			 * Sets the API key
			 */
			public function set_key( $value )
			{
				$this->key = $value;
				update_option( 'pdf-forms-for-woocommerce-settings-pdf-ninja-api-key', $value );
				delete_transient( 'pdf_forms_for_woocommerce_pdfninja_key_failure' );
				return true;
			}
			
			/**
			 * Searches for key in other plugins
			 */
			public function get_external_key()
			{
				// from PDF Forms Filler for CF7
				$option = get_option( 'wpcf7' );
				if( $option !== false && is_array( $option ) && isset( $option['wpcf7_pdf_forms_pdfninja_key'] ) )
					return $option['wpcf7_pdf_forms_pdfninja_key'];
				
				// from PDF Forms Filler for WPForms
				$option = get_option( 'wpforms_settings' );
				if( $option !== false && is_array( $option ) && isset( $option['pdf-ninja-api_key'] ) )
					return $option['pdf-ninja-api_key'];
				
				return null;
			}
			
			/**
			 * Determines administrator's email address (for use with requesting a new key from the API)
			 */
			public function get_admin_email()
			{
				$current_user = wp_get_current_user();
				if( ! $current_user )
					return null;
				
				$email = sanitize_email( $current_user->user_email );
				if( ! $email )
					return null;
				
				return $email;
			}
			
			/**
			 * Requests a key from the API server
			 */
			public function generate_key( $email = null )
			{
				if( $email === null )
					$email = $this->get_admin_email();
				
				if( $email === null )
					throw new Exception( __( "Failed to determine the administrator's email address.", 'pdf-forms-for-woocommerce' ) );
				
				$key = null;
				
				// try to get the key the normal way
				try { $key = $this->api_get_key( $email ); }
				catch( Exception $e )
				{
					// if we are not running for the first time, throw an error
					$old_key = get_option( 'pdf-forms-for-woocommerce-settings-pdf-ninja-api-key', false );
					if( $old_key )
						throw $e;
					
					// there might be an issue with certificate verification on this system, disable it and try again
					$this->set_ignore_ssl_errors( true );
					try { $key = $this->api_get_key( $email ); }
					catch( Exception $e )
					{
						// if it still fails, revert and throw
						$this->set_ignore_ssl_errors( false );
						throw $e;
					}
				}
				
				return $key;
			}
			
			/**
			 * Returns (and initializes, if necessary) the current API URL
			 */
			public function get_api_url()
			{
				if( ! $this->api_url )
					$this->api_url = get_option( 'pdf-forms-for-woocommerce-settings-pdf-ninja-api-url', self::API_URL );
				
				return $this->api_url;
			}
			
			/**
			 * Returns (and initializes, if necessary) the api version setting
			 */
			public function get_api_version()
			{
				if( $this->api_version === null )
				{
					$value = get_option( 'pdf-forms-for-woocommerce-settings-pdf-ninja-api-version', '2' );
					if( $value == '1' ) $this->api_version = 1;
					if( $value == '2' ) $this->api_version = 2;
				}
				
				return $this->api_version;
			}
			
			/**
			 * Returns (and initializes, if necessary) the ignore ssl errors setting
			 */
			public function get_ignore_ssl_errors()
			{
				if( $this->ignore_ssl_errors === null )
					$this->ignore_ssl_errors = get_option( 'pdf-forms-for-woocommerce-settings-pdf-ninja-ignore-ssl-errors', 'no' ) == 'yes';
				
				return $this->ignore_ssl_errors;
			}
			
			/**
			 * Sets the ignore ssl errors setting
			 */
			public function set_ignore_ssl_errors( $value )
			{
				$this->ignore_ssl_errors = $value;
				update_option( 'pdf-forms-for-woocommerce-settings-pdf-ninja-ignore-ssl-errors', $value ? 'yes' : 'no' );
				return true;
			}
			
			/**
			 * Generates common set of arguments to be used with remote http requests
			 */
			private function wp_remote_args()
			{
				return array(
					'headers'     => array(
						'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
						'Referer' => home_url(),
					),
					'compress'    => true,
					'decompress'  => true,
					'timeout'     => 300,
					'redirection' => 5,
					'user-agent'  => 'pdf-forms-for-woocommerce/' . Pdf_Forms_For_WooCommerce::VERSION,
					'sslverify'   => !$this->get_ignore_ssl_errors(),
				);
			}
			
			/**
			 * Helper function for processing the API response
			 */
			private function api_process_response( $response )
			{
				if( is_wp_error( $response ) )
				{
					$errors = $response->get_error_messages();
					foreach($errors as &$error)
						if( stripos( $error, 'cURL error 7' ) !== false )
							$error = Pdf_Forms_For_WooCommerce::replace_tags(
									__( "Failed to connect to {url}", 'pdf-forms-for-woocommerce' ),
									array( 'url' => $this->get_api_url() )
								);
					throw new Exception( implode( ', ', $errors ) );
				}
				
				$body = wp_remote_retrieve_body( $response );
				$content_type = wp_remote_retrieve_header( $response, 'content-type' );
				
				if( strpos($content_type, 'application/json' ) !== FALSE )
				{
					$result = json_decode( $body , true );
					
					if( ! $result || ! is_array( $result ) )
						throw new Exception( __( "Failed to decode API server response", 'pdf-forms-for-woocommerce' ) );
					
					if( ! isset( $result['success'] ) || ( $result['success'] === false && ! isset( $result['error'] ) ) )
						throw new Exception( __( "Pdf.Ninja API server did not send an expected response", 'pdf-forms-for-woocommerce' ) );
					
					if( $result['success'] === false )
						throw new Pdf_Forms_For_WooCommerce_Pdf_Ninja_Exception( $result );
					
					if( $result['success'] == true && isset( $result['fileUrl'] ) )
					{
						$args2 = $this->wp_remote_args();
						$args2['timeout'] = 100;
						$response2 = wp_remote_get( $result['fileUrl'], $args2 );
						if( is_wp_error( $response2 ) )
							throw new Exception( __( "Failed to download a file from the API server", 'pdf-forms-for-woocommerce' ) );
						
						$result['content_type'] = wp_remote_retrieve_header( $response2, 'content-type' );
						$result['content'] = wp_remote_retrieve_body( $response2 );
					}
				}
				else
				{
					if( wp_remote_retrieve_response_code( $response ) < 400 )
						$result = array(
							'success' => true,
							'content_type' => $content_type,
							'content' => $body,
						);
					else
						$result = array(
							'success' => false,
							'error' => wp_remote_retrieve_response_message( $response ),
						);
				}
				
				return $result;
			}
			
			/**
			 * Helper function that retries GET request if the file needs to be re-uploaded or md5 sum recalculated
			 */
			private function api_get_retry_attachment( $attachment_id, $endpoint, $params )
			{
				try
				{
					return $this->api_get( $endpoint, $params );
				}
				catch( Pdf_Forms_For_WooCommerce_Pdf_Ninja_Exception $e )
				{
					$reason = $e->getReason();
					if( $reason == 'noSuchFileId' || $reason == 'md5sumMismatch' )
					{
						if( $this->is_local_attachment( $attachment_id ) )
							$this->api_upload_file( $attachment_id );
						else
							// update local md5sum
							$params['md5sum'] = Pdf_Forms_For_WooCommerce::update_attachment_md5sum( $attachment_id );
						
						return $this->api_get( $endpoint, $params );
					}
					throw $e;
				}
			}
			
			/*
			* Helper function that retries POST request if the file needs to be re-uploaded or md5 sum recalculated
			*/
			private function api_post_retry_attachment( $attachment_id, $endpoint, $payload, $headers = array(), $args_override = array() )
			{
				try
				{
					return $this->api_post( $endpoint, $payload, $headers, $args_override );
				}
				catch( Pdf_Forms_For_WooCommerce_Pdf_Ninja_Exception $e )
				{
					$reason = $e->getReason();
					if( $reason == 'noSuchFileId' || $reason == 'md5sumMismatch' )
					{
						if( $this->is_local_attachment( $attachment_id ) )
							$this->api_upload_file( $attachment_id );
						else
							// update local md5sum
							$params['md5sum'] = Pdf_Forms_For_WooCommerce::update_attachment_md5sum( $attachment_id );
						
						return $this->api_post( $endpoint, $payload, $headers, $args_override );
					}
					throw $e;
				}
			}
			
			/*
			* Helper function for communicating with the API via the GET request
			*/
			private function api_get( $endpoint, $params )
			{
				$url = add_query_arg( $params, $this->get_api_url() . "/api/v" . $this->get_api_version() . "/" . $endpoint );
				$response = wp_remote_get( $url, $this->wp_remote_args() );
				return $this->api_process_response( $response );
			}
			
			/*
			* Helper function for communicating with the API via the POST request
			*/
			private function api_post( $endpoint, $payload, $headers = array(), $args_override = array() )
			{
				$args = $this->wp_remote_args();
				
				$args['body'] = $payload;
				
				if( is_array( $headers ) )
					foreach( $headers as $key => $value )
						$args['headers'][$key] = $value;
				
				if( is_array( $args_override ) )
					foreach( $args_override as $key => $value )
						$args[$key] = $value;
				
				$url = $this->get_api_url() . "/api/v" . $this->get_api_version() . "/" . $endpoint;
				$response = wp_remote_post( $url, $args );
				return $this->api_process_response( $response );
			}
			
			/**
			 * Communicates with the API server to get a new key
			 */
			public function api_get_key( $email )
			{
				$result = $this->api_get( 'key', array( 'email' => $email ) );
				
				if( ! isset( $result['key'] ) )
					throw new Exception( __( "Pdf.Ninja API server did not send an expected response", 'pdf-forms-for-woocommerce' ) );
				
				return $result['key'];
			}
			
			/**
			 * Generates and returns file id to be used with the API server
			 */
			private function get_file_id( $attachment_id )
			{
				$file_id = Pdf_Forms_For_WooCommerce::get_metadata( $attachment_id, 'pdf.ninja-file_id' );
				if( ! $file_id )
				{
					$file_id = substr( $attachment_id . "-" . get_site_url(), 0, 40 );
					Pdf_Forms_For_WooCommerce::set_metadata( $attachment_id, 'pdf.ninja-file_id', $file_id );
				}
				return substr( $file_id, 0, 40 );
			}
			
			/**
			 * Returns true if file hasn't yet been uploaded to the API server
			 */
			private function is_new_file( $attachment_id )
			{
				return Pdf_Forms_For_WooCommerce::get_metadata( $attachment_id, 'pdf.ninja-file_id' ) == null;
			}
			
			/**
			 * Returns true if attachment file is on the local file system
			 */
			private function is_local_attachment( $attachment_id )
			{
				$filepath = get_attached_file( $attachment_id );
				return $filepath !== false && is_readable( $filepath ) !== false;
			}
			
			/*
			* Returns file URL to be used with the API server
			*/
			private function get_file_url( $attachment_id )
			{
				$fileurl = wp_get_attachment_url( $attachment_id );
				
				if( $fileurl === false )
					throw new Exception( __( "Unknown attachment URL", 'pdf-forms-for-woocommerce' ) );
				
				return $fileurl;
			}
			
			/*
			* Communicates with the API to upload the media file
			*/
			public function api_upload_file( $attachment_id )
			{
				$md5sum = Pdf_Forms_For_WooCommerce::update_attachment_md5sum( $attachment_id );
				
				$params = array(
					'fileId' => $this->get_file_id( $attachment_id ),
					'md5sum' => $md5sum,
					'key'    => $this->get_key(),
				);
				
				$boundary = wp_generate_password( 48, $special_chars = false, $extra_special_chars = false );
				
				$payload = "";
				
				foreach( $params as $name => $value )
					$payload .= "--{$boundary}\r\n"
							. "Content-Disposition: form-data; name=\"{$name}\"\r\n"
							. "\r\n"
							. "{$value}\r\n";
				
				if( ! $this->is_local_attachment( $attachment_id ) )
					throw new Exception( __( "File is not accessible in the local file system", 'pdf-forms-for-woocommerce' ) );
				
				$filepath = get_attached_file( $attachment_id );
				$filename = wp_basename( $filepath );
				$filecontents = file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- $filepath is a local filesystem path and can't be used with wp_remote_get()
				
				$payload .= "--{$boundary}\r\n"
						. "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n"
						. "Content-Type: application/octet-stream\r\n"
						. "\r\n"
						. "{$filecontents}\r\n";
				
				$payload .= "--{$boundary}--";
				
				$headers  = array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary );
				$args = array( 'timeout' => 300 );
				
				$result = $this->api_post( 'file', $payload, $headers, $args );
				
				if( $result['success'] != true )
					throw new Exception( $result['error'] );
				
				return true;
			}
			
			/**
			 * Helper function for communicating with the API to obtain the PDF file fields
			 */
			public function api_get_info_helper( $endpoint, $attachment_id )
			{
				$params = array(
					'md5sum' => Pdf_Forms_For_WooCommerce::get_attachment_md5sum( $attachment_id ),
					'key'    => $this->get_key(),
				);
				
				if( $this->is_local_attachment( $attachment_id ) )
				{
					if( $this->is_new_file( $attachment_id ) )
						$this->api_upload_file( $attachment_id );
					$params['fileId'] = $this->get_file_id( $attachment_id );
				}
				else
					$params['fileUrl'] = $this->get_file_url( $attachment_id );
				
				return $this->api_get_retry_attachment( $attachment_id, $endpoint, $params );
			}
			
			/**
			 * Communicates with the API to obtain the PDF file fields
			 */
			public function api_get_fields( $attachment_id )
			{
				$result = $this->api_get_info_helper( 'fields', $attachment_id );
				
				if( ! isset( $result['fields'] ) || ! is_array( $result['fields'] ) )
					throw new Exception( __( "Pdf.Ninja API server did not send an expected response", 'pdf-forms-for-woocommerce' ) );
				
				return $result['fields'];
			}
			
			/**
			 * Communicates with the API to obtain the PDF file information
			 */
			public function api_get_info( $attachment_id )
			{
				$result = $this->api_get_info_helper( 'info', $attachment_id );
				
				if( ! isset( $result['fields'] ) || ! isset( $result['pages'] ) || ! is_array( $result['fields'] ) || ! is_array( $result['pages'] ) )
					throw new Exception( __( "Pdf.Ninja API server did not send an expected response", 'pdf-forms-for-woocommerce' ) );
				
				unset( $result['success'] );
				
				return $result;
			}
			
			/**
			 * Communicates with the API to get image of PDF pages
			 */
			public function api_image( $destfile, $attachment_id, $page )
			{
				$params = array(
					'md5sum' => Pdf_Forms_For_WooCommerce::get_attachment_md5sum( $attachment_id ),
					'key'    => $this->get_key(),
					'type'   => 'jpeg',
					'page'   => intval($page),
					'dumpFile' => true,
				);
				
				if( $this->is_local_attachment( $attachment_id ) )
				{
					if( $this->is_new_file( $attachment_id ) )
						$this->api_upload_file( $attachment_id );
					$params['fileId'] = $this->get_file_id( $attachment_id );
				}
				else
					$params['fileUrl'] = $this->get_file_url( $attachment_id );
				
				$result = $this->api_get_retry_attachment( $attachment_id, 'image', $params );
				
				if( ! isset( $result['content'] ) || ! isset( $result['content_type'] ) || $result['content_type'] != 'image/jpeg' )
					throw new Exception( __( "Pdf.Ninja API server did not send an expected response", 'pdf-forms-for-woocommerce' ) );
				
				if( file_put_contents( $destfile, $result['content'] ) === false || ! is_file( $destfile ) )
					throw new Exception( __( "Failed to create file", 'pdf-forms-for-woocommerce' ) );
				
				return true;
			}
			
			/**
			 * Helper function for communicating with the API to generate PDF file
			 */
			private function api_pdf_helper( $destfile, $endpoint, $attachment_id, $data, $embeds, $options )
			{
				if( ! is_array ( $data ) )
					$data = array();
				
				if( ! is_array ( $embeds ) )
					$embeds = array();
				
				if( ! is_array ( $options ) )
					$options = array();
				
				// prepare files and embed params
				$files = array();
				foreach( $embeds as $key => $embed )
				{
					$filepath = $embed['image'];
					if( !is_readable( $filepath ) )
					{
						unset( $embeds[$key] );
						continue;
					}
					$files[$filepath] = $filepath;
				}
				$files = array_values( $files );
				foreach( $embeds as &$embed )
				{
					$filepath = $embed['image'];
					$id = array_search($filepath, $files, $strict=true);
					if($id === FALSE)
						continue;
					$embed['image'] = $id;
				}
				unset($embed);
				
				$encoded_data = Pdf_Forms_For_WooCommerce_Wrapper::json_encode( $data );
				if( $encoded_data === FALSE || $encoded_data === null )
					throw new Exception( __( "Failed to encode JSON data", 'pdf-forms-for-woocommerce' ) );
				
				$encoded_embeds = Pdf_Forms_For_WooCommerce_Wrapper::json_encode( $embeds );
				if( $encoded_embeds === FALSE || $encoded_embeds === null )
					throw new Exception( __( "Failed to encode JSON data", 'pdf-forms-for-woocommerce' ) );
				
				$params = array(
					'md5sum'   => Pdf_Forms_For_WooCommerce::get_attachment_md5sum( $attachment_id ),
					'key'      => $this->get_key(),
					'data'     => $encoded_data,
					'embeds'   => $encoded_embeds,
					'dumpFile' => true,
				);
				
				if( $this->is_local_attachment( $attachment_id ) )
				{
					if( $this->is_new_file( $attachment_id ) )
						$this->api_upload_file( $attachment_id );
					$params['fileId'] = $this->get_file_id( $attachment_id );
				}
				else
					$params['fileUrl'] = $this->get_file_url( $attachment_id );
				
				foreach( $options as $key => $value )
				{
					if( $key == 'flatten' )
						$params[$key] = $value;
				}
				
				$boundary = wp_generate_password( 48, $special_chars = false, $extra_special_chars = false );
				
				$payload = "";
				
				foreach( $params as $name => $value )
					$payload .= "--{$boundary}\r\n"
							. "Content-Disposition: form-data; name=\"{$name}\"\r\n"
							. "\r\n"
							. "{$value}\r\n";
				
				foreach( $files as $fileId => $filepath )
				{
					$filename = wp_basename( $filepath );
					$filecontents = file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- $filepath is a local filesystem path and can't be used with wp_remote_get()
					
					$payload .= "--{$boundary}\r\n"
							. "Content-Disposition: form-data; name=\"images[{$fileId}]\"; filename=\"{$filename}\"\r\n"
							. "Content-Type: application/octet-stream\r\n"
							. "\r\n"
							. "{$filecontents}\r\n";
				}
				
				$payload .= "--{$boundary}--";
				
				$headers  = array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary );
				$args = array( 'timeout' => 300 );
				
				$result = $this->api_post_retry_attachment( $attachment_id, $endpoint, $payload, $headers, $args );
				
				if( ! isset( $result['content'] ) || ! isset( $result['content_type'] ) || $result['content_type'] != 'application/pdf' )
					throw new Exception( __( "Pdf.Ninja API server did not send an expected response", 'pdf-forms-for-woocommerce' ) );
				
				if( file_put_contents( $destfile, $result['content'] ) === false || ! is_file( $destfile ) )
					throw new Exception( __( "Failed to create file", 'pdf-forms-for-woocommerce' ) );
				
				return true;
			}
			
			/**
			 * Communicates with the API to fill fields in the PDF file
			 */
			public function api_fill( $destfile, $attachment_id, $data, $options = array() )
			{
				return $this->api_pdf_helper( $destfile, 'fill', $attachment_id, $data, array(), $options );
			}
			
			/*
			* Communicates with the API to fill fields in the PDF file
			*/
			public function api_fill_embed( $destfile, $attachment_id, $data, $embeds, $options = array() )
			{
				return $this->api_pdf_helper( $destfile, 'fillembed', $attachment_id, $data, $embeds, $options );
			}
			
			/**
			 * This function gets called to display admin notices
			 */
			public function admin_notices()
			{
				try { $this->get_key(); } catch(Exception $e) { }
				$fail = get_transient( 'pdf_forms_for_woocommerce_pdfninja_key_failure' );
				if( isset( $fail ) && $fail && current_user_can( 'manage_woocommerce' ) )
					echo Pdf_Forms_For_WooCommerce::render_error_notice( 'pdf-ninja-new-key-failure', array(
						'label' => esc_html__( "PDF Forms Filler for WooCommerce Error", 'pdf-forms-for-woocommerce' ),
						'message' =>
							Pdf_Forms_For_WooCommerce::replace_tags(
								esc_html__( "Failed to acquire the Pdf.Ninja API key on last attempt. {a-href-edit-service-page}Please retry manually{/a}.", 'pdf-forms-for-woocommerce' ),
								array(
									'a-href-edit-service-page' => "<a href='".esc_url( add_query_arg( array( 'tab' => 'pdf-forms-for-woocommerce-settings', 'section' => 'pdf-ninja' ), menu_page_url( 'wc-settings', false ) ) )."'>",
									'/a' => "</a>",
								)
							)
					) );
			}
			
			/**
			 * Returns settings screen notices that need to be displayed
			 */
			public function settings_notices()
			{
				try
				{
					$url = $this->get_api_url();
					$ignore_ssl_errors = $this->get_ignore_ssl_errors();
					if( substr( $url, 0, 5 ) == 'http:' || $ignore_ssl_errors )
						echo Pdf_Forms_For_WooCommerce::render_warning_notice( 'insecure-pdf.ninja-connection', array(
							'label' => esc_html__( "Warning", 'pdf-forms-for-woocommerce' ),
							'message' => esc_html__( "Your WooCommerce settings indicate that you are using an insecure connection to the Pdf.Ninja API server.", 'pdf-forms-for-woocommerce' ),
						) );
				}
				catch(Exception $e) { };
			}
		}
	}
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Pdf_Ninja_Exception' ) )
	{
		class Pdf_Forms_For_WooCommerce_Pdf_Ninja_Exception extends Exception
		{
			private $reason = null;
			
			public function __construct( $response )
			{
				$msg = $response;
				
				if( is_array( $response ) )
				{
					if( ! isset( $response['error'] ) || $response['error'] == "" )
						$msg = __( "Unknown error", 'pdf-forms-for-woocommerce' );
					else
						$msg = $response['error'];
					if( isset( $response['reason'] ) )
						$this->reason = $response['reason'];
				}
				
				parent::__construct( $msg );
			}
			
			public function getReason() { return $this->reason; }
		}
	}
