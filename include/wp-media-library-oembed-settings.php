<?php


if ( ! class_exists('MediaLibraryOembedSettings') ) :
class MediaLibraryOembedSettings {
	private static $_instance = null;
	
	private $_reset_post_oembed_done = false;
	
	/**
	 * Setup which to WP options page the options will be added.
	 */
	private $optionset = 'media';
	
	static function instance() {
		if ( is_null(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	// no other instances!
	private function __clone() {}
	
	private function __construct() {
		add_action( 'admin_init' , array( &$this , 'register_settings' ) );
		add_action( "load-options-{$this->optionset}.php" , array( &$this , 'enqueue_style' ) );
		add_action( 'update_option_medialiboembed_restrict_providers' , array( &$this , 'reset_post_eoembeds' ) , 10 , 2 );
		add_action( 'update_option_medialiboembed_providers' , array( &$this , 'reset_post_eoembeds' ) , 10 , 2 );
	}
	/**
	 * Register admin scripts
	 */
	public function register_settings() {
		$settings_section = 'medialiboembed_settings';
		register_setting( $this->optionset , 'medialiboembed_restrict_providers' , 'intval' );
		register_setting( $this->optionset , 'medialiboembed_providers' , array( &$this , 'sanitize_providers' ) );
		
		add_settings_section( $settings_section, __( 'External content Providers (oEmbed)', 'wp-media-library-oembed' ), array( $this, 'settings_description' ), $this->optionset );
		add_settings_field(
			'medialiboembed_restrict_providers',
			__( 'Restrict External Providers', 'wp-media-library-oembed' ),
			array( $this, 'set_restrict_providers' ),
			$this->optionset,
			$settings_section
		);
		add_settings_field(
			'medialiboembed_providers',
			__( 'External Providers', 'wp-media-library-oembed' ),
			array( $this, 'set_providers' ),
			$this->optionset,
			$settings_section
		);
	}
	/**
	 * Print some documentation for the optionset
	 */
	public function settings_description() {
		?>
		<div class="inside">
			<p><?php _e( 'Use this to restrict external providers.', 'wp-media-library-oembed' ); ?></p>
		</div>
		<?php
	}
	/**
	 * Enqueue options CSS and JS
	 */
	function enqueue_style() {
		wp_register_style( 'medialiboembed-settings-css' , plugins_url( '/css/medialiboembed-settings.css' , dirname(__FILE__) ));
		wp_register_style( 'genericons' , plugins_url( '/Genericons/genericons/genericons.css' , dirname(__FILE__) ) , array( 'medialiboembed-settings-css' ) );
		wp_enqueue_style('genericons');
		wp_enqueue_script( 'medialiboembed-settings' , plugins_url( 'js/medialiboembed-settings.js' , dirname(__FILE__) ) , array( 'jquery' ) , '0.0.1' );
	}
	public function set_restrict_providers() {
		$restrict_providers = get_option('medialiboembed_restrict_providers');
		?><label for="oembed-restrict-providers"><?php
			?><input id="oembed-restrict-providers" type="checkbox" name="medialiboembed_restrict_providers" value="1" <?php checked( $restrict_providers , true , true); ?> /><?php
			_e( 'Restrict providers' , 'wp-media-library-oembed' );
		?></label><?php 
		?><p class="description"><?php
			_e( 'If checked only the oEmbed providers listed below will be embedded in the media library.' , 'wp-media-library-oembed' );
		?></p><?php
		
	}
	public function set_providers() {
		$current_providers = (array) get_option('medialiboembed_providers');
		$selectable_providers = $this->get_selectable_providers();
		$style_enabled = get_option('medialiboembed_restrict_providers') ? '' : 'style="display:none;"';
		?><div class="oembed-provider-select" <?php echo $style_enabled ?>><?php
			foreach ( $selectable_providers as $providerdomain => $providername ) {
				?><div class="oembed-provider-select-item"><?php
					$fieldname = "medialiboembed_providers[{$providerdomain}]";
					?><input id="oembed-provider-<?php echo $providername ?>" type="checkbox" name="<?php echo $fieldname ?>" value="1" <?php checked( isset($current_providers[$providerdomain]) , true , true); ?> /><?php
					?><label for="oembed-provider-<?php echo $providername ?>"><?php
						//*
						printf( '<span class="genericon genericon-fallback genericon-%s"></span>',$providername );
						/*/
						printf( '<span class="monoicon monoicon-standard monoicon-%s"></span>',$providername );
						//*/
						printf( '<span class="providerdomain">%s</span>',$providerdomain);
					?></label><?php 
				?></div><?php
			}
		?></div><?php
		
	}
	public function sanitize_providers( $providers ) {
		$selectable = $this->get_selectable_providers( );
		return array_intersect_key( (array) $providers , $selectable );
	}
	
	private function get_selectable_providers( ) {
		$default_providers = $this->get_default_providers();
		$providers = array();
		foreach ( $default_providers as $matchmask => $data ) {
			list( $providerurl, $regex ) = $data;
			$providerdomain = preg_replace('/^https?:\/\/.*([a-z0-9]+)\.([a-z]+)\/.*$/sU','\1.\2',$providerurl);
			$providername = pathinfo($providerdomain,PATHINFO_FILENAME);
			if ( ! isset( $options[$providerdomain] ) )
			$providers[$providerdomain] = $providername;
		}
		return $providers;
	}
	
	private function get_default_providers() {
		require_once( ABSPATH . WPINC . '/class-oembed.php' );
		$filter_fct = array(MediaLibraryOembed::instance() , 'filter_oembed_providers' );
		// remove our filter
		if ( $filter_priority = has_filter( 'oembed_providers' ) )
			remove_filter( 'oembed_providers' , $filter_fct );
		
		// new instance of WP_oEmbed...
		$oembed = new WP_oEmbed();
		$providers = $oembed->providers;
		
		// re-add filter
		if ( $filter_priority )
			add_filter( 'oembed_providers' , $filter_fct , $filter_priority );
		return $providers;
	}
	
	public function reset_post_eoembeds( $new , $old ) {
		if ( ! $this->_reset_post_oembed_done ) {
			global $wpdb;
			$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_oembed_%'" );
			$this->_reset_post_oembed_done = true;
		}
	}
}
MediaLibraryOembedSettings::instance();


endif;