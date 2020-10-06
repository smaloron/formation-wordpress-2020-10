<?php
/**
 * Export/Import extension.
 *
 * @package sirsc
 * @version 1.0
 */

/**
 * Class for Image Regenerate & Select Crop plugin adon Export/Import.
 */
class SIRSC_Adons_Import_Export {

	const ADON_PAGE_SLUG = 'sirsc-adon-import-export';
	const ADON_SLUG      = 'import-export';

	/**
	 * Class options.
	 *
	 * @var array
	 */
	public static $options = array(
		'sirsc_settings',
		'sirsc_user_custom_rules',
		'sirsc_user_custom_rules_usable',
		'sirsc_use_custom_image_sizes',
		'sirsc_override_large_size',
		'sirsc_override_medium_size',
	);

	/**
	 * Class instance.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Get active object instance
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Adons_Import_Export();
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
		if ( ! class_exists( 'SIRSC_Image_Regenerate_Select_Crop' ) ) {
			return;
		}

		if ( is_admin() ) {
			add_action( 'init', array( get_called_class(), 'maybe_import_settings' ), 60 );
			add_action( 'admin_menu', array( get_called_class(), 'adon_admin_menu' ), 20 );
		}
	}

	/**
	 * Get options.
	 *
	 * @return void
	 */
	public static function get_options() {
		$post_types = SIRSC_Image_Regenerate_Select_Crop::get_all_post_types_plugin();
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $key => $value ) {
				self::$options[] = 'sirsc_settings_' . $key;
			}
		}
	}

	/**
	 * Prepate the options export string.
	 *
	 * @return string
	 */
	public static function prepare_export_string() {
		self::get_options();
		$export = array();
		foreach ( self::$options as $key ) {
			$export[ $key ] = get_option( $key, '' );
		}

		return serialize( $export );
	}

	/**
	 * Maybe import settings.
	 *
	 * @return void
	 */
	public static function maybe_import_settings() {
		$nonce = filter_input( INPUT_POST, '_sirsc_adon_export_settings_nonce', FILTER_DEFAULT );
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, '_sirsc_adon_export_settings_action' ) ) {
			$error = 0;
			if ( current_user_can( 'manage_options' ) ) {
				// Maybe update settings.
				$set = filter_input( INPUT_POST, 'sirsc-import-settings', FILTER_DEFAULT );
				if ( ! empty( $set ) ) {
					$array = maybe_unserialize( $set );
					if ( is_array( $array ) && ! empty( $array ) ) {
						foreach ( $array as $key => $value ) {
							if ( empty( $value ) ) {
								delete_option( $key );
							} else {
								update_option( $key, $value );
							}
						}
						add_action( 'admin_notices', function() {
							printf(
								'<div class="%1$s"><p>%2$s</p></div>',
								esc_attr( 'notice notice-success is-dismissible' ),
								esc_html( __( 'The plugin settings have been imported successfully.', 'sirsc' ) )
							);
						} );
					} else {
						++ $error;
					}
				} else {
					++ $error;
				}
			} else {
				++ $error;
			}

			if ( ! empty( $error ) ) {
				add_action( 'admin_notices', function() {
					printf(
						'<div class="%1$s"><p>%2$s</p></div>',
						esc_attr( 'notice notice-error is-dismissible' ),
						esc_html( __( 'The plugin settings were not imported, something went wrong.', 'sirsc' ) )
					);
				} );
			}
		}
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function adon_admin_menu() {
		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Import/Export', 'sirsc' ),
			'<span class="dashicons dashicons-admin-plugins sirsc-mini"></span> ' . __( 'Import/Export', 'sirsc' ),
			'manage_options',
			self::ADON_PAGE_SLUG,
			array( get_called_class(), 'adon_page' )
		);
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function adon_page() {
		$export = self::prepare_export_string();
		$import = maybe_unserialize( $export );
		SIRSC_Adons::check_adon_valid( self::ADON_SLUG );
		$desc   = SIRSC_Adons::get_adon_details( self::ADON_SLUG, 'description' );
		?>

		<div class="wrap sirsc-settings-wrap">
			<h1>
				<?php SIRSC_Image_Regenerate_Select_Crop::show_plugin_icon(); ?> <?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</h1>

			<?php SIRSC_Image_Regenerate_Select_Crop::maybe_all_features_tab(); ?>
			<div class="sirsc-tabbed-menu-content">
				<h1>
					<span class="dashicons dashicons-admin-plugins"></span>
					<?php esc_html_e( 'Import/Export Settings', 'sirsc' ); ?>
				</h1>
				<br>

				<table class="widefat sirsc-striped">
					<tr>
						<td><?php echo wp_kses_post( $desc ); ?></td>
					</tr>
				</table>

				<p><?php esc_html_e( 'Please note that the import/export of the settings is in relation with the image sizes that are found on the instance, through the plugins that are activated and also the theme settings. You might need to partially adjust these manually after an import.', 'sirsc' ); ?></p>
				<br>

				<form action="" method="post" autocomplete="off" id="js-sirsc_adon_import_frm">
					<?php wp_nonce_field( '_sirsc_adon_export_settings_action', '_sirsc_adon_export_settings_nonce' ); ?>
					<table class="fixed vtop">
						<tr>
							<td width="25%">
								<h1><?php esc_html_e( 'Export Settings', 'sirsc' ); ?></h1>
								<?php esc_html_e( 'Copy the settings and import these into another instance.', 'sirsc' ); ?>
							</td>
							<td><textarea rows="4"><?php echo esc_html( $export ); ?></textarea><br><br></td>
						</tr>
						<tr>
							<td>
								<h1><?php esc_html_e( 'Import Settings', 'sirsc' ); ?></h1>
								<?php esc_html_e( 'Paste here the settings and import these into the current instance.', 'sirsc' ); ?>
							</td>
							<td><textarea name="sirsc-import-settings" rows="4"></textarea>

								<?php
								submit_button( __( 'Import Settings', 'sirsc' ), 'primary', '', false, array(
									'onclick' => 'jQuery(\'#js-sirsc_adon_import_frm\').addClass(\'js-sirsc-adon processing\');',
								) );
								?>
							</td>
						</tr>
					</table>
				</form>
			</div>

		</div>

		<div class="wrap">
			<?php SIRSC_Image_Regenerate_Select_Crop::plugin_global_footer(); ?>
		</div>

		<?php
	}

}

// Instantiate the class.
SIRSC_Adons_Import_Export::get_instance();
