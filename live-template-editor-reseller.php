<?php
/*
 * Plugin Name: Live Template Editor Reseller
 * Version: 1.0
 * Plugin URI: https://github.com/rafasashi
 * Description: This Live Template Editor addon plugin allows a user to resell products
 * Author: Rafasashi
 * Author URI: https://github.com/rafasashi
 * Requires at least: 4.6
 * Tested up to: 4.7
 *
 * Text Domain: ltple
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Rafasashi
 * @since 1.0.0
 */
	
	/**
	* Add documentation link
	*
	*/
	
	if ( ! defined( 'ABSPATH' ) ) exit;
	
	/**
	 * Returns the main instance of LTPLE_Reseller to prevent the need to use globals.
	 *
	 * @since  1.0.0
	 * @return object LTPLE_Reseller
	 */
	function LTPLE_Reseller ( $version = '1.0.0' ) {
		
		$instance = LTPLE_Client::instance( __FILE__, $version );
		
		if ( empty( $instance->reseller ) ) {
			
			$instance->reseller = new stdClass();
			
			$instance->reseller = LTPLE_Reseller::instance( __FILE__, $instance, $version );
		}

		return $instance;
	}	
	
	add_filter( 'plugins_loaded', function(){

		// Load plugin functions
		
		require_once( 'includes/functions.php' );	
		
		// Load plugin class files

		require_once( 'includes/class-ltple.php' );
		require_once( 'includes/class-ltple-settings.php' );

		// Autoload plugin libraries
		
		$lib = glob( __DIR__ . '/includes/lib/class-ltple-*.php');
		
		foreach($lib as $file){
			
			require_once( $file );
		}
	
		LTPLE_Reseller('1.1.0');	
	});