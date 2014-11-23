<?php


/*
ToDo:
√ add menu item to upload.php
√ js error handling
√ refactor: use add_oembed_media( $oembed_url , $post_id = null )
√ add settings: allowed oembeds in media library
- show video thumbnails
- localize DE
√ extra admin.php
*/

if ( ! class_exists('MediaLibraryOembedAdmin') ) :
class MediaLibraryOembedAdmin {
	private static $_instance = null;
	
	static function instance() {
		if ( is_null(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	// no other instances!
	private function __clone() {}
	
	private function __construct() {
        add_action( 'admin_init' , array( &$this , 'admin_init' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'load-post.php' , array( &$this , 'load_scripts' ) );
        add_action( 'load-post-new.php' , array( &$this , 'load_scripts' ) );
		add_filter( 'media_send_to_editor' , array( &$this,'media_send_to_editor' ) , 10,3);
		add_action( 'wp_ajax_oembed_media' , array(&$this , 'ajax_oembed_media' )  );
		add_action( 'load-media_page_medialiboembed' , array(&$this , 'load_upload_page' ) );
	}

    public function admin_menu() {
        add_submenu_page("upload.php", __('Embed in Library', 'wp-media-library-oembed'), __('External', 'wp-media-library-oembed'), 'upload_files', 'medialiboembed', array($this, 'page_embed'));
        add_action( 'admin_action_oembed_media', array($this, 'oembed_media') );
    }
    public function load_upload_page(){
    	add_action( 'admin_notices' , array( &$this , 'show_admin_error' ) );
    	
    }
    /**
     * Plugin Page
     */
    public function page_embed() { ?>
        <div class="wrap">
            <div id="icon-upload" class="icon32"><br></div>	<h2><?php _e('Embed in Library', 'wp-media-library-oembed'); ?></h2>

            <p><?php _e('Use this form to add an external media in your library.', 'wp-media-library-oembed'); ?></p><?php
			if ( $providers = get_option('medialiboembed_providers') ) {
				?><p class="description"><?php
					$providers = array_filter($providers);
					printf( _n('You can only use this provider: %s' , 'You can use any of these providers: %s' , count($providers) , 'wp-media-library-oembed' ), implode( _x(', ','enumeration delemiter','wp-media-library-oembed') , array_keys($providers) ) );
				?></p><?php
			}
            ?><form action="<?php echo admin_url( 'admin.php' ); ?>" method="post">
                <p>
                    <label for="oembed_url"><?php _e('URL', 'wp-media-library-oembed') ?></label>
                    <input type="text" name="oembed_url" id="oembed_url"/>
                    <input type="hidden" name="action" value="oembed_media" />
                    <input type="submit" value="<?php _e('Add in library', 'wp-media-library-oembed') ?>" class="button button-primary"/>
                </p>
            </form>

            <div id="oembed_in_library_preview" class="hide-if-no-js"></div>
        </div>
        <?php
    }


	/**
	 * Register admin scripts
	 */
	public function admin_init() {
		$provider_restriction_note = '';
		if ( $providers = get_option('medialiboembed_providers') )
			$provider_restriction_note = sprintf( _n('You can only use this provider: %s' , 'You can use any of these providers: %s' , count($providers) , 'wp-media-library-oembed' ), implode( _x(', ','enumeration delemiter','wp-media-library-oembed') , array_keys($providers) ) );
		
		wp_register_script( 'medialiboembed-media-view' , plugins_url( 'js/media-view.js' , dirname(__FILE__) ) , array('jquery','media-editor' ) , '0.0.1' );
		wp_localize_script( 'medialiboembed-media-view' , 'medialiboembed_l10n' , array(
			'external' => __('External','wp-media-library-oembed'),
			'media_url' => __('Media URL','wp-media-library-oembed'),
			'add_media' => __('Add Media','wp-media-library-oembed'),
			'generic_error_message' => __('An error occured','wp-media-library-oembed'),
			'provider_restriction_note' => $provider_restriction_note,
			'settings' => array(
				'oembed_ajax_nonce' => wp_create_nonce( 'oembed-media' ),
				'ajaxurl' => admin_url('admin-ajax.php'),
			),
		) );

		wp_register_style( 'medialiboembed-admin' , plugins_url( 'css/medialiboembed-admin.css' , dirname(__FILE__) ) , array( ) , '0.0.1' );
	}
	/**
	 * Load admin scripts o post edit.
	 */
	public function load_scripts() {
		wp_enqueue_script( 'medialiboembed-media-view');
		wp_enqueue_style( 'medialiboembed-admin' );
	}

	
	/**
	 * oEmbed Media from Uploads -> External form
	 */
	function oembed_media() {
		$oembed_url = esc_url_raw($_REQUEST['oembed_url'] , array('http','https') );
		$add_media_response = $this->add_oembed_media( $oembed_url );
		if ( is_wp_error( $add_media_response ) ) {
			wp_safe_redirect( admin_url( 'upload.php?page=medialiboembed&error='.$add_media_response->get_error_code() )  );
		} else {
			wp_safe_redirect( get_edit_post_link( $add_media_response , '' )  );
		}
	}
	/**
	 * Show errors
	 */
	function show_admin_error() {
		if ( isset( $_REQUEST['error'] ) ) {
			switch ( $_REQUEST['error'] ) {
				case 'oembed-no-provider':
					$message = __('External provider could not be detected.','wp-media-library-oembed');
					break;
				case 'oembed-response-failed':
					$message = __('No response from External provider.','wp-media-library-oembed');
					break;
			}
			?><div class="error"><?php
			   ?><p><?php 
					echo $message; 
				?></p><?php
			?></div><?php
		}
	}
	
	/**
	 * Ajax response after adding media.
	 */
	function ajax_oembed_media() {
		// do nonce check
		check_ajax_referer( 'oembed-media' , '_ajax_nonce' );

		// check permissions
		if ( ! current_user_can( 'upload_files' ) )
			wp_die();
	
		if ( isset( $_REQUEST['post_id'] ) ) {
			$post_id = intval($_REQUEST['post_id']);
			if ( ! current_user_can( 'edit_post', $post_id ) )
				wp_die();
		} else {
			$post_id = null;
		}
		
		$oembed_url = esc_url_raw($_REQUEST['oembed_url'] , array('http','https') );
				
		// insert attachment
		$add_media_response = $this->add_oembed_media( $oembed_url , $post_id );
		
		if ( is_wp_error( $add_media_response ) ) {
			// error saving attachment
			echo json_encode( array(
				'success' => false,
				'data'    => array(
					'message' => $add_media_response->get_error_message(),
					'filename' => $oembed_url,
				),
			) );
			wp_die();
		}
		$attachment_id = $add_media_response;
		
		// get attachment data for json output
		if ( ! $attachment = wp_prepare_attachment_for_js( $attachment_id ) ) {
			wp_die();
		
		}
		
		echo json_encode( array(
			'success' => true,
			'data'    => $attachment,
		) );
		
		wp_die();
	}
	
	
	/**
	 * Creates Attachment from oembed URL in posts table.
	 *
	 * @param string $oembed_url Content URL to include
	 * @param array $post_id (optional) Parent post id
	 * @return int $attachment_id or WP_Error
	 */
	public function add_oembed_media( $oembed_url , $post_id = null ) {
		// 
		// sanitize
		$oembed_response = $this->get_oembed_response( $oembed_url );
		if ( is_wp_error( $oembed_response ) ) {
			return $oembed_response;
		}
		
		// allows to rewrite response according to provider
		$mime_type = $oembed_response->type . '/' . $oembed_response->provider_name;
		
		// generate attachment data
		$attachment_data = array(
            'post_title'    => $oembed_response->title,
            'post_parent'	=> $post_id,
            'post_content'  => isset( $oembed_response->description ) ? wp_kses_post($oembed_response->description) : '',
            'post_status'   => 'inherit',
            'post_author'   => get_current_user_id(),
            'post_type'     => 'attachment',
            'guid'          => $oembed_url,
            'post_mime_type'=> $mime_type . '+oembed', // $oembed_response->type.'/' . $oembed_response->provider_name,
		);
		
		/**
		 * Filter for attachment post data before it is passed to `wp_insert_post()`.
		 *
		 * @since 1.0.0
		 *
		 * @param object $attachment_data Post data to be inserted.
		 * @param object $oembed_response response from oembed provider
		 */
		$attachment_data = apply_filters( 'mla_oembed_attachment_data' , $attachment_data , $oembed_response );
		
        return wp_insert_post( $attachment_data , true ); // will return wp_error on failure.
	}
	
	/**
	 * Get oEmbed response data from url.
	 *
	 * @param string $url Content URL to include
	 * @return object $data response Data fetched from oembed provider or WP_Error
	 */
	public function get_oembed_response( $url ) {
		require_once( ABSPATH . WPINC . '/class-oembed.php' );
        $oembed = new WP_oEmbed();
		$args = array();

		$provider = false;

		if ( !isset($args['discover']) )
			$args['discover'] = true;

		foreach ( $oembed->providers as $matchmask => $data ) {
			list( $providerurl, $regex ) = $data;

			// Turn the asterisk-type provider URLs into regex
			if ( !$regex ) {
				$matchmask = '#' . str_replace( '___wildcard___', '(.+)', preg_quote( str_replace( '*', '___wildcard___', $matchmask ), '#' ) ) . '#i';
				$matchmask = preg_replace( '|^#http\\\://|', '#https?\://', $matchmask );
			}

			if ( preg_match( $matchmask, $url ) ) {
				$provider = str_replace( '{format}', 'json', $providerurl ); // JSON is easier to deal with than XML
				break;
			}
		}
		

 		if ( !$provider && $args['discover'] && ! get_option('medialiboembed_restrict_providers') ) // no provider found, last attempt
 			$provider = $oembed->discover( $url );

		if ( ! $provider ) {
			return new WP_Error('oembed-no-provider',__('External provider could not be detected.','wp-media-library-oembed'),$url);
		} else if ( false === $data = $oembed->fetch( $provider, $url, $args ) ) {
			return new WP_Error('oembed-response-failed',__('No response from External provider.','wp-media-library-oembed'),array('oembed_url'=>$url,'oembed_provider'=>$provider));
		}
		return $data;
	}
	


	/**
	 * Filter html added to rte.
	 */
	public function media_send_to_editor( $html , $id  ) {
		$post = get_post( $id );
		if ( preg_match('/\+oembed$/' , $post->post_mime_type) )
			return $post->guid;
		return $html;
	}
	
}
//https://www.youtube.com/watch?v=ae2T4utxxPw
//https://vimeo.com/83687791
MediaLibraryOembedAdmin::instance();


endif;