<?php
	
	if( ! defined( 'ABSPATH' ) )
		return;
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Storage' ) )
	{
		class Pdf_Forms_For_WooCommerce_Storage
		{
			private static $instance = null;
			
			private $storage_path = null;
			private $storage_url = null;
			private $subpath = null;
			
			private function __construct()
			{
				$uploads = wp_upload_dir( null, false );
				$this->storage_path = $uploads['basedir'];
				$this->storage_url = $uploads['baseurl'];
				$this->subpath = 'pdf-forms-for-woocommerce';
			}
			
			/*
			* Returns a global instance of this class
			*/
			public static function get_instance()
			{
				if ( empty( self::$instance ) )
					self::$instance = new self;
				
				return self::$instance;
			}
			
			/**
			 * Returns storage path
			 */
			public function get_storage_path()
			{
				return $this->storage_path;
			}
			
			/**
			 * Returns storage url
			 */
			public function get_storage_url()
			{
				return $this->storage_url;
			}
			
			/**
			 * Sets subpath, automatically removing preceeding invalid special characters
			 */
			private function set_storage_path( $path )
			{
				$this->storage_path = wp_normalize_path( $path );
				return $this;
			}
			
			/**
			 * Sets subpath, automatically removing preceeding invalid special characters
			 */
			public function set_subpath( $subpath )
			{
				$this->subpath = ltrim( $subpath, "/\\." );
			}
			
			/**
			 * Returns subpath
			 */
			public function get_subpath()
			{
				return $this->subpath;
			}
			
			/**
			 * Returns full path, including the subpath
			 */
			public function get_full_path()
			{
				return path_join( $this->get_storage_path(), $this->get_subpath() );
			}
			
			/**
			 * Returns full url, including the subpath
			 */
			public function get_full_url()
			{
				return path_join( $this->get_storage_url(), $this->get_subpath() );
			}
			
			/**
			 * Recurively creates path directories and prevents directory listing
			 */
			private function initialize_path( $path )
			{
				$path = wp_normalize_path( $path );
				
				if( ! is_dir( $path ) )
				{
					wp_mkdir_p( $path );
					
					// prevent directory listing
					$index_file = path_join( $path, 'index.php' );
					if( ! file_exists( $index_file ) )
						file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
				}
			}
			
			/**
			 * Copy a source file to the storage location, ensuring a unique file name
			 */
			public function save( $srcfile, $filename )
			{
				$full_path = $this->get_full_path();
				
				$this->initialize_path( $full_path );
				
				$filename = sanitize_file_name( $filename );
				$filename = wp_unique_filename( $full_path, $filename );
				
				copy($srcfile, trailingslashit( $full_path ) . $filename);
			}
			
			/**
			 * Returns a list of files in the storage location
			 */
			public function get_files()
			{
				$full_path = $this->get_full_path();
				$full_url = $this->get_full_url();
				
				$this->initialize_path( $full_path );
				
				$files = scandir( $full_path );
				$files = array_diff( $files, array( '.', '..', 'index.php' ) );
				$files_with_info = array();
				foreach( $files as $file )
				{
					$info = array(
						'filename' => $file,
						'filepath' => path_join( $full_path, $file ),
						'url' => path_join( $full_url, $file ),
					);
					
					$files_with_info[] = $info;
				}
				
				return $files_with_info;
			}
		}
	}
