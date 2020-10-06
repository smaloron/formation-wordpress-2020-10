<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Regen_Thumbs {

	public function __construct() {
		add_action( 'post_submitbox_misc_actions', array( $this, 'regen_thumbs_button' ), 99, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ), 10, 1 );
		add_action( 'wp_ajax_regen_thumbs', array( $this, 'regen_thumbs' ), 10, 0 );
		add_action( 'wp_ajax_regen_thumbs', array( $this, 'regen_thumbs' ), 10, 0 );
	}

	/*******************************************************************
	* Public methods
	*******************************************************************/

	public function regen_thumbs_button( $post ) {

	?>

	<div class="misc-pub-section" id="regen-thumbs">
		<a class="button" href="#" id="post-regen-thumbs" data-post_id="<?php print esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Regen. Thumbs', 'regen-thumbs' ); ?></a>
	</div>

	<?php

	}

	public function add_admin_scripts( $hook ) {

		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			global $post;

			$debug        = apply_filters( 'regen_thumbs_debug', false );
			$js_ext       = ( $debug ) ? '.min.js' : '.js';
			$version      = filemtime( REGEN_THUMBS_PLUGIN_PATH . 'js/main' . $js_ext );
			$dependencies = array(
				'jquery',
			);
			$params       = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'regen-thumbs' ),
				'debug'    => apply_filters( 'regen_thumbs_debug', false ),
			);

			wp_enqueue_script( 'regen-thumbs-main-script', REGEN_THUMBS_PLUGIN_URL . 'js/main' . $js_ext, $dependencies, $version, true );
			wp_localize_script( 'regen-thumbs-main-script', 'RegenThumbs', $params );
		}
	}

	public function regen_thumbs() {

		if ( ! wp_verify_nonce( $_POST['nonce'], 'regen-thumbs' ) ) {
			$error = new WP_Error( 'RegenThumbs::regen_thumbs', 'Unauthorised access' );

			wp_send_json_error( $error );
		}

		$post_id           = absint( filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_STRING ) );
		$post_thumbnail_id = get_post_thumbnail_id( $post_id );
		$post              = get_post( $post_id );

		if ( $post && ! empty( $post_thumbnail_id ) ) {
			$this->_regen_thumb( $post_thumbnail_id );
		}

		if ( $post && 'product' === $post->post_type ) {
			$product        = wc_get_product( $post_id );
			$attachment_ids = $product->get_gallery_image_ids();

			if ( $product instanceof WC_Product_Variable ) {
				$variations = $product->get_available_variations();

				foreach ( $variations as $variation ) {

					if ( ! empty( $variation['image_id'] ) ) {
						$attachment_ids[] = absint( $variation['image_id'] );
					}
				}
			}

			foreach ( $attachment_ids as $key => $attachment_id ) {
				$this->_regen_thumb( $attachment_id );
			}
		}

		wp_die();
	}


	/*******************************************************************
	* Private methods
	*******************************************************************/

	private function _regen_thumb( $attachment_id ) {// @codingStandardsIgnoreLine
		$fullsizepath = get_attached_file( $attachment_id );

		if ( false !== $fullsizepath && file_exists( $fullsizepath ) ) {
			$meta = wp_generate_attachment_metadata( $attachment_id, $fullsizepath );

			wp_update_attachment_metadata( $attachment_id, $meta );
		}
	}
}
