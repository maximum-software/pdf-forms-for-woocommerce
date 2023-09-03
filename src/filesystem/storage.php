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
			 * Returns storage path relative to site root
			 */
			public function get_site_root_relative_storage_path()
			{
				return str_replace( ABSPATH, '', $this->get_storage_path() );
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
				$subpath = wp_normalize_path( $subpath );
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
			 * Returns full path relative to site root
			 */
			public function get_site_root_relative_path()
			{
				return str_replace( ABSPATH, '', $this->get_full_path() );
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
			private function initialize_path()
			{
				$path = $this->get_storage_path();
				$subpath = $this->get_subpath();
				$subpath = trim( $subpath, "/\\" );
				if( $subpath != '' )
				{
					foreach( explode( '/', $subpath ) as $dir )
					{
						$path = path_join( $path, $dir );
						
						if( ! is_dir( $path ) )
						{
							wp_mkdir_p( $path );
							
							// prevent directory listing in each directory in subpath
							$index_file = path_join( $path, 'index.php' );
							if( ! file_exists( $index_file ) )
								file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
						}
					}
				}
			}
			
			/**
			 * Copy a source file to the storage location, ensuring a unique file name
			 */
			public function save( $srcfile, $filename, $overwrite = false )
			{
				$this->initialize_path();
				
				$full_path = $this->get_full_path();
				
				$filename = sanitize_file_name( $filename );
				if( ! $overwrite )
					$filename = wp_unique_filename( $full_path, $filename );
				
				$dstfile = trailingslashit( $full_path ) . $filename;
				copy($srcfile, $dstfile);
				
				return $filename;
			}
			
			/**
			 * Returns a list of files in the storage location
			 */
			public function get_files()
			{
				$this->initialize_path();
				
				$full_path = $this->get_full_path();
				$full_url = $this->get_full_url();
				
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
