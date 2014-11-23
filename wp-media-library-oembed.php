<?php

/*
Plugin URI: https://gibthub.com/mcguffin/wp-media-library-oembed
Plugin Name: WordPress Media Library oembed
Description: Allows you to embed externally hosted Content like from Vimeo or YouTube in your media library.
Author: Jörn Lund
Version: 1.0.0
Author URI: https://gibthub.com/mcguffin/
License: GPL2
Text Domain: medialiboembed
Domain Path: /languages
*/

if ( ! class_exists('MediaLibraryOembed') ) :
class MediaLibraryOembed {
	private static $_instance = null;
	private $_selected_providers;
	
	static function instance() {
		if ( is_null(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	// no other instances!
	private function __clone() {}
	
	private function __construct() {
        add_action( 'plugins_loaded', array($this, 'plugins_loaded') );
	
		add_filter( 'wp_video_shortcode_override', array( &$this,'media_shortcode_override' ) ,10,2); //
		add_filter( 'wp_audio_shortcode_override', array( &$this,'media_shortcode_override' ) ,10,2); //

		add_filter( 'oembed_providers' , array( &$this , 'filter_oembed_providers' ) );

		add_option( 'medialiboembed_providers' , false );
		add_option( 'medialiboembed_restrict_providers' , false );
		$this->_selected_providers = get_option('medialiboembed_providers');
		
		register_uninstall_hook( __FILE__ , array( __CLASS__ , 'uninstall' ) );
	}
	
	public static function uninstall() {
		remove_option( 'medialiboembed_providers'  );
	}

	/**
	 * Overrides WP media shortcode for output on edit media and media attacment page
	 *
	 * @since 1.0.0
	 * @param string $html empty string
	 * @param array $attr assoc containing at least 'src'
	 * @return string $html
	 */
	public function media_shortcode_override($html, $attr ) {
		$current_post = get_post();
		if ( is_object($current_post) && isset($attr['src']) && $current_post->guid == $attr['src'] && preg_match('/\+oembed$/' , $current_post->post_mime_type) ) {
			// this will cache oembed result in $current_post postmeta
			$wp_embed = new WP_Embed();
			$html = $wp_embed->shortcode( $attr , $current_post->guid );
		}
		return $html;
	}

    /**
     * Load plugin text domain
     */
    public function plugins_loaded() {
        load_plugin_textdomain( 'wp-media-library-oembed', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
	
	function filter_oembed_providers( $providers ) {
		if ( $this->_selected_providers && get_option('medialiboembed_restrict_providers') )
			$providers = array_filter($providers,array(&$this,'_filter_oembed_provider'));

		return $providers;
	}
	private function _filter_oembed_provider( $item ) {
		if ( is_array($item) ) {
			if ( is_string($item[0]) ) {
				foreach ( $this->_selected_providers as $providerdomain => $enabled ) {
					if ( $enabled && strpos( $item[0] , $providerdomain ) )
						return true;
				}
			}
		}
		return false;
	}
}
//https://www.youtube.com/watch?v=ae2T4utxxPw
//https://vimeo.com/83687791
MediaLibraryOembed::instance();

if ( is_admin() ) {
	require_once( 'include/wp-media-library-oembed-admin.php' );
	require_once( 'include/wp-media-library-oembed-settings.php' );
}
endif;