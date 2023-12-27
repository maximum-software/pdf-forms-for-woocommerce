<?php
	
	if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
	
	if( ! class_exists( 'Pdf_Forms_For_WooCommerce_Storage', false ) )
	{
		class Pdf_Forms_For_WooCommerce_Storage
		{
			private static $instance = null;
			
			private $storage_path = null;
			private $storage_url = null;
			private $subpath = null;
			
			public function __construct()
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
				
				return clone self::$instance;
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
			public function set_storage_path( $path )
			{
				// TODO: do security checks
				$this->storage_path = wp_normalize_path( $path );
				return $this;
			}
			
			/**
			 * Sets storage path relative to site root
			 */
			public function set_site_root_relative_storage_path( $path )
			{
				// TODO: do security checks
				return $this->set_storage_path( trailingslashit( ABSPATH ) . $path );
			}
			
			/**
			 * Sets subpath, automatically removing preceeding invalid special characters
			 */
			public function set_subpath( $subpath )
			{
				// TODO: do security checks
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
				return trailingslashit( $this->get_storage_path() ) . $this->get_subpath();
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
				return  trailingslashit( $this->get_storage_url() ) . $this->get_subpath();
			}
			
			/**
			 * Recurively creates path directories and prevents directory listing
			 */
			public function initialize_path()
			{
				$path = $this->get_storage_path();
				$subpath = $this->get_subpath();
				$subpath = trim( $subpath, "/\\" );
				if( $subpath != '' )
				{
					foreach( explode( DIRECTORY_SEPARATOR, $subpath ) as $dir )
					{
						$path = trailingslashit( $path ) . $dir;
						
						if( is_file( $path ) )
							throw new Exception( __( "Can't create directory because a file with the same name already exists", 'pdf-forms-for-woocommerce' ) );
						
						if( ! is_dir( $path ) )
						{
							wp_mkdir_p( $path );
							
							// prevent directory listing in each directory in subpath
							$index_file =  trailingslashit( $path ) . 'index.php';
							if( ! file_exists( $index_file ) )
								@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
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
				
				$files = @scandir( $full_path );
				if( $files === false)
					return array();
				
				$files = array_diff( $files, array( '.', '..', 'index.php' ) );
				
				$files_with_info = array();
				foreach( $files as $file )
				{
					$filepath = trailingslashit( $full_path ) . $file;
					if( is_dir( $filepath ) )
						continue;
					
					$info = array(
						'filename' => $file,
						'filepath' => $filepath,
						'url' => trailingslashit( $full_url ) . $file,
					);
					
					$files_with_info[] = $info;
				}
				
				return $files_with_info;
			}
			
			/*
			* Deletes a directory tree recursively
			*/
			public function delete_directory_recursively( $dir )
			{
				if( ! is_dir( $dir ) )
					return;
				
				$files = @scandir( $dir );
				if( $files === false )
					return;
				
				$files = array_diff( $files, array( '.', '..' ) );
				
				foreach( $files as $file )
				{
					$filepath = trailingslashit( $dir ) . $file;
					if( is_dir( $filepath ) && ! is_link( $filepath ) )
						$this->delete_directory_recursively( $filepath );
					else
						@unlink( $filepath );
				}
				
				@rmdir( $dir );
			}
			
			/*
			* Deletes the subpath recursively
			*/
			public function delete_subpath_recursively()
			{
				if( $this->subpath == '' )
					return;
				
				$this->delete_directory_recursively( $this->get_full_path() );
			}
		}
	}
