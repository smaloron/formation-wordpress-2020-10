<?php
/**
 * Plugin Name: Image Regenerate & Select Crop
 * Plugin URI: https://iuliacazan.ro/image-regenerate-select-crop/
 * Description: Regenerate and crop images, details and actions for image sizes registered and image sizes generated, clean up, placeholders, custom rules, register new image sizes, crop medium settings, WP-CLI commands, optimize images.
 * Text Domain: sirsc
 * Domain Path: /langs
 * Version: 5.4.4
 * Author: Iulia Cazan
 * Author URI: https://profiles.wordpress.org/iulia-cazan
 * Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ
 * License: GPL2
 *
 * @package ic-devops
 *
 * Copyright (C) 2014-2020 Iulia Cazan
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

$upload_dir = wp_upload_dir();
$dest_url   = $upload_dir['baseurl'] . '/placeholders';
$dest_path  = $upload_dir['basedir'] . '/placeholders';
if ( ! file_exists( $dest_path ) ) {
	@wp_mkdir_p( $dest_path );
}
define( 'SIRSC_PLUGIN_FOLDER', dirname( __FILE__ ) );
define( 'SIRSC_PLACEHOLDER_FOLDER', realpath( $dest_path ) );
define( 'SIRSC_PLACEHOLDER_URL', esc_url( $dest_url ) );
define( 'SIRSC_ASSETS_VER', '20200830.1145' );
define( 'SIRSC_PLUGIN_VER', 5.44 );
define( 'SIRSC_ADONS_FOLDER', dirname( __FILE__ ) . '/adons/' );

/**
 * Class for Image Regenerate & Select Crop.
 */
class SIRSC_Image_Regenerate_Select_Crop {

	const PLUGIN_NAME        = 'Image Regenerate & Select Crop';
	const PLUGIN_SUPPORT_URL = 'https://wordpress.org/support/plugin/image-regenerate-select-crop/';
	const PLUGIN_TRANSIENT   = 'sirsc-plugin-notice';
	const BULK_PROCESS_DELAY = 500;
	const BULK_CLEANUP_ITEMS = 10;
	const PLUGIN_PAGE_SLUG   = 'image-regenerate-select-crop-settings';
	const DEFAULT_QUALITY    = 82;

	/**
	 * Class instance.
	 *
	 * @var object
	 */
	private static $instance;
	/**
	 * The plugin is configured.
	 *
	 * @var boolean
	 */
	public static $is_configured = false;
	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	public static $settings;
	/**
	 * Plugin user custom rules.
	 *
	 * @var array
	 */
	public static $user_custom_rules;
	/**
	 * Plugin user custom usable rules.
	 *
	 * @var array
	 */
	public static $user_custom_rules_usable;
	/**
	 * Excluded post types.
	 *
	 * @var array
	 */
	public static $exclude_post_type = array();
	/**
	 * Limit the posts.
	 *
	 * @var integer
	 */
	public static $limit9999 = 300;
	/**
	 * Crop positions.
	 *
	 * @var array
	 */
	public static $crop_positions = array();
	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	public static $plugin_url = '';
	/**
	 * Plugin native sizes.
	 *
	 * @var array
	 */
	private static $wp_native_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large' );
	/**
	 * Plugin debug to file.
	 *
	 * @var boolean
	 */
	public static $debug = false;
	/**
	 * Plugin adons list.
	 *
	 * @var array
	 */
	public static $adons;
	/**
	 * Plugin menu items.
	 *
	 * @var array
	 */
	public static $menu_items;
	/**
	 * Upscale width value.
	 *
	 * @var integer
	 */
	public static $upscale_new_w;
	/**
	 * Upscale height value.
	 *
	 * @var array
	 */
	public static $upscale_new_h;
	/**
	 * Core version.
	 *
	 * @var float
	 */
	public static $wp_ver = 5.24;
	/**
	 * Get active object instance
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Image_Regenerate_Select_Crop();
		}
		return self::$instance;
	}

	/**
	 * Class constructor. Includes constants, includes and init method.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Run action and filter hooks.
	 */
	private function init() {
		$called = get_called_class();
		self::$settings = get_option( 'sirsc_settings' );
		self::get_default_user_custom_rules();
		self::$is_configured     = ( ! empty( self::$settings ) ) ? true : false;
		self::$exclude_post_type = array( 'nav_menu_item', 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'attachment', 'wp_block', 'scheduled-action' );

		self::$wp_ver = (float) get_bloginfo( 'version', 'display' );
		if ( is_admin() ) {
			if ( true === self::$debug && file_exists( SIRSC_PLUGIN_FOLDER . '/sirsc-hooks-tester.php' ) ) {
				include_once SIRSC_PLUGIN_FOLDER . '/sirsc-hooks-tester.php';
			}

			add_action( 'init', array( $called, 'maybe_save_settings' ), 0 );
			add_action( 'wp_ajax_sirsc_autosubmit_save', array( $called, 'maybe_save_settings' ) );

			if ( self::$wp_ver >= 5.0 ) {
				add_filter( 'admin_post_thumbnail_html', array( $called, 'append_image_generate_button' ), 60, 3 );
			} else {
				add_action( 'image_regenerate_select_crop_button', array( $called, 'image_regenerate_select_crop_button' ) );
				// The init action that is used with older core versions.
				add_action( 'init', array( $called, 'register_image_button' ) );
			}

			add_action( 'admin_enqueue_scripts', array( $called, 'load_assets' ) );
			add_action( 'add_meta_boxes', array( $called, 'register_image_meta' ), 10, 3 );
			add_action( 'wp_ajax_sirsc_show_actions_result', array( $called, 'show_actions_result' ) );
			add_action( 'plugins_loaded', array( $called, 'load_textdomain' ) );
			add_action( 'admin_menu', array( $called, 'admin_menu' ) );
			self::$crop_positions = array(
				'lt' => __( 'Left/Top', 'sirsc' ),
				'ct' => __( 'Center/Top', 'sirsc' ),
				'rt' => __( 'Right/Top', 'sirsc' ),
				'lc' => __( 'Left/Center', 'sirsc' ),
				'cc' => __( 'Center/Center', 'sirsc' ),
				'rc' => __( 'Right/Center', 'sirsc' ),
				'lb' => __( 'Left/Bottom', 'sirsc' ),
				'cb' => __( 'Center/Bottom', 'sirsc' ),
				'rb' => __( 'Right/Bottom', 'sirsc' ),
			);
			self::$plugin_url = admin_url( 'admin.php?page=image-regenerate-select-crop-settings' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $called, 'plugin_action_links' ) );
			add_filter( 'manage_media_columns', array( $called, 'register_media_columns' ), 5 );
			add_action( 'manage_media_custom_column', array( $called, 'media_column_value' ), 5, 2 );
			add_action( 'admin_notices', array( $called, 'admin_notices' ) );
			add_action( 'wp_ajax_sirsc-plugin-deactivate-notice', array( $called, 'admin_notices_cleanup' ) );
			add_action( 'sirsc_action_after_image_delete', array( $called, 'refresh_extra_info_footer' ) );
			add_filter( 'admin_post_thumbnail_size', array( $called, 'admin_featured_size' ), 60, 3 );
		}
		// This is global, as the image sizes can be also registerd in the themes or other plugins.
		add_filter( 'intermediate_image_sizes_advanced', array( $called, 'filter_ignore_global_image_sizes' ), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array( $called, 'wp_generate_attachment_metadata' ), 10, 2 );
		add_action( 'added_post_meta', array( $called, 'process_filtered_attachments' ), 10, 4 );
		add_filter( 'big_image_size_threshold', array( $called, 'big_image_size_threshold_forced' ), 20, 4 );
		add_action( 'delete_attachment', array( $called, 'on_delete_attachment' ) );
		if ( ! is_admin() && ! empty( self::$settings['placeholders'] ) ) {
			// For the front side, let's use placeolders if the case.
			if ( ! empty( self::$settings['placeholders']['force_global'] ) ) {
				add_filter( 'image_downsize', array( $called, 'image_downsize_placeholder_force_global' ), 10, 3 );
			} elseif ( ! empty( self::$settings['placeholders']['only_missing'] ) ) {
				add_filter( 'image_downsize', array( $called, 'image_downsize_placeholder_only_missing' ), 10, 3 );
			}
		}

		// Intitialize Gutenberg filters.
		add_action( 'init', array( $called, 'sirsc_block_init' ) );

		// Hook up the custom media settings.
		add_action( 'admin_init', array( $called, 'media_settings_override' ) );
		add_action( 'update_option_sirsc_override_medium_size', array( $called, 'on_update_sirsc_override_size' ), 10, 3 );
		add_action( 'update_option_sirsc_override_large_size', array( $called, 'on_update_sirsc_override_size' ), 10, 3 );
		add_action( 'update_option_sirsc_use_custom_image_sizes', array( $called, 'on_update_sirsc_override_size' ), 10, 3 );
		add_action( 'update_option_sirsc_admin_featured_size', array( $called, 'on_update_sirsc_override_size' ), 10, 3 );

		add_action( 'after_setup_theme', array( $called, 'maybe_register_custom_image_sizes' ) );
		add_filter( 'image_size_names_choose', array( $called, 'custom_image_size_names_choose' ), 60 );
		add_action( 'plugins_loaded', array( $called, 'plugin_ver_check' ) );
		add_filter( 'wp_php_error_message', array( $called, 'assess_background_errors' ), 60, 2 );

		// Support for WooCommerce product gallery.
		add_action( 'woocommerce_admin_after_product_gallery_item', array( $called, 'append_image_generate_button_small' ), 60, 2 );
		if ( ! empty( self::$settings['disable_woo_thregen'] ) ) {
			// Disable WooCommerce background thumbnail regeneration.
			add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
		}

		if ( ! empty( self::$settings['sync_settings_ewww'] ) ) {
			// Maybe sync settings with EWWW plugin.
			add_action( 'update_option_ewww_image_optimizer_disable_resizes', array( $called, 'sync_sirsc_with_ewww' ), 10, 3 );
			add_action( 'update_option_sirsc_settings', array( $called, 'sync_ewww_with_sirsc' ), 10, 3 );
		}
	}

	/**
	 * Assess the background errors.
	 *
	 * @param string $message Error message.
	 * @param array  $error   The error arrray.
	 * @return string
	 */
	public static function assess_background_errors( $message, $error ) {
		if ( ! empty( $error ) || ! empty( $message ) ) {
			if ( ! empty( $error['message'] ) && substr_count( $error['message'], 'memor' ) ) {

				$monitor = get_option( 'sirsc_monitor_errors', array() );
				if ( empty( $monitor['error'] ) ) {
					$monitor['error'] = array();
				}
				if ( empty( $monitor['schedule'] ) ) {
					$monitor['schedule'] = array();
				}
				if ( ! empty( $monitor['schedule'] ) ) {
					$keys = arrat_keys( $monitor['schedule'] );
					$id   = $keys[ count( $keys ) - 1 ];
					$monitor['error'][ $id ] = $monitor['schedule'][ $id ] . ' ' . trim( $message . ' ' . $error['message'] );
				}

				update_option( 'sirsc_monitor_errors', $monitor );
			}
		}
		return $message;
	}

	/**
	 * The actions to be executed when the plugin is updated.
	 *
	 * @return void
	 */
	public static function plugin_ver_check() {
		$db_version = get_option( 'sirsc_db_version', 0 );
		if ( SIRSC_PLUGIN_VER !== (float) $db_version ) {
			update_option( 'sirsc_db_version', SIRSC_PLUGIN_VER );
			self::activate_plugin();
		}
	}

	/**
	 * The actions to be executed when the plugin is deactivated.
	 */
	public static function deactivate_plugin() {
		global $wpdb;

		if ( ! empty( self::$settings['leave_settings_behind'] ) ) {
			// Cleanup only the notifications.
			self::admin_notices_cleanup( false );
			return;
		}

		delete_option( 'sirsc_override_medium_size' );
		delete_option( 'sirsc_override_large_size' );
		delete_option( 'sirsc_admin_featured_size' );
		delete_option( 'medium_crop' );
		delete_option( 'large_crop' );
		delete_option( 'sirsc_use_custom_image_sizes' );
		delete_option( 'sirsc_monitor_errors' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name like %s OR option_name like %s OR option_name like %s ',
				$wpdb->esc_like( 'sirsc_settings' ) . '%',
				$wpdb->esc_like( 'sirsc_types' ) . '%',
				$wpdb->esc_like( 'sirsc_user_custom_rules' ) . '%'
			),
			ARRAY_A
		);
		if ( ! empty( $rows ) && is_array( $rows ) ) {
			foreach ( $rows as $v ) {
				delete_option( $v['option_name'] );
			}
		}
		self::admin_notices_cleanup( false );
	}

	/**
	 * The actions to be executed when the plugin is deactivated.
	 */
	public static function activate_plugin() {
		set_transient( self::PLUGIN_TRANSIENT, true );
		set_transient( self::PLUGIN_TRANSIENT . '_adons_notice', true );
	}

	/**
	 * Admin notices.
	 *
	 * @return void
	 */
	public static function admin_notices() {
		$maybe_trans = get_transient( self::PLUGIN_TRANSIENT );
		if ( ! empty( $maybe_trans ) ) {
			?>
			<style>.notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?>{background:rgba(144,202,233,0.5);border-left-color:rgb(144,202,233); padding-left:15px !important; padding-bottom: 15px; padding-right: 0 !important;}.notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?> h3{margin-bottom: 15px;}.notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?> h3 > b{text-transform: uppercase;}.notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?> img{max-width: 100%}.notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?> .button.button-primary{vertical-align: middle; background:#33293f; border-color:#33293f; line-height: 24px; min-height: 24px; text-transform: uppercase;}.notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?> .button.button-primary:hover {color: #C9F427} .notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?> .notice-other-items {padding:10px; width: calc(100% - 3px); margin-left:-16px; color: #000; background-color: rgb(144,202,233)}.notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?> .button.button-primary .dashicons{ color:#C9F427; line-height: 24px;}.notice .pro-item:before, .notice .pro-item-after:after { display: inline-block; box-sizing: border-box; position: relative; content: 'PRO'; background: #8ec920; border: 2px solid #ffffff; color: #FFF; padding: 0 3px; line-height: 16px; font-size: 11px; text-align: center; font-style: normal; vertical-align: middle; border-radius: 3px; margin-right: 5px; box-shadow: 0 0 5px 0 rgba(0,0,0,0.3); margin-top:-2px;} .notice .pro-item-after:after {margin-right:0; margin-left: 5px;}</style>
			<script>(function($) { $(document).ready(function() { var $notice = $('.notice.<?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?>'); var $button = $notice.find('.notice-dismiss'); $notice.unbind('click'); $button.unbind('click'); $notice.on('click', '.notice-dismiss', function(e) { $.get( $notice.data('dismissurl') ); }); }); })(jQuery);</script>

			<div class="updated notice is-dismissible <?php echo esc_attr( self::PLUGIN_TRANSIENT ); ?>"
				data-dismissurl="<?php echo esc_url( admin_url( 'admin-ajax.php?action=sirsc-plugin-deactivate-notice' ) ); ?>">
				<p>
					<?php
					$maybe_pro = sprintf(
						// Translators: %1$s - extensions URL.
						__( '<a class="button button-primary" href="%1$s">%2$s Premium extensions</a> are available for this plugin. ', 'sirsc' ),
						esc_url( admin_url( 'admin.php?page=sirsc-features-manager' ) ),
						'<span class="dashicons dashicons-admin-plugins"></span>'
					);
					$other_notice = sprintf(
						// Translators: %1$s - extensions URL.
						__( '%5$sCheck out my other <a class="button button-primary" href="%1$s" target="_blank">%2$s free plugins</a> on WordPress.org and the <a class="button button-primary" href="%3$s" target="_blank">%4$s other extensions</a> available!', 'sirsc' ),
						'https://profiles.wordpress.org/iulia-cazan/#content-plugins',
						'<span class="dashicons dashicons-heart"></span>',
						'https://iuliacazan.ro/shop/',
						'<span class="dashicons dashicons-star-filled"></span>',
						$maybe_pro
					);

					echo wp_kses_post(
						sprintf(
							// Translators: %1$s - image URL, %2$s - icon URL, %3$s - donate URL, %4$s - link style, %5$s - icon style, %6$s - rating.
							__( '<a href="%3$s" target="_blank"%4$s><img src="%1$s"></a><a href="%9$s"><img src="%2$s"%5$s></a> <h3>%8$s plugin was activated!</h3> This plugin is free to use, but not to operate. Please consider supporting my services by making a <a href="%3$s" target="_blank">donation</a>. It would make me very happy if you would leave a %6$s rating. %7$s', 'sirsc' ),
							esc_url( plugin_dir_url( __FILE__ ) . '/assets/images/buy-me-a-coffee.png' ),
							esc_url( plugin_dir_url( __FILE__ ) . '/assets/images/icon-128x128.gif' ),
							'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ&item_name=Support for development and maintenance (' . urlencode( self::PLUGIN_NAME ) . ')',
							' style="float:right; margin:20px"',
							' style="float:left; margin-right:20px; margin-top:10px; width:86px"',
							'<a href="' . self::PLUGIN_SUPPORT_URL . 'reviews/?rate=5#new-post" class="rating" target="_blank" title="' . esc_attr( 'A huge thanks in advance!', 'sirsc' ) . '">★★★★★</a>',
							__( 'A huge thanks in advance!', 'sirsc' ),
							'<b>' . __( 'Image Regenerate & Select Crop', 'sirsc' ) . '</b>',
							admin_url( 'admin.php?page=image-regenerate-select-crop-settings' )
						)
					);
					?>
					<div class="clear"></div>
				</p>
				<div class="notice-other-items"><?php echo wp_kses_post( $other_notice ); ?></div>
			</div>
			<?php
		}

		$maybe_errors = self::assess_collected_errors();
		if ( ! empty( $maybe_errors ) ) {
			?>
			<div class="updated error is-dismissible">
				<p>
					<?php echo wp_kses_post( $maybe_errors ); ?>
				</p>
			</div>
			<?php
			delete_option( 'sirsc_monitor_errors' );
		}
	}

	/**
	 * Maybe donate or rate.
	 *
	 * @return void
	 */
	public static function show_donate_text() {
		echo wp_kses_post(
			sprintf(
				// Translators: %1$s - donate URL, %2$s - rating.
				__( 'If you find the plugin useful and would like to support my work, please consider making a <a href="%1$s" target="_blank">donation</a>.<br>It would make me very happy if you would leave a %2$s rating.', 'sirsc' ) . ' ' . __( 'A huge thanks in advance!', 'sirsc' ),
				'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ&item_name=Support for development and maintenance (' . urlencode( self::PLUGIN_NAME ) . ')',
				'<a href="' . self::PLUGIN_SUPPORT_URL . 'reviews/?rate=5#new-post" class="rating" target="_blank" title="' . esc_attr( 'A huge thanks in advance!', 'sirsc' ) . '">★★★★★</a>'
			)
		);
	}

	/**
	 * Maybe the custom plugin icon.
	 *
	 * @param  boolean $return True to return.
	 * @return void|string
	 */
	public static function show_plugin_icon( $return = false ) {
		if ( true === $return ) {
			ob_start();
		}
		?>
		<img src="<?php echo esc_url( plugins_url( 'assets/images/icon.svg?v=' . SIRSC_ASSETS_VER, __FILE__ ) ); ?>" class="sirsc-icon-svg" width="32" height="32">
		<?php
		if ( true === $return ) {
			return ob_get_clean();
		}
	}

	/**
	 * Execute notices cleanup.
	 *
	 * @param  boolean $ajax Is AJAX call.
	 * @return void
	 */
	public static function admin_notices_cleanup( $ajax = true ) {
		// Delete transient, only display this notice once.
		delete_transient( self::PLUGIN_TRANSIENT );

		if ( true === $ajax ) {
			// No need to continue.
			wp_die();
		}
	}

	/**
	 * Load text domain for internalization.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'sirsc', false, basename( dirname( __FILE__ ) ) . '/langs/' );
	}

	/**
	 * Enqueue the css and javascript files
	 */
	public static function load_assets() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_style( 'sirsc-style', plugins_url( '/assets/css/style.css', __FILE__ ), array(), SIRSC_ASSETS_VER, false );
		wp_register_script( 'sirsc-custom-js', plugins_url( '/assets/js/custom.js', __FILE__ ), array(), SIRSC_ASSETS_VER, false );

		$upls = wp_upload_dir();
		wp_localize_script(
			'sirsc-custom-js',
			'SIRSC_settings',
			array(
				'confirm_cleanup'        => __( 'Cleanup all?', 'sirsc' ),
				'confirm_regenerate'     => __( 'Regenerate all?', 'sirsc' ),
				'time_warning'           => __( 'This operation might take a while, depending on how many images you have.', 'sirsc' ),
				'irreversible_operation' => __( 'The operation is irreversible!', 'sirsc' ),
				'resolution'             => __( 'Resolution', 'sirsc' ),
				'button_options'         => __( 'Details/Options', 'sirsc' ),
				'button_details'         => __( 'Image Details', 'sirsc' ),
				'button_regenerate'      => __( 'Regenerate', 'sirsc' ),
				'regenerate_log_title'   => __( 'Regenerate Log', 'sirsc' ),
				'cleanup_log_title'      => __( 'Cleanup Log', 'sirsc' ),
				'upload_root_path'       => trailingslashit( $upls['basedir'] ),
				'display_small_buttons'  => ( ! empty( self::$settings['listing_tiny_buttons'] ) ) ? ' tiny' : '',
				'admin_featured_size'    => get_option( 'sirsc_admin_featured_size' ),
				'confirm_raw_cleanup'    => __( 'This action will remove all images generated for this attachment, except for the original file. Are you sure you want proceed?', 'sirsc' ),
			)
		);
		wp_enqueue_script( 'sirsc-custom-js' );
	}

	/**
	 * Return the sgv logo of the plugin.
	 *
	 * @return string
	 */
	public static function get_sirsc_logo() {
		/* width="2.30978in" height="2.30978in" */
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="16px" height="16px" version="1.1" style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd" viewBox="0 0 2541 2541" xmlns:xlink="http://www.w3.org/1999/xlink"><g id="Layer_x0020_1"><metadata id="CorelCorpID_0Corel-Layer"/><path fill="#AAAAAA" d="M173 0l1399 0c-42,139 -50,303 7,479l228 66c-13,-39 -25,-91 -33,-134l-4 -131c91,90 334,354 406,386 61,27 177,23 245,0l-92 -92c-413,-415 -543,-494 -532,-574l175 0 569 569 0 173c-372,172 -744,-159 -1241,-199 -624,-50 -855,427 -729,944l220 68c-3,-67 -22,-100 -22,-171 108,104 300,374 556,292l-573 -574 65 -151 771 773c-360,288 -1029,-307 -1588,-150l0 -1401c0,-95 78,-173 173,-173zm2067 0l128 0c95,0 173,78 173,173l0 131 -301 -304zm301 970l0 1398c0,95 -78,173 -173,173l-1401 0c42,-139 50,-303 -8,-479l-227 -66c12,39 24,91 32,135l5 131c-264,-262 -379,-478 -651,-387l92 93c413,415 542,493 531,573l-175 0 -566 -566 0 -177c371,-169 743,160 1239,200 623,50 855,-426 729,-944l-220 -67c2,66 21,99 22,170 -109,-104 -300,-374 -557,-291l573 574 -64 150 -772 -772c142,-114 417,-68 576,-25 208,55 365,142 574,180 145,26 287,45 441,-3zm-2243 1571l-125 0c-95,0 -173,-78 -173,-173l0 -127 298 300z"/></g></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Add the new menu in tools section that allows to configure the image sizes restrictions.
	 */
	public static function admin_menu() {
		add_menu_page(
			__( 'Image Regenerate & Select Crop', 'sirsc' ),
			'<font>' . __( 'Image Regenerate & Select Crop', 'sirsc' ) . '</font>',
			'manage_options',
			'image-regenerate-select-crop-settings',
			array( get_called_class(), 'image_regenerate_select_crop_settings' ),
			self::get_sirsc_logo(),
			70
		);

		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Advanced Rules', 'sirsc' ),
			__( 'Advanced Rules', 'sirsc' ),
			'manage_options',
			'sirsc-custom-rules-settings',
			array( get_called_class(), 'sirsc_custom_rules_settings' )
		);
		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Media Settings', 'sirsc' ),
			__( 'Media Settings', 'sirsc' ),
			'manage_options',
			admin_url( 'options-media.php#opt_new_crop' )
		);
		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Additional Sizes', 'sirsc' ),
			__( 'Additional Sizes', 'sirsc' ),
			'manage_options',
			admin_url( 'options-media.php#opt_new_sizes' )
		);
	}

	/**
	 * Load the post type settings if available.
	 *
	 * @param string|array|object $ob   The item to be exposed.
	 * @param boolean             $time Show microtime.
	 * @param boolean             $log  Write to error log.
	 */
	public static function debug( $ob = '', $time = true, $log = false ) {
		if ( true === self::$debug && ! empty( $ob ) ) {
			$debug  = PHP_EOL . ( true === $time ) ? '---' . microtime( true ) . PHP_EOL : '';
			$debug .= ( ! is_scalar( $ob ) ) ? print_r( $ob, 1 ) : $ob;
			file_put_contents( dirname( __FILE__ ) . '/logproc', PHP_EOL . $debug, FILE_APPEND );
			if ( true === $log ) {
				error_log( str_replace( PHP_EOL, ' ', $debug ) );
			}
		}
	}

	/**
	 * Load the post type settings if available.
	 *
	 * @param string $post_type The post type.
	 */
	public static function get_post_type_settings( $post_type ) {
		$pt = '';
		if ( ! empty( $post_type ) && ! in_array( $post_type, self::$exclude_post_type, true ) ) {
			$pt = '_' . $post_type;
		}

		$tmp_set = get_option( 'sirsc_settings' . $pt );
		if ( ! empty( $tmp_set ) ) {
			self::$settings = $tmp_set;
		}
	}

	/**
	 * Load the user custom rules if available.
	 *
	 * @return void
	 */
	public static function get_default_user_custom_rules() {
		$default = array();
		for ( $i = 1; $i <= 20; $i ++ ) {
			$default[ $i ] = array(
				'type'     => '',
				'value'    => '',
				'original' => '',
				'only'     => array(),
				'suppress' => '',
			);
		}
		$opt = get_option( 'sirsc_user_custom_rules' );
		if ( ! empty( $opt ) ) {
			$opt = maybe_unserialize( $opt );
			if ( is_array( $opt ) ) {
				foreach ( $opt as $key => $value ) {
					if ( is_array( $value ) ) {
						$default[ $key ] = array_merge( $default[ $key ], $value );
					}
				}
			}
		}

		self::$user_custom_rules = $default;
		self::$user_custom_rules_usable = get_option( 'sirsc_user_custom_rules_usable' );
	}

	/**
	 * Load the settings for a post ID (by parent post type).
	 *
	 * @param integer $post_id The post ID.
	 */
	public static function load_settings_for_post_id( $post_id = 0 ) {
		$post = get_post( $post_id );
		if ( ! empty( $post->post_parent ) ) {
			$pt = get_post_type( $post->post_parent );
			if ( ! empty( $pt ) && ! in_array( $post->post_type, self::$exclude_post_type, true ) ) {
				self::get_post_type_settings( $pt );
			}
			self::hook_upload_extra_rules( $post_id, $post->post_type, $post->post_parent, $pt );
		} elseif ( ! empty( $post->post_type )
			&& ! in_array( $post->post_type, self::$exclude_post_type, true ) ) {
			self::get_post_type_settings( $post->post_type );
			self::hook_upload_extra_rules( $post_id, $post->post_type, 0, '' );
		}

		if ( empty( self::$settings ) ) {
			// Get the general settings.
			self::get_post_type_settings( '' );
		}
	}

	/**
	 * Attempts to override the settings for a single media file.
	 *
	 * @param integer $id          Attachment post ID.
	 * @param string  $type        Attachment post type.
	 * @param integer $parent_id   Attachment post parent ID.
	 * @param string  $parent_type Attachment post parent type.
	 * @return void
	 */
	public static function hook_upload_extra_rules( $id, $type, $parent_id = 0, $parent_type = '' ) {
		if ( ! isset( self::$settings['force_original_to'] ) ) {
			self::$settings['force_original_to'] = '';
		}
		if ( ! isset( self::$settings['complete_global_ignore'] ) ) {
			self::$settings['complete_global_ignore'] = array();
		}
		if ( ! isset( self::$settings['restrict_sizes_to_these_only'] ) ) {
			self::$settings['restrict_sizes_to_these_only'] = array();
		}

		// First, let's apply user custom rules if any are set.
		self::apply_user_custom_rules( $id, $type, $parent_id, $parent_type );

		// Allow to hook from external scripts and create your own upload rules.
		self::$settings = apply_filters( 'sirsc_custom_upload_rule', self::$settings, $id, $type, $parent_id, $parent_type );
	}

	/**
	 * Attempts to override the settings for a single media file.
	 *
	 * @param integer $id          Attachment post ID.
	 * @param string  $type        Attachment post type.
	 * @param integer $parent_id   Attachment post parent ID.
	 * @param string  $parent_type Attachment post parent type.
	 * @return void
	 */
	public static function apply_user_custom_rules( $id, $type, $parent_id = 0, $parent_type = '' ) {
		if ( empty( self::$user_custom_rules_usable ) ) {
			// Fail-fast, no custom rule set.
			return;
		}
		foreach ( self::$user_custom_rules_usable as $key => $val ) {
			$apply        = false;
			$val['value'] = str_replace( ' ', '', $val['value'] );
			switch ( $val['type'] ) {
				case 'ID':
					// This is the attachment parent id.
					if ( in_array( $parent_id, explode( ',', $val['value'] ) ) ) {
						$apply = true;
					}
					break;
				case 'post_parent':
					// This is the post parent.
					$par = wp_get_post_parent_id( $parent_id );
					if ( in_array( $par, explode( ',', $val['value'] ) ) ) {
						$apply = true;
					}
					break;
				case 'post_type':
					// This is the attachment parent type.
					if ( in_array( $parent_type, explode( ',', $val['value'] ) ) ) {
						$apply = true;
					} elseif ( in_array( $type, explode( ',', $val['value'] ) ) ) {
						$apply = true;
					}
					break;
				case 'post_format':
					// This is the post format.
					$format = get_post_format( $parent_id );
					if ( in_array( $format, explode( ',', $val['value'] ) ) ) {
						$apply = true;
					}
					break;
				case 'post_tag':
					// This is the post tag.
					if ( has_tag( explode( ',', $val['value'] ), $parent_id ) ) {
						$apply = true;
					}
					break;
				case 'category':
					// This is the post category.
					if ( has_term( explode( ',', $val['value'] ), 'category', $parent_id ) ) {
						$apply = true;
					}
					break;
				default:
					// This is a taxonomy.
					if ( has_term( explode( ',', $val['value'] ), $val['type'], $parent_id ) ) {
						$apply = true;
					}
					break;
			}

			if ( true === $apply ) {
				// The post matched the rule.
				self::$settings = self::custom_rule_to_settings_rules( self::$settings, $val );

				// Fail-fast, no need to iterate more through the rules to speed things up.
				return;
			}
		}

		// The post did not matched any of the cusom rule.
		self::$settings = self::get_post_type_settings( $type );
	}

	/**
	 * Override and returns the settings after apllying a rule.
	 *
	 * @param array $settings The settings.
	 * @param array $rule     The rule.
	 * @return array
	 */
	public static function custom_rule_to_settings_rules( $settings = array(), $rule = array() ) {
		if ( empty( $rule ) || ! is_array( $rule ) ) {
			// Fail-fast, no need to continue.
			return $settings;
		}

		if ( ! empty( $rule['original'] ) ) {
			if ( '**full**' === $rule['original'] ) {
				$settings['force_original_to'] = '';
			} else {
				// Force original.
				$settings['force_original_to'] = $rule['original'];

				// Let's remove it from the global ignore if it was previously set.
				$settings['complete_global_ignore'] = array_diff(
					$settings['complete_global_ignore'],
					array( $rule['original'] )
				);
			}
		}
		if ( ! empty( $rule['only'] ) && is_array( $rule['only'] ) ) {
			// Make sure we only generate these image sizes.
			$rule['only'] = array_diff( $rule['only'], array( '**full**' ) );
			$settings['restrict_sizes_to_these_only'] = $rule['only'];
			$settings['restrict_sizes_to_these_only'] = array_unique( $settings['restrict_sizes_to_these_only'] );

			if ( ! empty( $settings['default_quality'] ) ) {
				foreach ( $settings['default_quality'] as $s => $q ) {
					if ( ! in_array( $s, $rule['only'] ) ) {
						array_push( $settings['complete_global_ignore'], $s );
					}
				}
			}

			$settings['complete_global_ignore'] = array_unique( $settings['complete_global_ignore'] );
		}

		// Fail-fast, no need to continue.
		return $settings;
	}

	/**
	 * Exclude globally the image sizes selected in the settings from being generated on upload.
	 *
	 * @param array $sizes    The computed image sizes.
	 * @param array $metadata The image metadata.
	 * @return array
	 */
	public static function filter_ignore_global_image_sizes( $sizes, $metadata = array() ) {
		if ( empty( $sizes ) ) {
			$sizes = get_intermediate_image_sizes();
		}
		if ( ! empty( self::$settings['complete_global_ignore'] ) ) {
			foreach ( self::$settings['complete_global_ignore'] as $s ) {
				if ( isset( $sizes[ $s ] ) ) {
					unset( $sizes[ $s ] );
				} else {
					$k = array_keys( $sizes, $s, true );
					if ( ! empty( $k[0] ) ) {
						unset( $sizes[ $k[0] ] );
					}
				}
			}
		}

		$check_size = serialize( $sizes );
		if ( substr_count( $check_size, 'width' ) && substr_count( $check_size, 'height' ) ) {
			// Fail-fast here.
			return array();
		}

		$sizes = self::filter_some_more_based_on_metadata( $sizes, $metadata );
		return $sizes;
	}

	/**
	 * Filter the sizes based on the metadata.
	 *
	 * @param array $sizes    Images sizes.
	 * @param array $metadata Uploaded image metadata.
	 * @return array
	 */
	public static function filter_some_more_based_on_metadata( $sizes, $metadata = array() ) {
		if ( empty( $metadata['file'] ) ) {
			// Fail-fast, no upload.
			return $sizes;
		} else {
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $key => $value ) {
					unset( $sizes[ $key ] );
					if ( in_array( $key, $sizes ) ) {
						$sizes = array_diff( $sizes, array( $key ) );
					}
				}
			}
			if ( empty( $sizes ) ) {
				return array();
			}
		}

		$args = array(
			'meta_key'       => '_wp_attached_file',
			'meta_value'     => $metadata['file'],
			'post_status'    => 'any',
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		$post = new WP_Query( $args );
		if ( ! empty( $post->posts[0] ) ) {
			// The attachment was found.
			self::load_settings_for_post_id( $post->posts[0] );

			if ( ! empty( self::$settings['restrict_sizes_to_these_only'] ) ) {
				foreach ( $sizes as $s => $v ) {
					if ( ! in_array( $s, self::$settings['restrict_sizes_to_these_only'] ) ) {
						unset( $sizes[ $s ] );
					}
				}
			}
		}
		wp_reset_postdata();

		return $sizes;
	}

	/**
	 * Force the custom threshold for WP >= 5.3, when there is a forced original size in the settings.
	 *
	 * @param  integer $initial_value Maximum width.
	 * @param  integer $imagesize     Computed attributes for the file.
	 * @param  string  $file          The file.
	 * @param  integer $attachment_id The attachment ID.
	 * @return integer|boolean
	 */
	public static function big_image_size_threshold_forced( $initial_value, $imagesize, $file, $attachment_id ) {
		if ( ! empty( self::$settings['force_original_to'] ) ) {
			self::load_settings_for_post_id( $attachment_id );
			$size = self::get_all_image_sizes( self::$settings['force_original_to'] );
			if ( empty( $size ) ) {
				return $initial_value;
			}

			$estimated = wp_constrain_dimensions( $imagesize[0], $imagesize[1], $size['width'], $size['height'] );
			self::debug( 'Estimated before applying threshold ' . print_r( $estimated, 1 ), true, true );

			$relative = $estimated[0];
			if ( $estimated[0] < $estimated[1] ) {
				$relative = $estimated[1];
			}

			if ( $relative < $initial_value ) {
				self::debug( 'Force the image threshold to ' . $relative, true, true );
				add_filter(
					'wp_editor_set_quality',
					function( $def, $mime = '' ) {
						return self::DEFAULT_QUALITY;
					},
					10
				);
				return (int) $relative;
			}

			if ( ! empty( $size['width'] ) && $size['width'] < $initial_value ) {
				self::debug( 'Force the image threshold to ' . $size['width'], true, true );
				add_filter(
					'wp_editor_set_quality',
					function( $def, $mime = '' ) {
						return self::DEFAULT_QUALITY;
					},
					10
				);
				return (int) $size['width'];
			}
		}
		return $initial_value;
	}

	/**
	 * Identify a crop position by the image size and return the crop array.
	 *
	 * @param string $size_name Image size slug.
	 * @param string $selcrop   Perhaps a selected crop string.
	 * @return array|boolean
	 */
	public static function identify_crop_pos( $size_name = '', $selcrop = '' ) {
		if ( empty( $size_name ) ) {
			// Fail-fast.
			return false;
		}
		if ( ! empty( $selcrop ) ) {
			$sc = $selcrop;
		} else {
			$sc = ( ! empty( self::$settings['default_crop'][ $size_name ] ) )
				? self::$settings['default_crop'][ $size_name ] : 'cc';
		}

		$c_v = $sc[0];
		$c_h = $sc[1];

		$c_v = ( 'l' === $c_v ) ? 'left' : $c_v;
		$c_v = ( 'c' === $c_v ) ? 'center' : $c_v;
		$c_v = ( 'r' === $c_v ) ? 'right' : $c_v;
		$c_h = ( 't' === $c_h ) ? 'top' : $c_h;
		$c_h = ( 'c' === $c_h ) ? 'center' : $c_h;
		$c_h = ( 'b' === $c_h ) ? 'bottom' : $c_h;

		return array( $c_v, $c_h );
	}

	/**
	 * Compute image size readable info from settings.
	 *
	 * @param string $k    Image size slug.
	 * @param array  $info Settings array.
	 */
	public static function get_usable_info( $k, $info ) {
		$data = array(
			'is_ignored'     => ( ! empty( $info['complete_global_ignore'] ) && in_array( $k, $info['complete_global_ignore'], true ) ) ? 1 : 0,
			'is_checked'     => ( ! empty( $info['exclude'] ) && in_array( $k, $info['exclude'], true ) ) ? 1 : 0,
			'is_unavailable' => ( ! empty( $info['unavailable'] ) && in_array( $k, $info['unavailable'], true ) ) ? 1 : 0,
			'is_forced'      => ( ! empty( $info['force_original_to'] ) && $k === $info['force_original_to'] ) ? 1 : 0,
			'has_crop'       => ( ! empty( $info['default_crop'][ $k ] ) ) ? $info['default_crop'][ $k ] : 'cc',
			'quality'        => ( ! empty( $info['default_quality'][ $k ] ) ) ? (int) $info['default_quality'][ $k ] : self::DEFAULT_QUALITY,
			'line_class'     => '',
		);
		$data['quality'] = ( empty( $data['quality'] ) ) ? self::DEFAULT_QUALITY : $data['quality'];

		$data['line_class'] .= ( ! empty( $data['is_ignored'] ) ) ? ' _sirsc_ignored' : '';
		$data['line_class'] .= ( ! empty( $data['is_forced'] ) ) ? ' _sirsc_force_original' : '';
		$data['line_class'] .= ( empty( $data['is_checked'] ) ) ? ' _sirsc_included' : '';
		return $data;
	}

	/**
	 * Execute the update of the general settings.
	 *
	 * @return void
	 */
	public static function maybe_save_general_settings() {
		$to_update = filter_input( INPUT_POST, 'sirsc-settings-submit', FILTER_DEFAULT );
		if ( ! empty( $to_update ) ) {
			$settings = array(
				'exclude'                  => array(),
				'unavailable'              => array(),
				'force_original_to'        => '',
				'complete_global_ignore'   => array(),
				'placeholders'             => array(),
				'default_crop'             => array(),
				'default_quality'          => array(),
				'enable_perfect'           => false,
				'enable_upscale'           => false,
				'regenerate_missing'       => false,
				'disable_woo_thregen'      => false,
				'sync_settings_ewww'       => false,
				'listing_tiny_buttons'     => false,
				'force_size_choose'        => false,
				'leave_settings_behind'    => false,
				'listing_show_summary'     => false,
				'regenerate_only_featured' => false,
			);

			$post_types   = filter_input( INPUT_POST, '_sirsc_post_types', FILTER_DEFAULT );
			$placeholders = filter_input( INPUT_POST, '_sirsrc_placeholders', FILTER_DEFAULT, FILTER_SANITIZE_STRING );
			if ( ! empty( $placeholders ) ) {
				if ( 'force_global' === $placeholders ) {
					$settings['placeholders']['force_global'] = 1;
				} elseif ( 'only_missing' === $placeholders ) {
					$settings['placeholders']['only_missing'] = 1;
				}
			}

			$ignore = filter_input( INPUT_POST, '_sirsrc_complete_global_ignore', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
			if ( ! empty( $ignore ) ) {
				$settings['complete_global_ignore'] = array_keys( $ignore );
			}

			$exclude = filter_input( INPUT_POST, '_sirsrc_exclude_size', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
			if ( ! empty( $exclude ) ) {
				$settings['exclude'] = array_keys( $exclude );
			}

			$unavailable = filter_input( INPUT_POST, '_sirsrc_unavailable_size', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
			if ( ! empty( $unavailable ) ) {
				$settings['unavailable'] = array_keys( $unavailable );
			}

			$forced = filter_input( INPUT_POST, '_sirsrc_force_original_to', FILTER_DEFAULT, FILTER_SANITIZE_STRING );
			if ( ! empty( $forced ) ) {
				$settings['force_original_to'] = $forced;
			}

			$crop = filter_input( INPUT_POST, '_sirsrc_default_crop', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
			if ( ! empty( $crop ) ) {
				$settings['default_crop'] = $crop;
			}

			$quality = filter_input( INPUT_POST, '_sirsrc_default_quality', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
			if ( ! empty( $quality ) ) {
				$settings['default_quality'] = $quality;
			}

			$use_perfect = filter_input( INPUT_POST, '_sirsrc_enable_perfect', FILTER_DEFAULT );
			if ( ! empty( $use_perfect ) ) {
				$settings['enable_perfect'] = true;
			}
			$use_upscale = filter_input( INPUT_POST, '_sirsrc_enable_upscale', FILTER_DEFAULT );
			if ( ! empty( $use_upscale ) ) {
				$settings['enable_upscale'] = true;
			}
			$regenerate_missing = filter_input( INPUT_POST, '_sirsrc_regenerate_missing', FILTER_DEFAULT );
			if ( ! empty( $regenerate_missing ) ) {
				$settings['regenerate_missing'] = true;
			}
			$regenerate_only_featured = filter_input( INPUT_POST, '_sirsrc_regenerate_only_featured', FILTER_DEFAULT );
			if ( ! empty( $regenerate_only_featured ) ) {
				$settings['regenerate_only_featured'] = true;
			}
			$disable_woo = filter_input( INPUT_POST, '_disable_woo_thregen', FILTER_DEFAULT );
			if ( ! empty( $disable_woo ) ) {
				$settings['disable_woo_thregen'] = true;
			}
			$sync_settings_ewww = filter_input( INPUT_POST, '_sync_settings_ewww', FILTER_DEFAULT );
			if ( ! empty( $sync_settings_ewww ) ) {
				$settings['sync_settings_ewww'] = true;
			}
			$tiny_buttons = filter_input( INPUT_POST, '_sirsrc_listing_tiny_buttons', FILTER_DEFAULT );
			if ( ! empty( $tiny_buttons ) ) {
				$settings['listing_tiny_buttons'] = true;
			}
			$show_summary = filter_input( INPUT_POST, '_sirsrc_listing_show_summary', FILTER_DEFAULT );
			if ( ! empty( $show_summary ) ) {
				$settings['listing_show_summary'] = true;
			}
			$size_choose = filter_input( INPUT_POST, '_sirsrc_force_size_choose', FILTER_DEFAULT );
			if ( ! empty( $size_choose ) ) {
				$settings['force_size_choose'] = true;
			}

			$leave_settings = filter_input( INPUT_POST, '_sirsrc_leave_settings_behind', FILTER_DEFAULT );
			if ( ! empty( $leave_settings ) ) {
				$settings['leave_settings_behind'] = true;
			}

			if ( ! empty( $post_types ) ) { // Specific post type.
				update_option( 'sirsc_settings_' . $post_types, $settings );
			} else { // General settings.
				update_option( 'sirsc_settings', $settings );
			}

			self::$settings = get_option( 'sirsc_settings' );
			update_option( 'sirsc_settings_updated', current_time( 'timestamp' ) );

			self::image_placeholder_for_image_size( 'full', true );

			$is_ajax = filter_input( INPUT_POST, 'sirsc_autosubmit_save', FILTER_DEFAULT );
			if ( empty( $is_ajax ) ) {
				wp_safe_redirect( self::$plugin_url );
				exit;
			}
		}
	}

	/**
	 * Maybe execute the update of custom rules.
	 *
	 * @return void
	 */
	public static function maybe_update_user_custom_rules() {
		$user_custom_rules = self::get_default_user_custom_rules();

		$to_update = filter_input( INPUT_POST, 'sirsc-save-custom-rules', FILTER_DEFAULT );
		if ( empty( $to_update ) ) {
			// Fail-fast, the custom rules form was not submitted.
			return;
		}

		$urules  = filter_input( INPUT_POST, '_user_custom_rule', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
		$ucrules = array();
		foreach ( self::$user_custom_rules as $k => $v ) {
			if ( isset( $urules[ $k ] ) ) {
				$ucrules[ $k ] = ( ! empty( $urules[ $k ] ) ) ? $urules[ $k ] : '';
			}
		}

		foreach ( $ucrules as $k => $v ) {
			if ( ! empty( $v['type'] ) && ! empty( $v['original'] ) ) {
				if ( empty( $v['only'] ) || ! is_array( $v['only'] ) ) {
					$v['only'] = array();
				}
				if ( ! empty( $v['only'] ) ) {
					$ucrules[ $k ]['only'] = $v['only'];
				} else {
					if ( '**full**' !== $v['original'] ) {
						$ucrules[ $k ]['only'] = array( $v['original'] );
					}
				}
				if ( '**full**' !== $v['original'] ) {
					$ucrules[ $k ]['only'] = array_merge( $ucrules[ $k ]['only'], array( $v['original'] ) );
				}
				if ( ! empty( $ucrules[ $k ]['only'] ) ) {
					$ucrules[ $k ]['only'] = array_diff( $ucrules[ $k ]['only'], array( '**full**' ) );
				}
			}
		}

		$ucrules = self::update_user_custom_rules_priority( $ucrules );
		update_option( 'sirsc_user_custom_rules', $ucrules );

		$usable_crules = array();
		foreach ( $ucrules as $key => $val ) {
			if ( ! empty( $val['type'] ) && ! empty( $val['value'] )
				&& ! empty( $val['original'] ) && ! empty( $val['only'] )
				&& empty( $val['suppress'] ) ) {
				$usable_crules[] = $val;
			}
		}
		$usable_crules = self::update_user_custom_rules_priority( $usable_crules );
		update_option( 'sirsc_user_custom_rules_usable', $usable_crules );

		self::$user_custom_rules_usable = $usable_crules;
		update_option( 'sirsc_settings_updated', current_time( 'timestamp' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=sirsc-custom-rules-settings' ) );
		exit;
	}

	/**
	 * Maybe execute the options update if the nonce is valid, then redirect.
	 *
	 * @return void
	 */
	public static function maybe_save_settings() {
		$notice = get_option( 'sirsc_settings_updated' );
		if ( ! empty( $notice ) ) {
			add_action( 'admin_notices', array( get_called_class(), 'on_settings_update_notice' ), 10 );
			delete_option( 'sirsc_settings_updated' );
		}

		$nonce = filter_input( INPUT_POST, '_sirsc_settings_nonce', FILTER_DEFAULT );
		if ( empty( $nonce ) ) {
			return;
		}
		if ( ! empty( $nonce ) ) {
			if ( ! wp_verify_nonce( $nonce, '_sirsc_settings_save' ) || ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Action not allowed.', 'sirsc' ), esc_html__( 'Security Breach', 'sirsc' ) );
			}

			// Save the general settings.
			self::maybe_save_general_settings();
			self::$settings = get_option( 'sirsc_settings' );

			// Custom rules update.
			self::maybe_update_user_custom_rules();
			self::get_default_user_custom_rules();

			$is_ajax = filter_input( INPUT_POST, 'sirsc_autosubmit_save', FILTER_DEFAULT );
			if ( ! empty( $is_ajax ) ) {
				wp_die();
				die();
			}
		}
	}

	/**
	 * Output the admin success message for email test sent.
	 *
	 * @return void
	 */
	public static function on_settings_update_notice() {
		$class   = 'notice notice-success is-dismissible';
		$message = __( 'The plugin settings have been updated successfully.', 'sirsc' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Maybe re-order the custom rules options as priorities.
	 *
	 * @access public
	 * @static
	 * @param array $usable_crules The rules to be prioritized.
	 * @return array
	 */
	public static function update_user_custom_rules_priority( $usable_crules = array() ) {
		if ( ! empty( $usable_crules ) ) {
			// Put the rules in the priority order.
			$ucr = array();
			$c   = 0;

			// Collect the ID rules.
			foreach ( $usable_crules as $k => $rule ) {
				if ( 'ID' === $rule['type'] ) {
					$ucr[ ++ $c ] = $rule;
					unset( $usable_crules[ $k ] );
				}
			}
			// Collect the post type rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'post_type' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the post format rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'post_format' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the post parent rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'post_parent' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the tags rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'post_tag' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the categories rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					if ( 'category' === $rule['type'] ) {
						$ucr[ ++ $c ] = $rule;
						unset( $usable_crules[ $k ] );
					}
				}
			}
			// Collect the test of the taxonomies rules.
			if ( ! empty( $usable_crules ) ) {
				foreach ( $usable_crules as $k => $rule ) {
					$ucr[ ++ $c ] = $rule;
					unset( $usable_crules[ $k ] );
				}
			}

			$usable_crules = $ucr;
		}

		return $usable_crules;
	}

	/**
	 * Maybe all features tab.
	 *
	 * @return void
	 */
	public static function maybe_all_features_tab() {
		$tab = filter_input( INPUT_GET, 'page', FILTER_DEFAULT );
		?>
		<div class="sirsc-tabbed-menu-buttons">
			<?php foreach ( self::$menu_items as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>"
					class="button <?php if ( $item['slug'] === $tab ) : ?>
					button-primary<?php endif; ?>"
					>
					<?php if ( ! empty( $item['icon'] ) ) : ?>
						<?php echo wp_kses_post( $item['icon'] ); ?>
					<?php endif; ?>
					<?php echo esc_html( $item['title'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Functionality to manage the image regenerate & select crop settings.
	 */
	public static function image_regenerate_select_crop_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			// Verify user capabilities in order to deny the access if the user does not have the capabilities.
			wp_die( esc_html__( 'Action not allowed.', 'sirsc' ) );
		}

		$allow_html = array(
			'table' => array(
				'class' => array(),
				'cellspacing' => array(),
				'cellpadding' => array(),
				'title' => array(),
			),
			'tbody' => array(),
			'tr' => array(),
			'td' => array( 'title' => array() ),
			'label' => array(),
			'input' => array(
				'type' => array(),
				'name' => array(),
				'id' => array(),
				'value' => array(),
				'checked' => array(),
				'onchange' => array(),
			),
		);

		$post_types              = self::get_all_post_types_plugin();
		$_sirsc_post_types       = filter_input( INPUT_GET, '_sirsc_post_types', FILTER_DEFAULT );

		self::$settings          = maybe_unserialize( get_option( 'sirsc_settings' ) );
		$settings                = self::$settings;
		$default_plugin_settings = $settings;
		if ( ! empty( $_sirsc_post_types ) ) {
			$settings = maybe_unserialize( get_option( 'sirsc_settings_' . $_sirsc_post_types ) );
		}

		// Display the form and the next digests contents.
		?>
		<div class="wrap sirsc-settings-wrap">
			<h1>
				<?php self::show_plugin_icon(); ?> <?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</h1>

			<?php self::maybe_all_features_tab(); ?>
			<div class="sirsc-tabbed-menu-content">
				<h1><?php esc_html_e( 'General Settings', 'sirsc' ); ?></h1>
				<br>
				<div class="sirsc-image-generate-functionality">
					<table class="widefat sirsc-striped">
						<tr>
							<td>

								<?php esc_html_e( 'Please make sure you visit and update your settings here whenever you activate a new theme or plugins, so that the new image size registered, adjusted or removed to be reflected also here, and in this way to assure the optimal behavior for the features of this plugin.', 'sirsc' ); ?>
								<span class="dashicons dashicons-image-crop"></span> <a href="<?php echo esc_url( admin_url( 'options-media.php' ) ); ?>#opt_new_crop"><?php esc_html_e( 'Images Custom Settings', 'sirsc' ); ?></a>
								<span class="dashicons dashicons-format-gallery"></span> <a href="<?php echo esc_url( admin_url( 'options-media.php' ) ); ?>#opt_new_sizes"><?php esc_html_e( 'Define Custom Image Sizes', 'sirsc' ); ?></a>
							</td>
						</tr>
					</table>

					<?php
					if ( false === self::$is_configured ) {
						echo '<div class="update-nag">' . esc_html__( 'Image Regenerate & Select Crop Settings are not configured yet.', 'sirsc' ) . '</div><hr/>';
					}
					?>
					<form id="sirsc_settings_frm" name="sirsc_settings_frm" action="" method="post">
						<?php wp_nonce_field( '_sirsc_settings_save', '_sirsc_settings_nonce' ); ?>

						<h3><?php esc_html_e( 'Option to Enable Placeholders', 'sirsc' ); ?></h3>
						<p><?php esc_html_e( 'This option allows you to display placeholders for the front-side images called programmatically (the images that are not embedded in the content with their src, but exposed using WordPress native functions). If there is no placeholder set, then the WordPress default behavior would be to display the full-size image instead of a missing image size, hence your pages might load slower, and when using grids, the items would not look even.', 'sirsc' ); ?>
							<?php
							if ( ! wp_is_writable( realpath( SIRSC_PLACEHOLDER_FOLDER ) ) ) {
								esc_html_e( 'This feature might not work properly, your placeholders folder is not writtable.', 'sirsc' );
							}
							?>
						</p>
						<table cellpadding="0" cellspacing="0" class="wp-list-table widefat striped sirsc-table">
							<tr class="middle">
								<td>
									<h3><a class="dashicons dashicons-info" title="<?php echo esc_attr( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_developer_mode')"></a>
										<?php esc_html_e( 'Images Placeholders Developer Mode', 'sirsc' ); ?></h3>
									<div class="sirsc_info_box_wrap">
										<div id="info_developer_mode" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_developer_mode')">
											<?php esc_html_e( 'If you activate the "force global" option, all the images on the front-side that are related to posts will be replaced with the placeholders that mention the image size required. This is useful for debugging, to quickly identify the image sizes used for each layout and perhaps to help you regenerate the mission ones or decide what to keep or what to remove.', 'sirsc' ); ?><hr/><?php esc_html_e( 'If you activate the "only missing images" option, all the programmatically called images on the front-side that are related to posts and do not have the requested image size generated will be replaced with the placeholders that mention the image size required. This is useful for showing smaller images instead of the full-size images (as WordPress does by default), hence for speeding up the pages loading.', 'sirsc' ); ?>
										</div>
									</div>
								</td>
								<td>
									<label><input type="radio" name="_sirsrc_placeholders" id="_sirsrc_placeholders_none" value="" <?php checked( true, ( empty( $settings['placeholders'] ) ) ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'no placeholder', 'sirsc' ); ?></label>
								</td>
								<td>
									<label><input type="radio" name="_sirsrc_placeholders" id="_sirsrc_placeholders_force_global" value="force_global" <?php checked( true, ( ! empty( $settings['placeholders']['force_global'] ) ) ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'force global', 'sirsc' ); ?></label>
								</td>
								<td>
									<label><input type="radio" name="_sirsrc_placeholders" id="_sirsrc_placeholders_only_missing" value="only_missing" <?php checked( true, ( ! empty( $settings['placeholders']['only_missing'] ) ) ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'only missing images', 'sirsc' ); ?></label>
								</td>
								<td class="textright"><?php submit_button( __( 'Save Settings', 'sirsc' ), 'primary', 'sirsc-settings-submit', false, array( 'id' => 'sirsc-settings-submit-0' ) ); ?></td>

							</tr>
						</table>

						<h3><?php esc_html_e( 'Option to Exclude Image Sizes', 'sirsc' ); ?></h3>
						<p><?php esc_html_e( 'This plugin provides the option to select image sizes that will be excluded from the generation of the new images. By default, all image sizes defined in the system will be allowed (these are programmatically registered by the themes and plugins you activate in your site, without you even knowing about these). You can set up a global configuration, or more specific configuration for all images attached to a particular post type. If no particular settings are made for a post type, then the default general settings will be used.', 'sirsc' ); ?> <b><?php esc_html_e( 'The options for which you made some settings are marked with * in the dropdown below.', 'sirsc' ); ?></b></p>

						<table cellpadding="0" cellspacing="0" class="wp-list-table widefat striped sirsc-table">
							<tr class="middle">
								<td><h3><?php esc_html_e( 'Apply the settings below for the selected option', 'sirsc' ); ?></h3></td>
								<td>
									<?php
									if ( ! empty( $post_types ) ) {
										$ptypes = array();
										$has    = ( ! empty( $default_plugin_settings ) ) ? '* ' : '';
										?>
										<select name="_sirsc_post_types" id="_sirsc_post_type" onchange="sirsc_load_post_type(this, '<?php echo esc_url( self::$plugin_url ); ?>')"><option value=""><?php echo esc_html( $has . esc_html__( 'General settings (used as default for all images)', 'sirsc' ) ); ?></option>
										<?php
										foreach ( $post_types as $pt => $obj ) {
											array_push( $ptypes, $pt );
											$is_sel = ( $_sirsc_post_types === $pt ) ? 1 : 0;
											$extra  = ( ! empty( $obj->_builtin ) ) ? '' : ' (custom post type)';
											$pt_s   = maybe_unserialize( get_option( 'sirsc_settings_' . $pt ) );
											$has    = ( ! empty( $pt_s ) ) ? '* ' : '';
											?>
											<option value="<?php echo esc_attr( $pt ); ?>"<?php selected( 1, $is_sel ); ?>><?php echo esc_html( $has . esc_html__( 'Settings for images attached to a ', 'sirsc' ) . ' ' . $pt . $extra ); ?></option>
											<?php
										}
										?>
										</select>
										<?php
										update_option( 'sirsc_types_options', $ptypes );
									}
									?>
								</td>
								<td class="textright"><?php submit_button( __( 'Save Settings', 'sirsc' ), 'primary', 'sirsc-settings-submit', false, array( 'id' => 'sirsc-settings-submit-1' ) ); ?></td>
							</tr>
						</table>
						<table cellpadding="0" cellspacing="0" class="wp-list-table widefat fixed striped sirsc-table notop">
							<thead>
								<tr class="middle noborder">
									<td id="th-set-ignore" scope="col" class="manage-column">
										<h3><a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_global_ignore')"></a>
											<span><?php esc_html_e( 'Global Ignore', 'sirsc' ); ?></span></h3>
										<div class="sirsc_info_box_wrap">
											<div id="info_global_ignore" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_global_ignore')">
												<?php esc_html_e( 'This option allows you to exclude globally from the application some of the image sizes that are registered through various plugins and themes options, that you perhaps do not need at all in your application (these are just stored in your folders and database but not actually used/visible on the front-end).', 'sirsc' ); ?>
												<hr/><?php esc_html_e( 'By excluding these, the unnecessary image sizes will not be generated at all.', 'sirsc' ); ?>
											</div>
										</div>
									</td>
									<td id="th-set-info" scope="col" class="manage-column">
										<h3><a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_default_quality')"></a> <span><?php esc_html_e( 'Image Size Info', 'sirsc' ); ?></span></h3>
										<div class="sirsc_info_box_wrap">
											<div id="info_default_quality" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_default_quality')">
												<?php esc_html_e( 'The quality option is allowing you to control the quality of the images that are generated for each of the image sizes, starting from the quality of the image you upload. This can be useful for performance.', 'sirsc' ); ?><hr><b><?php esc_html_e( 'However, please be careful not to change the quality of the full image or the quality of the image size that you set as the forced original.', 'sirsc' ); ?></b><hr><?php esc_html_e( 'Setting a lower quality is recommended for smaller images sizes, that are generated from the full/original file.', 'sirsc' ); ?>
											</div>
										</div>
									</td>
									<td id="th-set-hide" scope="col" class="manage-column">
										<h3><a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_exclude')"></a> <span><?php esc_html_e( 'Hide Preview', 'sirsc' ); ?></span></h3>
										<div class="sirsc_info_box_wrap">
											<div id="info_exclude" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_exclude')">
												<?php esc_html_e( 'This option allows you to hide from the "Image Regenerate & Select Crop Settings" lightbox the details and options for the selected image sizes (when you or other admins are checking the image details, the hidden image sizes will not be shown).', 'sirsc' ); ?><hr/><?php esc_html_e( 'This is useful when you want to restrict from other users the functionality of crop or resize for particular image sizes, or to just hide the image sizes you added to global ignore.', 'sirsc' ); ?><hr/><?php esc_html_e( 'If you set the image size as ignored or unavailable, this will not be listed in the media screen when the dropdown of image sizes will be shown.', 'sirsc' ); ?>
											</div>
										</div>
									</td>
									<td id="th-set-original" scope="col" class="manage-column">
										<h3><a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_force_original')"></a> <span><?php esc_html_e( 'Force Original', 'sirsc' ); ?></span></h3>
										<div class="sirsc_info_box_wrap">
											<div id="info_force_original" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_force_original')">
												<?php esc_html_e( 'This option means that when uploading an image, the original image will be replaced completely by the image size you select (the image generated, scaled or cropped to a specific width and height will become the full size for that image going further).', 'sirsc' ); ?><hr/><?php esc_html_e( 'This can be very useful if you do not use the original image in any of the layouts at the full size, and this might save some storage space.', 'sirsc' ); ?><hr/><?php esc_html_e( 'Leave "nothing selected" to keep the full/original image as the file you upload (default WordPress behavior).', 'sirsc' ); ?>
											</div>
										</div>
									</td>
									<td id="th-set-crop" width="14%" scope="col" class="manage-column">
										<h3><a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Default Crop', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_default_crop')"></a> <span><?php esc_html_e( 'Default Crop', 'sirsc' ); ?></span></h3>
										<div class="sirsc_info_box_wrap">
											<div id="info_default_crop" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_default_crop')">
												<?php esc_html_e( 'This option allows you to set a default crop position for the images generated for a particular image size. This option will be applied when you chose to regenerate an individual image or all of these, and also when a new image is uploaded.', 'sirsc' ); ?>
											</div>
										</div>
									</td>
									<td id="th-set-cleanup" width="14%" scope="col" class="manage-column">
										<h3><a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_clean_up')"></a> <span><?php esc_html_e( 'Cleanup', 'sirsc' ); ?></span></h3>
										<div class="sirsc_info_box_wrap">
											<div id="info_clean_up" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_clean_up')">
												<?php esc_html_e( 'This option allows you to clean up all the image generated for a particular image size you already have in the application, and that you do not use or do not want to use anymore on the front-end.', 'sirsc' ); ?><hr/><b><?php esc_html_e( 'Please be careful, once you click to remove the images for a selected image size, the action is irreversible, the images generated up this point will be deleted from your folders and database records.', 'sirsc' ); ?></b> <?php esc_html_e( 'You can regenerate these later if you click the Regenerate button.', 'sirsc' ); ?>
											</div>
										</div>
									</td>
									<td id="th-set-regenerate" width="15%" scope="col" class="manage-column">
										<h3><a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_regenerate')"></a> <span><?php esc_html_e( 'Regenerate', 'sirsc' ); ?></span></h3>
										<div class="sirsc_info_box_wrap">
											<div id="info_regenerate" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_regenerate')">
												<?php esc_html_e( 'This option allows you to regenerate the images for the selected image size.', 'sirsc' ); ?><hr/><b><?php esc_html_e( 'Please be careful, once you click the button to regenerate the selected image size, the action is irreversible, the images already generated will be overwritten.', 'sirsc' ); ?></b>
											</div>
										</div>
									</td>
								</tr>
								<tr>
									<td></td>
									<td colspan="2">
										<a onclick="jQuery('input.sirsc-size-quality').val(<?php echo (int) self::DEFAULT_QUALITY; ?>); sirsc_autosubmit()"> <?php esc_html_e( 'reset to default quality', 'sirsc' ); ?></a>
									</td>
									<td colspan="4"><div><label><input type="radio" name="_sirsrc_force_original_to" id="_sirsrc_force_original_to_0" value="0" <?php checked( 1, 1 ); ?> onchange="sirsc_autosubmit()" onclick="jQuery('.maybe-sirsc-selector.row-original').removeClass('row-original');" /> <?php esc_html_e( 'nothing selected (keep the full/original file uploaded)', 'sirsc' ); ?></label></div></td>
								</tr>
							</thead>
							<tbody id="the-list">
								<?php $all_sizes = self::get_all_image_sizes(); ?>
								<?php if ( ! empty( $all_sizes ) ) : ?>
									<?php foreach ( $all_sizes as $k => $v ) : ?>
										<?php
										$use  = self::get_usable_info( $k, $settings );
										$clon = '';
										if ( ! substr_count( $use['line_class'], '_sirsc_included' ) ) {
											$clon .= ' row-hide';
										}
										if ( substr_count( $use['line_class'], '_sirsc_ignored' ) ) {
											$clon .= ' row-ignore';
										}
										if ( substr_count( $use['line_class'], '_sirsc_force_original' ) ) {
											$clon .= ' row-original';
										}

										$tr_id = 'sirsc-settings-for-' . esc_attr( $k );
										?>
										<tr id="<?php echo esc_attr( $tr_id ); ?>" class="hentry maybe-sirsc-selector <?php echo esc_attr( $clon ); ?>">
											<td class="th-set-ignore">
												<div class="inner"><label><input type="checkbox" name="_sirsrc_complete_global_ignore[<?php echo esc_attr( $k ); ?>]" id="_sirsrc_complete_global_ignore_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $k ); ?>" <?php checked( 1, $use['is_ignored'] ); ?>
												onchange="sirsc_autosubmit()" onclick="jQuery('#<?php echo esc_attr( $tr_id ); ?>').toggleClass('row-ignore')" /> <?php esc_html_e( 'global ignore', 'sirsc' ); ?></label></div>
											</td>
											<td class="th-set-info">
												<div class="inner">
													<b><?php echo esc_html( $k ); ?></b>
													<hr><?php self::image_placeholder_for_image_size( $k, true ); ?>
													<p class="info"><?php echo wp_kses_post( self::size_to_text( $v ) ); ?></p>
													<div class="sirsc-size-quality-wrap">
														<?php esc_html_e( 'Quality', 'sirsc' ); ?>
														<input type="number" name="_sirsrc_default_quality[<?php echo esc_attr( $k ); ?>]" id="_sirsrc_default_quality_<?php echo esc_attr( $k ); ?>" max="100" min="1" value="<?php echo (int) $use['quality']; ?>" size="2" onchange="alert('<?php esc_attr_e( 'Please be aware that your are changing the quality of the images going further for this images size!', 'sirsc' ); ?>'); sirsc_autosubmit()" class="sirsc-size-quality">
													</div>
												</div>
											</td>
											<td class="th-set-hide">
												<div class="inner">
													<label><input type="checkbox" name="_sirsrc_exclude_size[<?php echo esc_attr( $k ); ?>]" id="_sirsrc_exclude_size_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $k ); ?>" <?php checked( 1, $use['is_checked'] ); ?> onchange="sirsc_autosubmit()" onclick="jQuery('#<?php echo esc_attr( $tr_id ); ?>').toggleClass('row-hide')" /> <?php esc_html_e( 'hide', 'sirsc' ); ?></label>
													<br><label><input type="checkbox" name="_sirsrc_unavailable_size[<?php echo esc_attr( $k ); ?>]" id="_sirsrc_unavailable_size_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $k ); ?>" <?php checked( 1, $use['is_unavailable'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'unavailable', 'sirsc' ); ?></label>
												</div>
											</td>
											<td class="th-set-original">
												<div class="inner"><label><input type="radio" name="_sirsrc_force_original_to" id="_sirsrc_force_original_to_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $k ); ?>" <?php checked( 1, $use['is_forced'] ); ?> onchange="sirsc_autosubmit()" onclick="jQuery('.maybe-sirsc-selector.row-original').removeClass('row-original'); jQuery('#<?php echo esc_attr( $tr_id ); ?>').toggleClass('row-original')" /> <?php esc_html_e( 'force original', 'sirsc' ); ?></label></div>
											</td>
											<td class="th-set-crop">
												<div class="inner position"><?php if ( ! empty( $v['crop'] ) ) : ?>
													<?php esc_html_e( 'Crop Position', 'sirsc' ); ?><?php echo wp_kses( str_replace( 'crop_small_type_' . $k . '"', '_sirsrc_default_crop[' . $k . ']" onchange="sirsc_autosubmit()"', self::make_generate_images_crop( 0, $k, false, $use['has_crop'] ) ), $allow_html ); ?>
												<?php endif; ?></div>
											</td>
											<td class="th-set-cleanup textcenter">
												<?php
												$total_cleanup = self::calculate_total_to_cleanup( $_sirsc_post_types, $k );
												$cleanup_class = ( ! empty( $total_cleanup ) ) ? '' : 'display: none';
												?>
												<span id="sirsc-cleanup-button-for-<?php echo esc_attr( $k ); ?>" class="button widefat button-secondary" style="<?php echo esc_attr( $cleanup_class ); ?>" title="<?php echo intval( $total_cleanup ); ?>" onclick="sirsc_initiate_cleanup('<?php echo esc_attr( $k ); ?>');"><b class="dashicons dashicons-no" title="<?php esc_attr_e( 'Cleanup All', 'sirsc' ); ?>"></b> <?php esc_html_e( 'Cleanup', 'sirsc' ); ?></span>
												<div class="sirsc_button-regenerate-wrap title">
													<div id="_sirsc_cleanup_initiated_for_<?php echo esc_attr( $k ); ?>">
														<input type="hidden" name="_sisrsc_image_size_name" id="_sisrsc_image_size_name<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $k ); ?>"/>
														<input type="hidden" name="_sisrsc_post_type" id="_sisrsc_post_type<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $_sirsc_post_types ); ?>"/>
														<input type="hidden" name="_sisrsc_image_size_name_page" id="_sisrsc_image_size_name_page<?php echo esc_attr( $k ); ?>" value="0"/>
														<div class="sirsc_button-regenerate">
															<div><div id="_sirsc_cleanup_initiated_for_<?php echo esc_attr( $k ); ?>_result" data-processing="0" class="result"><span class="spinner off"></span></div></div>
														</div>
														<div class="sirsc_clearAll"></div>
													</div>
												</div>
											</td>
											<td class="th-set-regenerate textcenter">
												<?php if ( empty( $use['is_ignored'] ) ) { ?>

													<span class="button widefat button-primary" onclick="sirsc_initiate_regenerate('<?php echo esc_attr( $k ); ?>');"><b class="dashicons dashicons-update" title="<?php esc_attr_e( 'Regenerate All', 'sirsc' ); ?>"></b> <?php esc_html_e( 'Regenerate', 'sirsc' ); ?></span>
													<?php
													$last_id   = self::get_regenerate_last_processed_id( $k );
													$cl_resume = ( ! empty( $last_id ) ) ? '' : 'is-hidden';
													?>
													<p id="_sirsc_initiate_regenerate_resume_<?php echo esc_attr( $k ); ?>"
														class="<?php echo esc_attr( $cl_resume ); ?>">
														<span class="button widefat" onclick="sirsc_initiate_regenerate_resume('<?php echo esc_attr( $k ); ?>');"><?php esc_html_e( 'Resume', 'sirsc' ); ?></span>
														<input type="hidden" name="resume_from" id="_sirsc_initiate_regenerate_resume_<?php echo esc_attr( $k ); ?>_id" value="<?php echo (int) $last_id; ?>">
													</p>

													<div class="sirsc_button-regenerate-wrap title on">
														<div id="_sirsc_regenerate_initiated_for_<?php echo esc_attr( $k ); ?>">
															<input type="hidden" name="_sisrsc_regenerate_image_size_name" id="_sisrsc_regenerate_image_size_name<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $k ); ?>"/>
															<input type="hidden" name="_sisrsc_post_type" id="_sisrsc_regenerate_post_type<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $_sirsc_post_types ); ?>"/>
															<input type="hidden" name="_sisrsc_regenerate_image_size_name_page" id="_sisrsc_regenerate_image_size_name_page<?php echo esc_attr( $k ); ?>" value="0"/>
															<div class="sirsc_button-regenerate">
																<div><div id="_sirsc_regenerate_initiated_for_<?php echo esc_attr( $k ); ?>_result" data-processing="0" class="result"><span class="spinner off"></span></div></div>
															</div>
															<div class="sirsc_clearAll"></div>
														</div>
													</div>
												<?php } ?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>

						<br>
						<table cellpadding="0" cellspacing="0" class="wp-list-table widefat striped sirsc-table">
							<tr class="middle">
								<td width="15%">
									<h3><?php esc_html_e( 'Other Settings', 'sirsc' ); ?></h3>
								</td>
								<td>
									<?php
									if ( empty( $settings['enable_perfect'] ) ) {
										$settings['enable_perfect'] = false;
									}
									?>
									<label><input type="checkbox" name="_sirsrc_enable_perfect" id="_sirsrc_enable_perfect" <?php checked( true, $settings['enable_perfect'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'generate only perfect fit sizes', 'sirsc' ); ?></label>
									<a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_perfect_fit')"></a>

									<?php
									if ( empty( $settings['enable_upscale'] ) ) {
										$settings['enable_upscale'] = false;
									}
									?>
									&nbsp;&nbsp;&nbsp; <label><input type="checkbox" name="_sirsrc_enable_upscale" id="_sirsrc_enable_upscale" <?php checked( true, $settings['enable_upscale'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'attempt to upscale when generating only perfect fit sizes', 'sirsc' ); ?></label>
									<a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_perfect_fit_upscale')"></a>

									<div class="sirsc_info_box_wrap">
										<div id="info_perfect_fit" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_regenerate')">
											<?php esc_html_e( 'This option allows you to generate only images that match exactly the width and height of the crop/resize requirements, when the option is enabled. Otherwise, the script will generate anything possible for smaller images.', 'sirsc' ); ?>
										</div>
									</div>

									<div class="sirsc_info_box_wrap">
										<div id="info_perfect_fit_upscale" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_regenerate')">
											<?php esc_html_e( 'This option allows you to upscale the images when using the perfect fit option. This allows that images that have at least the original width close to the expected width or the original height close to the expected height (for example, the original image has 800x600 and the crop size 700x700) to be generated from a upscaled image.', 'sirsc' ); ?>
										</div>
									</div>

									<hr>
									<?php
									if ( empty( $settings['regenerate_missing'] ) ) {
										$settings['regenerate_missing'] = false;
									}
									if ( empty( $settings['regenerate_only_featured'] ) ) {
										$settings['regenerate_only_featured'] = false;
									}
									?>
									<label><input type="checkbox" name="_sirsrc_regenerate_missing" id="_sirsrc_regenerate_missing" <?php checked( true, $settings['regenerate_missing'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'regenerate only missing files', 'sirsc' ); ?></label>
									<a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_regenerate_missing')"></a>

									&nbsp;&nbsp;&nbsp; <label><input type="checkbox" name="_sirsrc_regenerate_only_featured" id="_sirsrc_regenerate_only_featured" <?php checked( true, $settings['regenerate_only_featured'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'regenerate/cleanup only featured images', 'sirsc' ); ?></label>
									<a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_regenerate_only_featured')"></a>

									<div class="sirsc_info_box_wrap">
										<div id="info_regenerate_missing" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_regenerate_missing')">
											<?php esc_html_e( 'This option allows you to regenerate only the images that do not exist, without overriding the existing ones.', 'sirsc' ); ?>
										</div>
									</div>
									<div class="sirsc_info_box_wrap">
										<div id="info_regenerate_only_featured" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_regenerate_only_featured')">
											<?php esc_html_e( 'This option allows you to regenerate/cleanup only the images that are set as featured image for any of the posts.', 'sirsc' ); ?>
										</div>
									</div>

									<hr>
									<?php
									if ( empty( $settings['listing_tiny_buttons'] ) ) {
										$settings['listing_tiny_buttons'] = false;
									}
									?>
									<label><input type="checkbox" name="_sirsrc_listing_tiny_buttons" id="_sirsrc_listing_tiny_buttons" <?php checked( true, $settings['listing_tiny_buttons'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'show small buttons in the media screen', 'sirsc' ); ?></label>

									<?php
									if ( empty( $settings['listing_show_summary'] ) ) {
										$settings['listing_show_summary'] = false;
									}
									?>
									&nbsp;&nbsp;&nbsp; <label><input type="checkbox" name="_sirsrc_listing_show_summary" id="_sirsrc_listing_show_summary" <?php checked( true, $settings['listing_show_summary'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'show attachment image sizes summary in the media screen', 'sirsc' ); ?></label>

									<hr>
									<?php
									if ( empty( $settings['force_size_choose'] ) ) {
										$settings['force_size_choose'] = false;
									}
									?>
									<label><input type="checkbox" name="_sirsrc_force_size_choose" id="_sirsrc_force_size_choose" <?php checked( true, $settings['force_size_choose'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'filter and expose the image sizes available for the attachment display settings in the media dialog (any registered available size, even when there is no explicit filter applied)', 'sirsc' ); ?></label>

									<?php if ( class_exists( 'WooCommerce' ) ) : ?>
										<?php
										if ( empty( $settings['disable_woo_thregen'] ) ) {
											$settings['disable_woo_thregen'] = false;
										}
										?>
										<hr>
										<label><input type="checkbox" name="_disable_woo_thregen" id="_sirsrc_disable_woo_thregen" <?php checked( true, $settings['disable_woo_thregen'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'turn off the WooCommerce background thumbnails regenerate', 'sirsc' ); ?></label>

									<?php endif; ?>

									<?php if ( defined( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH' ) ) : ?>
										<?php
										if ( empty( $settings['sync_settings_ewww'] ) ) {
											$settings['sync_settings_ewww'] = false;
										}
										?>
										<hr>
										<label><input type="checkbox" name="_sync_settings_ewww" id="_sirsrc_sync_settings_ewww" <?php checked( true, $settings['sync_settings_ewww'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'sync ignored image sizes with EWWW Image Optimizer plugin', 'sirsc' ); ?></label>

										<a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_sync_settings_ewww')"></a>
										<div class="sirsc_info_box_wrap">
											<div id="info_sync_settings_ewww" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_regenerate')">
												<?php echo wp_kses_post( __( 'This option allows you to sync <em>disable creation</em> image sizes from <b>EWWW Image Optimizer</b> plugin with the <em>global ignore</em> image sizes from <b>Image Regenerate & Select Crop</b>. In this way, when you update the settings in one of the plugins, the settings will be synced in the other plugin.', 'sirsc' ) ); ?>
											</div>
										</div>

									<?php endif; ?>

									<hr>
									<?php
									if ( empty( $settings['leave_settings_behind'] ) ) {
										$settings['leave_settings_behind'] = false;
									}
									?>
									<label><input type="checkbox" name="_sirsrc_leave_settings_behind" id="_sirsrc_leave_settings_behind" <?php checked( true, $settings['leave_settings_behind'] ); ?> onchange="sirsc_autosubmit()" /> <?php esc_html_e( 'do not cleanup the settings after the plugin is deactivated', 'sirsc' ); ?></label>
								</td>

								<td class="textright">
									<?php submit_button( __( 'Save Settings', 'sirsc' ), 'primary', 'sirsc-settings-submit', false, array( 'id' => 'sirsc-settings-submit-2' ) ); ?>
								</td>
							</tr>
							<tr class="middle">
								<td width="15%">
									<h3><?php esc_html_e( 'General Cleanup', 'sirsc' ); ?></h3>
									*<?php esc_html_e( 'It is recommended to run the cleanup using the command line tools.', 'sirsc' ); ?>
								</td>
								<td colspan="2">

									<table cellpadding="0" cellpadding="0" width="100%">
										<tr>
											<td style="padding: 0">
												<?php
												$k     = 'sirscregsizes';
												$tr_id = 'sirsc-settings-for-' . esc_attr( $k );
												$total_cleanup = self::calculate_total_to_cleanup( $_sirsc_post_types, 'file' );
												$cleanup_class = ( ! empty( $total_cleanup ) ) ? '' : 'display: none';
												?>
												<div id="<?php echo esc_attr( $tr_id ); ?>" class="hentry maybe-sirsc-selector">
													*<?php esc_html_e( 'Cleanup unused files and keep currently registered sizes files', 'sirsc' ); ?>
													<a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_current_cleanup')"></a>
													<div class="sirsc_info_box_wrap">
														<div id="info_current_cleanup" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_regenerate')">
															<?php esc_html_e( 'This type of cleanup is performed for all the attachments, and it removes any attachment unused file and keeps only the files associated with the currently registered image sizes. This action is also changing the attachment metadata in the database, and it is irreversible.', 'sirsc' ); ?>
														</div>
													</div>

													<div class="sirsc_button-regenerate-wrap title">
														<div id="_sirsc_cleanup_initiated_for_<?php echo esc_attr( $k ); ?>">
															<input type="hidden" name="_sisrsc_image_size_name" id="_sisrsc_image_size_name<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $k ); ?>"/>
															<input type="hidden" name="_sisrsc_post_type" id="_sisrsc_post_type<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $_sirsc_post_types ); ?>"/>
															<input type="hidden" name="_sisrsc_image_size_name_page" id="_sisrsc_image_size_name_page<?php echo esc_attr( $k ); ?>" value="0"/>
															<div class="sirsc_button-regenerate">
																<div><div id="_sirsc_cleanup_initiated_for_<?php echo esc_attr( $k ); ?>_result" class="result" data-processing="0"><span class="spinner off"></span></div></div>
															</div>
															<div class="sirsc_clearAll"></div>
														</div>
													</div>
												</div>
												<div class="sirsc_clearAll"></div>
											</td>
											<td style="padding: 0" width="15%" class="th-set-cleanup textcenter">
												<span id="sirsc-cleanup-button-for-<?php echo esc_attr( $k ); ?>" class="button widefat button-secondary" style="" title="<?php echo intval( $total_cleanup ); ?>" onclick="sirsc_initiate_raw_cleanup('<?php echo esc_attr( $k ); ?>');"><b class="dashicons dashicons-no" title="<?php esc_attr_e( 'Cleanup Unused', 'sirsc' ); ?>"></b> <?php esc_html_e( 'Cleanup Unused', 'sirsc' ); ?></span>
											</td>
										</tr>
									</table>

									<hr>
									<table cellpadding="0" cellpadding="0" width="100%">
										<tr class="middle">
											<td style="padding: 0">
												<?php
												$k     = 'sirscallsizes';
												$tr_id = 'sirsc-settings-for-' . esc_attr( $k );
												$total_cleanup = self::calculate_total_to_cleanup( $_sirsc_post_types, 'file' );
												$cleanup_class = ( ! empty( $total_cleanup ) ) ? '' : 'display: none';
												?>
												<div id="<?php echo esc_attr( $tr_id ); ?>" class="hentry maybe-sirsc-selector">
													*<?php esc_html_e( 'Keep only the original/full size files', 'sirsc' ); ?>
													<a class="dashicons dashicons-info" title="<?php esc_attr_e( 'Details', 'sirsc' ); ?>" onclick="sirsc_toggle_info('#info_raw_cleanup')"></a>
													<div class="sirsc_info_box_wrap">
														<div id="info_raw_cleanup" class="sirsc_info_box" onclick="sirsc_toggle_info('#info_regenerate')">
															<?php esc_html_e( 'This type of cleanup is performed for all the attachments, and it keeps only the file associated with the original/full size. This action is also changing the attachment metadata in the database, and it is irreversible. After this process is done, you need to regenerate the files for the desired image sizes.', 'sirsc' ); ?>
														</div>
													</div>

													<div class="sirsc_button-regenerate-wrap title">
														<div id="_sirsc_cleanup_initiated_for_<?php echo esc_attr( $k ); ?>">
															<input type="hidden" name="_sisrsc_image_size_name" id="_sisrsc_image_size_name<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $k ); ?>"/>
															<input type="hidden" name="_sisrsc_post_type" id="_sisrsc_post_type<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $_sirsc_post_types ); ?>"/>
															<input type="hidden" name="_sisrsc_image_size_name_page" id="_sisrsc_image_size_name_page<?php echo esc_attr( $k ); ?>" value="0"/>
															<div class="sirsc_button-regenerate">
																<div><div id="_sirsc_cleanup_initiated_for_<?php echo esc_attr( $k ); ?>_result" data-processing="0" class="result"><span class="spinner off"></span></div></div>
															</div>
															<div class="sirsc_clearAll"></div>
														</div>
													</div>
												</div>
												<div class="sirsc_clearAll"></div>
											</td>
											<td style="padding: 0" width="15%" class="th-set-cleanup textcenter">
												<span id="sirsc-cleanup-button-for-<?php echo esc_attr( $k ); ?>" class="button widefat button-secondary" style="" title="<?php echo intval( $total_cleanup ); ?>" onclick="sirsc_initiate_raw_cleanup('<?php echo esc_attr( $k ); ?>');"><b class="dashicons dashicons-no" title="<?php esc_attr_e( 'Cleanup Raw', 'sirsc' ); ?>"></b> <?php esc_html_e( 'Cleanup Raw', 'sirsc' ); ?></span>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</form>
				</div>
			</div>

			<?php self::plugin_global_footer(); ?>
		</div>
		<?php
	}

	/**
	 * For the featured image of the show we should be able to generate the missing image sizes.
	 */
	public static function register_image_button() {
		if ( self::$wp_ver < 4.6 ) {
			// Before 4.6 we have only 2 parameters for admin_post_thumbnail_html hook.
			add_filter( 'admin_post_thumbnail_html', array( get_called_class(), 'append_image_generate_button_old' ), 10, 2 );
		} else {
			// Since 4.6 we have 3 parameters for admin_post_thumbnail_html hook.
			add_filter( 'admin_post_thumbnail_html', array( get_called_class(), 'append_image_generate_button' ), 10, 3 );
		}
	}

	/**
	 * Append the image sizes generator button to the edit media page.
	 */
	public static function register_image_meta() {
		global $post;
		if ( ! empty( $post->post_type ) && 'attachment' === $post->post_type ) {
			add_action( 'edit_form_top', array( get_called_class(), 'append_image_generate_button' ), 10, 2 );
		}
	}

	/**
	 * This can be used in do_action.
	 *
	 * @param integer $image_id Attachment ID.
	 */
	public static function image_regenerate_select_crop_button( $image_id = 0 ) {
		self::append_image_generate_button( '', '', $image_id );
	}

	/**
	 * Append or display the button for generating the missing image sizes and request individual crop of images, this is for core version < 4.6, for backward compatibility.
	 *
	 * @param string  $content       The button content.
	 * @param integer $post_id       The main post ID.
	 * @param integer $thumbnail_id  The attachemnt ID.
	 */
	public static function append_image_generate_button_old( $content, $post_id = 0, $thumbnail_id = 0 ) {
		$display = false;
		if ( is_object( $content ) ) {
			$thumbnail_id = $content->ID;
			$content      = '';
			$display      = true;
		}
		if ( ! empty( $post_id ) || ! empty( $thumbnail_id ) ) {
			if ( ! empty( $thumbnail_id ) ) {
				$thumb_id = $thumbnail_id;
				$display  = true;
			} else {
				$thumb_id = get_post_thumbnail_id( $post_id );
				$display  = false;
			}
			self::load_settings_for_post_id( $thumb_id );
			if ( ! empty( $thumb_id ) ) {
				$content = '
				<div class="sirsc-image-generate-functionality">
					<div id="sirsc_recordsArray_' . intval( $thumb_id ) . '">
						<input type="hidden" name="post_id" id="post_id' . 'thumb' . (int) $thumb_id . '" value="' . esc_attr( (int) $thumb_id ) . '" />' . self::make_generate_images_button( $thumb_id ) . ' &nbsp;
					</div>
				</div>
				' . $content;
			}
		}
		if ( $display ) {
			echo '<div class="sirsc_button-regenerate-wrap">' . $content . '</div>'; // WPCS: XSS OK.
		} else {
			return $content;
		}
	}

	/**
	 * Append or display the button for generating the missing image sizes and request individual crop of images.
	 *
	 * @param string  $content      The button content.
	 * @param integer $post_id      The main post ID.
	 * @param integer $thumbnail_id The attachemnt ID.
	 * @param string  $extra_class  The wrapper extra class.
	 */
	public static function append_image_generate_button( $content, $post_id = 0, $thumbnail_id = 0, $extra_class = '' ) {
		$content_button    = '';
		$display           = false;
		$is_the_attachment = false;
		if ( is_object( $content ) ) {
			$thumbnail_id      = $content->ID;
			$display           = true;
			$is_the_attachment = true;
		}

		if ( ! empty( $post_id ) || ! empty( $thumbnail_id ) ) {
			if ( ! empty( $thumbnail_id ) ) {
				$thumb_id = $thumbnail_id;
			} else {
				$thumb_id = get_post_thumbnail_id( $post_id );
			}
			self::load_settings_for_post_id( $thumb_id );
			if ( ! empty( $thumb_id ) ) {
				$extra_class .= ( ! empty( self::$settings['listing_tiny_buttons'] ) ) ? ' tiny' : '';

				$content_button = '
					<div class="sirsc-image-generate-functionality ' . esc_attr( $extra_class ) . '">
						<div id="sirsc_recordsArray_' . intval( $thumb_id ) . '">
							<input type="hidden" name="post_id" id="post_id' . 'thumb' . intval( $thumb_id ) . '" value="' . esc_attr( intval( $thumb_id ) ) . '" />' . self::make_generate_images_button( $thumb_id ) . ' &nbsp;
						</div>
					</div>
					';
			}

			if ( ! $is_the_attachment && empty( $thumbnail_id ) ) {
				$content_button = '';
			}

			if ( ! $is_the_attachment ) {
				$content = $content_button . $content;
			}
		}

		if ( true === $display && true === $is_the_attachment ) {
			// When the button is in the attachment edit screen, we display the buttons.
			echo '<div class="sirsc_button-regenerate-wrap">' . $content_button . '</div>'; // WPCS: XSS OK.
		}

		return $content;
	}

	/**
	 * Append or display the button for generating the missing image sizes and request individual crop of images.
	 *
	 * @param integer $post_id      The main post ID.
	 * @param integer $thumbnail_id The attachemnt ID.
	 * @return void
	 */
	public static function append_image_generate_button_small( $post_id = 0, $thumbnail_id = 0 ) {
		$content = str_replace( '&nbsp;', '', self::append_image_generate_button( '', $post_id, $thumbnail_id, 'tiny' ) );
		echo $content; // WPCS: XSS OK.
	}

	/**
	 * Return the html code for a button that triggers the image sizes generator.
	 *
	 * @param integer $attachment_id The attachment ID.
	 */
	public static function make_generate_images_button( $attachment_id = 0 ) {
		global $sirsc_column_summary;
		$button_regenerate = '
		<div class="sirsc_button-regenerate">
			<div id="sirsc_inline_regenerate_sizes' . (int) $attachment_id . '">
				<div class="button-primary button-large" onclick="sirsc_open_details(\'' . (int) $attachment_id . '\')"><div class="dashicons dashicons-format-gallery" title="' . esc_attr__( 'Details/Options', 'sirsc' ) . '"></div> ' . esc_html__( 'Image Details', 'sirsc' ) . '</div>
				<div class="button-primary button-large" onclick="sirsc_start_regenerate(\'' . (int) $attachment_id . '\')"><div class="dashicons dashicons-update" title="' . esc_attr__( 'Regenerate', 'sirsc' ) . '"></div> ' . esc_html__( 'Regenerate', 'sirsc' ) . '</div>';

		if ( ! empty( $sirsc_column_summary ) ) {
			$button_regenerate .= '
				<div class="button-primary button-large" onclick="sirsc_start_raw_cleanup_single(\'' . (int) $attachment_id . '\')"><div class="dashicons dashicons-editor-removeformatting" title="' . esc_attr__( 'Raw Cleanup', 'sirsc' ) . '"></div> ' . esc_html__( 'Raw Cleanup', 'sirsc' ) . '</div>';
		}

		$button_regenerate .= '
				<div id="sirsc_recordsArray_' . intval( $attachment_id ) . '_result" class="result"><span class="spinner inline off"></span></div>
			</div>
		</div>
		<div class="sirsc_clearAll"></div>';
		return $button_regenerate;
	}

	/**
	 * Return the sirsc_show_ajax_action.
	 *
	 * @param string $callback The callback.
	 * @param string $element  The element.
	 * @param string $target   The target.
	 */
	public static function make_ajax_call( $callback, $element, $target ) {
		$make_ajax_call = "sirsc_show_ajax_action('" . $callback . "', '" . $element . "', '" . $target . "');";
		return $make_ajax_call;
	}

	/**
	 * Return the array of keys => values from the ajax post.
	 *
	 * @param array $data The data.
	 */
	public static function parse_ajax_data( $data ) {
		$result = false;
		if ( ! empty( $data ) && is_array( $data ) ) {
			$result = wp_list_pluck( $data, 'value', 'name' );
		}
		return $result;
	}

	/**
	 * Notify doing SIRSC action.
	 *
	 * @param string $extra Maybe extra hints.
	 */
	public static function notify_doing_sirsc( $extra = '' ) {
		if ( ! defined( 'DOING_SIRSC' ) ) {
			// Maybe indicate to other scrips/threads that SIRSC is processing.
			define( 'DOING_SIRSC', true );
			do_action( 'sirsc_doing_sirsc', $extra );
		}
	}

	/**
	 * Assess and return SIRSC data.
	 *
	 * @return mixed
	 */
	public static function has_sirsc_data() {
		$data = filter_input( INPUT_GET, 'sirsc_data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		if ( empty( $data ) ) {
			$data = filter_input( INPUT_POST, 'sirsc_data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		}
		if ( empty( $data ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Execute and return the response of the callback, if the specified method exists.
	 */
	public static function show_actions_result() {
		$data = self::has_sirsc_data();
		if ( ! empty( $data ) ) {
			$post_data = self::parse_ajax_data( $data );
			if ( ! empty( $post_data['callback'] ) ) {
				if ( method_exists( get_called_class(), $post_data['callback'] ) ) {
					self::notify_doing_sirsc( $post_data['callback'] );
					call_user_func( array( get_called_class(), $post_data['callback'] ), $post_data );
				}
			}
		}
		die();
	}

	/**
	 * Delete image file on request handler.
	 *
	 * @param  array $sirsc_data Generic post data.
	 * @return void
	 */
	public static function sirsc_ajax_delete_image_file_on_request( $sirsc_data = array() ) {
		if ( ! empty( $sirsc_data ) ) {
			$id    = ( ! empty( $sirsc_data['id'] ) ) ? (int) $sirsc_data['id'] : 0;
			$size  = ( ! empty( $sirsc_data['imagesize'] ) ) ? esc_attr( $sirsc_data['imagesize'] ) : '';
			$file  = ( ! empty( $sirsc_data['filename'] ) ) ? esc_attr( $sirsc_data['filename'] ) : '';
			$twra  = ( ! empty( $sirsc_data['elementwrap'] ) ) ? esc_attr( $sirsc_data['elementwrap'] ) : '';
			$image = wp_get_attachment_metadata( $id );
			if ( ! empty( $size ) && substr_count( $size, ',' ) ) {
				$s = explode( ',', $size );
				foreach ( $s as $size ) {
					$res = self::execute_specified_attachment_file_delete( $id, $size, $file, $image );
					if ( ! empty( $twra ) && true === $res ) {
						?>
						<script>
						jQuery(document).ready(function(){
							jQuery('#idsrc<?php echo esc_attr( $id ); ?><?php echo esc_attr( $size ); ?>').html('NOT FOUND');
							jQuery('#sirsc_recordsArray_<?php echo esc_attr( $id ); ?><?php echo esc_attr( $size ); ?> .image-file-size').html('N/A');
							jQuery('#sirsc_recordsArray_<?php echo esc_attr( $id ); ?><?php echo esc_attr( $size ); ?> .sirsc_delSize').remove();
						});
						</script>
						<?php
					}
				}
			} else {
				$res = self::execute_specified_attachment_file_delete( $id, $size, $file, $image );
			}

			// Notify other scripts that the file was deleted.
			do_action( 'sirsc_image_file_deleted', $id, $file );

			if ( ! empty( $twra ) ) {
				if ( true === $res ) {
					?>
					<script>
					jQuery(document).ready(function(){
						jQuery('#<?php echo esc_attr( $twra ); ?>').remove();
						<?php if ( ! empty( $size ) ) { ?>
							jQuery('#idsrc<?php echo esc_attr( $id ); ?><?php echo esc_attr( $size ); ?>').html('NOT FOUND');
							jQuery('#sirsc_recordsArray_<?php echo esc_attr( $id ); ?><?php echo esc_attr( $size ); ?> .image-file-size').html('N/A');
							jQuery('#sirsc_recordsArray_<?php echo esc_attr( $id ); ?><?php echo esc_attr( $size ); ?> .sirsc_delSize').remove();
						<?php } ?>
					});
					</script>
					<?php
				} else {
					?>
					<script>
					jQuery(document).ready(function(){
						jQuery('#<?php echo esc_attr( $twra ); ?>').removeClass('processing');
						jQuery('#<?php echo esc_attr( $twra ); ?>_rez').html('<div class="sirsc-warning"><div class="sirsc-crop-pos" style="padding: 5px 10px"><?php echo esc_attr( $res ); ?></div></div>');
					});
					</script>
					<?php
				}
			}
		}
		wp_die();
		die();
	}

	/**
	 * Execute the removal of an attachment image size and file.
	 *
	 * @param  integer $id    The attachment id.
	 * @param  string  $size  The specified image size.
	 * @param  string  $fname A specified filename.
	 * @param  array   $image Maybe the previously computed attachment metadata.
	 * @return boolean|string
	 */
	public static function execute_specified_attachment_file_delete( $id = 0, $size = '', $fname = '', &$image = array() ) {
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
		}
		$upload_dir = wp_upload_dir();
		$orig       = array();
		if ( ! empty( $image['file'] ) ) {
			$orig[] = $image['file'];
		}
		if ( ! empty( $image['file'] ) && ! empty( $image['original_image'] ) ) {
			$folder = str_replace( basename( $image['file'] ), '', $image['file'] );
			$orig[] = trailingslashit( $folder ) . $image['original_image'];
		}
		if ( in_array( $fname, $orig ) ) {
			return __( 'The file is the original one, it is not safe to remove it.', 'sirsc' );
		}

		$file = '';
		if ( ! empty( $image['sizes'][ $size ] ) ) {
			if ( ! substr_count( $image['file'], $image['sizes'][ $size ]['file'] ) ) {
				// The size is not the original file.
				if ( ! empty( $image['sizes'][ $size ]['path'] ) && file_exists( $image['sizes'][ $size ]['path'] ) ) {
					$file = $image['sizes'][ $size ]['path'];
				} else {
					if ( ! empty( $image['sizes'][ $size ]['file'] ) && ! empty( $image['file'] ) ) {
						$folder = str_replace( basename( $image['file'] ), '', $image['file'] );
						$path   = trailingslashit( trailingslashit( $upload_dir['basedir'] ) . $folder );
						$file   = $path . $image['sizes'][ $size ]['file'];
					}
				}
				unset( $image['sizes'][ $size ] );
			}
		}
		if ( empty( $file ) && ! empty( $fname ) ) {
			$file = trailingslashit( $upload_dir['basedir'] ) . $fname;
		}
		if ( ! empty( $file ) ) {
			@unlink( $file );
			do_action( 'sirsc_image_file_deleted', $id, $file );
		}

		if ( ! empty( $image ) ) {
			wp_update_attachment_metadata( $id, $image );
		}
		return true;
	}

	/**
	 * Return the html code for a button that triggers the image sizes generator.
	 *
	 * @param integer $attachment_id The attachment ID.
	 * @param string  $size          The size slug.
	 * @param boolean $click         True to append the onclick attribute.
	 * @param string  $selected      Selected crop.
	 * @return string
	 */
	public static function make_generate_images_crop( $attachment_id = 0, $size = 'thumbnail', $click = true, $selected = '' ) {
		$id                = intval( $attachment_id ) . $size;
		$action            = ( $click ) ? ' onclick="sirsc_crop_position(\'' . $id . '\');"' : '';
		$button_regenerate = '<table class="sirsc-crop-pos" cellspacing="0" cellpadding="0" title="' . esc_attr__( 'Click to generate a crop of the image from this position', 'sirsc' ) . '">';
		$c                 = 0;
		if ( ! empty( self::$settings['default_crop'][ $size ] ) && empty( $selected ) ) {
			$selected = self::$settings['default_crop'][ $size ];
		}
		$selected = ( empty( $selected ) ) ? 'cc' : $selected;
		$selected = trim( $selected );
		foreach ( self::$crop_positions as $k => $v ) {
			$button_regenerate .= ( 0 === $c % 3 ) ? '<tr>' : '';
			$button_regenerate .= '<td title="' . esc_attr( $v ) . '"><label><input type="radio" name="crop_small_type_' . esc_attr( $size ) . '" id="crop_small_type' . esc_attr( $size ) . $id . '_' . esc_attr( $k ) . '" value="' . esc_attr( $k ) . '"' . $action . ( ( $k === $selected ) ? ' checked="checked"' : '' ) . ' /></label></td>';
			$button_regenerate .= ( 2 === $c % 3 ) ? '</tr>' : '';
			++ $c;
		}
		$button_regenerate .= '</table>';
		return $button_regenerate;
	}

	/**
	 * Return the details about an image size for an image.
	 *
	 * @param string  $filename      The file name.
	 * @param array   $image         The image attributes.
	 * @param string  $size          The image size slug.
	 * @param integer $selected_size The selected image size.
	 */
	public static function allow_resize_from_original( $filename, $image, $size, $selected_size ) {
		$result     = array(
			'found'                => 0,
			'is_crop'              => 0,
			'is_identical_size'    => 0,
			'is_resize'            => 0,
			'is_proportional_size' => 0,
			'width'                => 0,
			'height'               => 0,
			'path'                 => '',
			'url'                  => '',
			'can_be_cropped'       => 0,
			'can_be_generated'     => 0,
			'must_scale_up'        => 0,
			'native_crop_type'     => ( ! empty( $size[ $selected_size ]['crop'] ) ? true : false ),
		);
		$original_w = ( ! empty( $image['width'] ) ) ? $image['width'] : 0;
		$original_h = ( ! empty( $image['height'] ) ) ? $image['height'] : 0;

		$w = ( ! empty( $size[ $selected_size ]['width'] ) ) ? intval( $size[ $selected_size ]['width'] ) : 0;
		$h = ( ! empty( $size[ $selected_size ]['height'] ) ) ? intval( $size[ $selected_size ]['height'] ) : 0;
		$c = ( ! empty( $size[ $selected_size ]['crop'] ) ) ? $size[ $selected_size ]['crop'] : false;

		if ( empty( $image['sizes'][ $selected_size ]['file'] ) ) {
			// Not generated probably.
			if ( ! empty( $c ) ) {
				if ( $original_w >= $w && $original_h >= $h ) {
					$result['can_be_generated'] = 1;
				} else {
					if ( $original_w >= $w || $original_h >= $h ) {
						// At least one size seems big enough to scale up.
						$result['can_be_generated'] = 1;
						$result['can_be_cropped']   = 1;
						$result['must_scale_up']    = 1;
					}
				}
			} else {
				if ( ( 0 === $w && $original_h >= $h ) || ( 0 === $h && $original_w >= $w )
					|| ( 0 !== $w && 0 !== $h && ( $original_w >= $w || $original_h >= $h ) ) ) {
					$result['can_be_generated'] = 1;
				}
			}
		} else {
			$file = str_replace( basename( $filename ), $image['sizes'][ $selected_size ]['file'], $filename );
			if ( file_exists( $file ) ) {
				$c_image_size     = getimagesize( $file );
				$ciw              = intval( $c_image_size[0] );
				$cih              = intval( $c_image_size[1] );
				$result['found']  = 1;
				$result['width']  = $ciw;
				$result['height'] = $cih;
				$result['path']   = $file;
				if ( $ciw === $w && $cih === $h ) {
					$result['is_identical_size'] = 1;
					$result['can_be_cropped']    = 1;
					$result['can_be_generated']  = 1;
				}
				if ( ! empty( $c ) ) {
					$result['is_crop'] = 1;
					if ( $original_w >= $w && $original_h >= $h ) {
						$result['can_be_cropped']   = 1;
						$result['can_be_generated'] = 1;
					} elseif ( $original_w >= $w || $original_h >= $h ) {
						// At least one size seems big enough to scale up.
						$result['can_be_generated'] = 1;
						$result['can_be_cropped']   = 1;
						$result['must_scale_up']    = 1;
					}
				} else {
					$result['is_resize'] = 1;
					if ( ( 0 === $w && $cih === $h ) || ( $ciw === $w && 0 === $h ) ) {
						$result['is_proportional_size'] = 1;
						$result['can_be_generated']     = 1;
					} elseif ( 0 !== $w && 0 !== $h && ( $ciw === $w || $cih === $h ) ) {
						$result['is_proportional_size'] = 1;
						$result['can_be_generated']     = 1;
					}
					if ( $original_w >= $w && $original_h >= $h ) {
						$result['can_be_generated'] = 1;
					}
				}
			} else {
				// To do the not exists but size exists.
				if ( ! empty( $c ) ) {
					if ( $original_w >= $w && $original_h >= $h ) {
						$result['can_be_generated'] = 1;
					} elseif ( $original_w >= $w || $original_h >= $h ) {
						// At least one size seems big enough to scale up.
						$result['can_be_generated'] = 1;
						$result['can_be_cropped']   = 1;
						$result['must_scale_up']    = 1;
					}
				} else {
					if ( ( 0 === $w && $original_h >= $h ) || ( 0 === $h && $original_w >= $w )
						|| ( 0 !== $w && 0 !== $h && ( $original_w >= $w || $original_h >= $h ) ) ) {
						$result['can_be_generated'] = 1;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Compute image paths.
	 *
	 * @param  integer $id      Attachment ID.
	 * @param  string  $size    Size name.
	 * @param  array   $image   Maybe image size metadata.
	 * @param  array   $uplinfo Maybe upload paths details.
	 * @return array
	 */
	public static function assess_expected_image( $id, $size, $image = array(), $uplinfo = array() ) {
		if ( empty( $uplinfo ) ) {
			$uplinfo = wp_upload_dir();
		}
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
		}

		$result = array(
			'file'   => '',
			'dir'    => '',
			'path'   => '',
			'url'    => '',
			'exists' => false,
			'meta'   => array(),
		);

		if ( empty( $image ) ) {
			return $result;
		}

		$filename = get_attached_file( $id );
		$source   = str_replace( trailingslashit( $uplinfo['basedir'] ), '', $filename );

		// Compute the expected if the size does not exist.
		$all_size = self::get_all_image_sizes_plugin();
		if ( ! empty( $size ) && ! empty( $all_size[ $size ] ) ) {
			$maybe = image_resize_dimensions(
				(int) $image['width'],
				(int) $image['height'],
				$all_size[ $size ]['width'],
				$all_size[ $size ]['height'],
				$all_size[ $size ]['crop']
			);
			$suffix   = ( ! empty( $maybe ) ) ? '-' . $maybe[4] . 'x' . $maybe[5] : '';
			$ext      = '.' . strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$expected = str_replace( $ext, $suffix . $ext, $source );

			$result = array(
				'file'   => $expected,
				'dir'    => trailingslashit( dirname( $source ) ),
				'path'   => trailingslashit( $uplinfo['basedir'] ) . $expected,
				'url'    => trailingslashit( $uplinfo['baseurl'] ) . $expected,
				'exists' => false,
				'meta'   => array(),
			);

			if ( ! empty( $result['path'] ) && file_exists( $result['path'] ) && ! is_dir( $result['path'] ) ) {
				$result['exists'] = true;
				$filetype = wp_check_filetype( $result['path'] );
				$result['meta'] = array(
					'file'      => basename( $result['path'] ),
					'width'     => ( ! empty( $maybe[4] ) ) ? (int) $maybe[4] : (int) $image['width'],
					'height'    => ( ! empty( $maybe[5] ) ) ? (int) $maybe[5] : (int) $image['height'],
					'mime-type' => $filetype['type'],
				);
			}
		}

		return $result;
	}

	/**
	 * Compute image paths.
	 *
	 * @param  integer $id      Attachment ID.
	 * @param  string  $size    Size name.
	 * @param  array   $uplinfo Maybe upload paths details.
	 * @return array
	 */
	public static function compute_image_paths( $id, $size, $uplinfo = array() ) {
		if ( empty( $uplinfo ) ) {
			$uplinfo = wp_upload_dir();
		}
		$filename = get_attached_file( $id );
		$source   = str_replace( trailingslashit( $uplinfo['basedir'] ), '', $filename );
		$image    = wp_get_attachment_metadata( $id );

		if ( empty( $image ) && ! empty( $source ) && function_exists( 'wp_create_image_subsizes' ) ) {
			self::notify_doing_sirsc();
			self::debug( 'The image metadata is empty and the source exists ' . $filename . '. Force regenerate with thread for attachment ' . $id . '.', true, true );
			wp_create_image_subsizes( $filename, $id );
			$image = self::attempt_to_create_metadata( $id, $filename );
		}

		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
		}

		if ( ! empty( $size ) && 'original' != $size && 'full' != $size ) {
			$th = wp_get_attachment_image_src( $id, $size );
		}
		$thsrc  = ( ! empty( $th[0] ) ) ? str_replace( $uplinfo['baseurl'], '', $th[0] ) : '';
		$result = array(
			'metadata'        => $image,
			'source'          => $source,
			'source_path'     => trailingslashit( $uplinfo['basedir'] ) . $source,
			'source_url'      => trailingslashit( $uplinfo['baseurl'] ) . $source,
			'dir_path'        => trailingslashit( trailingslashit( $uplinfo['basedir'] ) . str_replace( basename( $source ), '', $source ) ),
			'dir_url'         => trailingslashit( trailingslashit( $uplinfo['baseurl'] ) . str_replace( basename( $source ), '', $source ) ),

			'attachment_id'   => $id,
			'attachment_file' => $thsrc,
			'attachment_path' => $uplinfo['basedir'] . $thsrc,
			'attachment_url'  => $uplinfo['baseurl'] . $thsrc,
			'is_original'     => ( $source === $thsrc ) ? true : false,
			'file_exists'     => ( ! empty( $thsrc ) && file_exists( $uplinfo['basedir'] . $thsrc ) ) ? true : false,
			'original_exists' => ( ! empty( $source ) && file_exists( $uplinfo['basedir'] . $source ) ) ? true : false,
		);

		return $result;
	}

	/**
	 * Return the html code that contains the description of the images sizes defined in the application and provides details about the image sizes of an uploaded image.
	 */
	public static function ajax_show_available_sizes() {
		$sirsc_data = self::has_sirsc_data();
		if ( ! empty( $sirsc_data ) ) {
			$post_data = self::parse_ajax_data( $sirsc_data );
			if ( ! empty( $post_data['post_id'] ) ) {
				$post = get_post( $post_data['post_id'] );
				if ( ! empty( $post ) ) {
					self::load_settings_for_post_id( intval( $post_data['post_id'] ) );
					$all_size = self::get_all_image_sizes_plugin();
					// $image    = wp_get_attachment_metadata( $post_data['post_id'] );
					$upldir   = wp_upload_dir();
					$compute  = self::compute_image_paths( $post_data['post_id'], '', $upldir );
					$image    = $compute['metadata'];
					$filename = get_attached_file( $post_data['post_id'] );
					if ( ! empty( $filename ) ) :
						$original_w   = ( ! empty( $compute['metadata']['width'] ) ) ? $compute['metadata']['width'] : 0;
						$original_h   = ( ! empty( $compute['metadata']['height'] ) ) ? $compute['metadata']['height'] : 0;
						$folder       = $compute['dir_path'];
						$path         = $compute['dir_path'];
						$original_s   = ( ! empty( $compute['source_path'] ) ) ? @filesize( $compute['source_path'] ) : 0;
						$img_filename = $compute['source'];
						$img_url      = $compute['source_url'];
						?>
						<div class="sirsc_under-image-options"></div>
						<div class="sirsc_image-size-selection-box">
							<div class="sirsc_options-title">
								<div class="sirsc_options-close-button-wrap"><a class="sirsc_options-close-button" onclick="sirsc_clear_result('<?php echo (int) $post_data['post_id']; ?>');"><span class="dashicons dashicons-dismiss"></span></a></div>
								<h2><?php esc_html_e( 'Image Details & Options', 'sirsc' ); ?></h2>
								<a onclick="sirsc_open_details('<?php echo (int) $post_data['post_id']; ?>');"><span class="dashicons dashicons-update"></span></a>
							</div>
							<div class="inside">
						<?php if ( ! empty( $all_size ) ) : ?>
							<table class="wp-list-table widefat media the-sisrc-list">
								<thead>
									<tr>
										<th colspan="2"><?php esc_html_e( 'The original image', 'sirsc' ); ?>: <b><?php echo (int) $original_w; ?></b>x<b><?php echo (int) $original_h; ?></b>px, <?php esc_html_e( 'file size', 'sirsc' ); ?>: <b><?php echo self::human_filesize( $original_s ); // WPCS: XSS OK. ?></b>.
											<br><?php esc_html_e( 'File', 'sirsc' ); ?>: <a href="<?php echo esc_url( $img_url ); ?>" target="_blank"><div class="dashicons dashicons-admin-links"></div> <?php echo esc_html( $img_filename ); ?></a>
										</th>
									</tr>
									<tr>
										<th class="manage-column"><b><?php esc_html_e( 'Image size details & generated image', 'sirsc' ); ?></b></th>
										<th class="manage-column textcenter" width="120"><b><?php esc_html_e( 'Actions', 'sirsc' ); ?></b></th>
									</tr>
								</thead>
								<tbody id="the-list">
									<?php
									$count = 0;
									$good  = array();
									foreach ( $all_size as $k => $v ) {
										++ $count;
										$rez_img      = self::allow_resize_from_original( $filename, $image, $all_size, $k );
										$size_quality = ( empty( self::$settings['default_quality'][ $k ] ) ) ? self::DEFAULT_QUALITY : (int) self::$settings['default_quality'][ $k ];
										$action       = '';
										$action_title = '';
										if ( ! empty( $rez_img['native_crop_type'] ) ) {
											$action_title = '<span class="sirsc-size-label"><div class="dashicons dashicons-image-crop"></div> ' . esc_html__( 'Crop image', 'sirsc' ) . '</span>';
										} else {
											$action_title = '<span class="sirsc-size-label"><div class="dashicons dashicons-editor-expand"></div> ' . esc_html__( 'Scale image', 'sirsc' ) . '</span>';
										}

										$maybelink = '';
										if ( ! empty( $rez_img['found'] ) ) {
											$ima = wp_get_attachment_image_src( $post_data['post_id'], $k );
											$im  = '<span id="idsrc' . intval( $post_data['post_id'] ) . $k . '"><img src="' . $ima[0] . '?v=' . time() . '" border="0" /><br /> ' . esc_html__( 'Resolution', 'sirsc' ) . ': <b>' . $rez_img['width'] . '</b>x<b>' . $rez_img['height'] . '</b>px</span>';
											$maybelink = '<a href="' . $ima[0] . '?v=' . time() . '" target="_blank"><div class="dashicons dashicons-admin-links"></div></a>';

											$good[] = $ima[0];
										} else {
											$im = '<span id="idsrc' . intval( $post_data['post_id'] ) . $k . '">' . esc_html__( 'NOT FOUND', 'sirsc' ) . '</span>';
										}

										if ( ! empty( $rez_img['is_crop'] ) ) {
											if ( ! empty( $rez_img['can_be_cropped'] ) ) {
												$action_title = '<span class="sirsc-size-label"><div class="dashicons dashicons-image-crop"></div> ' . esc_html__( 'Crop image', 'sirsc' ) . '</span>';
												$action .= '<div class="sirsc_clearAll"></div>' . self::make_generate_images_crop( $post_data['post_id'], $k ) . '';
											} else {
												$action_title = '<span class="sirsc-size-label disabled"><div class="dashicons dashicons-image-crop"></div> ' . esc_html__( 'Crop image', 'sirsc' ) . '</span>';
											}
										}

										if ( ! empty( $rez_img['can_be_generated'] ) ) {
											if ( ! empty( $rez_img['native_crop_type'] ) ) {
												$action_title = '<span class="sirsc-size-label"><div class="dashicons dashicons-image-crop"></div> ' . esc_html__( 'Crop image', 'sirsc' ) . '</span>';
											} else {
												$action_title = '<span class="sirsc-size-label"><div class="dashicons dashicons-editor-expand"></div> ' . esc_html__( 'Scale image', 'sirsc' ) . '</span>';
											}
											$iddd    = intval( $post_data['post_id'] ) . $k;
											$action .= '<div class="sirsc_clearAll"></div><div class="sirsc-size-quality-wrap">' . esc_html__( 'Quality', 'sirsc' ) . '<input type="number" name="selected_quality" id="selected_quality' . (int) $post_data['post_id'] . $k . '" value="' . $size_quality . '" class="sirsc-size-quality"></div><a class="button" onclick="sirsc_start_regenerate(\'' . $iddd . '\');"><b class="dashicons dashicons-update"></b> ' . esc_html__( 'Regenerate', 'sirsc' ) . '</a>';
										} else {
											if ( ! empty( $rez_img['native_crop_type'] ) ) {
												$action_title = '<span class="sirsc-size-label disabled"><div class="dashicons dashicons-image-crop"></div> ' . esc_html__( 'Crop image', 'sirsc' ) . '</span>';
											} else {
												$action_title = '<span class="sirsc-size-label disabled"><div class="dashicons dashicons-editor-expand"></div> ' . esc_html__( 'Scale image', 'sirsc' ) . '</span>';
											}

											$action .= '<table class="wp-list-table widefat fixed"><tr><td class="sirsc-small-info">' . esc_html__( 'The width and height of the original image are smaller than the requested image size.', 'sirsc' ) . '</td></tr></table>';

											if ( ! empty( $rez_img['found'] ) ) {
												if ( ! empty( $rez_img['is_crop'] ) ) {
													$action .= '<div class="sirsc_clearAll"></div>' . self::make_generate_images_crop( $post_data['post_id'], $k ) . '';
												}

												$iddd    = intval( $post_data['post_id'] ) . $k;
												$action .= '<div class="sirsc_clearAll"></div><div class="sirsc-size-quality-wrap">' . esc_html__( 'Quality', 'sirsc' ) . '<input type="number" name="selected_quality" id="selected_quality' . (int) $post_data['post_id'] . $k . '" value="' . $size_quality . '" class="sirsc-size-quality"></div><a class="button" onclick="sirsc_start_regenerate(\'' . $iddd . '\');"><b class="dashicons dashicons-update"></b> ' . esc_html__( 'Regenerate', 'sirsc' ) . '</a>';

												$action = '<div class="sirsc-warning">' . $action . '</div>';
											}
										}

										$cl        = ( 1 === $count % 2 ) ? 'alternate' : '';
										$size_text = self::size_to_text( $v );

										$size_quality = ( empty( self::$settings['default_quality'][ $k ] ) ) ? self::DEFAULT_QUALITY : (int) self::$settings['default_quality'][ $k ];

										$filesize = 0;
										if ( ! empty( $image['sizes'][ $k ]['file'] ) && file_exists( trailingslashit( $path ) . $image['sizes'][ $k ]['file'] ) ) {
											$filesize = @filesize( trailingslashit( $path ) . $image['sizes'][ $k ]['file'] );
										}

										$del = '';
										if ( 0 != $filesize ) {
											if ( ! substr_count( $compute['source'], $image['sizes'][ $k ]['file'] ) ) {
												// The size is the not the original file.
												$del = '<a class="sirsc_delSize" onclick="sirsc_start_delete(\'' . intval( $post_data['post_id'] ) . $k . '\');" title="' . esc_attr__( 'Delete', 'sirsc' ) . '"><b class="dashicons dashicons-trash"></b></a>';
											} else {
												$action = '<table class="wp-list-table widefat fixed"><tr><td class="sirsc-small-info">' . esc_html__( 'This image size shares the same file as the full size (the original image) and it cannot be altered.', 'sirsc' ) . '</td></tr></table>';
											}
										}

										echo '
										<tr class="' . $cl . ' textright"><td><b class="sirsc-size-label size" title="' . esc_attr( $k ) . '"><span id="idsrc' . (int) $post_data['post_id'] . $k . '-url">' . $maybelink . '</span>' . $k . '</b> <span class="sirsc-small-info">' . esc_html__( 'Info', 'sirsc' ) . ': ' . $size_text . '</span></td><td width="120" class="textleft">' . $action_title . '<span class="sirsc-small-info">' . esc_html__( 'Default quality', 'sirsc' ) . ': <b>' . $size_quality . '</b></span></td></tr><tr class="' . $cl . ' bottom-border" id="sirsc_recordsArray_' . (int) $post_data['post_id'] . $k . '"><input type="hidden" id="sirsc-fallback-action" value="regenerateone"><input type="hidden" name="post_id" id="post_id' . (int) $post_data['post_id'] . $k . '" value="' . (int) $post_data['post_id'] . '" /><input type="hidden" name="selected_size" id="selected_size' . (int) $post_data['post_id'] . $k . '" value="' . $k . '" /><td class="image-src-column"><div class="result_inline"><div id="sirsc_recordsArray_' . (int) $post_data['post_id'] . $k . '_result" class="result inline"><span class="spinner off"></span></div></div>' . $im . '<br><span class="image-size-column">' . esc_html__( 'File size', 'sirsc' ) . ': <b class="image-file-size">' . self::human_filesize( $filesize ) . '</b></span></td><td class="sirsc_image-action-column">' . $del . ' ' . $action . '</td></tr>';  // WPCS: XSS OK.
									}

									++ $count;
									$cl = ( 1 === $count % 2 ) ? 'alternate' : '';

									echo '
									<tr class="' . $cl . '"><td colspan="2"><div id="sirsc-extra-info-footer-' . (int) $post_data['post_id'] . '">'; // WPCS: XSS OK.
											self::compute_all_gen_like_images( $post_data['post_id'], $image, $compute, $good );
									echo '
										</div></td>
									</tr>
								</tbody>
							</table>';
						endif;

						echo '</div></div>';
						echo '<script>
						jQuery(document).ready(function () {
							sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
						 });</script>';
					else :
						?>
						<span class="sirsc_successfullysaved"><?php esc_html__( 'The file is missing!', 'sirsc' ); ?></span>
						<?php
					endif;
				}
			} else {
				echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Something went wrong!', 'sirsc' ) . '</span>';
			}
		}
	}

	/**
	 * Attempt to refresh extra info in the footer of the image details lightbox.
	 *
	 * @return void
	 */
	public static function refresh_extra_info_footer() {
		if ( empty( $_REQUEST['sirsc_data'] ) ) {
			// Fail-fast.
			return;
		}

		$data = self::has_sirsc_data();
		$id   = ( ! empty( $data['post_id'] ) ) ? (int) $data['post_id'] : 0;
		if ( empty( $id ) ) {
			// Fail-fast.
			return;
		}
		?>
		<script>
		jQuery(document).ready(function(){
			sirsc_refresh_extra_info_footer('<?php echo (int) $id; ?>', '#sirsc-extra-info-footer-<?php echo (int) $id; ?>');
		});
		</script>
		<?php
	}

	/**
	 * Assess the files generated for an attachment.
	 *
	 * @param  integer $id    Attachment ID.
	 * @param  array   $image Maybe the known attachment metadata.
	 * @return array
	 */
	public static function assess_files_for_attachment_original( $id, $image = array() ) {
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
		}

		$dir        = '';
		$upload_dir = wp_upload_dir();
		$basedir    = trailingslashit( $upload_dir['basedir'] );
		$list       = array();
		$full       = array();
		$gene       = array();

		// Assess the original files.
		if ( ! empty( $image['file'] ) ) {
			$full[] = $basedir . $image['file'];
			$dir = trailingslashit( dirname( $image['file'] ) );
		}
		$full = array_unique( $full );
		if ( ! empty( $image['original_image'] ) ) {
			$list[] = $basedir . $dir . $image['original_image'];
		}
		$list = array_unique( $list );
		if ( ! empty( $image['sizes'] ) ) {
			foreach ( $image['sizes'] as $key => $value ) {
				if ( ! empty( $value['file'] ) ) {
					$gene[] = $basedir . $dir . $value['file'];
				}
			}
		}

		// Assess the generated files.
		if ( ! empty( $list ) ) {
			foreach ( $list as $file ) {
				$ext    = pathinfo( $file, PATHINFO_EXTENSION );
				$name   = pathinfo( $file, PATHINFO_FILENAME );
				$path   = pathinfo( $file, PATHINFO_DIRNAME );
				$gene[] = $file;
				$gene   = array_merge( $gene, glob( $path . '/' . $name . '-*x*.' . $ext, GLOB_BRACE ) );
			}
		}
		$gene = array_unique( $gene );
		$gene = array_diff( $gene, $full );
		$gene = array_diff( $gene, $list );

		// Process lists to see only names.
		$list_names = array();
		if ( ! empty( $list ) ) {
			foreach ( $list as $value ) {
				$list_names[] = str_replace( $basedir, '', $value );
			}
		}
		$full_names = array();
		if ( ! empty( $full ) ) {
			foreach ( $full as $value ) {
				$full_names[] = str_replace( $basedir, '', $value );
			}
		}
		$gene_names = array();
		if ( ! empty( $gene ) ) {
			foreach ( $gene as $value ) {
				$gene_names[] = str_replace( $basedir, '', $value );
			}
		}

		$result  = array(
			'names' => array(
				'original'  => $list_names,
				'full'      => $full_names,
				'generated' => $gene_names,
			),
			'paths' => array(
				'original'  => $list,
				'full'      => $full,
				'generated' => $gene,
			),
		);

		return $result;
	}

	/**
	 * Attempt to delete all generate files on delete attachment.
	 *
	 * @param  integer $post_id Attachment ID.
	 * @return void
	 */
	public static function on_delete_attachment( $post_id ) {
		$gene_all = self::assess_files_for_attachment_original( $post_id );
		if ( ! empty( $gene_all['paths']['generated'] ) ) {
			foreach ( $gene_all['paths']['generated'] as $value ) {
				@unlink( $value );
			}
		}
	}

	/**
	 * Compute all generated like images.
	 *
	 * @param  integer $id      Attachment ID.
	 * @param  array   $image   Maybe metadata.
	 * @param  array   $compute Maybe extra computed info.
	 * @param  array   $good    Maybe a list of good images.
	 * @return void
	 */
	public static function compute_all_gen_like_images( $id, $image = array(), $compute = array(), $good = array() ) {
		if ( empty( $id ) ) {
			// Fail-fast.
			return;
		}

		if ( is_array( $id ) ) {
			$id = ( ! empty( $id['id'] ) ) ? (int) $id['id'] : 0;
		}
		if ( empty( $id ) ) {
			// Fail-fast.
			return;
		}

		$upload_dir = wp_upload_dir();
		if ( empty( $compute ) ) {
			$compute = self::compute_image_paths( $id, '', $upload_dir );
		}
		if ( empty( $image ) && ! empty( $compute['metadata'] ) ) {
			$image = $compute['metadata'];
		}
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
			if ( empty( $image ) ) {
				$filename = get_attached_file( $id );
				$image    = self::attempt_to_create_metadata( $id, $filename );
			}
		}

		$summary = self::general_sizes_and_files_match( $id, $image, $compute );
		$count = 0;
		?>
		<div id="frm-sirsc-main-additional-info-wrap-<?php echo (int) $compute['attachment_id']; ?>" class="sirsc-main-additional-info-wrap">
			<input type="hidden" name="id" value="<?php echo (int) $compute['attachment_id']; ?>">
			<input type="hidden" name="filename" value="">
			<input type="hidden" name="imagesize" value="">
			<input type="hidden" name="elementwrap" value="">
		</div>
		<br>
		<?php esc_html_e( 'You can see below some additional information about the files generated, recorded in the database, also files left behind from image sizes that are no longer registered in your site, or other image processing (but not linked in the database anymore, hence, probably no used anymore).', 'sirsc' ); ?>
		<table width="100%" cellpadding="0" cellpadding="0" class="striped fixed sirsc-small-info-table">
			<?php foreach ( $summary as $k => $v ) : ?>
				<?php
				$trid  = 'trsirsc-' . intval( $compute['attachment_id'] ) . md5( '-' . $k . '-' . $v['size'] );
				$fsize = ( empty( $v['fsize'] ) || 'N/A' == $v['filesize'] ) ? '<span class="missing-file">' . __( 'The file is missing!', 'sirsc' ) . '</span>' : '';
				$hint  = ( ! empty( $fsize ) ) ? ' missing-file' : '';
				$delt  = ( ! empty( $fsize ) ) ? __( 'Cleanup the metadata', 'sirsc' ) : __( 'Delete', 'sirsc' );
				?>
				<tr id="<?php echo esc_attr( $trid ); ?>" class="vtop bordertop<?php echo esc_attr( $hint ); ?>">
					<td width="32" align="center">
						<span class="dashicons <?php echo esc_attr( $v['icon'] ); ?>"></span>
						<?php echo intval( ++ $count ); ?>.
					</td>
					<td width="54"><?php echo esc_attr( $v['hint'] ); ?></td>
					<td>
						<b><?php echo esc_attr( $k ); ?></b> <br><?php echo esc_attr( $v['size'] ); ?> <?php echo wp_kses_post( $fsize ); ?>
						<div id="<?php echo esc_attr( $trid ); ?>_rez"></div>

					</td>
					<td width="54" align="right">
						<?php esc_html_e( 'file size', 'sirsc' ); ?>
						<br><?php echo esc_attr( $v['filesize'] ); ?>
					</td>
					<td width="32" align="center">
						<?php if ( ! substr_count( $v['icon'], 'is-full' ) && ! substr_count( $v['icon'], 'is-original' ) ) : ?>
							<a onclick="sirsc_start_delete_file('<?php echo (int) $compute['attachment_id']; ?>', '<?php echo esc_attr( $k ); ?>', '<?php echo esc_attr( $v['size'] ); ?>', '<?php echo esc_attr( $trid ); ?>');" title="<?php echo esc_attr( $delt ); ?>"><b class="dashicons dashicons-trash"></b></a>
						<?php else : ?>
							<b class="dashicons dashicons-trash"></b>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<div class="sirsc_clearAll"></div>
		<?php
	}

	/**
	 * Compute the images generated summary for a specified attachment.
	 *
	 * @param  integer $id    The attachment ID.
	 * @param  array   $image Maybe an attachment metadata array.
	 * @return void
	 */
	public static function attachment_files_summary( $id, $image = array() ) {
		if ( empty( $id ) ) {
			return;
		}

		$reuse_wrapper = false;
		$sirsc_data    = self::has_sirsc_data();
		if ( is_array( $id ) && ! empty( $sirsc_data ) ) {
			$post_data = self::parse_ajax_data( $sirsc_data );
			if ( ! empty( $post_data['post_id'] ) ) {
				$id = (int) $post_data['post_id'];
				$reuse_wrapper = true;
			}
		}
		if ( empty( $id ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$compute    = self::compute_image_paths( $id, '', $upload_dir );
		if ( empty( $image ) && ! empty( $compute['metadata'] ) ) {
			$image = $compute['metadata'];
		}
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
			if ( empty( $image ) ) {
				$filename = get_attached_file( $id );
				$image    = self::attempt_to_create_metadata( $id, $filename );
			}
		}

		if ( empty( $image ) ) {
			return;
		}

		$summary = self::general_sizes_and_files_match( $id, $image, $compute );
		$count   = 0;

		if ( ! $reuse_wrapper ) :
			?>
			<div id="sirsc-column-summary-<?php echo (int) $id; ?>">
			<?php
		endif;
		?>

		<input type="hidden" name="post_id" value="<?php echo (int) $id; ?>">
		<div class="sirsc-image-generate-functionality">
			<div class="inside">
				<table class="widefat sirsc-small-info-table sirsc-column-summary">
					<?php foreach ( $summary as $k => $v ) : ?>
						<?php
						$fsize     = ( empty( $v['fsize'] ) || 'N/A' == $v['filesize'] ) ? '<span class="missing-file">' . __( 'The file is missing!', 'sirsc' ) . '</span>' : '';
						$hint      = ( ! empty( $fsize ) ) ? ' missing-file' : '';
						$v['size'] = str_replace( ',', ', ', $v['size'] );
						?>
						<tr class="vtop bordertop<?php echo esc_attr( $hint ); ?>">
							<td width="32" align="center" title="<?php echo esc_attr( $v['hint'] ); ?>">
								<?php echo intval( ++ $count ); ?>.
							</td>
							<td width="32" align="center" title="<?php echo esc_attr( $v['hint'] ); ?>">
								<span class="dashicons <?php echo esc_attr( $v['icon'] ); ?>"></span>
							</td>
							<td>
								<?php if ( empty( $fsize ) ) : ?>
									<a href="<?php echo esc_url( trailingslashit( $upload_dir['baseurl'] ) . $k ); ?>" target="_blank"><?php echo esc_attr( $v['size'] ); ?></a>
								<?php else : ?>
									<?php echo esc_attr( $v['size'] ); ?>
									<?php echo ( $fsize ); //phpcs:ignore ?>
								<?php endif; ?>
								| <?php echo esc_attr( $v['width'] ); ?> x <?php echo esc_attr( $v['height'] ); ?>
							</td>
							<td width="72" align="right" nowrap="nowrap">
								<?php echo esc_attr( $v['filesize'] ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
		<?php
		if ( ! $reuse_wrapper ) :
			?>
			</div>
			<?php
		endif;
	}

	/**
	 * Match all the files and the images sizes registered.
	 *
	 * @param  integer $id      Attachment ID.
	 * @param  array   $image   Maybe metadata.
	 * @param  array   $compute Maybe extra computed info.
	 * @return array|void
	 */
	public static function general_sizes_and_files_match( $id, $image = array(), $compute = array() ) {
		if ( empty( $id ) ) {
			// Fail-fast.
			return;
		}

		if ( is_array( $id ) ) {
			$id = ( ! empty( $id['id'] ) ) ? (int) $id['id'] : 0;
		}
		if ( empty( $id ) ) {
			// Fail-fast.
			return;
		}

		$upload_dir = wp_upload_dir();
		if ( empty( $compute ) ) {
			$compute = self::compute_image_paths( $id, '', $upload_dir );
		}
		if ( empty( $image ) && ! empty( $compute['metadata'] ) ) {
			$image = $compute['metadata'];
		}
		if ( empty( $image ) ) {
			$image = wp_get_attachment_metadata( $id );
			if ( empty( $image ) ) {
				$filename = get_attached_file( $id );
				$image    = self::attempt_to_create_metadata( $id, $filename );
			}
		}

		$list       = array();
		$registered = get_intermediate_image_sizes();
		$basedir    = trailingslashit( $upload_dir['basedir'] );
		$baseurl    = trailingslashit( $upload_dir['baseurl'] );
		if ( ! empty( $image['file'] ) ) {
			$dir = trailingslashit( dirname( $image['file'] ) );
		} elseif ( ! empty( $compute['source'] ) ) {
			$dir = trailingslashit( dirname( $compute['source'] ) );
		}
		$gene_all   = self::assess_files_for_attachment_original( $id, $image );
		if ( ! empty( $gene_all['names'] ) ) {
			$list = array_merge( $gene_all['names']['original'], $gene_all['names']['full'], $gene_all['names']['generated'] );
		}

		// Start to gather data.
		$summary = array();
		if ( ! empty( $gene_all['names']['full'][0] ) ) {
			$file    = $gene_all['names']['full'][0];
			$fsize   = ( file_exists( $basedir . $file ) ) ? filesize( $basedir . $file ) : 0;
			$info    = array(
				'width'      => $compute['metadata']['width'],
				'height'     => $compute['metadata']['height'],
				'size'       => 'full',
				'registered' => true,
				'fsize'      => $fsize,
				'filesize'   => self::human_filesize( $fsize ),
				'icon'       => 'dashicons-yes-alt is-full',
				'hint'       => __( 'currently registered', 'sirsc' ),
			);
			$summary[ $file ] = $info;
			$list = array_diff( $list, array( $file ) );
		}

		if ( ! empty( $gene_all['names']['original'][0] ) ) {
			$file  = $gene_all['names']['original'][0];
			$s     = ( file_exists( $basedir . $file ) ) ? getimagesize( $basedir . $file ) : 0;
			$fsize = ( file_exists( $basedir . $file ) ) ? filesize( $basedir . $file ) : 0;
			$info  = array(
				'width'      => ( ! empty( $s[0] ) ) ? $s[0] : 0,
				'height'     => ( ! empty( $s[1] ) ) ? $s[1] : 0,
				'size'       => ( $file === $gene_all['names']['full'][0] ) ? 'full,original' : 'original',
				'registered' => true,
				'fsize'      => $fsize,
				'filesize'   => self::human_filesize( $fsize ),
				'icon'       => ( $file === $gene_all['names']['full'][0] ) ? 'dashicons-yes-alt is-full is-original' : 'dashicons-yes-alt is-original',
				'',
				'hint'       => __( 'currently registered', 'sirsc' ),
			);
			$summary[ $file ] = $info;
			$list = array_diff( $list, array( $file ) );
		}

		if ( ! empty( $compute['metadata']['sizes'] ) ) {
			foreach ( $compute['metadata']['sizes'] as $k => $v ) {
				$file  = $dir . $v['file'];
				$fsize = ( file_exists( $basedir . $file ) ) ? filesize( $basedir . $file ) : 0;
				$info  = array(
					'width'      => ( ! empty( $v['width'] ) ) ? $v['width'] : 0,
					'height'     => ( ! empty( $v['height'] ) ) ? $v['height'] : 0,
					'size'       => $k,
					'registered' => ( in_array( $k, $registered ) ),
					'fsize'      => $fsize,
					'filesize'   => self::human_filesize( $fsize ),
					'icon'       => ( in_array( $k, $registered ) ) ? 'dashicons-yes-alt' : 'dashicons-marker',
					'hint'       => ( in_array( $k, $registered ) ) ? __( 'currently registered', 'sirsc' ) : __( 'not registered anymore', 'sirsc' ),
				);
				if ( ! isset( $summary[ $file ] ) ) {
					$summary[ $file ] = $info;
				} else {
					$summary[ $file ]['size'] .= ',' . $k;
				}
				$list = array_diff( $list, array( $file ) );
			}
		}

		if ( ! empty( $list ) ) {
			foreach ( $list as $k ) {
				$fsize = ( file_exists( $basedir . $k ) ) ? filesize( $basedir . $k ) : 0;
				$s     = ( file_exists( $basedir . $k ) ) ? getimagesize( $basedir . $k ) : array();
				$summary[ $k ] = array(
					'width'      => ( ! empty( $s[0] ) ) ? $s[0] : 0,
					'height'     => ( ! empty( $s[1] ) ) ? $s[1] : 0,
					'size'       => __( 'unknown', 'sirsc' ),
					'registered' => false,
					'fsize'      => $fsize,
					'filesize'   => self::human_filesize( $fsize ),
					'icon'       => 'dashicons-marker',
					'hint'       => __( 'never registered', 'sirsc' ),
				);
			}
		}
		if ( empty( $summary ) ) {
			return;
		}
		$fsize = array_column( $summary, 'fsize' );
		array_multisort( $fsize, SORT_DESC, $summary );
		$count = 0;

		// This attempts to matche the sizes and updates the summary.
		self::maybe_match_unknown_files_to_meta( $id, $summary );

		return $summary;
	}

	/**
	 * Attempt to match the unknown files and update the attachment metadata.
	 *
	 * @param  integer $id      Attachment ID.
	 * @param  array   $summary Identified generated files.
	 * @return void
	 */
	public static function maybe_match_unknown_files_to_meta( $id, &$summary ) {
		$assess = array();
		if ( ! empty( $summary ) ) {
			$image_meta   = wp_get_attachment_metadata( $id );
			$initial_meta = $image_meta;
			$sizes_info   = self::get_all_image_sizes_plugin();
			if ( ! empty( $image_meta['sizes'] ) ) {
				$direct = wp_list_pluck( $image_meta['sizes'], 'file' );
				if ( ! empty( $direct ) ) {
					$dir = trailingslashit( dirname( $image_meta['file'] ) );
					foreach ( $direct as $key => $value ) {
						$file = $dir . $value;
						if ( ! empty( $summary[ $file ] ) ) {
							if ( substr_count( $summary[ $file ]['size'], 'unknown' ) ) {
								$summary[ $file ]['size'] = $key;
							} else {
								if ( ! substr_count( $summary[ $file ]['size'], $key ) ) {
									$summary[ $file ]['size'] .= ',' . $key;
								}
							}
						}
					}
				}
			}

			foreach ( $summary as $file => $info ) {
				if ( 'unknown' === $info['size'] && ! empty( $info['width'] ) && ! empty( $info['height'] ) ) {
					$filetype = wp_check_filetype( $file );
					foreach ( $sizes_info as $name => $details ) {
						if ( $details['width'] == $info['width'] && $details['height'] == $info['height'] ) {
							// This is a perfect match.
							$image_meta['sizes'][ $name ] = array(
								'file'      => wp_basename( $file ),
								'width'     => $info['width'],
								'height'    => $info['height'],
								'mime-type' => $filetype['type'],
							);
							$summary[ $file ]['size'] .= ',' . $name;
						} else {
							if ( empty( $details['crop'] ) ) {
								// This can be a scale type.
								if ( $details['width'] == $info['width'] && empty( $details['height'] ) ) {
									$image_meta['sizes'][ $name ] = array(
										'file'      => wp_basename( $file ),
										'width'     => $details['width'],
										'height'    => $info['height'],
										'mime-type' => $filetype['type'],
									);
									$summary[ $file ]['size'] .= ',' . $name;
								} elseif ( $details['height'] == $info['height'] && empty( $details['width'] ) ) {
									$image_meta['sizes'][ $name ] = array(
										'file'      => wp_basename( $file ),
										'width'     => $info['width'],
										'height'    => $height['height'],
										'mime-type' => $filetype['type'],
									);
									$summary[ $file ]['size'] .= ',' . $name;
								}
							}
						}
					}

					if ( substr_count( $summary[ $file ]['size'], ',' ) ) {
						$summary[ $file ]['size'] = str_replace( 'unknown,', '', $summary[ $file ]['size'] );
						$summary[ $file ]['icon'] = 'dashicons-yes-alt';
						$summary[ $file ]['hint'] = __( 'currently registered', 'sirsc' );
					}
				}
			}

			if ( ! empty( $summary ) ) {
				foreach ( $summary as $key => $value ) {
					$summary[ $key ]['match'] = explode( ',', $summary[ $key ]['size'] );
				}
			}

			if ( $image_meta !== $initial_meta ) {
				// Override the meta with matched images, to fix missing metadata.
				wp_update_attachment_metadata( $id, $image_meta );
			}
		}

	}

	/**
	 * Return hmain readable files size.
	 *
	 * @access public
	 * @static
	 *
	 * @param  integer $bytes    Bytes.
	 * @param  integer $decimals Decimals.
	 *
	 * @return string
	 */
	public static function human_filesize( $bytes, $decimals = 2 ) {
		if ( empty( $bytes ) ) {
			return esc_html__( 'N/A', 'sirsc' );
		}
		$sz = 'BKMGTP';
		$factor = floor( ( strlen( $bytes ) - 1 ) / 3 );
		return sprintf( "%.{$decimals}f&nbsp;", $bytes / pow( 1024, $factor ) ) . @$sz[ $factor ];
	}

	/**
	 * Regenerate the image sizes for a specified image.
	 */
	public static function sirsc_ajax_delete_image_sizes_on_request() {
		$sirsc_data = self::has_sirsc_data();
		if ( ! empty( $sirsc_data ) ) {
			$post_data = self::parse_ajax_data( $sirsc_data );
			if ( ! empty( $post_data['post_id'] ) && ! empty( $post_data['selected_size'] ) ) {
				$size  = $post_data['selected_size'];
				$qual  = ( ! empty( $post_data['selected_quality'] ) ) ? (int) $post_data['selected_quality'] : self::DEFAULT_QUALITY;
				$image = wp_get_attachment_metadata( $post_data['post_id'] );
				self::execute_specified_attachment_file_delete( $post_data['post_id'], $size, '', $image );
				do_action( 'sirsc_action_after_image_delete', $post_data['post_id'] );
				self::expose_image_after_processing( $post_data['post_id'], $size, false, false, $qual );
			}
		}
	}

	/**
	 * Raw cleanup the image sizes for a specified image.
	 */
	public static function sirsc_ajax_raw_cleanup_single_on_request() {
		$sirsc_data = self::has_sirsc_data();
		if ( ! empty( $sirsc_data ) ) {
			$post_data = self::parse_ajax_data( $sirsc_data );
			if ( ! empty( $post_data['post_id'] ) ) {
				$id   = (int) $post_data['post_id'];
				$meta = wp_get_attachment_metadata( $id );
				$list = self::assess_files_for_attachment_original( $id, $meta );
				if ( ! empty( $list['paths']['generated'] ) ) {
					foreach ( $list['paths']['generated'] as $c => $removable ) {
						if ( file_exists( $removable ) ) {
							@unlink( $removable );
							do_action( 'sirsc_image_file_deleted', $id, $removable );
						}
					}
					// Update the cleaned meta.
					$meta['sizes'] = array();
					wp_update_attachment_metadata( $id, $meta );

					// Re-fetch the meta.
					$image = wp_get_attachment_metadata( $id );
					do_action( 'sirsc_attachment_images_ready', $image, $id );
				}

				echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Done!', 'sirsc' ) . '</span>';
				echo '<script>
				jQuery(document).ready(function () {
					sirsc_maybe_refresh_sirsc_column_summary(\'' . $id . '\');
				});
				</script>'; // WPCS: XSS OK.
			} else {
				echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Something went wrong!', 'sirsc' ) . '</span>';
			}
		}
	}
	/**
	 * Expose image after processing.
	 *
	 * @param  integer $post_id    The attachment ID.
	 * @param  string  $sel_size   The image size.
	 * @param  boolean $generate   True if the size should be regenerated.
	 * @param  string  $crop_small Maybe a specific crop position.
	 * @param  integer $quality    Perhaps a quality.
	 * @return void
	 */
	public static function expose_image_after_processing( $post_id, $sel_size, $generate = false, $crop_small = '', $quality = 0 ) {
		if ( empty( $post_id ) ) {
			echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Something went wrong!', 'sirsc' ) . '</span>';
			// Fail-fast.
			return;
		}
		self::load_settings_for_post_id( (int) $post_id );
		$sizes = ( ! empty( $sel_size ) ) ? trim( $sel_size ) : 'all';
		if ( true === $generate ) {
			self::debug( 'Processing the AJAX regenerate for ' . $post_id . '|' . $sizes, true, true );
			self::make_images_if_not_exists( $post_id, $sizes, $crop_small, $quality );
		}

		if ( 'all' !== $sizes ) {
			$th_src = '';
			$image  = wp_get_attachment_metadata( $post_id );
			if ( ! empty( $image['sizes'][ $sizes ] ) ) {
				$th     = wp_get_attachment_image_src( $post_id, $sizes );
				$th_src = $th[0];
			}
			$crop_table  = '';
			$tmp_details = self::get_all_image_sizes( $sizes );
			if ( ! empty( $tmp_details['crop'] ) ) {
				$crop_table = '<div class="sirsc_clearAll"></div>' . preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', self::make_generate_images_crop( (int) $post_id, $sizes ) );
			}
			$button = '<div class="sirsc_clearAll"></div><div class="sirsc-size-quality-wrap">' . esc_html__( 'Quality', 'sirsc' ) . '<input type="number" name="selected_quality" id="selected_quality' . (int) $post_id . $sizes . '" value="' . (int) $quality . '" class="sirsc-size-quality"></div><a class="button" onclick="sirsc_start_regenerate(\'' . (int) $post_id . $sizes . '\');"><b class="dashicons dashicons-update"></b> ' . esc_html__( 'Regenerate', 'sirsc' ) . '</a>';

			if ( ! empty( $th_src ) ) {
				if ( empty( $image['file'] ) && ! empty( $image['path'] ) ) {
					$image['file'] = $image['path'];
				}
				if ( ! substr_count( $image['file'], $image['sizes'][ $sizes ]['file'] ) ) {
					// The size is the not the original file.
					$crop_table = '<a class="sirsc_delSize" onclick="sirsc_start_delete(\'' . intval( $post_id ) . $sizes . '\');" title="' . esc_attr__( 'Delete', 'sirsc' ) . '"><b class="dashicons dashicons-trash"></b></a>' . $crop_table;
				}
			}

			$filesize = 0;
			if ( ! empty( $th_src ) ) {
				$folder     = str_replace( basename( $image['file'] ), '', $image['file'] );
				$upload_dir = wp_upload_dir();
				$path       = trailingslashit( trailingslashit( $upload_dir['basedir'] ) . $folder );
				$file       = $path . $image['sizes'][ $sizes ]['file'];
				if ( file_exists( $file ) ) {
					// Clear the file cache, so that the filesize to be read correct.
					clearstatcache( true, $file );
					$filesize = @filesize( $file );
				}
			}
			$w = ( ! empty( $image['sizes'][ $sizes ]['width'] ) ) ? $image['sizes'][ $sizes ]['width'] : 0;
			$h = ( ! empty( $image['sizes'][ $sizes ]['height'] ) ) ? $image['sizes'][ $sizes ]['height'] : 0;

			if ( ! empty( $th_src ) ) {
				$th_src .= '?v=' . time();
			}
			echo '<script>
			jQuery(document).ready(function () {
				sirsc_thumbnail_details(\'' . intval( $post_id ) . '\', \'' . $sizes . '\', \'' . $th_src . '\', \'' . $w . '\', \'' . $h . '\', \'' . addslashes( $crop_table . $button ) . '\');
				jQuery(\'#sirsc_recordsArray_' . $post_id . $sel_size . ' .image-file-size\').html(\'' . self::human_filesize( $filesize ) . '\');
			});
			</script>'; // WPCS: XSS OK.

			$image = wp_get_attachment_metadata( $post_id );
			do_action( 'sirsc_image_processed', $post_id, $sizes );
			do_action( 'sirsc_attachment_images_ready', $image, $post_id );
		} else {
			$image = wp_get_attachment_metadata( $post_id );
			do_action( 'sirsc_attachment_images_processed', $image, $post_id );
			do_action( 'sirsc_attachment_images_ready', $image, $post_id );
		}
		echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Done!', 'sirsc' ) . '</span>';
		echo '<script>
			jQuery(document).ready(function () {
				sirsc_maybe_refresh_sirsc_column_summary(\'' . intval( $post_id ) . '\');
			});
			</script>'; // WPCS: XSS OK.
		clean_post_cache( $post_id );
	}
	/**
	 * Regenerate the image sizes for a specified image.
	 */
	public static function sirsc_ajax_process_image_sizes_on_request() {
		$sirsc_data = self::has_sirsc_data();
		if ( ! empty( $sirsc_data ) ) {
			$data = self::parse_ajax_data( $sirsc_data );
			if ( ! empty( $data['post_id'] ) ) {
				$post = get_post( $data['post_id'] );
				if ( ! empty( $post ) ) {
					self::notify_doing_sirsc();
					$size = ( ! empty( $data['selected_size'] ) ) ? $data['selected_size'] : 'all';
					$crop = ( ! empty( $data[ 'crop_small_type_' . $size ] ) ) ? $data[ 'crop_small_type_' . $size ] : '';
					$qual = ( ! empty( $data['selected_quality'] ) ) ? $data['selected_quality'] : 0;
					self::expose_image_after_processing( $data['post_id'], $size, true, $crop, $qual );
					do_action( 'sirsc_action_after_image_delete', $data['post_id'] );
				}
			} else {
				echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Something went wrong!', 'sirsc' ) . '</span>';
			}
		}
	}

	/**
	 * Assess original vs target.
	 *
	 * @param  array  $image The attachment meta.
	 * @param  array  $sval  The intended image size details.
	 * @param  string $sname The intended image size name.
	 * @return boolean
	 */
	public static function assess_original_vs_target( $image = array(), $sval = array(), $sname = '' ) {
		if ( empty( $image ) || empty( $sval ) ) {
			return false;
		}
		if ( ! empty( $image ) && ! empty( $sval ) ) {
			if ( ! empty( $image['sizes'][ $sname ]['file'] ) || empty( self::$settings['enable_perfect'] ) ) {
				// For the images already created, bypasss the check.
				return true;
			}

			if ( ! empty( $sval['crop'] ) ) {
				// This should be a crop.
				if ( $image['width'] < $sval['width'] || $image['height'] < $sval['height'] ) {
					// The image is too small, return.
					return false;
				}
			} else {
				// This should be a resize.
				if ( ! empty( $sval['width'] ) && $image['width'] < $sval['width'] ) {
					// The image is too small, return.
					return false;
				}
				if ( empty( $sval['width'] ) && ! empty( $sval['height'] ) && $image['height'] < $sval['height'] ) {
					// The image is too small, return.
					return false;
				}
			}

			return true;
		}
	}

	/**
	 * Check if an image size should be generated or not for image meta.
	 *
	 * @param array   $image    The image metadata.
	 * @param string  $sname    Image size slug.
	 * @param array   $sval     The image size detail.
	 * @param string  $filename Image filename.
	 * @param boolean $force    True to force re-crop.
	 * @return boolean
	 */
	public static function check_if_execute_size( $image = array(), $sname = '', $sval = array(), $filename = '', $force = false ) {
		$execute = false;
		if ( ! self::assess_original_vs_target( $image, $sval, $sname ) ) {
			// Fail-fast.
			return false;
		}

		if ( empty( $image['sizes'][ $sname ] ) ) {
			$execute = true;
		} else {
			// Check if the file does exist, else generate it.
			if ( empty( $image['sizes'][ $sname ]['file'] ) ) {
				$execute = true;
			} else {
				$file = str_replace( basename( $filename ), $image['sizes'][ $sname ]['file'], $filename );

				if ( ! file_exists( $file ) ) {
					$execute = true;
				} else {
					// Check if the file does exist and has the required width and height.
					$w = ( ! empty( $sval['width'] ) ) ? (int) $sval['width'] : 0;
					$h = ( ! empty( $sval['height'] ) ) ? (int) $sval['height'] : 0;
					$c = ( ! empty( $sval['crop'] ) ) ? $sval['crop'] : false;

					$c_image_size = getimagesize( $file );
					$ciw          = (int) $c_image_size[0];
					$cih          = (int) $c_image_size[1];
					if ( ! empty( $c ) ) {
						if ( $w !== $ciw || $h !== $cih ) {
							$execute = true;
						} elseif ( true === $force ) {
							$execute = true;
						}
					} else {
						if ( ( 0 === $w && $cih <= $h )
							|| ( 0 === $h && $ciw <= $w )
							|| ( 0 !== $w && 0 !== $h && $ciw <= $w && $cih <= $h ) ) {
							$execute = true;
						}
					}
				}
			}
		}
		return $execute;
	}

	/**
	 * Assess the quality by mime-type.
	 *
	 * @param string  $sname         Size name.
	 * @param string  $mime          Mime-type.
	 * @param integer $force_quality Custom quality.
	 * @return integer
	 */
	public static function editor_set_custom_quality( $sname, $mime, $force_quality ) {
		if ( ! empty( $force_quality ) ) {
			$quality = (int) $force_quality;
		} else {
			$quality = ( ! empty( self::$settings['default_quality'][ $sname ] ) ) ? (int) self::$settings['default_quality'][ $sname ] : self::DEFAULT_QUALITY;
		}
		$quality = ( $quality < 0 ) ? 0 : $quality;
		$quality = ( $quality > 100 ) ? self::DEFAULT_QUALITY : $quality;

		if ( ! empty( $quality ) && 'image/png' == $mime ) {
			$quality = abs( 10 - ceil( $quality / 10 ) );
			if ( $quality > 9 ) {
				$quality = 9;
			}
			if ( $quality < 0 ) {
				$quality = 0;
			}
		}

		if ( ! empty( $quality ) ) {
			add_filter(
				'wp_editor_set_quality',
				function( $m ) use ( $mime, $quality ) {
					return $quality;
				},
				90
			);
		}

		return $quality;
	}

	/**
	 * Assess and maybe remove older file.
	 *
	 * @param  string $previous Previous filename.
	 * @param  string $new      New filename.
	 * @return void
	 */
	public static function assess_maybe_remove_older_file( $previous, $new ) {
		if ( empty( $previous ) || empty( $new ) ) {
			// Fail-fast.
			return;
		}
		if ( $previous !== $new ) {
			$upls = wp_upload_dir();
			$path = trailingslashit( $upls['basedir'] ) . $previous;
			if ( file_exists( $path ) ) {
				@unlink( $path );
			}
		}
	}

	/**
	 * Attempt to scale the width and height to cover the expected size.
	 *
	 * @param  integer $initial_w  Initial image width.
	 * @param  integer $initial_h  Initial image height.
	 * @param  integer $expected_w Expected image width.
	 * @param  integer $expected_h Expected image height.
	 * @return array
	 */
	public static function upscale_match_sizes( $initial_w, $initial_h, $expected_w, $expected_h ) {
		$new_w  = $initial_w;
		$new_h  = $initial_h;
		$result = array(
			'width'  => $new_w,
			'height' => $new_h,
			'scale'  => false,
		);
		if ( $initial_w >= $expected_w && $initial_h >= $expected_h ) {
			// The original is bigger than the expected, no need to scale, no need to continue either.
			return array(
				'width'  => $initial_w,
				'height' => $initial_h,
				'scale'  => false,
			);
		}

		if ( $initial_w >= $expected_w ) {
			// This means that the initial width is good, but the initial height is smaller than the expected height.
			$new_h  = $expected_h;
			$new_w  = ceil( $initial_w * $expected_h / $initial_h );
			return array(
				'width'  => $new_w,
				'height' => $new_h,
				'scale'  => true,
			);
		}

		if ( $initial_w < $expected_w ) {
			// This means that the initial width is smaller than the expected width.
			$new_w = $expected_w;
			$new_h = ceil( $expected_w * $initial_h / $initial_w );
			if ( ! ( $new_h >= $expected_h ) ) {
				$new_h = $expected_h;
				$new_w = ceil( $initial_w * $expected_h / $initial_h );
			}

			return array(
				'width'  => $new_w,
				'height' => $new_h,
				'scale'  => true,
			);
		}
	}

	/**
	 * Recompute the image size components that are used to override the private editor properties.
	 *
	 * @param  null|mixed $default Whether to preempt output of the resize dimensions.
	 * @param  integer    $orig_w  Original width in pixels.
	 * @param  integer    $orig_h  Original height in pixels.
	 * @param  integer    $new_w   New width in pixels.
	 * @param  integer    $new_h   New height in pixels.
	 * @param  bool|array $crop    Whether to crop image to specified width and height or resize.
	 * @return array               An array can specify positioning of the crop area. Default false.
	 */
	public static function sirsc_image_crop_dimensions_up( $default, $orig_w, $orig_h, $new_w, $new_h, $crop ) {
		$new_w        = self::$upscale_new_w;
		$new_h        = self::$upscale_new_h;
		$aspect_ratio = $orig_w / $orig_h;
		$size_ratio   = max( $new_w / $orig_w, $new_h / $orig_h );
		$crop_w       = round( $new_w / $size_ratio );
		$crop_h       = round( $new_h / $size_ratio );
		$s_x          = floor( ( $orig_w - $crop_w ) / 2 );
		$s_y          = floor( ( $orig_h - $crop_h ) / 2 );
		return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );
	}

	/**
	 * Force native editor to upscale the image before applying the expected crop.
	 *
	 * @param  string  $id            The attachment ID.
	 * @param  string  $file          The original file.
	 * @param  string  $size_name     The image size name.
	 * @param  integer $original_w    The original image width.
	 * @param  integer $original_h    The original image height.
	 * @param  object  $editor        Editor instance.
	 */
	public static function force_upscale_before_crop( $id, $file, $size_name, $original_w, $original_h, $editor ) {
		if ( ! empty( self::$settings['enable_perfect'] ) ) {
			$all_sizes = self::get_all_image_sizes();
			$meta      = wp_get_attachment_metadata( $id );
			$rez_img   = self::allow_resize_from_original( $file, $meta, $all_sizes, $size_name );
			if ( ! empty( $rez_img['must_scale_up'] ) ) {
				$sw = $all_sizes[ $size_name ]['width'];
				$sh = $all_sizes[ $size_name ]['height'];

				$assess = self::upscale_match_sizes( $original_w, $original_h, $sw, $sh );
				if ( ! empty( $assess['scale'] ) ) {
					self::$upscale_new_w = $assess['width'];
					self::$upscale_new_h = $assess['height'];

					// Apply the filter here to override the private properties.
					add_filter( 'image_resize_dimensions', array( get_called_class(), 'sirsc_image_crop_dimensions_up' ), 10, 6 );

					// Make the editor resize the loaded resource.
					$editor->resize( self::$upscale_new_w, self::$upscale_new_h );

					// Remove the custom override, so that the editor to fallback to it's defaults.
					remove_filter( 'image_resize_dimensions', array( get_called_class(), 'sirsc_image_crop_dimensions_up' ), 10 );
				}

				// Make the editor crop the upscaled resource.
				$editor->resize( $sw, $sh, true );
			}
		}
	}

	/**
	 * Access directly the image editor to generate a specific image.
	 *
	 * @param  string  $id            The attachment ID.
	 * @param  string  $file          The original file.
	 * @param  string  $name          The image size name.
	 * @param  array   $info          The image size info.
	 * @param  string  $small_crop    Maybe some crop position.
	 * @param  integer $force_quality Maybe some forced quality.
	 * @return array|boolean
	 */
	public static function image_editor( $id, $file, $name = '', $info = array(), $small_crop = '', $force_quality = 0 ) {
		$filetype   = wp_check_filetype( $file );
		$mime_type  = $filetype['type'];
		$image_size = getimagesize( $file );
		$estimated  = wp_constrain_dimensions( $image_size[0], $image_size[1], $info['width'], $info['height'] );
		if ( ! empty( $estimated ) && $estimated[0] === $image_size[0] && $estimated[1] === $image_size[1] ) {
			$meta = wp_get_attachment_metadata( $id );

			// Skip the editor, this is the same as the current file.
			if ( self::$wp_ver < 5.3 ) {
				// For older version, let's check the size in DB.
				if ( ! empty( $meta['sizes'][ $name ]['file'] ) ) {
					$maybe_size = trailingslashit( dirname( $file ) ) . $meta['sizes'][ $name ]['file'];
					if ( file_exists( $maybe_size ) ) {
						$image_size = getimagesize( $maybe_size );
						rename( $maybe_size, $file );
						$saved = array(
							'file'   => wp_basename( $file ),
							'width'  => $image_size[0],
							'height' => $image_size[1],
							'mime'   => $mime_type,
							'reused' => true,
						);
						return $saved;
					}
				}
			} else {
				if ( ! empty( $meta['width'] ) && $estimated[0] === $meta['width']
					&& ! empty( $meta['height'] ) && $estimated[1] === $meta['height'] ) {
					// This matches the orginal.
					$saved = array(
						'file'   => wp_basename( $file ),
						'width'  => $estimated[0],
						'height' => $estimated[1],
						'mime'   => $mime_type,
						'reused' => true,
					);
					return $saved;
				} else if ( ! empty( $meta['sizes'][ $name ]['file'] ) ) {
					$maybe_size = trailingslashit( dirname( $file ) ) . $meta['sizes'][ $name ]['file'];
					if ( file_exists( $maybe_size ) ) {
						$image_size = getimagesize( $maybe_size );
						$saved      = array(
							'file'   => wp_basename( $file ),
							'width'  => $image_size[0],
							'height' => $image_size[1],
							'mime'   => $mime_type,
							'reused' => true,
						);
						return $saved;
					}
				}
			}

			/*
			// Fall-back for newer version.
			$saved = array(
				'file'   => wp_basename( $file ),
				'width'  => $image_size[0],
				'height' => $image_size[1],
				'mime'   => $mime_type,
			);
			return $saved;
			*/
			return false;
		}

		$editor = @wp_get_image_editor( $file );
		if ( ! is_wp_error( $editor ) ) {

			$quality   = self::editor_set_custom_quality( $name, $mime_type, $force_quality );
			$editor->set_quality( $quality );

			if ( ! empty( $info['crop'] ) ) {
				$crop = self::identify_crop_pos( $name, $small_crop );
				self::debug( 'CROP ' . $info['width'] . 'x' . $info['height'] . '|' . print_r( $crop, 1 ), true, true );
				$editor->resize( $info['width'], $info['height'], $crop );

				if ( ! empty( self::$settings['enable_perfect'] ) && ! empty( self::$settings['enable_upscale'] ) ) {
					$result = $editor->get_size();
					if ( $result['width'] != $info['width'] || $result['height'] != $info['height'] ) {
						self::debug( '^^^^^ CROP failed, attempt to UPSCALE', true, true );
						if ( ! empty( self::$settings['enable_perfect'] ) ) {
							self::force_upscale_before_crop( $id, $file, $name, $image_size[0], $image_size[1], $editor );
						}
					}
				}
			} else {
				self::debug( 'SCALE ' . $info['width'] . 'x' . $info['height'], true, true );
				$editor->resize( $info['width'], $info['height'] );
			}

			// Finally, let's store the image.
			$saved = $editor->save();
			return $saved;
		}
		return false;
	}

	/**
	 * Assess unique original.
	 *
	 * @param  integer $id     Attachment ID.
	 * @param  string  $folder The path.
	 * @param  string  $dir    File relative directory.
	 * @param  string  $name   File name.
	 * @return string
	 */
	public static function assess_unique_original( $id, $folder, $dir = '', $name ) {
		if ( ! file_exists( $folder . $name ) ) {
			return $name;
		}

		// @TODO $unique = wp_unique_filename( $folder, $name ).
		return $name;
	}

	/**
	 * Assess rename original.
	 *
	 * @param  integer $id Attachment ID.
	 * @return array
	 */
	public static function assess_rename_original( $id ) {
		$metadata = wp_get_attachment_metadata( $id );
		if ( ! empty( $metadata ) ) {
			$orig_me = $metadata;
			$uploads = wp_get_upload_dir();
			if ( empty( $metadata['file'] ) ) {
				// Read the filename from the attachmed file, as this was not set in the metadata.
				$filename = get_attached_file( $id );
			} else {
				// Read the filename from the metadata.
				$filename = trailingslashit( $uploads['basedir'] ) . $metadata['file'];
			}

			$ext  = pathinfo( $filename, PATHINFO_EXTENSION );
			$name = pathinfo( $filename, PATHINFO_FILENAME );
			$path = pathinfo( $filename, PATHINFO_DIRNAME );
			if ( file_exists( $filename ) ) {
				$size = getimagesize( $filename );
			} else {
				// This means that the image was probably moved in the previous iteration.
				$size = array( $metadata['width'], $metadata['height'] );
			}

			$filetype = wp_check_filetype( $filename );
			$info = array(
				'path'   => trailingslashit( $path ),
				'dir'    => trailingslashit( dirname( str_replace( trailingslashit( $uploads['basedir'] ), '', $filename ) ) ),
				'name'   => $name . '.' . $ext,
				'width'  => ( ! empty( $size[0] ) ) ? (int) $size[0] : 0,
				'height' => ( ! empty( $size[1] ) ) ? (int) $size[1] : 0,
				'mime'   => $filetype['type'],
			);

			$initial_unique = '';
			if ( ! empty( $metadata['original_image'] ) && $metadata['original_image'] !== $info['name'] ) {
				$initial_unique = $metadata['original_image'];
				$unique         = wp_unique_filename( $info['path'], $metadata['original_image'] );

				// Remove the initial original file id that is not used by another attachment.
				if ( file_exists( $info['path'] . $metadata['original_image'] ) && $metadata['original_image'] != $unique ) {
					@unlink( $info['path'] . $metadata['original_image'] );
				}

				// Rename the full size as  the initial original file.
				if ( file_exists( $info['path'] . $info['name'] ) ) {
					@rename( $info['path'] . $info['name'], $info['path'] . $unique );
				}

				// Pass the new name.
				$info['name'] = $unique;
				$metadata['original_image'] = $unique;
			}

			$info['filename']   = $info['path'] . $info['name'];
			$metadata['file']   = $info['dir'] . $info['name'];
			$metadata['width']  = $info['width'];
			$metadata['height'] = $info['height'];

			if ( ! empty( self::$settings['force_original_to'] ) ) {
				$fo_orig = self::$settings['force_original_to'];
				if ( empty( $metadata['sizes'][ $fo_orig ] ) ) {
					$metadata['sizes'][ $fo_orig ] = array(
						'file'      => $info['name'],
						'width'     => $info['width'],
						'height'    => $info['height'],
						'mime-type' => $info['mime'],
					);
				}
			}

			// Save this.
			update_post_meta( $id, '_wp_attachment_metadata', $metadata );
			update_post_meta( $id, '_wp_attached_file', $info['dir'] . $info['name'] );

			if ( ! empty( $initial_unique ) && $initial_unique != $unique ) {
				$new = self::assess_unique_original( $id, $info['path'], $info['dir'], $initial_unique );
				if ( $new === $initial_unique ) {
					self::debug( 'FOUND A POTENTIAL REVERT ' . $new, true, true );
					@rename( $info['path'] . wp_basename( $metadata['file'] ), $info['path'] . $new );

					$metadata['file'] = $info['dir'] . $new;
					$metadata['original_image'] = $new;

					if ( ! empty( $fo_orig ) && ! empty( $metadata['sizes'][ $fo_orig ] ) ) {
						$metadata['sizes'][ $fo_orig ]['file'] = $new;
					}
					update_post_meta( $id, '_wp_attachment_metadata', $metadata );
					update_post_meta( $id, '_wp_attached_file', $info['dir'] . $new );
					clean_attachment_cache( $id );

					$info['name']     = $new;
					$info['filename'] = $info['path'] . $info['name'];
				}
			}
			clean_attachment_cache( $id );
			return $info;
		}
		return array();
	}

	/**
	 * Swap full image with another image size.
	 *
	 * @param  integer $id            The attachment ID.
	 * @param  string  $file          The original file.
	 * @param  string  $size_name     The image size name.
	 * @param  string  $small_crop    Maybe some crop position.
	 * @param  integer $force_quality Maybe some forced quality.
	 * @return array|boolean
	 */
	public static function swap_full_with_another_size( $id, $file, $size_name, $small_crop, $force_quality ) {
		$metadata  = wp_get_attachment_metadata( $id );
		$initial_m = $metadata;
		if ( empty( $metadata ) ) {
			// Fail-fast.
			return false;
		}
		self::debug( 'IN SWAP SIZES ' . $size_name, true, true );

		// Make the image.
		self::load_settings_for_post_id( $id );
		$size_info = self::get_all_image_sizes_plugin( $size_name );
		$saved     = self::image_editor( $id, $file, $size_name, $size_info, $small_crop, 0 );

		// Maybe rename the full size with the original.
		$info = self::assess_rename_original( $id );
		$metadata = wp_get_attachment_metadata( $id );

		if ( ! empty( $saved ) && ! empty( $info ) ) {
			if ( ! empty( $saved['path'] ) ) {
				unset( $saved['path'] );
			}

			self::debug( 'FORCED SIZE EDITOR PROCESSED IMAGE', true, true );
			$saved_filename = $info['path'] . $saved['file'];
			if ( wp_basename( $saved_filename ) != $info['name'] ) {
				// Remove the initial full.
				if ( file_exists( $info['filename'] ) ) {
					@unlink( $info['filename'] );
				}

				// Rename the new size as the full image.
				@copy( $saved_filename, $info['filename'] );

				// Remove the image size.
				@unlink( $saved_filename );

				// Adjust the metadata to match the new set.
				$metadata['width']  = $saved['width'];
				$metadata['height'] = $saved['height'];
				$saved['file'] = $info['name'];
				$metadata['sizes'][ $size_name ] = $saved;
			}

			if ( $initial_m != $metadata ) {
				// If something changed, then save the metadata.
				update_post_meta( $id, '_wp_attachment_metadata', $metadata );
				update_post_meta( $id, '_wp_attached_file', $info['dir'] . $info['name'] );
				clean_attachment_cache( $id );

				if ( ! defined( 'SIRSC_REPLACED_ORIGINAL' ) ) {
					// Notify other scripts that the original file is now this one.
					define( 'SIRSC_REPLACED_ORIGINAL', $info['dir'] . $info['name'] );
				}
			}

			self::debug( 'AFTER EDITOR PROCESSED IMAGE ' . print_r( $metadata, 1 ), true, true );
			return $metadata;
		}

		return $metadata;
	}

	/**
	 * Check if the attached image is required to be replaced with the "Force Original" from the settings.
	 *
	 * @param  integer $meta_id    Post meta id.
	 * @param  integer $post_id    Post ID.
	 * @param  string  $meta_key   Post meta key.
	 * @param  array   $meta_value Post meta value.
	 */
	public static function process_filtered_attachments( $meta_id = '', $post_id = '', $meta_key = '', $meta_value = '' ) {
		if ( ! empty( $post_id ) && '_wp_attachment_metadata' === $meta_key && ! empty( $meta_value ) ) {
			self::notify_doing_sirsc();
			self::load_settings_for_post_id( $post_id );

			self::debug( 'SETTINGS BEFORE FIRST METADATA SAVED ' . print_r( self::$settings, 1 ), true, true );
			self::debug( 'FIRST METADATA SAVED ' . print_r( $meta_value, 1 ), true, true );

			if ( ! empty( self::$settings['force_original_to'] ) ) {
				if ( self::$wp_ver >= 5.3 ) {
					// Maybe rename the full size with the original.
					$info = self::assess_rename_original( $post_id );
				} else {
					// Maybe swap the forced size with the full.
					$file    = get_attached_file( $post_id );
					$fo_orig = self::$settings['force_original_to'];
					$size    = self::get_all_image_sizes( $fo_orig );
					self::swap_full_with_another_size( $post_id, $file, $fo_orig, $size['crop'], 0 );
				}
			}

			if ( ! empty( $info ) && $info['dir'] . $info['name'] !== $meta_value['file'] ) {
				// Brute update and notify other scripts of this.
				$meta = wp_get_attachment_metadata( $post_id );
				$meta['file'] = $info['dir'] . $info['name'];
				update_post_meta( $post_id, '_wp_attachment_metadata', $meta );
				if ( ! defined( 'SIRSC_BRUTE_RENAME' ) ) {
					define( 'SIRSC_BRUTE_RENAME', $meta['file'] );
				}
			}

			self::debug( 'START PROCESS ALL REMAINING SIZES FOR ' . $post_id, true, true );
			self::make_images_if_not_exists( $post_id, 'all' );
		}
	}

	/**
	 * Maybe filter initial metadata.
	 *
	 * @param  array   $metadata      Computed metadata.
	 * @param  integer $attachment_id The attachment that is processing.
	 * @return array
	 */
	public static function wp_generate_attachment_metadata( $metadata, $attachment_id ) {
		if ( self::$wp_ver >= 5.3 ) {
			// Metadata parameter is empty, let's fetch it from the database if existing.
			$metadata = wp_get_attachment_metadata( $attachment_id );
		} else {
			// Initially preserve it.
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
		}

		self::debug( 'PREPARE AND RELEASE THE METADATA FOR ' . $attachment_id, true, true );
		$filter_out = self::cleanup_before_releasing_the_metadata_on_upload( $attachment_id );
		$filter_out = apply_filters( 'sirsc_computed_metadata_after_upload', $filter_out, $attachment_id );
		do_action( 'sirsc_attachment_images_ready', $filter_out, $attachment_id );
		return $filter_out;
	}

	/**
	 * Cleanup before releasing the atachment metadata on upload.
	 *
	 * @param  integer $attachment_id The attachment ID.
	 * @return array
	 */
	public static function cleanup_before_releasing_the_metadata_on_upload( $attachment_id ) {
		$filter_out = wp_get_attachment_metadata( $attachment_id );
		if ( defined( 'SIRSC_BRUTE_RENAME' ) && SIRSC_BRUTE_RENAME != $filter_out['file'] ) {
			$filter_out['file'] = SIRSC_BRUTE_RENAME;
			if ( ! empty( self::$settings['force_original_to'] )
				&& empty( $filter_out['sizes'][ self::$settings['force_original_to'] ] ) ) {
				$uploads  = wp_get_upload_dir();
				$filetype = wp_check_filetype( trailingslashit( $uploads['basedir'] ) . $filter_out['file'] );

				$filter_out['sizes'][ self::$settings['force_original_to'] ] = array(
					'file'      => wp_basename( $filter_out['file'] ),
					'width'     => $filter_out['width'],
					'height'    => $filter_out['height'],
					'mime-type' => $filetype['type'],
				);
			}
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $filter_out );
		}

		return $filter_out;
	}

	/**
	 * Process a single image size for an attachment.
	 *
	 * @param  integer $id                 The Attachment ID.
	 * @param  string  $size_name          The image size name.
	 * @param  array   $size_info          Maybe a previously computed image size info.
	 * @param  string  $small_crop         Maybe a position for the content crop.
	 * @param  integer $force_quality      Maybe a specified quality loss.
	 * @param  boolean $first_time_replace Maybe it is the first time when the image is processed after upload.
	 * @return mixed
	 */
	public static function process_single_size_from_file( $id, $size_name = '', $size_info = array(), $small_crop = '', $force_quality = 0, $first_time_replace = false ) {
		if ( empty( $size_name ) ) {
			return;
		}

		if ( empty( $small_crop ) && ! empty( self::$settings['default_crop'][ $size_name ] ) ) {
			$small_crop = self::$settings['default_crop'][ $size_name ];
		}

		$from_file = '';
		$metadata  = wp_get_attachment_metadata( $id );
		if ( is_wp_error( $metadata ) ) {
			return;
		}

		$initial_m = $metadata;
		$filename  = get_attached_file( $id );
		$uploads   = wp_get_upload_dir();
		if ( ! empty( $filename ) ) {
			$file_full = $filename;
			$from_file = $file_full;
		}
		if ( self::$wp_ver >= 5.3 ) {
			if ( ! empty( $metadata['original_image'] ) && ! empty( $metadata['file'] ) ) {
				$file_orig = path_join( trailingslashit( $uploads['basedir'] ) . dirname( $metadata['file'] ), $metadata['original_image'] );
				$from_file = $file_orig;
			}
		}

		if ( true === $first_time_replace ) {
			// Do the switch.
			self::debug( 'REPLACE ORIGINAL', true, true );
			if ( ! empty( self::$settings['force_original_to'] ) && $size_name === self::$settings['force_original_to'] ) {
				$maybe_new_meta = self::swap_full_with_another_size( $id, $from_file, $size_name, $small_crop, $force_quality );
				if ( ! empty( $maybe_new_meta ) ) {
					return $maybe_new_meta;
				}
			}
		}

		self::debug( 'PROCESSING SINGLE ' . $id . '|WP' . self::$wp_ver . '|' . $size_name . '|' . $from_file, true, true );
		if ( ! empty( $from_file ) ) {
			if ( empty( $size_info ) ) {
				self::load_settings_for_post_id( $id );
				$size_info = self::get_all_image_sizes_plugin( $size_name );
			}
			if ( ! empty( $size_info ) ) {
				$assess = self::assess_original_vs_target( $metadata, $size_info, $size_name );
				self::debug( 'Assess ' . print_r( $assess, 1 ), true, true );
				if ( ! $assess ) {
					if ( empty( self::$settings['enable_perfect'] ) ) {
						// Fail-fast, the original is too small.
						self::debug( 'ERROR TOO SMALL', true, true );
						return 'error-too-small';
					}
				}

				$allow_upscale = ( ! empty( self::$settings['enable_perfect'] ) && ! empty( self::$settings['enable_upscale'] ) ) ? true : false;

				$execute = self::check_if_execute_size( $metadata, $size_name, $size_info, $from_file, true );
				if ( ! empty( $execute ) || $allow_upscale ) {
					self::debug( 'ALLOW EXECUTION, CONTINUE', true, true );
					$saved = self::image_editor( $id, $from_file, $size_name, $size_info, $small_crop, $force_quality );
					if ( ! empty( $saved ) ) {
						if ( is_wp_error( $metadata ) ) {
							self::debug( 'DO NOT UPDATE METADATA', true, true );
							return;
						}
						$is_reused = ( ! empty( $saved['reused'] ) ) ? true : false;
						self::debug( 'EDITOR PROCESSED IMAGE', true, true );
						if ( empty( $metadata['sizes'] ) ) {
							$metadata['sizes'] = array();
						}
						unset( $saved['path'] );
						unset( $saved['reused'] );
						$metadata['sizes'][ $size_name ] = $saved;
						wp_update_attachment_metadata( $id, $metadata );
						$initial_m = $metadata;

						if ( ! $is_reused ) {
							do_action( 'sirsc_image_processed', $id, $size_name );
						}
					}
				} else {
					self::debug( 'DO NOT EXECUTE', true, true );
				}
			}
		}

		if ( $initial_m != $metadata ) {
			// If something changed, then save the metadata.
			wp_update_attachment_metadata( $id, $metadata );
		}
	}

	/**
	 * Create the image for a specified attachment and image size if that does not exist and update the image metadata. This is useful for example in the cases when the server configuration does not permit to generate many images from a single uploaded image (timeouts or image sizes defined after images have been uploaded already). This should be called before the actual call of wp_get_attachment_image_src with a specified image size
	 *
	 * @param integer $id            Id of the attachment.
	 * @param array   $selected_size The set of defined image sizes used by the site.
	 * @param array   $small_crop    The position of a potential crop (lt = left/top, lc = left/center, etc.).
	 * @param integer $force_quality Maybe force a specific custom quality, not the default.
	 */
	public static function make_images_if_not_exists( $id, $selected_size = 'all', $small_crop = '', $force_quality = 0 ) {
		try {
			self::notify_doing_sirsc();
			self::debug( 'MAKE IMAGE ' . $id . '|' . $selected_size . '|' . $small_crop . '|' . $force_quality, true, true );
			if ( 'all' === $selected_size ) {
				$allowed_sizes = self::get_all_image_sizes_plugin( '', true );
				if ( ! empty( $allowed_sizes ) ) {
					foreach ( $allowed_sizes as $size_name => $size_info ) {
						self::process_single_size_from_file( $id, $size_name, $size_info, $small_crop, $force_quality );
					}
				}
			} else {
				$res = self::process_single_size_from_file( $id, $selected_size, array(), $small_crop, $force_quality );
				if ( 'error-too-small' === $res ) {
					return $res;
				}
			}
		} catch ( ErrorException $e ) {
			error_log( 'sirsc exception ' . print_r( $e, 1 ) );
		}
	}

	/**
	 * Attempts to create metadata from file if that exists for an id.
	 *
	 * @param integer $id       Attachment post id.
	 * @param string  $filename Maybe a filename.
	 * @return array
	 */
	public static function attempt_to_create_metadata( $id, $filename = '' ) {
		self::notify_doing_sirsc();
		if ( empty( $filename ) ) {
			$fname = get_attached_file( $id );
		} else {
			$fname = $filename;
		}
		$image_meta = array();
		if ( ! empty( $fname ) && file_exists( $fname ) ) {
			$image_size = @getimagesize( $fname );
			$image_meta = array(
				'width'          => ! empty( $image_size[0] ) ? (int) $image_size[0] : 0,
				'height'         => ! empty( $image_size[1] ) ? (int) $image_size[1] : 0,
				'file'           => _wp_relative_upload_path( $fname ),
				'path'           => _wp_relative_upload_path( $fname ),
				'sizes'          => array(),
				'original_image' => wp_basename( $fname ),
			);

			$exif_meta = wp_read_image_metadata( $fname );
			if ( $exif_meta ) {
				$image_meta['image_meta'] = $exif_meta;
			}

			wp_update_attachment_metadata( $id, $image_meta );
		}
		return $image_meta;
	}

	/**
	 * Returns a text description of an image size details.
	 *
	 * @param array $v Image size details.
	 */
	public static function size_to_text( $v ) {
		if ( 0 === (int) $v['height'] ) {
			$size_text = '<b>' . esc_html__( 'scale', 'sirsc' ) . '</b> ' . esc_html__( 'to max width of', 'sirsc' ) . ' <b>' . $v['width'] . '</b>px';
		} elseif ( 0 === (int) $v['width'] ) {
			$size_text = '<b>' . esc_html__( 'scale', 'sirsc' ) . '</b> ' . esc_html__( 'to max height of', 'sirsc' ) . ' <b>' . $v['height'] . '</b>px';
		} else {
			if ( ! empty( $v['crop'] ) ) {
				$size_text = '<b>' . esc_html__( 'crop', 'sirsc' ) . '</b> ' . esc_html__( 'of', 'sirsc' ) . ' <b>' . $v['width'] . '</b>x<b>' . $v['height'] . '</b>px';
			} else {
				$size_text = '<b>' . esc_html__( 'scale', 'sirsc' ) . '</b> ' . esc_html__( 'to max width of', 'sirsc' ) . ' <b>' . $v['width'] . '</b>px ' . esc_html__( 'or to max height of', 'sirsc' ) . ' <b>' . $v['height'] . '</b>px';
			}
		}
		return $size_text;
	}

	/**
	 * Returns an array of all the image sizes registered in the application.
	 *
	 * @param string $size Image size slug.
	 */
	public static function get_all_image_sizes( $size = '' ) {
		global $_wp_additional_image_sizes;
		$sizes = array();
		$get_intermediate_image_sizes = get_intermediate_image_sizes();
		// Create the full array with sizes and crop info.
		foreach ( $get_intermediate_image_sizes as $_size ) {
			if ( in_array( $_size, self::$wp_native_sizes, true ) ) {
				$sizes[ $_size ]['width'] = get_option( $_size . '_size_w' );
				$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
				$sizes[ $_size ]['crop'] = (bool) get_option( $_size . '_crop' );
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = array(
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
				);
			}
		}

		if ( ! empty( $sizes ) ) {
			$all = array();
			foreach ( $sizes as $name => $details ) {
				if ( ! empty( $name ) ) {
					$all[ $name ] = $details;
				}
			}
			$sizes = $all;
		}

		if ( ! empty( $size ) && is_scalar( $size ) ) { // Get only 1 size if found.
			if ( ! empty( $sizes ) && isset( $sizes[ $size ] ) ) {
				return $sizes[ $size ];
			} else {
				return false;
			}
		}

		return $sizes;
	}

	/**
	 * Returns an array of all the image sizes registered in the application filtered by the plugin settings and for a specified image size name.
	 *
	 * @param string  $size   Image size slug.
	 * @param boolean $strict True if needs to return only the strict available from settings.
	 * @return  array|boolean
	 */
	public static function get_all_image_sizes_plugin( $size = '', $strict = false ) {
		$sizes = self::get_all_image_sizes( $size );
		if ( ! empty( self::$settings['exclude'] ) ) {
			$new_sizes = array();
			foreach ( $sizes as $k => $si ) {
				if ( ! in_array( $k, self::$settings['exclude'] ) ) {
					$new_sizes[ $k ] = $si;
				}
			}
			$sizes = $new_sizes;
		}
		if ( true === $strict ) {
			if ( ! empty( self::$settings['complete_global_ignore'] ) ) {
				foreach ( self::$settings['complete_global_ignore'] as $ignored ) {
					unset( $sizes[ $ignored ] );
				}
			}
			if ( ! empty( self::$settings['restrict_sizes_to_these_only'] ) ) {
				foreach ( $sizes as $s => $v ) {
					if ( ! in_array( $s, self::$settings['restrict_sizes_to_these_only'] ) ) {
						unset( $sizes[ $s ] );
					}
				}
			}
		}

		if ( $size ) { // Get only 1 size if found.
			if ( isset( $sizes[ $size ] ) ) {
				// Pick it from the list.
				return $sizes[ $size ];
			} elseif ( isset( $sizes['width'] ) && isset( $sizes['height'] ) && isset( $sizes['crop'] ) ) {
				// This must be the requested size.
				return $sizes;
			} else {
				return false;
			}
		}

		return $sizes;
	}

	/**
	 * Returns an array of all the post types allowed in the plugin filters.
	 */
	public static function get_all_post_types_plugin() {
		$post_types = get_post_types( array(), 'objects' );
		if ( ! empty( $post_types ) && ! empty( self::$exclude_post_type ) ) {
			foreach ( self::$exclude_post_type as $k ) {
				unset( $post_types[ $k ] );
			}
		}
		return $post_types;
	}

	/**
	 * Returns the number if images of "image size name" that can be clean up for a specified post type if is set, or the global number of images that can be clean up for the "image size name".
	 *
	 * @param string  $post_type       The post type.
	 * @param string  $image_size_name The size slug.
	 * @param integer $next_post_id    The next post to be processed.
	 */
	public static function calculate_total_to_cleanup( $post_type = '', $image_size_name = '', $next_post_id = 0 ) {
		global $wpdb;
		$total_to_delete = 0;
		if ( ! empty( $image_size_name ) ) {
			$cond_join = '';
			$cond_where = '';
			if ( ! empty( $post_type ) ) {
				$cond_join = ' LEFT JOIN ' . $wpdb->posts . ' as parent ON( parent.ID = p.post_parent )';
				$cond_where = $wpdb->prepare( ' AND parent.post_type = %s ', $post_type );
			}
			if ( ! empty( self::$settings['regenerate_only_featured'] ) ) {
				$cond_join .= ' INNER JOIN ' . $wpdb->postmeta . ' as pm2 ON (pm2.meta_value = p.ID and pm2.meta_key = \'_thumbnail_id\' ) ';
			}
			$tmp_query = $wpdb->prepare( ' SELECT count( distinct p.ID ) as total_to_delete FROM ' . $wpdb->posts . ' as p LEFT JOIN ' . $wpdb->postmeta . ' as pm ON(pm.post_id = p.ID) ' . $cond_join . ' WHERE pm.meta_key like %s AND pm.meta_value like %s AND p.ID > %d ' . $cond_where, // phpcs:ignore
				'_wp_attachment_metadata',
				'%' . $image_size_name . '%',
				intval( $next_post_id )
			); // WPCS: Unprepared SQL OK.
			$rows = $wpdb->get_results( $tmp_query, ARRAY_A ); // phpcs:ignore
			if ( ! empty( $rows ) && is_array( $rows ) ) {
				$total_to_delete = $rows[0]['total_to_delete'];
			}
		}
		return $total_to_delete;
	}

	/**
	 * Remove the images from the folders and database records for the specified image size name.
	 */
	public static function sirsc_ajax_raw_cleanup_image_sizes_on_request() {
		$sirsc_data = self::has_sirsc_data();
		if ( ! empty( $sirsc_data ) ) {
			self::notify_doing_sirsc();
			$post_data = self::parse_ajax_data( $sirsc_data );

			$_sisrsc_image_size_name = ( ! empty( $post_data['_sisrsc_image_size_name'] ) ) ? $post_data['_sisrsc_image_size_name'] : 'sirscregsizes';
			$_sisrsc_post_type       = ( ! empty( $post_data['_sisrsc_post_type'] ) ) ? $post_data['_sisrsc_post_type'] : '';

			global $wpdb;
			$next_post_id        = ( ! empty( $post_data['_sisrsc_image_size_name_page'] ) ) ? $post_data['_sisrsc_image_size_name_page'] : 0;
			$max_in_one_go       = ceil( self::BULK_CLEANUP_ITEMS / 2 );
			$total_to_delete     = self::calculate_total_to_cleanup( $_sisrsc_post_type, 'file', $next_post_id );
			$remaining_to_delete = $total_to_delete;

			if ( $total_to_delete > 0 ) {
				$cond_join  = '';
				$cond_where = '';
				if ( ! empty( $_sisrsc_post_type ) ) {
					$cond_join  = ' LEFT JOIN ' . $wpdb->posts . ' as parent ON( parent.ID = p.post_parent ) ';
					$cond_where = $wpdb->prepare( ' AND parent.post_type = %s ', $_sisrsc_post_type );
				}
				if ( ! empty( self::$settings['regenerate_only_featured'] ) ) {
					$cond_join .= ' INNER JOIN ' . $wpdb->postmeta . ' as pm2 ON (pm2.meta_value = p.ID and pm2.meta_key = \'_thumbnail_id\' ) ';
				}
				echo '
				<div class="sirsc_under-image-options"></div>
				<div class="sirsc_image-size-selection-box">
					<div class="sirsc_options-title">
						<div class="sirsc_options-close-button-wrap"><a class="sirsc_options-close-button" onclick="sirsc_finish_raw_cleanup(\'' . esc_attr( $_sisrsc_image_size_name ) . '\');"><span class="dashicons dashicons-dismiss"></span></a></div>
						<h2>' . esc_html__( 'REMAINING TO RAW CLEAN UP', 'sirsc' ) . ': ' . $total_to_delete . '</h2>
					</div>
					<div class="inside"><div class="custom-execution-wrap">'; // WPCS: XSS OK.
				$rows = $wpdb->get_results( $wpdb->prepare( ' SELECT distinct p.ID FROM ' . $wpdb->posts . ' as p LEFT JOIN ' . $wpdb->postmeta . ' as pm ON(pm.post_id = p.ID) ' . $cond_join . ' WHERE pm.meta_key like %s AND pm.meta_value like %s AND p.ID > %d ' . $cond_where . ' ORDER BY p.ID ASC, pm.meta_id ASC LIMIT 0, %d ', // phpcs:ignore
					'_wp_attachment_metadata',
					'%sizes%',
					(int) $next_post_id,
					(int) $max_in_one_go
				), ARRAY_A ); // phpcs:ignore
				if ( ! empty( $rows ) && is_array( $rows ) ) {
					$upls = wp_upload_dir();
					echo '<b class="spinner inline"></b><div>';
					$reg  = get_intermediate_image_sizes();
					$upls = wp_upload_dir();
					$pref = trailingslashit( $upls['basedir'] );
					foreach ( $rows as $v ) {
						$compute      = self::compute_image_paths( $v['ID'], '', $upls );
						$image_meta   = ( ! empty( $compute['metadata'] ) ) ? $compute['metadata'] : wp_get_attachment_metadata( $v['ID'] );
						$initial_meta = $image_meta;
						$summary      = self::general_sizes_and_files_match( $v['ID'], $image_meta, $compute );
						if ( ! empty( $summary ) ) {
							foreach ( $summary as $sfn => $info ) {
								$to_delete = false;
								if ( ! empty( $info['match'] )
									&& ( in_array( 'full', $info['match'] ) || in_array( 'original', $info['match'] ) ) ) {
									// Not removable.
									self::collect_regenerate_results( $v['ID'], '', 'success', 'cleanup' );
									self::output_bulk_message_cleanup_skip_original( $sfn, $upls );
								} else {
									if ( 'sirscregsizes' === $_sisrsc_image_size_name ) {
										if ( empty( $info['registered'] ) ) {
											// This is not a registered size file.
											$to_delete = true;
										} else {
											// Not removable.
											self::collect_regenerate_results( $v['ID'], '', 'success', 'cleanup' );
											self::output_bulk_message_cleanup_skip_registered( $sfn, $upls );
										}
									} elseif ( 'sirscallsizes' === $_sisrsc_image_size_name ) {
										$to_delete = true;
									}
								}

								if ( true === $to_delete ) {
									$removable = $pref . $sfn;
									if ( file_exists( $removable ) ) {
										@unlink( $removable );
										if ( ! file_exists( $removable ) ) {
											if ( ! empty( $info['match'] ) ) {
												foreach ( $info['match'] as $sn ) {
													if ( isset( $image_meta['sizes'][ $sn ] ) ) {
														unset( $image_meta['sizes'][ $sn ] );
													}
												}
											}

											self::collect_regenerate_results( $v['ID'], '', 'success', 'cleanup' );
											self::output_bulk_message_cleanup_success( $sfn, $upls );

											// Notify other scripts that the file was deleted.
											do_action( 'sirsc_image_file_deleted', $v['ID'], $removable );
										} else {
											if ( empty( $info['registered'] ) ) {
												self::collect_regenerate_results( $v['ID'], '<em>' . $sfn . '</em> - ' . esc_html__( 'could not be deleted.', 'sirsc' ), 'error', 'cleanup' );
												self::output_bulk_message_cleanup_fail( $sfn, $upls );
											} else {
												self::collect_regenerate_results( $v['ID'], '', 'success', 'cleanup' );
											}
										}
									}
								}
							}
						} else {
							self::output_bulk_message_cleanup_not_needed( $v['ID'] );
						}

						if ( $initial_meta != $image_meta ) {
							// Update the cleaned meta.
							wp_update_attachment_metadata( $v['ID'], $image_meta );
						}

						-- $remaining_to_delete;
						$next_post_id = $v['ID'];
					}
					echo '</div></div>';
				}
				echo '
					</div>
				</div>';
			}

			if ( $remaining_to_delete > 0 ) {
				echo '<script>jQuery(document).ready(function () {
					sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
					setTimeout(function() {
						sirsc_continue_raw_cleanup(\'' . esc_attr( $_sisrsc_image_size_name ) . '\', \'' . intval( $next_post_id ) . '\');
					}, ' . (int) self::BULK_PROCESS_DELAY . ');
				});</script>';
			} else {
				$errros = self::assess_collected_errors();
				if ( ! empty( $errros ) ) {
					echo '<input type="hidden" id="sirsc-result-log" value="' . esc_attr( $errros ) . '">
					<script>jQuery(document).ready(function () {
						sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
						setTimeout(function() {
							sirsc_finish_raw_cleanup_log(\'' . esc_attr( $_sisrsc_image_size_name ) . '\');
						}, ' . self::BULK_PROCESS_DELAY . ');
					});</script>'; // WPCS: XSS OK.
					delete_option( 'sirsc_monitor_errors' );
				} else {
					echo '
					<script>jQuery(document).ready(function () {
						sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
						setTimeout(function() {
							sirsc_finish_raw_cleanup(\'' . esc_attr( $_sisrsc_image_size_name ) . '\');
						}, ' . (int) self::BULK_PROCESS_DELAY . ');
					});</script>';
					echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Done!', 'sirsc' ) . '</span>';
				}
			}
		} else {
			echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Something went wrong!', 'sirsc' ) . '</span>';
		}
	}

	/**
	 * Remove the images from the folders and database records for the specified image size name.
	 */
	public static function sirsc_ajax_cleanup_image_sizes_on_request() {
		$sirsc_data = self::has_sirsc_data();
		if ( ! empty( $sirsc_data ) ) {
			self::notify_doing_sirsc();
			$post_data = self::parse_ajax_data( $sirsc_data );
			if ( ! empty( $post_data['_sisrsc_image_size_name'] ) ) {
				global $wpdb;
				$_sisrsc_image_size_name = ( ! empty( $post_data['_sisrsc_image_size_name'] ) ) ? $post_data['_sisrsc_image_size_name'] : '';
				$_sisrsc_post_type       = ( ! empty( $post_data['_sisrsc_post_type'] ) ) ? $post_data['_sisrsc_post_type'] : '';
				$next_post_id            = ( ! empty( $post_data['_sisrsc_image_size_name_page'] ) ) ? $post_data['_sisrsc_image_size_name_page'] : 0;
				$max_in_one_go           = self::BULK_CLEANUP_ITEMS;
				$total_to_delete         = self::calculate_total_to_cleanup( $_sisrsc_post_type, $post_data['_sisrsc_image_size_name'], $next_post_id );
				$remaining_to_delete     = $total_to_delete;
				if ( $total_to_delete > 0 ) {
					$cond_join  = '';
					$cond_where = '';
					if ( ! empty( $_sisrsc_post_type ) ) {
						$cond_join  = ' LEFT JOIN ' . $wpdb->posts . ' as parent ON( parent.ID = p.post_parent ) ';
						$cond_where = $wpdb->prepare( ' AND parent.post_type = %s ', $_sisrsc_post_type );
					}
					echo '
					<div class="sirsc_under-image-options"></div>
					<div class="sirsc_image-size-selection-box">
						<div class="sirsc_options-title">
							<div class="sirsc_options-close-button-wrap"><a class="sirsc_options-close-button" onclick="sirsc_finish_cleanup(\'' . esc_attr( $_sisrsc_image_size_name ) . '\');"><span class="dashicons dashicons-dismiss"></span></a></div>
							<h2>' . esc_html__( 'REMAINING TO CLEAN UP', 'sirsc' ) . ': ' . $total_to_delete . '</h2>
						</div>
						<div class="inside"><div class="custom-execution-wrap">'; // WPCS: XSS OK.
					$rows = $wpdb->get_results(
						$wpdb->prepare(
							' SELECT p.ID FROM ' . $wpdb->posts . ' as p LEFT JOIN ' . $wpdb->postmeta . ' as pm ON(pm.post_id = p.ID) ' . $cond_join . ' WHERE pm.meta_key like %s AND pm.meta_value like %s AND p.ID > %d ' . $cond_where . ' ORDER BY p.ID ASC, pm.meta_id ASC LIMIT 0, %d ',
							'_wp_attachment_metadata',
							'%' . $post_data['_sisrsc_image_size_name'] . '%',
							(int) $next_post_id,
							(int) $max_in_one_go
						),
						ARRAY_A
					); // WPCS: Unprepared SQL OK.
					if ( ! empty( $rows ) && is_array( $rows ) ) {
						$upls = wp_upload_dir();
						echo '<b class="spinner inline"></b><div>';
						foreach ( $rows as $v ) {
							$image    = wp_get_attachment_metadata( $v['ID'] );
							$filename = realpath( get_attached_file( $v['ID'] ) );
							$unset    = false;
							$deleted  = false;
							$file     = '';
							$string   = '';

							if ( ! empty( $filename ) ) {
								$assessed = self::assess_expected_image( $v['ID'], $_sisrsc_image_size_name, $image, $upls );
								if ( empty( $image['sizes'][ $_sisrsc_image_size_name ] ) ) {
									// The meta is not even set.
									self::collect_regenerate_results( $v['ID'], '', 'success', 'cleanup' );
									self::output_bulk_message_cleanup_not_found( $assessed['file'], $upls );
									$unset = true;
								} else {
									if ( empty( $assessed['exists'] ) ) {
										// Not found.
										self::collect_regenerate_results( $v['ID'], '<em>' . $assessed['path'] . '</em> - ' . esc_html__( 'could not be found.', 'sirsc' ), 'error', 'cleanup' );
										self::output_bulk_message_cleanup_not_found( $assessed['file'], $upls );
										$unset = true;
									} else {
										if ( $filename == $assessed['path'] ) {
											// Original.
											self::collect_regenerate_results( $v['ID'], '<em>' . $assessed['path'] . '</em> - ' . esc_html__( 'could not be deleted (it is the original file).', 'sirsc' ), 'error', 'cleanup' );
											self::output_bulk_message_cleanup_skip_original( $assessed['file'], $upls );
										} else {
											if ( file_exists( $assessed['path'] ) && ! is_dir( $assessed['path'] ) ) {
												// Make sure not to delete the original file.
												self::collect_regenerate_results( $v['ID'], '<em>' . $assessed['path'] . '</em> - ' . esc_html__( 'has been deleted.', 'sirsc' ), 'success', 'cleanup' );
												self::output_bulk_message_cleanup_success( $assessed['file'], $upls );

												@unlink( $assessed['path'] );

												// Notify other scripts that the file was deleted.
												do_action( 'sirsc_image_file_deleted', $v['ID'], $assessed['path'] );

												$unset   = true;
												$deleted = true;
											}
										}
									}
								}
							} else {
								// Not found.
								self::collect_regenerate_results( $v['ID'], '<em>' . $filename . '</em> - ' . esc_html__( 'could not be found.', 'sirsc' ), 'error', 'cleanup' );
								self::output_bulk_message_cleanup_not_found( $filename, $upls );
							}

							if ( $unset || $deleted ) {
								unset( $image['sizes'][ $_sisrsc_image_size_name ] );
								wp_update_attachment_metadata( $v['ID'], $image );
							}

							-- $remaining_to_delete;
							$next_post_id = $v['ID'];
						}
						echo '</div></div>';
					}
					echo '
						</div>
					</div>';
				}
				if ( $remaining_to_delete > 0 ) {
					echo '<script>jQuery(document).ready(function () {
						sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
						setTimeout(function() {
							sirsc_continue_cleanup(\'' . esc_attr( $_sisrsc_image_size_name ) . '\', \'' . intval( $next_post_id ) . '\');
						}, ' . (int) self::BULK_PROCESS_DELAY . ');
					});</script>';
				} else {
					$errros = self::assess_collected_errors();
					if ( ! empty( $errros ) ) {
						echo '<input type="hidden" id="sirsc-result-log" value="' . esc_attr( $errros ) . '">
						<script>jQuery(document).ready(function () {
							sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
							setTimeout(function() {
								sirsc_finish_cleanup_log(\'' . esc_attr( $_sisrsc_image_size_name ) . '\');
							}, ' . self::BULK_PROCESS_DELAY . ');
						});</script>'; // WPCS: XSS OK.
						delete_option( 'sirsc_monitor_errors' );
					} else {
						echo '
						<script>jQuery(document).ready(function () {
							sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
							setTimeout(function() {
								sirsc_finish_cleanup(\'' . esc_attr( $_sisrsc_image_size_name ) . '\');
							}, ' . (int) self::BULK_PROCESS_DELAY . ');
						});</script>';
					}
					echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Done!', 'sirsc' ) . '</span>';
				}
			} else {
				echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Something went wrong!', 'sirsc' ) . '</span>';
			}
		}
	}

	/**
	 * Regenerate all the images for the specified image size name.
	 */
	public static function sirsc_ajax_regenerate_image_sizes_on_request() {
		$sirsc_data = self::has_sirsc_data();
		if ( ! empty( $sirsc_data ) ) {
			self::notify_doing_sirsc();
			$post_data = self::parse_ajax_data( $sirsc_data );
			if ( ! empty( $post_data['_sisrsc_regenerate_image_size_name'] ) ) {
				global $wpdb;
				$_sisrsc_post_type = ( ! empty( $post_data['_sisrsc_post_type'] ) ) ? $post_data['_sisrsc_post_type'] : '';
				$cond_join         = '';
				$cond_where        = '';
				if ( ! empty( $_sisrsc_post_type ) ) {
					$cond_join  = ' LEFT JOIN ' . $wpdb->posts . ' as parent ON( parent.ID = p.post_parent ) ';
					$cond_where = $wpdb->prepare( ' AND parent.post_type = %s ', $_sisrsc_post_type );
				}
				if ( ! empty( self::$settings['regenerate_only_featured'] ) ) {
					$cond_join .= ' INNER JOIN ' . $wpdb->postmeta . ' as pm ON (pm.meta_value = p.ID and pm.meta_key = \'_thumbnail_id\' ) ';
				}

				$next_post_id = ( ! empty( $post_data['_sisrsc_regenerate_image_size_name_page'] ) ) ? $post_data['_sisrsc_regenerate_image_size_name_page'] : 0;
				if ( ! empty( $post_data['resume_from'] ) ) {
					$next_post_id = (int) $post_data['resume_from'] - 1;
				}

				$total_to_update = 0;
				$image_size_name = $post_data['_sisrsc_regenerate_image_size_name'];
				$use_condition   = ! empty( $post_data['_sisrsc_regenerate_image_size_name_page'] ) ? true : false;

				$rows = $wpdb->get_results( $wpdb->prepare( ' SELECT count(distinct p.ID) as total_to_update FROM ' . $wpdb->posts . ' as p ' . $cond_join . ' WHERE p.ID > %d AND ( p.post_mime_type like %s OR p.post_mime_type like %s OR p.post_mime_type like %s )' . $cond_where . ' ORDER BY p.ID ASC ', (int) $next_post_id, 'image/gif', 'image/jpeg', 'image/png' ), ARRAY_A ); // WPCS: Unprepared SQL OK.
				if ( ! empty( $rows ) && is_array( $rows ) ) {
					$total_to_update = $rows[0]['total_to_update'];
				}
				if ( $total_to_update > 0 ) {
					echo '
					<div class="sirsc_under-image-options"></div>
					<div class="sirsc_image-size-selection-box">
						<div class="sirsc_options-title">
							<div class="sirsc_options-close-button-wrap"><a class="sirsc_options-close-button" onclick="sirsc_finish_regenerate(\'' . esc_attr( $image_size_name ) . '\');"><span class="dashicons dashicons-dismiss"></span></a></div>
							<h2>' . esc_html__( 'REMAINING TO REGENERATE', 'sirsc' ) . ': ' . (int) $total_to_update . '</h2>
						</div>
						<div class="inside"><div class="custom-execution-wrap">
							<input type="hidden" id="sirsc-fallback-action" value="regenerate">
							<input type="hidden" id="sirsc-fallback-id" value="' . ( intval( $next_post_id ) + 1 ) . '">
							<input type="hidden" id="sirsc-fallback-size" value="' . esc_attr( $image_size_name ) . '">
							<center>';

					$upls = wp_upload_dir();
					$rows = $wpdb->get_results( $wpdb->prepare( ' SELECT distinct p.ID FROM ' . $wpdb->posts . ' as p ' . $cond_join . ' WHERE p.ID > %d AND ( p.post_mime_type like %s OR p.post_mime_type like %s OR p.post_mime_type like %s )' . $cond_where . ' ORDER BY p.ID ASC LIMIT 0, 1', (int) $next_post_id, 'image/gif', 'image/jpeg', 'image/png' ), ARRAY_A ); // WPCS: Unprepared SQL OK.
					if ( ! empty( $rows ) && is_array( $rows ) ) {
						foreach ( $rows as $v ) {
							$filename = get_attached_file( $v['ID'] );
							$outputfn = str_replace( trailingslashit( $upls['basedir'] ), '', $filename );
							$outputfn = str_replace( trailingslashit( $upls['baseurl'] ), '', $outputfn );

							$image         = wp_get_attachment_metadata( $v['ID'] );
							$assessed      = self::assess_expected_image( $v['ID'], $image_size_name, $image, $upls );
							$outputfn      = ( ! empty( $assessed['path'] ) ) ? $assessed['path'] : $outputfn;
							$expected_name = ( ! empty( $assessed['file'] ) ) ? $assessed['file'] : '';
							if ( empty( $expected_name ) ) {
								$expected_name = str_replace( trailingslashit( $upls['basedir'] ), '', $filename );
							}

							echo '<input type="hidden" id="sirsc-fallback-filename" value="' . esc_attr( $outputfn ) . '">';
							self::collect_regenerate_results( $v['ID'], $outputfn );

							if ( ! empty( $filename ) && file_exists( $filename ) ) {
								$skip_regenerate = false;
								if ( ! empty( self::$settings['regenerate_missing'] ) ) {
									if ( ! empty( $image['sizes'][ $image_size_name ]['file'] ) && file_exists( trailingslashit( dirname( $filename ) ) . $image['sizes'][ $image_size_name ]['file'] ) ) {
										$skip_regenerate = true;
										$expected_name = trailingslashit( dirname( $filename ) ) . $image['sizes'][ $image_size_name ]['file'];
										$expected_name = str_replace( trailingslashit( $upls['basedir'] ), '', $expected_name );
									} else {
										if ( true == $assessed['exists'] ) {
											$skip_regenerate = true;
											$expected_name   = $assessed['file'];
											if ( ! empty( $assessed['meta'] && $assessed['file'] != $outputfn ) ) {
												$image['sizes'][ $image_size_name ] = $assessed['meta'];
												update_post_meta( $v['ID'], '_wp_attachment_metadata', $image );
											}
										}
									}
								}

								if ( true === $skip_regenerate ) {
									self::collect_regenerate_results( $v['ID'], '', 'success' );
									self::output_bulk_message_regenerate_skip( $expected_name, $upls );
								} else {
									$resp = self::make_images_if_not_exists( $v['ID'], $image_size_name );

									if ( 'error-too-small' === $resp ) {
										self::collect_regenerate_results( $v['ID'], '<em>' . $outputfn . '</em> - ' . esc_html__( 'Could not generate, the original is too small.', 'sirsc' ), 'error' );
										self::output_bulk_message_regenerate_original_small( $expected_name, $upls );
									} else {
										$image = wp_get_attachment_metadata( $v['ID'] );
										$th = wp_get_attachment_image_src( $v['ID'], $image_size_name );
										if ( ! empty( $th[0] ) ) {
											$th_src = $th[0];
											self::collect_regenerate_results( $v['ID'], '', 'success' );
											self::output_bulk_message_regenerate_success( $th_src, $upls );
										} else {
											self::collect_regenerate_results( $v['ID'], '<em>' . $outputfn . '</em> - ' . esc_html__( 'Could not generate, the original is too small.', 'sirsc' ), 'error' );
											self::output_bulk_message_regenerate_original_small( $expected_name, $upls );
										}
									}

									// Notifiy other scripts that the image was regenerated.
									$image = wp_get_attachment_metadata( $v['ID'] );
									do_action( 'sirsc_image_processed', $v['ID'], $image_size_name );
									do_action( 'sirsc_attachment_images_processed', $image, $v['ID'] );
								}
							} else {
								self::collect_regenerate_results( $v['ID'], '<em>' . $outputfn . '</em> - ' . esc_html__( 'Could not generate, the original is missing.', 'sirsc' ), 'error' );
								self::output_bulk_message_regenerate_original_missing( $expected_name, $upls );
							}

							$next_post_id = $v['ID'];
							self::set_regenerate_last_processed_id( $image_size_name, $v['ID'] );
						}
					}
					echo '</center></div></div></div>';

				}
				$next_post_id = (int) $next_post_id;
				$remaining_to_update = $total_to_update - 1;
				if ( $remaining_to_update >= 0 ) {
					$script = '<script>jQuery(document).ready(function () {
						sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
						jQuery(\'[id="_sirsc_initiate_regenerate_resume_' . esc_attr( $image_size_name ) . '"]\').removeClass(\'is-hidden\');
						jQuery(\'[id="_sirsc_initiate_regenerate_resume_' . esc_attr( $image_size_name ) . '_id"]\').val(\'' . $next_post_id . '\');
						setTimeout(function() {';
					if ( $use_condition ) {
						$script .= '
						if ( \'0\' != jQuery(\'#_sisrsc_regenerate_image_size_name_page' . esc_attr( $image_size_name ) . '\').val() ) {
							sirsc_continue_regenerate(\'' . esc_attr( $image_size_name ) . '\', \'' . $next_post_id . '\');
						}';
					} else {
						$script .= 'sirsc_continue_regenerate(\'' . esc_attr( $image_size_name ) . '\', \'' . $next_post_id . '\'); ';
					}
					$script .= '}, ' . self::BULK_PROCESS_DELAY . ');
					});</script>';
					echo $script; // WPCS: XSS OK.
				} else {
					$errros = self::assess_collected_errors();
					self::remove_regenerate_last_processed_id( $image_size_name );
					if ( ! empty( $errros ) ) {
						echo '<input type="hidden" id="sirsc-result-log" value="' . esc_attr( $errros ) . '">
						<script>jQuery(document).ready(function () {
							sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
							setTimeout(function() {
								sirsc_finish_regenerate_log(\'' . esc_attr( $image_size_name ) . '\');
							}, ' . self::BULK_PROCESS_DELAY . ');
						});</script>'; // WPCS: XSS OK.
						delete_option( 'sirsc_monitor_errors' );
					} else {
						echo '<script>jQuery(document).ready(function () {
							sirsc_arrange_center_element(\'.sirsc_image-size-selection-box\');
							setTimeout(function() {
								sirsc_finish_regenerate(\'' . esc_attr( $image_size_name ) . '\');
							}, ' . self::BULK_PROCESS_DELAY . ');
						});</script>'; // WPCS: XSS OK.
					}
					echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Done!', 'sirsc' ) . '</span>';
				}
			} else {
				echo '<span class="sirsc_successfullysaved">' . esc_html__( 'Something went wrong!', 'sirsc' ) . '</span>';
			}
		}
	}

	/**
	 * Collect regenerate results.
	 *
	 * @param  integer $id        Attachment ID.
	 * @param  string  $message   An intent or error message.
	 * @param  string  $type      The collect type (error|schedule).
	 * @param  string  $initiator The collect initiator.
	 * @return void
	 */
	public static function collect_regenerate_results( $id, $message = '', $type = 'schedule', $initiator = 'regenerate' ) {
		$monitor = get_option( 'sirsc_monitor_errors', array() );
		if ( empty( $monitor['error'] ) ) {
			$monitor['error'] = array();
		}
		if ( empty( $monitor['schedule'] ) ) {
			$monitor['schedule'] = array();
		}

		if ( 'error' === $type ) {
			$monitor['error'][ $id ] = $message;
		} elseif ( 'success' === $type ) {
			if ( isset( $monitor['schedule'][ $id ] ) ) {
				unset( $monitor['schedule'][ $id ] );
			}
		} else {
			$monitor['schedule'][ $id ] = $message;
		}
		$monitor['initiator'] = $initiator;
		update_option( 'sirsc_monitor_errors', $monitor );
	}

	/**
	 * Assess the collected regenerate results and returns the errors if found.
	 *
	 * @return string
	 */
	public static function assess_collected_errors() {
		$message      = '';
		$maybe_errors = get_option( 'sirsc_monitor_errors' );
		if ( ! empty( $maybe_errors['schedule'] ) ) {
			foreach ( $maybe_errors['schedule'] as $id => $filename ) {
				if ( empty( $maybe_errors['error'][ $id ] ) ) {
					$maybe_errors['error'][ $id ] = '<em>' . $filename . '</em> - ' . esc_html__( 'The original filesize is too big and the server does not have enough resources to process it.', 'sirsc' );
				}
			}
		}
		if ( ! empty( $maybe_errors['error'] ) ) {
			if ( ! empty( $maybe_errors['initiator'] ) && 'cleanup' == $maybe_errors['initiator'] ) {
				$message = wp_kses_post(
					sprintf(
						// Translators: %1$s - server side error.
						__( '<b>Unfortunately, there was an error</b>. Some of the execution might not have been successful. This can happen when: <br>&bull; the image you were trying to delete is <b>the original</b> file,<br>&bull; the image size was pointing to the <b>the original</b> and it should not be removed,<br>&bull; the <b>file is missing</b>. <br><br>See the details: %1$s', 'sirsc' ),
						'<div class="sirsc-errors"><div class="file-reswrap error-msg"><b class="dashicons dashicons-dismiss"></b> ' . implode( '</div><div class="file-reswrap error-msg"><b class="dashicons dashicons-dismiss"></b> ', $maybe_errors['error'] ) . '</div></div>'
					)
				);
			} else {
				$message = wp_kses_post(
					sprintf(
						// Translators: %1$s - server side error.
						__( '<b>Unfortunately, there was an error</b>. Some of the execution might not have been successful. This can happen in when: <br>&bull; the image from which the script is generating the specified image size does not have the <b>proper size</b> for resize/crop to a specific width and height,<br>&bull; the attachment <b>metadata is broken</b>,<br>&bull; the original <b>file is missing</b>,<br>&bull; the image that is processed is <b>very big</b> (rezolution or size) and the <b>allocated memory</b> on the server is not enough to handle the request,<br>&bull; the overall processing on your site is <b>too intensive</b>. <br><br>See the details: %1$s', 'sirsc' ),
						'<div class="sirsc-errors"><div class="file-reswrap error-msg"><b class="dashicons dashicons-dismiss"></b> ' . implode( '</div><div class="file-reswrap error-msg"><b class="dashicons dashicons-dismiss"></b> ', $maybe_errors['error'] ) . '</div></div>'
					)
				);
			}

			$upls    = wp_upload_dir();
			$message = str_replace( trailingslashit( $upls['basedir'] ), '', $message );
			$message = str_replace( trailingslashit( $upls['baseurl'] ), '', $message );
		}
		return $message;
	}

	/**
	 * Set regenerate last processed id.
	 *
	 * @param string  $name Image size name.
	 * @param integer $id   Post ID.
	 */
	public static function set_regenerate_last_processed_id( $name = '', $id = 0 ) {
		update_option( 'sirsc_regenerate_most_recent_' . esc_attr( $name ), $id );
	}

	/**
	 * Get regenerate last processed id.
	 *
	 * @param string $name Image size name.
	 */
	public static function get_regenerate_last_processed_id( $name = '' ) {
		return get_option( 'sirsc_regenerate_most_recent_' . esc_attr( $name ), 0 );
	}

	/**
	 * Remove regenerate last processed id.
	 *
	 * @param string $name Image size name.
	 */
	public static function remove_regenerate_last_processed_id( $name = '' ) {
		delete_option( 'sirsc_regenerate_most_recent_' . esc_attr( $name ) );
	}

	/**
	 * Output bulk message cleanup skip original.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_cleanup_skip_original( $name, $upls ) {
		$name = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		echo '<div class="file-reswrap"><b class="dashicons dashicons-warning"></b> <b>' . esc_attr( $name ) . '</b> ' . esc_html__( 'Skipping the cleanup or this file (it is the original file).', 'sirsc' ) . '</div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message cleanup skip registered.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_cleanup_skip_registered( $name, $upls ) {
		$name = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		echo '<div class="file-reswrap"><b class="dashicons dashicons-warning"></b> <b>' . esc_attr( $name ) . '</b> ' . esc_html__( 'Skipping the cleanup or this file (the size is registered).', 'sirsc' ) . '</div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message cleanup success.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_cleanup_success( $name, $upls ) {
		$name = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		echo '<div class="file-reswrap success-msg"><b class="dashicons dashicons-yes-alt"></b> ' . __( 'The image ', 'sirsc' ) . ' <b>' . esc_attr( $name ) . '</b> ' . esc_html__( 'has been deleted.', 'sirsc' ) . '</div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message cleanup fail.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_cleanup_fail( $name, $upls ) {
		$name = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		echo '<div class="file-reswrap error-msg"><b class="dashicons dashicons-dismiss"></b> ' . __( 'The image ', 'sirsc' ) . ' <b>' . esc_attr( $name ) . '</b> ' . esc_html__( 'could not be deleted.', 'sirsc' ) . '</div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message cleanup fail.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_cleanup_not_found( $name, $upls ) {
		$name = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		echo '<div class="file-reswrap error-msg"><b class="dashicons dashicons-dismiss"></b> ' . __( 'The image ', 'sirsc' ) . ' <b>' . esc_attr( $name ) . '</b> ' . esc_html__( 'could not be found.', 'sirsc' ) . '</div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message cleanup not needed.
	 *
	 * @param  string $id Attachment IS.
	 * @return void
	 */
	public static function output_bulk_message_cleanup_not_needed( $id ) {
		echo '<div class="file-reswrap success-msg"><b class="dashicons dashicons-yes-alt"></b> ' . esc_html__( 'No cleanup necessary for', 'sirsc' ) . ' <b>' . $id . '</b></div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message regenerate skip.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_regenerate_skip( $name, $upls ) {
		$name = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		echo '<div class="inline-action-wrap skip">' . esc_html__( 'Skipping the regeneration or this file (as per settings).', 'sirsc' ) . '<b class="spinner inline"></b></div>
			<div class="file-reswrap"><div class="sirsc-regen-url"><b class="dashicons dashicons-warning"></b> ' . $name . '</div></div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message regenerate original too small.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_regenerate_original_small( $name, $upls ) {
		$name = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		echo '<div class="inline-action-wrap">' . esc_html__( 'Could not generate, the original is too small.', 'sirsc' ) . '<b class="spinner inline"></b></div><div class="file-reswrap error-msg"><div class="sirsc-regen-url"><b class="dashicons dashicons-dismiss"></b> ' . $name . '</div></div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message regenerate original is missing.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_regenerate_original_missing( $name, $upls ) {
		$name = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		echo '<div class="inline-action-wrap">' . esc_html__( 'Could not generate, the original file is missing.', 'sirsc' ) . '<b class="spinner inline"></b></div><div class="file-reswrap error-msg"><div class="sirsc-regen-url"><b class="dashicons dashicons-dismiss"></b> ' . $name . '</div></div>'; // WPCS: XSS OK.
	}

	/**
	 * Output bulk message regenerate original too small.
	 *
	 * @param  string $name File name.
	 * @param  array  $upls Upload info array.
	 * @return void
	 */
	public static function output_bulk_message_regenerate_success( $name, $upls ) {
		$fname = str_replace( trailingslashit( $upls['basedir'] ), '', $name );
		$fname = str_replace( trailingslashit( $upls['baseurl'] ), '', $fname );
		echo '<span class="imagewrap"><img src="' . $name . '?cache=' . time() . '" /><b class="spinner inline"></b></span><div class="file-reswrap success-msg"><div class="sirsc-regen-url"><b class="dashicons dashicons-yes-alt"></b> ' . $fname . '</div></div>'; // WPCS: XSS OK.
	}

	/**
	 * Replace all the front side images retrieved programmatically with wp function with the placeholders instead of the full size image.
	 *
	 * @param string  $f  The file.
	 * @param integer $id The post ID.
	 * @param string  $s  The size slug.
	 */
	public static function image_downsize_placeholder_force_global( $f, $id, $s ) {
		if ( is_array( $s ) ) {
			$s = implode( 'x', $s );
		}
		$img_url = self::image_placeholder_for_image_size( $s );
		$size    = self::get_all_image_sizes( $s );
		$width   = ( ! empty( $size['width'] ) ) ? $size['width'] : 0;
		$height  = ( ! empty( $size['height'] ) ) ? $size['height'] : 0;
		return array( $img_url, $width, $height, true );
	}

	/**
	 * Replace the missing images sizes with the placeholders instead of the full size image. As the "image size name" is specified, we know what width and height the resulting image should have. Hence, first, the potential image width and height are matched against the entire set of image sizes defined in order to identify if there is the exact required image either an alternative file with the specific required width and height already generated for that width and height but with another "image size name" in the database or not. Basically, the first step is to identify if there is an image with the required width and height. If that is identified, it will be presented, regardless of the fact that the "image size name" is the requested one or it is not even yet defined for this specific post (due to a later definition of the image in the project development). If the image to be presented is not identified at any level, then the code is trying to identify the appropriate theme placeholder for the requested "image size name". For that we are using the placeholder function with the requested "image size name". If the placeholder exists, then this is going to be presented, else we are logging the missing placeholder alternative that can be added in the image_placeholder_for_image_size function.
	 *
	 * @param string  $f  The file.
	 * @param integer $id The pot ID.
	 * @param string  $s  The size slug.
	 */
	public static function image_downsize_placeholder_only_missing( $f, $id, $s ) {
		$all_sizes = self::get_all_image_sizes();
		if ( 'full' !== $s && is_scalar( $s ) && ! empty( $all_sizes[ $s ] ) ) {
			try {
				$execute    = false;
				$image      = wp_get_attachment_metadata( $id );
				$filename   = get_attached_file( $id );
				$rez_img    = self::allow_resize_from_original( $filename, $image, $all_sizes, $s );
				$upload_dir = wp_upload_dir();
				if ( ! empty( $rez_img['found'] ) ) {
					$url         = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $rez_img['path'] );
					$crop        = ( ! empty( $rez_img['is_crop'] ) ) ? true : false;
					$alternative = array( $url, $rez_img['width'], $rez_img['height'], $crop );
					return $alternative;
				}
				$request_w   = (int) $all_sizes[ $s ]['width'];
				$request_h   = (int) $all_sizes[ $s ]['height'];
				$alternative = array(
					'name'         => $s,
					'file'         => $f,
					'width'        => $request_w,
					'height'       => $request_h,
					'intermediate' => true,
				);
				$found_match = false;
				if ( empty( $image ) ) {
					$image = array();
				}
				$image['width'] = ( ! empty( $image['width'] ) ) ? (int) $image['width'] : 0;
				$image['height'] = ( ! empty( $image['height'] ) ) ? (int) $image['height'] : 0;
				if ( $request_w === (int) $image['width'] && $request_h === (int) $image['height'] && ! empty( $image['file'] ) ) {
					$tmp_file = str_replace( basename( $filename ), basename( $image['file'] ), $filename );
					if ( file_exists( $tmp_file ) ) {
						$folder      = str_replace( $upload_dir['basedir'], '', $filename );
						$old_file    = basename( str_replace( $upload_dir['basedir'], '', $filename ) );
						$folder      = str_replace( $old_file, '', $folder );
						$alternative = array(
							'name'         => 'full',
							'file'         => $upload_dir['baseurl'] . $folder . basename( $image['file'] ),
							'width'        => (int) $image['width'],
							'height'       => (int) $image['height'],
							'intermediate' => false,
						);
						$found_match = true;
					}
				}
				if ( ! empty( $image['sizes'] ) ) {
					foreach ( $image['sizes'] as $name => $var ) {
						if ( $found_match ) {
							break;
						}
						if ( $request_w === (int) $var['width'] && $request_h === (int) $var['height'] && ! empty( $var['file'] ) ) {
							$tmp_file = str_replace( basename( $filename ), $var['file'], $filename );
							if ( file_exists( $tmp_file ) ) {
								$folder      = str_replace( $upload_dir['basedir'], '', $filename );
								$old_file    = basename( str_replace( $upload_dir['basedir'], '', $filename ) );
								$folder      = str_replace( $old_file, '', $folder );
								$alternative = array(
									'name'         => $name,
									'file'         => $upload_dir['baseurl'] . $folder . $var['file'],
									'width'        => (int) $var['width'],
									'height'       => (int) $var['height'],
									'intermediate' => true,
								);
								$found_match = true;
								break;
							}
						}
					}
				}
				if ( ! empty( $alternative ) && $found_match ) {
					$placeholder = array( $alternative['file'], $alternative['width'], $alternative['height'], $alternative['intermediate'] );
					return $placeholder;
				} else {
					$img_url = self::image_placeholder_for_image_size( $s );
					if ( ! empty( $img_url ) ) {
						$width           = (int) $request_w;
						$height          = (int) $request_w;
						$is_intermediate = true;
						$placeholder     = array( $img_url, $width, $height, $is_intermediate );
						return $placeholder;
					} else {
						return;
					}
				}
			} catch ( ErrorException $e ) {
				error_log( 'sirsc exception ' . print_r( $e, 1 ) );
			}
		}
	}

	/**
	 * Maybe identify and alternative to match the image size.
	 *
	 * @param  string|array $maybe_size Maybe an image size name or an image size array.
	 * @return string
	 */
	public static function maybe_match_size_name_by_width_height( $maybe_size ) {
		if ( empty( $maybe_size ) ) {
			// Fail-fast, no name specified.
			return 'full';
		}
		$all_sizes = self::get_all_image_sizes();
		if ( empty( $all_sizes ) ) {
			// Fail-fast, no sizes computed.
			return 'full';
		}
		$w = 0;
		$h = 0;
		if ( is_scalar( $maybe_size ) ) {
			if ( ! empty( $all_sizes[ $maybe_size ] ) ) {
				// Fail-fast, the image size name exists.
				return $maybe_size;
			} else {
				// Check if there is any widtd and height available.
				$x = explode( 'x', $maybe_size );
				if ( ! empty( $x[0] ) ) {
					$w = (int) $x[0];
				}
				if ( ! empty( $x[1] ) ) {
					$h = (int) $x[1];
				}
			}
		} else {
			if ( ! empty( $maybe_size[0] ) ) {
				$w = (int) $maybe_size[0];
			}
			if ( ! empty( $maybe_size[1] ) ) {
				$h = (int) $maybe_size[1];
			}
		}
		if ( empty( $w ) && empty( $h ) ) {
			// Fail-fast, no width and no height to work with.
			return 'full';
		}

		foreach ( $all_sizes as $key => $value ) {
			if ( $value['width'] == $w && $value['height'] == $h ) {
				// Perfect match.
				return $key;
			}
		}

		foreach ( $all_sizes as $key => $value ) {
			if ( $value['width'] == $w ) {
				// Partial match.
				return $key;
			} elseif ( $value['height'] == $h ) {
				// Partial match.
				return $key;
			}
		}

		// Fallback to full size.
		return 'full';
	}

	/**
	 * Generate a placeholder image for a specified image size name.
	 *
	 * @param string  $selected_size The selected image size slug.
	 * @param boolean $force_update  True is the update is forced, to clear the cache.
	 */
	public static function image_placeholder_for_image_size( $selected_size, $force_update = false ) {
		if ( ! class_exists( 'SIRSC_Image_Placeholder' ) ) {
			require_once dirname( __FILE__ ) . '/sirsc-placeholder.php';
		}

		if ( empty( $selected_size ) ) {
			$selected_size = 'full';
		}

		$alternative = self::maybe_match_size_name_by_width_height( $selected_size );
		if ( ! is_scalar( $selected_size ) ) {
			if ( ! empty( $alternative ) ) {
				$selected_size = $alternative;
			} else {
				$selected_size = implode( 'x', $selected_size );
			}
		}

		$dest     = realpath( SIRSC_PLACEHOLDER_FOLDER ) . '/' . $selected_size . '.png';
		$dest_url = esc_url( SIRSC_PLACEHOLDER_URL . '/' . $selected_size . '.png' );
		if ( file_exists( $dest ) && ! $force_update ) {
			// Return the found image url.
			return $dest_url;
		}

		$alls     = self::get_all_image_sizes_plugin();
		$dest_url = SIRSC_Image_Placeholder::compute_placeholder_url( $alls, $dest, $dest_url, $selected_size, $alternative );
		return $dest_url;
	}

	/**
	 * Registers the Gutenberg custom block assets.
	 */
	public static function sirsc_block_init() {
		if ( ! function_exists( 'register_block_type' ) ) {
			// Gutenberg is not active.
			return;
		}

		$dir      = dirname( __FILE__ );
		$block_js = 'sirsc-block/block.js';
		wp_register_script(
			'sirsc-block-editor',
			plugins_url( $block_js, __FILE__ ),
			array(
				'wp-blocks',
				'wp-editor',
				'wp-i18n',
				'wp-element',
			),
			filemtime( $dir . '/' . $block_js )
		);

		register_block_type(
			'image-regenerate-select-crop/sirsc-block',
			array(
				'editor_script' => 'sirsc-block-editor',
			)
		);
	}

	/**
	 * Register custom settings for overriding the medium image.
	 */
	public static function media_settings_override() {
		// Add the custom section to media.
		add_settings_section(
			'sirsc_override_section',
			'<a name="opt_new_crop"></a><hr><br><br>' . self::show_plugin_icon( true ) . ' ' . __( 'Images Custom Settings', 'sirsc' ) . ' <div class="dashicons dashicons-image-crop"></div>',
			array( get_called_class(), 'sirsc_override_section_callback' ),
			'media'
		);

		// Add the custom field to the new section.
		add_settings_field(
			'sirsc_override_medium_size',
			__( 'Medium size crop', 'sirsc' ),
			array( get_called_class(), 'sirsc_override_medium_size_callback' ),
			'media',
			'sirsc_override_section'
		);

		// Add the custom field to the new section.
		add_settings_field(
			'sirsc_override_large_size',
			__( 'Large size crop', 'sirsc' ),
			array( get_called_class(), 'sirsc_override_large_size_callback' ),
			'media',
			'sirsc_override_section'
		);

		// Add the custom field to the new section.
		add_settings_field(
			'sirsc_admin_featured_size',
			__( 'Featured image size in meta box', 'sirsc' ),
			array( get_called_class(), 'sirsc_override_admin_featured_size_callback' ),
			'media',
			'sirsc_override_section'
		);

		// Register the custom settings.
		register_setting( 'media', 'sirsc_override_medium_size' );
		register_setting( 'media', 'sirsc_override_large_size' );
		register_setting( 'media', 'sirsc_admin_featured_size' );

		// Add the custom section to media.
		add_settings_section(
			'sirsc_custom_sizes_section',
			'<a name="opt_new_sizes"></a><hr><br><br>' . self::show_plugin_icon( true ) . ' ' . __( 'Define Custom Image Sizes', 'sirsc' ) . ' <div class="dashicons dashicons-format-gallery"></div>',
			array( get_called_class(), 'sirsc_custom_sizes_section_callback' ),
			'media'
		);

		// Add the custom field to the new section.
		add_settings_field(
			'sirsc_use_custom_image_sizes',
			__( 'Use Custom Image Sizes', 'sirsc' ),
			array( get_called_class(), 'sirsc_use_custom_image_sizes_callback' ),
			'media',
			'sirsc_custom_sizes_section'
		);

		// Register the custom settings.
		register_setting( 'media', 'sirsc_use_custom_image_sizes' );
	}

	/**
	 * Admin featured size.
	 *
	 * @param  string  $size         Initial size.
	 * @param  integer $thumbnail_id Attachment ID.
	 * @param  integer $post         Post ID.
	 * @return string
	 */
	public static function admin_featured_size( $size, $thumbnail_id = 0, $post = 0 ) {
		$override = get_option( 'sirsc_admin_featured_size' );
		if ( ! empty( $override ) ) {
			return $override;
		}
		return $size;
	}

	/**
	 * Expose the custom media settings.
	 */
	public static function sirsc_override_admin_featured_size_callback() {
		$checked   = get_option( 'sirsc_admin_featured_size' );
		$all_sizes = self::get_all_image_sizes_plugin();
		?>
		<select name="sirsc_admin_featured_size" id="sirsc_admin_featured_size">
			<option value=""></option>
			<?php foreach ( $all_sizes as $size => $prop ) : ?>
				<option value="<?php echo esc_attr( $size ); ?>"<?php selected( esc_attr( $size ), $checked ); ?>><?php echo esc_attr( $size ); ?></option>
			<?php endforeach; ?>
		</select>
		<br><?php esc_html_e( 'This setting allows you to change the post thumbnail image size that is displayed in the meta box. Leave empty if you want to use the default image size that is set by WordPress and your theme.', 'sirsc' ); ?>
		<?php
	}

	/**
	 * Describe the override settings section.
	 */
	public static function sirsc_override_section_callback() {
		?>
		<table class="widefat sirsc-striped">
			<tr>
				<td>
					<?php esc_html_e( 'You can override the default crop for the medium and large size of the images. Please note that the crop will apply to the designated image size only if it has both with and height defined (as you know, when you set 0 to one of the sizes, the image will be scaled proportionally, hence, the crop cannot be applied).', 'sirsc' ); ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Expose the custom media settings.
	 */
	public static function sirsc_override_medium_size_callback() {
		$checked     = get_option( 'sirsc_override_medium_size' );
		$medium_crop = get_option( 'medium_crop' );
		$checked     = ( 1 == $medium_crop && 1 == $checked ) ? 1 : 0;
		?>
		<label><input name="sirsc_override_medium_size" id="sirsc_override_medium_size"
			type="checkbox" value="1" class="code"
			<?php checked( 1, $checked ); ?>/> <?php esc_html_e( 'Crop medium image to exact dimensions (normally medium images are proportional)', 'sirsc' ); ?></label>
		<?php
	}

	/**
	 * Expose the custom media settings.
	 */
	public static function sirsc_override_large_size_callback() {
		$checked    = get_option( 'sirsc_override_large_size' );
		$large_crop = get_option( 'large_crop' );
		$checked    = ( 1 == $large_crop && 1 == $checked ) ? 1 : 0;
		?>
		<label><input name="sirsc_override_large_size" id="sirsc_override_large_size"
			type="checkbox" value="1" class="code"
			<?php checked( 1, $checked ); ?>/> <?php esc_html_e( 'Crop large image to exact dimensions (normally large images are proportional)', 'sirsc' ); ?></label>
		<?php
	}

	/**
	 * Expose the custom media settings.
	 */
	public static function sirsc_custom_sizes_section_callback() {
		?>
		<table class="widefat sirsc-striped">
			<tr>
				<td>
					<b><?php esc_html_e( 'Use this feature wisely.', 'sirsc' ); ?></b> <em><span class="dashicons dashicons-format-quote"></span> <?php esc_html_e( 'With great power comes great responsibility.', 'sirsc' ); ?></em>
					<br><?php esc_html_e( 'Please consult with a front-end developer before deciding to define more image sizes below (and in general in the application), as most of the times just updating the native image sizes settings and updating the front-end code (the theme) is enough.', 'sirsc' ); ?>
				</td>
			</tr>
		</table>
		<br>
		<?php esc_html_e( 'If you decided it is absolutely necessary to have new custom image sizes, you can make the setup below and these will be registered programmatically in your application if you configured these correctly (you have to input the size name and at least the width or height).', 'sirsc' ); ?>
		<b><?php esc_html_e( 'However, please make sure you only define these below if you are sure this is really necessary, as, any additional image size registered in your application is decreasing the performance on the images upload processing and also creates extra physical files on your hosting.', 'sirsc' ); ?></b>
		<?php esc_html_e( 'Also, please note that changing the image sizes names or width and height values is not recommended after these were defined and your application started to create images for these specifications.', 'sirsc' ); ?>
		<?php
	}

	/**
	 * Expose the custom media settings.
	 */
	public static function sirsc_use_custom_image_sizes_callback() {
		$def = array(
			'number' => 0,
			'sizes'  => array(),
		);
		$all = maybe_unserialize( get_option( 'sirsc_use_custom_image_sizes' ) );
		if ( empty( $all ) ) {
			$all = array();
		}
		$all = wp_parse_args( $all, $def );
		$all['number'] = 0;
		?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<td width="20"></td>
					<td width="40%"><?php esc_html_e( 'Image Sizes Name', 'sirsc' ); ?></td>
					<td width="120"><?php esc_html_e( 'Max Width', 'sirsc' ); ?></td>
					<td width="120"><?php esc_html_e( 'Max Height', 'sirsc' ); ?></td>
					<td><?php esc_html_e( 'Crop', 'sirsc' ); ?></td>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $all['sizes'] ) ) : ?>
					<?php foreach ( $all['sizes'] as $i => $asize ) : ?>
						<?php
						++ $all['number'];

						$name   = ( ! empty( $asize['name'] ) ) ? $asize['name'] : '';
						if ( empty( $name ) ) {
							continue;
						}

						$width  = ( ! empty( $asize['width'] ) ) ? (int) $asize['width'] : 0;
						$height = ( ! empty( $asize['height'] ) ) ? (int) $asize['height'] : 0;
						$crop   = ( ! empty( $asize['crop'] ) ) ? (int) $asize['crop'] : 0;
						?>
						<tr>
							<td><span class="dashicons dashicons-format-image"></span></td>
							<td>
								<input name="sirsc_use_custom_image_sizes[sizes][<?php echo (int) $i; ?>][name]"
								id="sirsc_image_size_<?php echo (int) $i; ?>_name"
								type="text" value="<?php echo esc_attr( $name ); ?>" class="code widefat"/>
								<?php esc_html_e( '(leave empty to remove this image size)', 'sirsc' ); ?>
							</td>
							<td>
								<input name="sirsc_use_custom_image_sizes[sizes][<?php echo (int) $i; ?>][width]"
									id="sirsc_image_size_<?php echo (int) $i; ?>_width"
									type="number" value="<?php echo esc_attr( $width ); ?>" class="code widefat"/>
									<?php esc_html_e( '(value in pixels)', 'sirsc' ); ?>
							</td>
							<td>
								<input name="sirsc_use_custom_image_sizes[sizes][<?php echo (int) $i; ?>][height]"
									id="sirsc_image_size_<?php echo (int) $i; ?>_height"
									type="number" value="<?php echo esc_attr( $height ); ?>" class="code widefat"/>
									<?php esc_html_e( '(value in pixels)', 'sirsc' ); ?>
							</td>
							<td>
								<label><input name="sirsc_use_custom_image_sizes[sizes][<?php echo (int) $i; ?>][crop]" id="sirsc_image_size_<?php echo (int) $i; ?>_crop" type="checkbox" value="1" class="code"
								<?php checked( 1, $crop ); ?>/> <?php esc_html_e( 'Crop the image to exact dimensions', 'sirsc' ); ?>.</label>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=sirsc-custom-rules-settings' ) ); ?>#sirsc-settings-for-<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'See/manage other settings', 'sirsc' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				<?php $i = (int) $all['number'] + 1; ?>
				<tr>
					<td><span class="dashicons dashicons-plus-alt"></span></td>
					<td>
						<input name="sirsc_use_custom_image_sizes[sizes][<?php echo (int) $i; ?>][name]"
						id="sirsc_image_size_<?php echo (int) $i; ?>_name"
						type="text" value="" class="code widefat"/>
					</td>
					<td>
						<input name="sirsc_use_custom_image_sizes[sizes][<?php echo (int) $i; ?>][width]"
							id="sirsc_image_size_<?php echo (int) $i; ?>_width"
							type="number" value="" class="code widefat"/>
					</td>
					<td>
						<input name="sirsc_use_custom_image_sizes[sizes][<?php echo (int) $i; ?>][height]"
							id="sirsc_image_size_<?php echo (int) $i; ?>_height"
							type="number" value="" class="code widefat"/>
					</td>
					<td>
						<label><input name="sirsc_use_custom_image_sizes[sizes][<?php echo (int) $i; ?>][crop]" id="sirsc_image_size_<?php echo (int) $i; ?>_crop" type="checkbox" value="1" class="code"/> <?php esc_html_e( 'Crop the image to exact dimensions', 'sirsc' ); ?> <?php esc_html_e( '(normally images are proportional)', 'sirsc' ); ?>.</label>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Update the settings as expected.
	 *
	 * @param  string $old_value Option old value.
	 * @param  string $value     Option new value.
	 * @param  string $option    Option name.
	 * @return void
	 */
	public static function on_update_sirsc_override_size( $old_value, $value, $option = '' ) {
		if ( ! empty( $option ) && 'sirsc_use_custom_image_sizes' === $option ) {
			// In some cases the options are not triggered, so enforce the update.
			$val_m = get_option( 'sirsc_override_medium_size' );
			update_option( 'medium_crop', ! empty( $val_m ) ? 1 : 0 );

			$val_l = get_option( 'sirsc_override_large_size' );
			update_option( 'large_crop', ! empty( $val_l ) ? 1 : 0 );
		}

		switch ( $option ) {
			case 'sirsc_override_medium_size':
				$val = get_option( 'sirsc_override_medium_size' );
				update_option( 'medium_crop', ! empty( $val ) ? 1 : 0 );
				return;
				break;

			case 'sirsc_override_large_size':
				$val = get_option( 'sirsc_override_large_size' );
				update_option( 'large_crop', ! empty( $val ) ? 1 : 0 );
				return;
				break;

			case 'sirsc_use_custom_image_sizes':
				if ( empty( self::$wp_native_sizes ) || ! is_array( self::$wp_native_sizes ) ) {
					// Fail-fast.
					return;
				}

				$all = maybe_unserialize( get_option( 'sirsc_use_custom_image_sizes' ) );
				if ( ! empty( $all['sizes'] ) ) {
					$existing = array();
					$native = self::$wp_native_sizes;
					$native = ( ! empty( $native ) && is_array( $native ) ) ? $native : array();
					$native = array_merge( $native, array( 'full', 'original', 'original_image', '1536x1536', '2048x2048' ) );
					foreach ( $all['sizes'] as $i => $value ) {
						if ( ! empty( $value['name'] ) ) {
							$value['name'] = str_replace( '-', '_', sanitize_title( $value['name'] ) );
							$value['name'] = strtolower( $value['name'] );
							$value['name'] = str_replace( ' ', '_', $value['name'] );
							$value['name'] = str_replace( '-', '_', $value['name'] );

							if ( in_array( $value['name'], $existing ) || in_array( $value['name'], $native ) ) {
								unset( $all['sizes'][ $i ] );
								continue;
							} else {
								$existing[] = $value['name'];
							}

							$all['sizes'][ $i ] = array(
								'name'   => $value['name'],
								'width'  => abs( (int) $value['width'] ),
								'height' => abs( (int) $value['height'] ),
								'crop'   => ( ! empty( $value['crop'] ) ) ? 1 : 0,
							);
							if ( empty( $all['sizes'][ $i ]['width'] ) && empty( $all['sizes'][ $i ]['height'] ) ) {
								unset( $all['sizes'][ $i ] );
							} else {
								if ( empty( $all['sizes'][ $i ]['width'] ) || empty( $all['sizes'][ $i ]['height'] ) ) {
									$all['sizes'][ $i ]['crop'] = 0;
									unset( $all['sizes'][ $i ]['crop'] );
								}
								if ( empty( $all['sizes'][ $i ] ) || empty( $all['sizes'][ $i ]['name'] )
									|| ( is_array( $native ) && in_array( $all['sizes'][ $i ]['name'], $native ) ) ) {
									unset( $all['sizes'][ $i ] );
								}
							}
						} else {
							unset( $all['sizes'][ $i ] );
						}
					}
					if ( ! empty( $all['sizes'] ) ) {
						$all['sizes'] = array_values( $all['sizes'] );
					}
				}
				$all['number'] = count( $all['sizes'] );
				update_option( 'sirsc_use_custom_image_sizes', $all );

				return;
				break;

			default:
				break;
		}
	}

	/**
	 * Maybe register the image sizes.
	 */
	public static function maybe_register_custom_image_sizes() {
		$all = maybe_unserialize( get_option( 'sirsc_use_custom_image_sizes' ) );
		if ( empty( $all['sizes'] ) ) {
			// Fail-fast, no custom image sizes registered.
			return;
		} else {
			foreach ( $all['sizes'] as $i => $value ) {
				if ( ! empty( $value['name'] ) && is_scalar( $value['name'] )
					&& ( ! empty( $value['width'] ) || ! empty( $value['height'] ) ) ) {
					$crop = ( ! empty( $value['crop'] ) ) ? true : false;
					add_image_size( $value['name'], (int) $value['width'], (int) $value['height'], $crop );
				}
			}
		}
	}

	/**
	 * Functionality to manage the image regenerate & select crop settings.
	 */
	public static function sirsc_custom_rules_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			// Verify user capabilities in order to deny the access if the user does not have the capabilities.
			wp_die( esc_html__( 'Action not allowed.', 'sirsc' ) );
		}

		if ( false === self::$is_configured ) {
			echo '<div class="update-nag">' . esc_html__( 'Image Regenerate & Select Crop Settings are not configured yet.', 'sirsc' ) . '</div><hr/>';
		}
		$post_types              = self::get_all_post_types_plugin();
		$_sirsc_post_types       = filter_input( INPUT_GET, '_sirsc_post_types', FILTER_DEFAULT );
		$settings                = maybe_unserialize( get_option( 'sirsc_settings' ) );
		$default_plugin_settings = $settings;
		if ( ! empty( $_sirsc_post_types ) ) {
			$settings = maybe_unserialize( get_option( 'sirsc_settings_' . $_sirsc_post_types ) );
		}
		$all_sizes  = self::get_all_image_sizes();

		?>

		<div class="wrap sirsc-settings-wrap">
			<h1>
				<?php self::show_plugin_icon(); ?> <?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</h1>

			<?php self::maybe_all_features_tab(); ?>
			<div class="sirsc-tabbed-menu-content">
				<h1><?php esc_html_e( 'Advanced Rules', 'sirsc' ); ?></h1>
				<br>

				<div class="sirsc-image-generate-functionality">
					<form id="sirsc_settings_frm" name="sirsc_settings_frm" action="" method="post">
						<?php wp_nonce_field( '_sirsc_settings_save', '_sirsc_settings_nonce' ); ?>

						<table class="widefat sirsc-striped">
							<tr>
								<td>
									<?php esc_html_e( 'The advanced custom rules you configure below are global and will override all the other settings you set above.', 'sirsc' ); ?> <b><?php esc_html_e( 'Please be aware that the custom rules will apply only if you actually set up the post to use one of the rules below, and only then upload images to that post.', 'sirsc' ); ?></b>
								</td>
							</tr>
						</table>

						<h3><?php esc_html_e( 'Advanced custom rules based on the post where the image will be uploaded', 'sirsc' ); ?></h3>
						<p>
							<?php esc_html_e( 'Very important: the order in which the rules are checked and have priority is: post ID, post type, post format, post parent, post tags, post categories, other taxonomies. Any of the rules that match first in this order will apply for the images that are generated when you upload images to that post (and the rest of the rules will be ignored). You can suppress at any time any of the rules and then enable these back as it suits you.', 'sirsc' ); ?>
						</p>
						<?php
						$select_ims = '';
						$checks_ims = '';
						if ( ! empty( $all_sizes ) ) {
							$select_ims .= '<option value="**full**">- ' . esc_attr( 'full/original' ) . ' -</option>';
							foreach ( $all_sizes as $k => $v ) {
								$select_ims .= '<option value="' . esc_attr( $k ) . '">' . esc_attr( $k ) . '</option>';
								$checks_ims .= ( ! empty( $checks_ims ) ) ? ' ' : '';
								$checks_ims .= '<label label-for="' . esc_attr( $k ) . '"><input type="checkbox" name="#NAME#" id="#ID#" value="' . esc_attr( $k ) . '">' . esc_attr( $k ) . '</label>';
							}
						}

						$taxonomies = get_taxonomies( array( 'public' => 1 ), 'objects' );
						$select_tax = '';
						if ( ! empty( $taxonomies ) ) {
							foreach ( $taxonomies as $k => $v ) {
								$select_tax .= '<option value="' . esc_attr( $k ) . '">' . esc_attr( $v->label ) . '</option>';
							}
						}
						$select_tax .= '<option value="ID">' . esc_html( 'Post ID', 'sirsc' ) . '</option>';
						$select_tax .= '<option value="post_parent">' . esc_html( 'Post Parent ID', 'sirsc' ) . '</option>';
						$select_tax .= '<option value="post_type">' . esc_html( 'Post Type', 'sirsc' ) . '</option>';
						?>

						<table cellpadding="0" cellspacing="0" class="wp-list-table widefat fixed striped sirsc-table sirsc-custom-rules">
							<thead>
								<tr class="middle noborder">
									<td id="th-rule-type" scope="col" class="manage-column" width="15%">
										<h3><span><?php esc_html_e( 'The post has', 'sirsc' ); ?></span></h3>
										<div class="row-hint"><?php esc_html_e( 'Ex: Categories', 'sirsc' ); ?></div>
									</td>
									<td id="th-rule-value" scope="col" class="manage-column" width="15%">
										<h3><span><?php esc_html_e( 'Value', 'sirsc' ); ?></span></h3>
										<div class="row-hint"><?php esc_html_e( 'Ex: gallery,my-photos', 'sirsc' ); ?></div>
									</td>
									<td id="th-rule-original" scope="col" class="manage-column" width="15%">
										<h3><span><?php esc_html_e( 'Force Original', 'sirsc' ); ?></span></h3>
										<div class="row-hint"><?php esc_html_e( 'Ex: large', 'sirsc' ); ?></div>
									</td>
									<td id="th-rule-only" scope="col" class="manage-column">
										<h3><span><?php esc_html_e( 'Generate only these image sizes for the rule', 'sirsc' ); ?></span></h3>
										<div class="row-hint"><?php esc_html_e( 'Ex: thumbnail, large', 'sirsc' ); ?></div>
									</td>
									<td id="th-rule-suppress" scope="col" class="manage-column" width="10%">
										<h3><span><?php esc_html_e( 'Suppress', 'sirsc' ); ?></span></h3>
									</td>
								</tr>
							</thead>
							<tbody id="the-list">

								<?php for ( $i = 1; $i <= 10; $i ++ ) : ?>
									<?php
									$class = 'row-hide';
									if ( ! empty( self::$user_custom_rules[ $i ]['type'] )
										&& ! empty( self::$user_custom_rules[ $i ]['value'] ) ) {
										$class = 'row-use';
									}
									if ( ! empty( self::$user_custom_rules[ $i ]['suppress'] )
										&& 'on' === self::$user_custom_rules[ $i ]['suppress'] ) {
										$class .= ' row-ignore';
									}
									$supp  = ( ! empty( self::$user_custom_rules[ $i ]['suppress'] ) && 'on' === self::$user_custom_rules[ $i ]['suppress'] ) ? ' checked="checked"' : '';
									?>
									<tr id="th-rule-row<?php echo (int) $i; ?>" class="hentry <?php echo esc_attr( $class ); ?>">
										<td class="th-rule-type">
											<select name="_user_custom_rule[<?php echo (int) $i; ?>][type]">
												<option value=""><?php esc_html_e( 'N/A', 'sirsc' ); ?></option>
												<?php
												echo str_replace(
													'value="' . esc_attr( self::$user_custom_rules[ $i ]['type'] ) . '"',
													'value="' . esc_attr( self::$user_custom_rules[ $i ]['type'] ) . '" selected="selected"',
													$select_tax
												); // WPCS: XSS OK.
												?>
											</select>
										</td>
										<td class="th-rule-value">
											<input type="text" name="_user_custom_rule[<?php echo (int) $i; ?>][value]"
												value="<?php echo esc_attr( self::$user_custom_rules[ $i ]['value'] ); ?>" size="20">
										</td>
										<td class="th-rule-original">
											<select name="_user_custom_rule[<?php echo (int) $i; ?>][original]">
												<?php
												$sel = ( ! empty( self::$user_custom_rules[ $i ]['original'] ) ) ? self::$user_custom_rules[ $i ]['original'] : 'large';
												echo str_replace(
													' value="' . $sel . '"',
													' value="' . $sel . '" selected="selected"',
													$select_ims
												); // WPCS: XSS OK.
												?>
											</select>
										</td>
										<td class="th-rule-only">
											<?php
											$only = str_replace( '#ID#', '_user_custom_rule_' . $i . '_only_', $checks_ims );
											$only = str_replace( '#NAME#', '_user_custom_rule[' . $i . '][only][]', $only );
											$sel  = ( ! empty( self::$user_custom_rules[ $i ]['only'] ) ) ? self::$user_custom_rules[ $i ]['only'] : array( 'thumbnail', 'large' );
											foreach ( $sel as $is ) {
												if ( ! empty( $class ) && substr_count( $class, 'row-use' ) ) {
													$only = str_replace(
														' value="' . $is . '"',
														' value="' . $is . '" checked="checked" class="row-use"',
														$only
													);
													$only = str_replace(
														' label-for="' . $is . '"',
														' label-for="' . $is . '" class="' . $class . '"',
														$only
													);
												}
											}
											echo $only; // WPCS: XSS OK.
											?>
										</td>
										<td class="th-rule-suppress textcenter">
											<input type="checkbox" name="_user_custom_rule[<?php echo (int) $i; ?>][suppress]" <?php echo $supp; // WPCS: XSS OK. ?>>
										</td>
									</tr>

									<tr class="<?php echo esc_attr( $class ); ?> rule-info">
										<td colspan="5">
											<?php
											if ( ! empty( $class ) && substr_count( $class, 'row-use' ) ) {

												echo '<div class="potential-rule ' . $class . '">'; // WPCS: XSS OK.
												if ( substr_count( $class, 'row-ignore' ) ) {
													esc_html_e( 'This rule is SUPPRESSED', 'sirsc' );
												} else {
													esc_html_e( 'This rule is ACTIVE', 'sirsc' );
												}
												echo ': ';

												if ( '**full**' === self::$user_custom_rules[ $i ]['original'] ) {
													echo sprintf(
														// Translators: %1$s type, %2$s value, %3$s only.
														esc_html__( 'uploading images to a post that has %1$s as %2$s will generate only the %3$s sizes.', 'sirsc' ),
														'<b>' . self::$user_custom_rules[ $i ]['type'] . '</b>',
														'<b>' . self::$user_custom_rules[ $i ]['value'] . '</b>',
														'<b>' . implode( ', ', array_unique( self::$user_custom_rules[ $i ]['only'] ) ) . '</b>'
													); // WPCS: XSS OK.
												} else {
													echo sprintf(
														// Translators: %1$s type, %2$s value, %3$s original, %4$s only.
														esc_html__( 'uploading images to a post that has %1$s as %2$s will force the original image to %3$s size and will generate only the %4$s sizes.', 'sirsc' ),
														'<b>' . self::$user_custom_rules[ $i ]['type'] . '</b>',
														'<b>' . self::$user_custom_rules[ $i ]['value'] . '</b>',
														'<b>' . self::$user_custom_rules[ $i ]['original'] . '</b>',
														'<b>' . implode( ', ', array_unique( self::$user_custom_rules[ $i ]['only'] ) ) . '</b>'
													); // WPCS: XSS OK.
												}
												echo '</div>'; // WPCS: XSS OK.
											}
											?>
										</td>
									</tr>
								<?php endfor; ?>

								<tr class="hentry">
									<td colspan="5" class="textright"><?php submit_button( __( 'Save Settings', 'sirsc' ), 'primary', 'sirsc-save-custom-rules', false, array( 'onclick' => 'jQuery(\'#sirsc_settings_frm\').addClass(\'js-sirsc-general processing\')' ) ); ?></td>
								</tr>
							</tbody>
						</table>
					</form>
				</div>
			</div>

			<?php self::plugin_global_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Plugin global footer.
	 *
	 * @return void
	 */
	public static function plugin_global_footer() {
		?>
		<br>
		<table class="widefat">
			<tr>
				<td>
					<?php self::show_plugin_icon(); ?>
					<?php self::show_donate_text(); ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Add the custom column.
	 *
	 * @access public
	 * @static
	 * @param array $columns The defined columns.
	 * @return array
	 */
	public static function register_media_columns( $columns ) {
		if ( ! empty( $columns ) ) {
			$before  = array_slice( $columns, 0, 2, true );
			$after   = array_slice( $columns, 2, count( $columns ) - 1, true );
			$columns = array_merge( $before, array( 'sirsc_buttons' => esc_html__( 'Details/Options', 'sirsc' ) ), $after );
		}
		return $columns;
	}

	/**
	 * Output the custom column value.
	 *
	 * @access public
	 * @static
	 * @param string  $column The current column.
	 * @param integer $value  The current column value.
	 * @return void
	 */
	public static function media_column_value( $column, $value ) {
		if ( 'sirsc_buttons' === $column ) {
			global $post, $sirsc_column_summary;
			if ( ! empty( self::$settings['listing_show_summary'] ) ) {
				$sirsc_column_summary = true;
			}
			if ( ! empty( $post ) && ! empty( $post->post_mime_type ) && substr_count( $post->post_mime_type, 'image/' ) ) {
				$extra_class = ( ! empty( self::$settings['listing_tiny_buttons'] ) ) ? 'tiny' : '';
				echo self::append_image_generate_button( '', '', $post->ID, $extra_class ); // WPCS: XSS OK.
				if ( ! empty( self::$settings['listing_show_summary'] ) ) {
					self::attachment_files_summary( $post->ID );
				}
			}
		}
	}

	/**
	 * Custom image size names list in the media screen.
	 *
	 * @param  array $list Initial list of sizes.
	 * @return array
	 */
	public static function custom_image_size_names_choose( $list ) {
		$initial  = $list;
		$all_ims  = array_filter( get_intermediate_image_sizes() );
		$override = false;

		if ( ! empty( self::$settings['complete_global_ignore'] ) ) {
			$override = true;
			foreach ( self::$settings['complete_global_ignore'] as $rem ) {
				// Remove from check the ignored sizes.
				$all_ims = array_diff( $all_ims, array( $rem ) );
			}
		}
		if ( ! empty( self::$settings['unavailable'] ) ) {
			$override = true;
			foreach ( self::$settings['unavailable'] as $rem ) {
				// Remove from check the unavailable sizes.
				$all_ims = array_diff( $all_ims, array( $rem ) );
			}
		}
		if ( true === $override || ! empty( self::$settings['force_size_choose'] ) ) {
			if ( ! empty( $all_ims ) ) {
				$list = array();
				foreach ( $all_ims as $value ) {
					if ( ! empty( $value ) ) {
						if ( ! empty( $initial[ $value ] ) ) {
							// Re-use the title from the initial array.
							$list[ $value ] = $initial[ $value ];
						} else {
							// Add this to the list of available sizes in the media screen.
							$list[ $value ] = ucwords( str_replace( '-', ' ', str_replace( '_', ' ', $value ) ) );
						}
					}
				}
				if ( ! empty( $initial['full'] ) ) {
					$list['full'] = $initial['full'];
				}
			} else {
				// Fall-back to the minimal.
				$list = array(
					'thumbnail' => $initial['thumbnail'],
				);
				if ( ! empty( $initial['full'] ) ) {
					$list['full'] = $initial['full'];
				}
			}
		}

		return $list;
	}

	/**
	 * Add the plugin settings and plugin URL links.
	 *
	 * @param array $links The plugin links.
	 */
	public static function plugin_action_links( $links ) {
		$all   = array();
		$all[] = '<a href="' . esc_url( self::$plugin_url ) . '">' . esc_html__( 'Settings', 'sirsc' ) . '</a>';
		$all[] = '<a href="https://iuliacazan.ro/image-regenerate-select-crop">' . esc_html__( 'Plugin URL', 'sirsc' ) . '</a>';
		$all   = array_merge( $all, $links );
		return $all;
	}

	/**
	 * Sync SIRSC settings with EWWW.
	 *
	 * @param  mixed  $old_value Old option value.
	 * @param  mixed  $value     New option value.
	 * @param  string $option    Option name.
	 * @return void
	 */
	public static function sync_sirsc_with_ewww( $old_value, $value, $option ) {
		$val = ( ! empty( $value ) ) ? array_keys( $value ) : array();
		self::$settings['complete_global_ignore'] = $val;
		update_option( 'sirsc_settings', self::$settings );
	}

	/**
	 * Sync EWWW settings with SIRSC.
	 *
	 * @param  mixed  $old_value Old option value.
	 * @param  mixed  $value     New option value.
	 * @param  string $option    Option name.
	 * @return void
	 */
	public static function sync_ewww_with_sirsc( $old_value, $value, $option ) {
		$val  = $value['complete_global_ignore'];
		$list = array();
		if ( ! empty( $val ) ) {
			foreach ( $val as $size ) {
				$list[ $size ] = true;
			}
		}
		update_option( 'ewww_image_optimizer_disable_resizes', $list );
	}
}

$sirsc_image_regenerate_select_crop = SIRSC_Image_Regenerate_Select_Crop::get_instance();
add_action( 'wp_loaded', array( $sirsc_image_regenerate_select_crop, 'filter_ignore_global_image_sizes' ) );
register_activation_hook( __FILE__, array( $sirsc_image_regenerate_select_crop, 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( $sirsc_image_regenerate_select_crop, 'deactivate_plugin' ) );

if ( file_exists( dirname( __FILE__ ) . '/sirsc-adons.php' ) ) {
	// Hookup the SIRSC adons component.
	require_once dirname( __FILE__ ) . '/sirsc-adons.php';
}

if ( file_exists( dirname( __FILE__ ) . '/sirsc-wp-cli.php' ) ) {
	// Hookup the SIRSC wp-cli component.
	require_once dirname( __FILE__ ) . '/sirsc-wp-cli.php';
}
