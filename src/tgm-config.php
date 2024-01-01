<?php
	
	if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
	
	require_once dirname( __FILE__ ) . '/lib/class-tgm-plugin-activation.php';
	
	add_action( 'tgmpa_register', 'pdf_forms_for_woocommerce_register_required_plugins' );
	
	function pdf_forms_for_woocommerce_register_required_plugins()
	{
		$plugins = array();
		
		if (!function_exists('is_plugin_active')) {
			include_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		
		$plugins[] = array(
			'name'     => 'WooCommerce',  // The plugin name.
			'slug'     => 'woocommerce',  // The plugin slug (typically the folder name).
			'required' => true,           // If false, the plugin is only 'recommended' instead of required.
			'version'  => '5.6.0',        // E.g. 1.0.0. If set, the active plugin must be this version or higher. If the plugin version is higher than the plugin version installed, the user will be notified to update the plugin.
		);
		
		$config = array(
			'id'           => 'pdf-forms-for-woocommerce',  // Unique ID for hashing notices for multiple instances of TGMPA.
			'menu'         => 'tgmpa-install-plugins',      // Menu slug.
			'parent_slug'  => 'plugins.php',                // Parent menu slug.
			'capability'   => 'manage_options',             // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
			'has_notices'  => true,                         // Show admin notices or not.
			'dismissable'  => true,                         // If false, a user cannot dismiss the nag message.
			'is_automatic' => false,                        // Automatically activate plugins after installation or not.
		);
		
		tgmpa( $plugins, $config );
	}
