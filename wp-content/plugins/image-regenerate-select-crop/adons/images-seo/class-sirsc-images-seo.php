<?php
/**
 * Images SEO extension.
 *
 * @package sirsc
 * @version 1.0
 */

define( 'SIRSC_ADON_IMGSEO_ASSETS_VER', '20191024.1851' );
define( 'SIRSC_ADON_IMGSEO_PLUGIN_VER', 1.0 );

/**
 * Class for Image Regenerate & Select Crop plugin adon Images SEO.
 */
class SIRSC_Adons_Images_SEO {

	const RENAME_QUERY_TYPE  = 1; // 0 = process by post, 1 = process by attachment.
	const PROCESS_BATCH_SIZE = 10; // The rename batch size.
	const ADON_PAGE_SLUG     = 'sirsc-adon-images-seo';
	const ADON_SLUG          = 'images-seo';

	/**
	 * Class instance.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	public static $settings;

	/**
	 * Plugin identified and filtered post types.
	 *
	 * @var array
	 */
	public static $post_types;

	/**
	 * Get active object instance
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Adons_Images_SEO();
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

		$called = get_called_class();
		add_action( 'init', array( $called, 'init_settings' ), 15 );
		add_action( 'wp_ajax_sirsc_imgseo_image_process_paginated_images', array( $called, 'image_process_paginated_images' ) );
		add_action( 'wp_generate_attachment_metadata', array( $called, 'process_rename_after_file_uploaded' ), 99, 2 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $called, 'images_admin_menu' ), 20 );
			add_action( 'add_meta_boxes', array( $called, 'rename_metaboxes' ) );
			add_action( 'admin_enqueue_scripts', array( $called, 'load_admin_assets' ), 1 );
		}
	}

	/**
	 * Get available filtered post types and settings.
	 *
	 * @return void
	 */
	public static function init_settings_types() {
		self::get_types();
		self::get_settings();
	}

	/**
	 * Get available filtered post types and settings.
	 *
	 * @return void
	 */
	public static function init_settings() {
		self::init_settings_types();

		$settings = self::$settings;
		$settings_nonce = filter_input( INPUT_POST, '_sirsc_imgseo_settings_nonce', FILTER_DEFAULT );
		if ( ! empty( $settings_nonce ) && wp_verify_nonce( $settings_nonce, '_sirsc_imgseo_settings_action' ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				// Maybe update settings.
				$set = filter_input( INPUT_POST, '_sirsc_imgseo_settings', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
				$settings['types']  = ( empty( $set['types'] ) ) ? array() : array_keys( $set['types'] );
				$settings['upload'] = ( empty( $set['upload'] ) ) ? array() : array_keys( $set['upload'] );
				$settings['bulk']   = ( empty( $set['bulk'] ) ) ? array() : array_keys( $set['bulk'] );

				$settings['override_title']     = ( ! empty( $set['override_title'] ) ) ? true : false;
				$settings['override_filename']  = ( ! empty( $set['override_filename'] ) ) ? true : false;
				$settings['track_initial']      = ( ! empty( $set['track_initial'] ) && ! empty( $set['override_filename'] ) ) ? true : false;
				$settings['override_alt']       = ( ! empty( $set['override_alt'] ) ) ? true : false;
				$settings['override_permalink'] = ( ! empty( $set['override_permalink'] ) ) ? true : false;

				update_option( 'sirsc_adon_images_seo_settings', $settings );
				self::init_settings_types();
			}
		}
	}

	/**
	 * Get available filtered post types.
	 *
	 * @return void
	 */
	public static function get_types() {
		$types      = array();
		$post_types = get_post_types( array(), 'objects' );
		if ( ! empty( $post_types ) ) {
			$list = wp_list_pluck( $post_types, 'label', 'name' );
			if ( ! empty( $list ) ) {
				foreach ( $list as $type => $label ) {
					if ( 'attachment' === $type || post_type_supports( $type, 'thumbnail' ) ) {
						$types[ $type ] = $label;
					}
				}
			}
		}
		self::$post_types = $types;
	}

	/**
	 * Get current settings of the plugin.
	 *
	 * @return void
	 */
	public static function get_settings() {
		$settings = get_option( 'sirsc_adon_images_seo_settings', array() );
		$defaults = array(
			'types'              => array(),
			'upload'             => array(),
			'bulk'               => array(),
			'track_initial'      => true,
			'override_title'     => true,
			'override_filename'  => true,
			'override_alt'       => true,
			'override_permalink' => true,
		);
		$settings = wp_parse_args( $settings, $defaults );
		self::$settings = $settings;
	}

	/**
	 * Enqueue the custom styles.
	 *
	 * @return void
	 */
	public static function load_admin_assets() {
		// Enqueue the custom plugin styles.
		wp_enqueue_style(
			'sirsc_imgseo',
			plugins_url( '/assets/styles-bed.css', __FILE__ ),
			array(),
			SIRSC_ADON_IMGSEO_ASSETS_VER,
			false
		);
		wp_register_script(
			'sirsc_imgseo',
			plugins_url( '/assets/script-bed.js', __FILE__ ),
			array( 'jquery' ),
			SIRSC_ADON_IMGSEO_ASSETS_VER,
			false
		);
		wp_localize_script( 'sirsc_imgseo', 'SIRSC_IMGSEO', array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'allProcessed' => __( 'All identified images were processed.', 'sirsc' ),
			'isProcessing' => __( 'The bulk request is processing', 'sirsc' ),
			'allDone'      => __( 'The bulk request finished', 'sirsc' ),
		) );
		wp_enqueue_script( 'sirsc_imgseo' );
	}

	/**
	 * Do some custom processing then return back the attachment metadata.
	 *
	 * @param  array   $metadata      Attachment metadata.
	 * @param  integer $attachment_id Attachment ID.
	 */
	public static function process_rename_after_file_uploaded( $metadata = array(), $attachment_id = 0 ) {
		if ( ! empty( $attachment_id ) && defined( 'DOING_SIRSC' ) ) {
			SIRSC_Image_Regenerate_Select_Crop::debug( 'ATTEMPT TO RENAME files for ' . $attachment_id, true, true );
			$post = get_post( $attachment_id );
			if ( ! empty( $post->post_parent ) ) {
				if ( empty( self::$settings ) ) {
					self::get_settings();
				}
				if ( empty( self::$settings['upload'] ) ) {
					// Fail-fast, no upload settings.
					return $metadata;
				}
				$type = get_post_type( $post->post_parent );
				if ( ! in_array( $type, self::$settings['upload'] ) ) {
					// Fail-fast, not a targeted type.
					return $metadata;
				}
				$title = get_the_title( $post->post_parent );
				if ( ! empty( $title ) ) {
					self::rename_image_filename( $attachment_id, $title, 0, 'attachment', false );
				}
			}

			// Re-fetch the latest metadata.
			$metadata = wp_get_attachment_metadata( $attachment_id );
			SIRSC_Image_Regenerate_Select_Crop::debug( 'RENAME FINISHED ' . print_r( $metadata, 1 ), true, true );
		}

		// This is what the filter expects back.
		return $metadata;
	}

	/**
	 * Rename image filename.
	 *
	 * @param  integer $id      The attachment ID.
	 * @param  string  $title   The "parent" post title.
	 * @param  integer $count   Perhaps a counter suffix for the image.
	 * @param  string  $type    The "parent" post type.
	 * @param  boolean $output  Output the result or not.
	 * @param  string  $message The extra message.
	 * @return void
	 */
	public static function rename_image_filename( $id, $title, $count, $type, $output = true, $message = '' ) {
		$meta  = wp_get_attachment_metadata( $id );
		$title = apply_filters( 'sirsc_seo_title_before_rename_file', $title, $id, $meta );

		if ( ! empty( $meta['file'] ) && ! empty( $title ) ) {
			$upls = wp_upload_dir();
			if ( empty( self::$settings ) ) {
				self::get_settings();
			}

			$extra_hints = array();
			if ( ! empty( self::$settings['track_initial'] ) ) {
				$was_tracked = get_post_meta( $id, '_initial_filename' );
				if ( empty( $was_tracked ) ) {
					// Only the first time.
					update_post_meta( $id, '_initial_filename', $meta['file'] );
					$extra_hints[] = __( 'The initial filename was recorded.', 'sirsc' );
				}
			}
			if ( ! empty( self::$settings['override_alt'] ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $title );
				$extra_hints[] = __( 'The attachment alternative text was updated.', 'sirsc' );
			}
			if ( ! empty( self::$settings['override_title'] ) ) {
				wp_update_post( array(
					'ID'         => $id,
					'post_title' => $title,
				) );
				$extra_hints[] = __( 'The attachment title was updated.', 'sirsc' );
			}
			if ( ! empty( self::$settings['override_permalink'] ) ) {
				wp_update_post( array(
					'ID'         => $id,
					'post_name'  => sanitize_title( $title ),
				) );
				$extra_hints[] = __( 'The attachment permalink was updated.', 'sirsc' );
			}

			$basedir    = trailingslashit( $upls['basedir'] );
			$old_path   = $basedir . $meta['file'];
			$change_log = '<ul><li>No changes for ' . $old_path . '</li></ul>';
			$renamed    = '<b class="dashicons dashicons-dismiss"></b>';
			$tmp_name   = '';

			if ( ! empty( self::$settings['override_filename'] ) ) {
				$maybe_type = wp_check_filetype( $meta['file'] );
				$tmp_name   = self::generate_filename(
					trailingslashit( $basedir . dirname( $meta['file'] ) ),
					$title,
					$maybe_type['ext'],
					$count,
					$old_path
				);

				$filename     = $tmp_name;
				$new_filename = $filename . '.' . $maybe_type['ext'];
				$subdir       = trailingslashit( dirname( $meta['file'] ) );
				$new_path     = trailingslashit( $basedir . $subdir ) . $new_filename;
				$new_meta     = $meta;
				$base_one    = wp_basename( $meta['file'] );
				$change_log   = '<ul><li>No changes for ' . $old_path . '</li></ul>';
				$renamed      = '<b class="dashicons dashicons-dismiss"></b>';
				if ( $old_path == $new_path ) {
					$renamed = '<b class="dashicons dashicons-yes-alt"></b>';
				} else {
					if ( ! empty( $new_path ) && ! is_dir( $new_path ) && ! file_exists( $new_path ) ) {
						$renamed = '<b class="dashicons dashicons-dismiss error"></b>';
						if ( $old_path !== $new_path && @rename( $old_path, $new_path ) ) {
							$new_meta['file'] = $subdir . $new_filename;

							if ( ! empty( $meta['original_image'] ) && $meta['original_image'] != $meta['file'] ) {
								$orig_old_path = $basedir . $subdir . $meta['original_image'];
								$orig_new_path = $basedir . $subdir . $new_filename;
								@rename( $orig_old_path, $orig_new_path );
								$new_meta['original_image'] = $new_filename;
							}

							$change_log = '';
							$size_count = 0;
							if ( ! empty( $meta['sizes'] ) ) {
								foreach ( $meta['sizes'] as $size => $image ) {
									if ( ! empty( $image['file'] )
										&& ( $base_one == $image['file'] || $new_filename == $image['file'] ) ) {
										// The file is the same as the full size or already renamed.
										// @TODO !!!!!!!
										$new_meta['sizes'][ $size ]['file'] = $new_filename;

										++ $size_count;
										$change_log .= '<li class="sirsc_imgseo-toggle maybe-hidden">' . $old_path . ' -> ' . $new_path . '</li>';
									} else {
										// This is a regular image size.
										$fname    = $filename . '-' . $image['width'] . 'x' . $image['height'] . '.' . $maybe_type['ext'];
										$size_old = $basedir . $subdir . $image['file'];
										$size_new = $basedir . $subdir . $fname;
										if ( file_exists( $size_old ) ) {
											@rename( $size_old, $size_new );
											do_action( 'sirsc_seo_file_renamed', $id, $size_old, $size_new );

											++ $size_count;
											$change_log .= '<li class="sirsc_imgseo-toggle maybe-hidden">' . $size_old . ' -> ' . $size_new . '</li>';
										}
										$new_meta['sizes'][ $size ]['file'] = $fname;
									}
								}
							}

							$maybe_toggle = '';
							if ( ! empty( $size_count ) ) {
								$maybe_toggle = '<p onclick="jQuery(this).parent().parent().find(\'.sirsc_imgseo-toggle\').toggleClass(\'maybe-hidden\');" style="cursor:pointer"><b>' . sprintf(
									// Translators: %1$d - image sized replaced.
									__( ' + %1$d more image sizes that were found for this', 'sirsc' ),
									$size_count
								) . ' <span class="dashicons dashicons-arrow-down-alt2"></span> </b></p>';
							}
							$change_log = '
							<ul>
								<li>' . $old_path . ' -> ' . $new_path . $maybe_toggle . '</li>
								' . $change_log . '
							</ul>';

							wp_update_attachment_metadata( $id, $new_meta );
							update_post_meta( $id, '_wp_attached_file', $subdir . $new_filename );
							$renamed = '<b class="dashicons dashicons-yes-alt success"></b>';
						}
					}
				}
			}

			$change_log = str_replace( $basedir, '', $change_log );
			$change_log = str_replace( $tmp_name, '<b>' . $tmp_name . '</b>', $change_log );

			if ( true === $output ) {
				if ( ! empty( $extra_hints ) ) {
					$extra      = '<li>' . implode( ' &bull; ', $extra_hints ) . '</li>';
					$change_log = str_replace( '</ul>', $extra . '</ul>', $change_log );
				}
				echo '
				<div class="sirsc_imgseo-item-processed sirsc_imgseo-label-wrap-' . $type . '">' . $renamed . '
					<label class="sirsc_imgseo-label-info">' . $type . '</label> ' . esc_html__( 'Attachment ID' ) . ' <b>' . $id . '</b> | ' . esc_html__( 'New Title' ) . ' <strong>' . $title . '</strong>' . $change_log . $message . '
				</div>'; // WPCS: XSS OK.
			}

			// Attempt to clear the attachment cache.
			clean_post_cache( $id );
			clean_attachment_cache( $id );
		}
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function rename_metaboxes() {
		if ( ! empty( self::$settings['types'] ) ) {
			add_meta_box(
				'sirsc_imgseo_rename_meta',
				__( 'Images SEO', 'sirsc' ),
				array( get_called_class(), 'rename_metaboxes_meta' ),
				self::$settings['types'],
				'side',
				'default'
			);
		}
	}

	/**
	 * Exposes the custom wishlist info in the orders edit page sidebar box.
	 *
	 * @return void
	 */
	public static function rename_metaboxes_meta() {
		global $post;
		if ( ! empty( $post->ID ) ) {
			?>
			<div class="sirsc_imgseo_meta">
			<p>
				<span class="dashicons dashicons-image-rotate-right"></span>
				<?php if ( 'attachment' === $post->post_type ) : ?>
					<?php esc_html_e( 'You can rename this attachment files (including the files generated as image sizes) and other attributes.', 'sirsc' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'You can rename and update attributes of some of the files already uploaded or attached to this post.', 'sirsc' ); ?>
				<?php endif; ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::ADON_PAGE_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'sirsc' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::ADON_PAGE_SLUG . '&tab=rename&target=' . $post->ID ) ); ?>" class="button"><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Images SEO', 'sirsc' ); ?></a>
			</p></div>
			<?php
		}
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function images_admin_menu() {
		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Images SEO', 'sirsc' ),
			'<span class="dashicons dashicons-admin-plugins sirsc-mini"></span> ' . __( 'Images SEO', 'sirsc' ),
			'manage_options',
			self::ADON_PAGE_SLUG,
			array( get_called_class(), 'images_settings' )
		);
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function images_settings() {
		$tab  = filter_input( INPUT_GET, 'tab', FILTER_DEFAULT );
		$id   = filter_input( INPUT_GET, 'target', FILTER_VALIDATE_INT );
		SIRSC_Adons::check_adon_valid( self::ADON_SLUG );
		$desc = SIRSC_Adons::get_adon_details( self::ADON_SLUG, 'description' );

		$settings = self::$settings;
		if ( empty( self::$post_types ) ) {
			self::init_settings();
			$settings = self::$settings;
		}

		?>

		<div class="wrap sirsc-settings-wrap">
			<h1>
				<?php SIRSC_Image_Regenerate_Select_Crop::show_plugin_icon(); ?> <?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</h1>

			<?php SIRSC_Image_Regenerate_Select_Crop::maybe_all_features_tab(); ?>
			<div class="sirsc-tabbed-menu-content">
				<h1><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Images SEO', 'sirsc' ); ?></h1>
				<br>

				<table class="widefat sirsc-striped">
					<tr>
						<td><?php echo wp_kses_post( $desc ); ?></td>
					</tr>
				</table>
				<br>

				<div class="sirsc_imgseo-menu-buttons">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::ADON_PAGE_SLUG ) ); ?>"
						class="button <?php if ( empty( $tab ) ) : ?>
						button-primary<?php endif; ?>"
						><?php esc_html_e( 'Settings', 'sirsc' ); ?></a>

					<?php if ( ! empty( $id ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::ADON_PAGE_SLUG . '&tab=rename&target=' . $id ) ); ?>"
						class="button <?php if ( 'rename' === $tab ) : ?>
						button-primary<?php endif; ?>"
						><?php esc_html_e( 'Rename Images', 'sirsc' ); ?></a>
					<?php endif; ?>

					<?php if ( ! empty( $settings['bulk'] ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::ADON_PAGE_SLUG . '&tab=bulk-rename' ) ); ?>"
							class="button <?php if ( 'bulk-rename' === $tab ) : ?>
							button-primary<?php endif; ?>"
							><?php esc_html_e( 'Bulk Rename Images', 'sirsc' ); ?></a>
					<?php endif; ?>
				</div>

				<div class="sirsc_imgseo-action-wrap">
					<?php
					if ( empty( $tab ) ) {
						self::form_settings_output();
					} elseif ( ! empty( $id ) && 'rename' === $tab ) {
						self::form_rename_output( $id );
					} elseif ( 'bulk-rename' === $tab ) {
						self::form_bulk_rename_output();
					}
					?>
				</div>
			</div>

			<?php self::maybe_rename_form_execute(); ?>
			<?php self::maybe_bulk_rename_form_execute(); ?>
		</div>

		<?php
	}

	/**
	 * Outputs the plugin settings form.
	 *
	 * @return void
	 */
	public static function form_settings_output() {
		$types    = self::$post_types;
		if ( empty( $types ) ) {
			self::init_settings();
			$types = self::$post_types;
		}

		$settings = self::$settings;
		?>
		<form action="" method="post" autocomplete="off" id="js-sirsc_imgseo-frm-settings">
			<?php wp_nonce_field( '_sirsc_imgseo_settings_action', '_sirsc_imgseo_settings_nonce' ); ?>

			<table class="fixed" cellspacing="20" width="100%">
				<tr>
					<td width="33%">
						<h3><span class="dashicons dashicons-feedback"></span> <?php esc_html_e( 'What Does Images SEO Do?', 'sirsc' ); ?></h3>
						<p class="sirsc_imgseo-box"><?php esc_html_e( 'You can enable/disable any of the actions that the SEO rename extension is providing. The ones enabled will be used for processing images on upload, on bulk rename, and on manual rename too.', 'sirsc' ); ?></p>
					</td>
					<td>

						<div>
							<label class="sirsc_imgseo-label" for="_sirsc_imgseo_settings_override_filename">
								<input type="checkbox"
									name="_sirsc_imgseo_settings[override_filename]"
									id="_sirsc_imgseo_settings_override_filename"
									<?php checked( true, $settings['override_filename'] ); ?>
									onclick="jQuery('#_sirsc_imgseo_settings_track_initial-wrap').toggleClass('wrap-disabled');">
								<?php esc_html_e( 'Rename Files', 'sirsc' ); ?>
							</label>
							<?php esc_html_e( 'Enable this to rename the attachment files (also the image sizes generated).', 'sirsc' ); ?>
						</div>

						<div id="_sirsc_imgseo_settings_track_initial-wrap"
							class="<?php if ( empty( $settings['override_filename'] ) ) { echo esc_attr( 'wrap-disabled' ); } ?>">
							<label class="sirsc_imgseo-label" for="_sirsc_imgseo_settings_track_initial">
								<input type="checkbox"
									name="_sirsc_imgseo_settings[track_initial]"
									id="_sirsc_imgseo_settings_track_initial"
									<?php checked( true, $settings['track_initial'] ); ?>>
								<?php esc_html_e( 'Track Initial File', 'sirsc' ); ?>
							</label>
							<?php esc_html_e( 'Enable this to keep a record of the initial filename if the file is renamed.', 'sirsc' ); ?>
						</div>

						<div>
							<label class="sirsc_imgseo-label" for="_sirsc_imgseo_settings_override_title">
								<input type="checkbox"
									name="_sirsc_imgseo_settings[override_title]"
									id="_sirsc_imgseo_settings_override_title"
									<?php checked( true, $settings['override_title'] ); ?>>
								<?php esc_html_e( 'Override Title', 'sirsc' ); ?>
							</label>
							<?php esc_html_e( 'Enable this to override the attachment title with the inherited title.', 'sirsc' ); ?>
						</div>

						<div>
							<label class="sirsc_imgseo-label" for="_sirsc_imgseo_settings_override_alt">
								<input type="checkbox"
									name="_sirsc_imgseo_settings[override_alt]"
									id="_sirsc_imgseo_settings_override_alt"
									<?php checked( true, $settings['override_alt'] ); ?>>
								<?php esc_html_e( 'Override Alternative', 'sirsc' ); ?>
							</label>
							<?php esc_html_e( 'Enable this to override the attachment alternative text with the inherited title.', 'sirsc' ); ?>
						</div>

						<div>
							<label class="sirsc_imgseo-label" for="_sirsc_imgseo_settings_override_permalink">
								<input type="checkbox"
									name="_sirsc_imgseo_settings[override_permalink]"
									id="_sirsc_imgseo_settings_override_permalinkt"
									<?php checked( true, $settings['override_permalink'] ); ?>>
								<?php esc_html_e( 'Override Permalink', 'sirsc' ); ?>
							</label>
							<?php esc_html_e( 'Enable this to override the attachment permalink with the inherited title.', 'sirsc' ); ?>
						</div>
					</td>
				</tr>
				<tr>
					<td colspan="2"><hr></td>
				</tr>

				<tr>
					<td>
						<h3><span class="dashicons dashicons-feedback"></span> <?php esc_html_e( 'Show rename button', 'sirsc' ); ?></h3>
						<p class="sirsc_imgseo-box"><?php esc_html_e( 'For the selected post types there will be shown a meta box with the button to rename the associated files.', 'sirsc' ); ?></p>
					</td>
					<td>
						<?php if ( ! empty( $types ) ) : ?>
							<?php foreach ( $types as $type => $name ) : ?>
								<label class="sirsc_imgseo-label" for="_sirsc_imgseo_settings_types_<?php echo esc_attr( $type ); ?>">
									<input type="checkbox"
										name="_sirsc_imgseo_settings[types][<?php echo esc_attr( $type ); ?>]"
										id="_sirsc_imgseo_settings_types_<?php echo esc_attr( $type ); ?>"
										<?php checked( true, in_array( $type, $settings['types'] ) ); ?>>
									<?php echo esc_html( $name ); ?>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td colspan="2"><hr></td>
				</tr>

				<tr>
					<td>
						<h3><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Rename images on upload', 'sirsc' ); ?></h3>
						<p class="sirsc_imgseo-box"><?php esc_html_e( 'Attempt to automatically rename the files on upload to these post types (these post parent types).', 'sirsc' ); ?></p>
					</td>
					<td>
						<?php unset( $types['attachment'] ); ?>
						<?php foreach ( $types as $type => $name ) : ?>
							<label class="sirsc_imgseo-label" for="_sirsc_imgseo_settings_upload_<?php echo esc_attr( $type ); ?>">
								<input type="checkbox"
									name="_sirsc_imgseo_settings[upload][<?php echo esc_attr( $type ); ?>]"
									id="_sirsc_imgseo_settings_upload_<?php echo esc_attr( $type ); ?>"
									<?php checked( true, in_array( $type, $settings['upload'] ) ); ?>>
								<?php echo esc_html( $name ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<td colspan="2"><hr></td>
				</tr>

				<tr>
					<td>
						<h3><span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Bulk rename images for types', 'sirsc' ); ?></h3>
						<p class="sirsc_imgseo-box"><?php esc_html_e( 'These will be the post types that will be available to select in the bulk rename process.', 'sirsc' ); ?></p>
					</td>
					<td>
						<?php foreach ( $types as $type => $name ) : ?>
							<label class="sirsc_imgseo-label" for="_sirsc_imgseo_settings_bulk_<?php echo esc_attr( $type ); ?>">
								<input type="checkbox"
									name="_sirsc_imgseo_settings[bulk][<?php echo esc_attr( $type ); ?>]"
									id="_sirsc_imgseo_settings_bulk_<?php echo esc_attr( $type ); ?>"
									<?php checked( true, in_array( $type, $settings['bulk'] ) ); ?>>
								<?php echo esc_html( $name ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<td colspan="2"><hr></td>
				</tr>

				<tr>
					<td>
						<?php
						submit_button( __( 'Save Settings', 'sirsc' ), 'primary', '', false, array(
							'onclick' => 'jQuery(\'#js-sirsc_imgseo-frm-settings\').addClass(\'js-sirsc_imgseo processing\');',
						) );
						?>
					</td>
					<td>
						<?php esc_html_e( 'Please note that any of the rename process options (on upload, manual rename, bulk rename) will take into account the currently enabled settings, this will not apply retroactively.', 'sirsc' ); ?>

					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	/**
	 * Outputs the rename form.
	 *
	 * @param  integer $id Post ID.
	 * @return void
	 */
	public static function form_rename_output( $id ) {
		?>
		<form action="" method="post" autocomplete="off">
			<?php wp_nonce_field( '_sirsc_imgseo_dorename_action', '_sirsc_imgseo_dorename_nonce' ); ?>
			<?php $post = get_post( $id ); ?>
			<?php if ( $post instanceof WP_Post ) : ?>
				<input type="hidden" name="sirsc_imgseo_type" value="<?php echo esc_attr( $post->post_type ); ?>">
				<input type="hidden" name="sirsc_imgseo_id" value="<?php echo (int) $id; ?>">

				<table class="fixed" cellspacing="20" width="100%">
					<tr>
						<?php if ( 'attachment' === $post->post_type ) : ?>
							<td width="50%">
								<h3><span class="dashicons dashicons-image-rotate-right"></span> <?php esc_html_e( 'Rename Attachment File', 'sirsc' ); ?></h3><hr>
								<p><?php esc_html_e( 'You can change the title below, then click the button to rename the attachment file, and the generated image sizes.', 'sirsc' ); ?></p>
								<table class="fixed" width="100%" cellspacing="0" cellpadding="0">
									<tr>
										<td>
											<input type="text" name="sirsc_imgseo-renamefile-title" id="sirsc_imgseo-renamefile-title" value="<?php echo esc_attr( $post->post_title ); ?>">
										</td>
										<td width="180" align="right">
											<?php
											submit_button( __( 'Rename', 'sirsc' ), 'primary', '', false, array(
												'onclick' => 'jQuery(\'.sirsc_imgseo-action-wrap\').addClass(\'js-sirsc_imgseo processing\');',
											) );
											?>
										</td>
									</tr>
								</table>
							</td>
							<td>
								<?php $atts = self::get_attachments_by_id( $id ); ?>
								<?php if ( ! empty( $atts ) ) : ?>
									<h3><?php esc_html_e( 'Attachment Image', 'sirsc' ); ?></h3><hr>
									<ul>
										<?php foreach ( $atts as $att ) : ?>
											<li>
												<?php esc_html_e( 'Go to', 'sirsc' ); ?> <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $att['id'] . '&action=edit' ) ); ?>"><em><?php echo esc_attr( $att['id'] ); ?></em></a>
												| <?php echo esc_html( $att['type'] ); ?>
												| <b><?php echo esc_html( $att['filename'] ); ?></b>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								<hr>
								<?php esc_html_e( 'Go to', 'sirsc' ); ?> <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>"><em><?php echo esc_attr( $post->post_title ); ?></em></a>
							</td>
						<?php else : ?>
							<td width="50%">
								<h3><span class="dashicons dashicons-image-rotate-right"></span> <?php esc_html_e( 'Rename images attached to the post', 'sirsc' ); ?></h3><hr>
								<p><?php esc_html_e( 'You can change the title below, then click the button to rename the identifies images associated with this post, and their generated image sizes.', 'sirsc' ); ?></p>
								<table class="fixed" width="100%" cellspacing="0" cellpadding="0">
									<tr>
										<td>
											<input type="text" name="sirsc_imgseo-renamefile-title" id="sirsc_imgseo-renamefile-title" value="<?php echo esc_attr( $post->post_title ); ?>">
										</td>
										<td width="180" align="right">
											<?php
											submit_button( __( 'Rename', 'sirsc' ), 'primary', '', false, array(
												'onclick' => 'jQuery(\'.sirsc_imgseo-action-wrap\').addClass(\'js-sirsc_imgseo processing\');',
											) );
											?>
										</td>
									</tr>
								</table>
							</td>
							<td>
								<?php $atts = self::get_attachments_by_post( $id ); ?>
								<?php if ( ! empty( $atts ) ) : ?>
									<h3><?php esc_html_e( 'Images Attached to the post', 'sirsc' ); ?></h3><hr>
									<ul>
										<?php foreach ( $atts as $att ) : ?>
											<li>
												<?php esc_html_e( 'Go to', 'sirsc' ); ?> <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $att['id'] . '&action=edit' ) ); ?>"><em><?php echo esc_attr( $att['id'] ); ?></em></a>
												| <?php echo esc_html( $att['type'] ); ?>
												| <b><?php echo esc_html( $att['filename'] ); ?></b>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								<hr>
								<?php esc_html_e( 'Go to', 'sirsc' ); ?> <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>"><em><?php echo esc_attr( $post->post_title ); ?></em></a>
							</td>
						<?php endif; ?>
					</tr>
				</table>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Outputs the bulk rename form.
	 *
	 * @return void
	 */
	public static function form_bulk_rename_output() {
		$settings = self::$settings;
		if ( ! empty( $settings['bulk'] ) ) {
			$settings['bulk'] = array_diff( $settings['bulk'], array( 'attachment' ) );
		}

		$types = $settings['bulk'];
		$bulk  = filter_input( INPUT_POST, '_sirsc_imgseo_bulk_update', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		?>
		<form action="" method="post" autocomplete="off">
			<?php wp_nonce_field( '_sirsc_imgseo_bulk_action', '_sirsc_imgseo_bulk_nonce' ); ?>

			<div class="js-sirsc_imgseo">

				<table class="fixed" cellspacing="20" width="100%">
					<tr>
						<td width="50%">
							<h3><span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Bulk Rename Images', 'sirsc' ); ?></h3>
							<p class="sirsc_imgseo-box">
								<?php echo wp_kses_post( __( 'The bulk rename process is targeting images set as <b>featured image</b> (for all post types selected that support the featured image feature) or attached as <b>media</b> (uploaded to that posts as children).', 'sirsc' ) ); ?>
								<?php if ( in_array( 'product', $types ) ) : ?>
									<?php echo wp_kses_post( __( 'For products, the rename will include also the <b>gallery images</b>.', 'sirsc' ) ); ?>
								<?php endif; ?>
								<?php esc_html_e( 'Please note that any of the rename process options (on upload, manual rename, bulk rename) will override the attachment attributes based on the images SEO settings you made.', 'sirsc' ); ?>
							</p>
						</td>
						<td>
							<?php foreach ( $types as $type ) : ?>
								<?php $type_on = ( ! empty( $types[ $type ] ) ) ? 'on' : ''; ?>
								<label class="sirsc_imgseo-label" class="sirsc_imgseo-label-<?php echo esc_attr( $type ); ?>"
									for="_sirsc_imgseo_bulk_update_<?php echo esc_attr( $type ); ?>">
									<input type="checkbox"
										name="_sirsc_imgseo_bulk_update[<?php echo esc_attr( $type ); ?>]"
										id="_sirsc_imgseo_bulk_update_<?php echo esc_attr( $type ); ?>"
										<?php checked( 'on', $type_on ); ?>> <?php echo esc_html( self::$post_types[ $type ] ); ?>
								</label>
							<?php endforeach; ?>
							<?php
							submit_button( __( 'Start Bulk Rename', 'sirsc' ), 'primary', '', false, array(
								'onclick' => 'jQuery(\'.js-sirsc_imgseo\').addClass(\'processing\')',
							) );
							?>
						</td>
					</tr>
				</table>
			</div>
		</form>
		<?php
	}

	/**
	 * Regenerate attachment filenames by post parent.
	 *
	 * @param  integer $id    Post ID.
	 * @param  string  $title The expected title.
	 * @return void
	 */
	public static function regenerate_filenames_by_post( $id, $title = '' ) {
		$title = ( empty( $title ) ) ? get_the_title( $id ) : $title;
		$type  = get_post_type( $id );
		$items = self::get_attachments_by_post( $id );
		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				self::rename_image_filename( $item['id'], $title, $item['count'], $type );
			}
		}
	}

	/**
	 * Maybe run the individual rename.
	 *
	 * @return void
	 */
	public static function maybe_rename_form_execute() {
		$rename = filter_input( INPUT_POST, '_sirsc_imgseo_dorename_nonce', FILTER_DEFAULT );
		if ( ! empty( $rename ) && wp_verify_nonce( $rename, '_sirsc_imgseo_dorename_action' ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				$type  = filter_input( INPUT_POST, 'sirsc_imgseo_type', FILTER_DEFAULT );
				$title = filter_input( INPUT_POST, 'sirsc_imgseo-renamefile-title', FILTER_DEFAULT );
				$id    = filter_input( INPUT_POST, 'sirsc_imgseo_id', FILTER_VALIDATE_INT );
				if ( ! empty( $id ) ) {
					if ( 'attachment' === $type ) {
						?>
						<h3 id="rename-result" name="rename-result" class="sirsc_imgseo-as-h3 js-sirsc_imgseo_processing_title"><?php esc_html_e( 'Attachment Rename Result', 'sirsc' ); ?></h3>
						<div id="sirsc_imgseo-images-process-wrap">
							<?php self::rename_image_filename( $id, $title, 0, $type ); ?>
						</div>
						<script>jQuery(document).ready(function(){ jQuery('html, body').animate({ scrollTop: jQuery('#rename-result').offset().top - 42 }, 500); });</script>
						<?php
					} else {
						?>
						<h3 id="rename-result" name="rename-result" class="sirsc_imgseo-as-h3 js-sirsc_imgseo_processing_title"><?php esc_html_e( 'Images Attached to the Post Rename Result', 'sirsc' ); ?></h3>
						<div id="sirsc_imgseo-images-process-wrap">
							<?php self::regenerate_filenames_by_post( $id, $title ); ?>
						</div>
						<script>jQuery(document).ready(function(){ jQuery('html, body').animate({ scrollTop: jQuery('#rename-result').offset().top - 42 }, 500); });</script>
						<?php
					}
				}
			}
		}
	}

	/**
	 * Maybe initiate the bulk rename process.
	 *
	 * @return void
	 */
	public static function maybe_bulk_rename_form_execute() {
		$bulk = filter_input( INPUT_POST, '_sirsc_imgseo_bulk_update', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		if ( ! empty( $bulk ) ) :
			?>
			<?php self::maybe_bulk_process_form( $bulk ); ?>
			<?php
		endif;
	}

	/**
	 * Maybe run the bulk process form.
	 *
	 * @param  string $bulk List of post types.
	 * @return void
	 */
	public static function maybe_bulk_process_form( $bulk ) {
		$used_nonce = filter_input( INPUT_POST, '_sirsc_imgseo_bulk_nonce', FILTER_DEFAULT );
		if ( ! empty( $used_nonce ) && wp_verify_nonce( $used_nonce, '_sirsc_imgseo_bulk_action' ) && ! empty( $bulk ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				esc_html_e( 'Action not allowed.', 'sirsc' );
			}

			global $wpdb;
			$types = implode( ',', array_keys( $bulk ) );
			$total = $wpdb->get_var( self::rename_get_query( $types, 0, true ) ); // phpcs:ignore
			?>
			<form id="sirsc_imgseo-images-process-frm" action="" method="post" autocomplete="off">
				<input type="hidden" name="action" id="action" value="sirsc_imgseo_image_process_paginated_images">
				<input type="hidden" name="sirsc_imgseo_type" id="sirsc_imgseo_type" value="<?php echo esc_attr( $types ); ?>">
				<input type="hidden" name="sirsc_imgseo_last_id" id="sirsc_imgseo_last_id" value="0">
				<input type="hidden" name="sirsc_imgseo_total" id="sirsc_imgseo_total" value="<?php echo (int) $total; ?>">
				<input type="hidden" name="sirsc_imgseo_processed" id="sirsc_imgseo_processed" value="0">
			</form>

			<h3 id="bulk-rename-result" class="sirsc_imgseo-as-h3 js-sirsc_imgseo_processing_title"><?php esc_html_e( 'The rename request is processing' ); ?></h3>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::ADON_PAGE_SLUG . '&tab=bulk-rename' ) ); ?>" class="button js-sirsc_imgseo_stop_bulk"><?php esc_html_e( 'Stop Processing', 'sirsc' ); ?></a></p>

			<div id="sirsc_imgseo-images-process-wrap" class="js-sirsc_imgseo">
				<?php self::show_progress_bar( 0, 0, $total, self::PROCESS_BATCH_SIZE ); ?>
			</div>
			<script>
				jQuery(document).ready(function() {
					sirsc_imgseo_process_bulk();
				});
			</script>
			<?php
		}
	}

	/**
	 * Compute the rename query.
	 *
	 * @param  string  $type     The post types list.
	 * @param  integer $prev     A previous processed attachment/post ID.
	 * @param  boolean $is_count The query is for count.
	 * @return string
	 */
	public static function rename_get_query( $type, $prev = 0, $is_count = false ) {
		global $wpdb;

		$types = explode( ',', $type );
		$use_products = ( in_array( 'product', $types ) ) ? true : false;

		if ( 0 === self::RENAME_QUERY_TYPE ) {
			if ( true === $is_count ) {
				$query = $wpdb->prepare(
					'SELECT count(p.ID)
					 FROM ' . $wpdb->posts . ' as p
					 INNER JOIN ' . $wpdb->postmeta . ' as pm ON (p.ID = pm.post_id AND ( pm.meta_key = %s OR pm.meta_key = %s ) )
					 LEFT OUTER JOIN ' . $wpdb->posts . ' as a ON ( a.post_parent = p.ID )
					 WHERE FIND_IN_SET( p.post_type, %s )
					 AND ( pm.meta_value IS NOT NULL OR a.post_type = %s ) ',
					'_thumbnail_id',
					'_product_image_gallery',
					$type,
					'attachment'
				);
			} else {
				$query = $wpdb->prepare(
					'SELECT p.ID, p.post_title as parent_title FROM ' . $wpdb->posts . ' as p
					 INNER JOIN ' . $wpdb->postmeta . ' as pm ON (p.ID = pm.post_id AND ( pm.meta_key = %s OR pm.meta_key = %s ) )
					 LEFT OUTER JOIN ' . $wpdb->posts . ' as a ON ( a.post_parent = p.ID )
					 WHERE FIND_IN_SET( p.post_type, %s )
					 AND ( pm.meta_value IS NOT NULL OR a.post_type = %s )
					 AND p.ID > %d ORDER BY p.ID ASC LIMIT 0, %d',
					'_thumbnail_id',
					'_product_image_gallery',
					$type,
					'attachment',
					$prev,
					self::PROCESS_BATCH_SIZE
				);
			}
		} else {
			$qstr = '';
			$args = array();

			// The attachments set as featured.
			$qstr = '
			(
				SELECT a.ID as ID, a.post_title as attachment_title, a.post_parent as post_parent
				FROM ' . $wpdb->posts . ' as a
				INNER JOIN ' . $wpdb->postmeta . ' as pm ON (pm.meta_value = a.ID and pm.meta_key = %s)
				INNER JOIN ' . $wpdb->posts . ' as thp ON (pm.post_id = thp.ID)
				WHERE a.post_type = %s
				AND thp.post_title IS NOT NULL
				AND FIND_IN_SET(thp.post_type, %s)
				AND (thp.post_status != %s AND thp.post_status != %s)
			)';
			$args[] = '_thumbnail_id';
			$args[] = 'attachment';
			$args[] = $type;
			$args[] = 'trash';
			$args[] = 'auto-draft';

			// The attachments set as media (children).
			$qstr .= '
			UNION
			(
				SELECT a.ID as ID, a.post_title as attachment_title, a.post_parent as post_parent
				FROM ' . $wpdb->posts . ' as a
				INNER JOIN ' . $wpdb->posts . ' as pp ON (pp.ID = a.post_parent)
				WHERE a.post_type = %s
				AND pp.post_title IS NOT NULL
				AND FIND_IN_SET(pp.post_type, %s)
				AND (pp.post_status != %s AND pp.post_status != %s)
			)';
			$args[] = 'attachment';
			$args[] = $type;
			$args[] = 'trash';
			$args[] = 'auto-draft';

			if ( true === $use_products ) {
				// The product gallery images.
				$qstr .= '
				UNION
				(
					SELECT a.ID as ID, a.post_title as attachment_title, a.post_parent as post_parent
					FROM ' . $wpdb->posts . ' as a
					INNER JOIN ' . $wpdb->postmeta . ' as pm2 ON (pm2.meta_value = a.ID and pm2.meta_key = %s)
					INNER JOIN ' . $wpdb->posts . ' as pr ON (pm2.post_id = pr.ID)
					WHERE a.post_type = %s
					AND pr.post_title IS NOT NULL
					AND pr.post_type = %s
					AND (pr.post_status != %s AND pr.post_status != %s)
				)
				';
				$args[] = '_product_image_gallery';
				$args[] = 'attachment';
				$args[] = 'product';
				$args[] = 'trash';
				$args[] = 'auto-draft';
			}

			if ( true === $is_count ) {
				$qstr  = ' SELECT count(u.ID) FROM ( ' . $qstr . ') as u ';
				$query = $wpdb->prepare( $qstr, $args ); // phpcs:ignore
			} else {
				$qstr   = ' SELECT * FROM ( ' . $qstr . ') as u WHERE u.ID > %d ORDER BY u.ID ASC LIMIT 0, %d ';
				$args[] = $prev;
				$args[] = self::PROCESS_BATCH_SIZE;
				$query  = $wpdb->prepare( $qstr, $args ); // phpcs:ignore
			}
		}

		return $query;
	}

	/**
	 * Show a progress bar.
	 *
	 * @param integer $items_proc The total items processed.
	 * @param integer $processed  The percent processed.
	 * @param integer $total      The total.
	 * @param integer $batch      The current batch count.
	 * @return void
	 */
	public static function show_progress_bar( $items_proc = 0, $processed = 0, $total = 0, $batch = 0 ) {
		?>
		<table class="fixed" width="100%">
			<tr>
				<td width="40%">
					<?php
					echo esc_html( sprintf(
						// Translators: %1$d - count products, %2$d - total.
						__( 'Processed the filename replacement for %1$d items of %2$d.', 'sirsc' ),
						$items_proc,
						$total
					) );
					?>
				</td>
				<td>
					<div class="progress">
						<div class="processed" style="width:<?php echo (int) $processed; ?>%"><?php echo (int) $processed; ?>%</div>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Execute the processing of each items batch rename.
	 *
	 * @return void
	 */
	public static function image_process_paginated_images() {
		global $wpdb;

		$post_type = filter_input( INPUT_POST, 'sirsc_imgseo_type', FILTER_DEFAULT );
		$prev_id   = filter_input( INPUT_POST, 'sirsc_imgseo_last_id', FILTER_VALIDATE_INT );
		$total     = filter_input( INPUT_POST, 'sirsc_imgseo_total', FILTER_VALIDATE_INT );
		$processed = filter_input( INPUT_POST, 'sirsc_imgseo_processed', FILTER_VALIDATE_INT );

		if ( ! empty( $post_type ) ) {
			$rows = $wpdb->get_results( self::rename_get_query( $post_type, $prev_id ) ); // phpcs:ignore
			if ( ! empty( $rows ) ) {
				$proc = ceil( $processed * 100 / $total );
				self::show_progress_bar( $processed, $proc, $total, count( $rows ) );

				foreach ( $rows as $row ) {
					$prev_id = (int) $row->ID;
					if ( 0 === self::RENAME_QUERY_TYPE ) {
						self::regenerate_filenames_by_post( $row->ID );
					} else {
						$info = self::assess_attachment_title( $row, $post_type );
						self::rename_image_filename( $row->ID, $info['title'], 0, $info['parent_type'], true, $info['message'] );
					}
					++ $processed;
				}
				?>
				<script>
					jQuery(document).ready(function() {
						sirsc_imgseo_process_bulk_continue('<?php echo (int) $prev_id; ?>', '<?php echo (int) $processed; ?>');
					});
				</script>
				<?php
			} else {
				?>
				<div class="progress">
					<div class="processed" style="width:100%">100%</div>
				</div>
				<script>
					jQuery(document).ready(function() {
						sirsc_imgseo_process_bulk_stop('');
					});
				</script>
				<?php
			}
		}
		die();
	}

	/**
	 * Assess attachment potential title by priority.
	 *
	 * @param  object $row  Attachment info.
	 * @param  string $type Query post type.
	 * @return string
	 */
	public static function assess_attachment_title( $row, $type ) {
		// Assess in the order of priority.
		$query_args = array(
			'post_type'   => explode( ',', $type ),
			'post_status' => 'any',
			'meta_query'  => array(
				array(
					'key'   => '_thumbnail_id',
					'value' => $row->ID,
				),
			),
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		);
		$query = new WP_Query( $query_args );
		if ( ! empty( $query->posts[0]->post_title ) ) {
			return array(
				'title'       => $query->posts[0]->post_title,
				'message'     => __( 'The image inherited the title from the post that is using this as featured image.', 'sirsc' ),
				'parent_type' => $query->posts[0]->post_type,
			);
		}

		// Assess if the image is used in a product gallery.
		$query_args = array(
			'post_type'   => explode( ',', $type ),
			'post_status' => 'any',
			'meta_query'  => array(
				array(
					'key'     => '_product_image_gallery',
					'value'   => $row->ID,
					'compare' => 'LIKE',
				),
			),
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		);
		$query = new WP_Query( $query_args );
		if ( ! empty( $query->posts[0]->post_title ) ) {
			return array(
				'title'       => $query->posts[0]->post_title,
				'message'     => __( 'The image inherited the title from the product that is using this as gallery image.', 'sirsc' ),
				'parent_type' => $query->posts[0]->post_type,
			);
		}

		// Assess if the image has a parent.
		if ( ! empty( $row->post_parent ) ) {
			$query_args = array(
				'post_type'   => explode( ',', $type ),
				'post_status' => 'any',
				'post__in'    => array( $row->post_parent ),
				'numberposts' => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			);
			$query = new WP_Query( $query_args );
			if ( ! empty( $query->posts[0]->post_title ) ) {
				return array(
					'title'       => $query->posts[0]->post_title,
					'message'     => __( 'The image inherited the title from the post parent of the image.', 'sirsc' ),
					'parent_type' => $query->posts[0]->post_type,
				);
			}
		}

		// Assess if the attachment title is used.
		if ( ! empty( $row->attachment_title ) ) {
			return array(
				'title'       => $row->attachment_title,
				'message'     => __( 'The image inherited the title from the attachment title.', 'sirsc' ),
				'parent_type' => 'attachment',
			);
		}

		return array(
			'title'       => '',
			'message'     => '',
			'parent_type' => '',
		);
	}

	/**
	 * Attempt to generate a unique filename.
	 *
	 * @param  string  $dir     Base directory.
	 * @param  string  $title   Parent title.
	 * @param  string  $type    Attachment mime type.
	 * @param  integer $count   A potential suffix.
	 * @param  string  $initial The initial filename (with the path too).
	 * @return string
	 */
	public static function generate_filename( $dir, $title, $type, $count = 0, $initial = '' ) {
		$new_filename = '';
		while ( '' === $new_filename ) {
			$suffix   = ( ! empty( $count ) ? '-' . $count : '' );
			$maxlen   = 80 - strlen( $suffix . '.' . $type ) - 1;
			$filename = substr( sanitize_title( $title ), 0, $maxlen ) . $suffix;

			if ( $dir . $filename . '.' . $type === $initial ) {
				$new_filename = $filename;
			}
			if ( ! empty( $filename ) && ! file_exists( $dir . $filename . '.' . $type ) ) {
				$new_filename = $filename;
			}
			++ $count;
		}

		return $new_filename;
	}

	/**
	 * Identify the attachment filenames by post parent.
	 *
	 * @param  integer $id Post ID.
	 * @return array
	 */
	public static function get_attachments_by_post( $id ) {
		$all   = array();
		$upls  = wp_upload_dir();
		$base  = trailingslashit( $upls['baseurl'] );
		$items = array();
		$title = get_the_title( $id );
		$meta  = get_post_meta( $id, '_thumbnail_id', true );
		if ( ! empty( $meta ) ) {
			$filename = wp_get_attachment_image_src( (int) $meta, 'full' );
			$items[]  = array(
				'type'      => 'featured image',
				'id'        => (int) $meta,
				'count'     => 0,
				'new_title' => $title,
				'filename'  => ( ! empty( $filename[0] ) ) ? str_replace( $base, '', $filename[0] ) : '',
			);
			$all[] = (int) $meta;
		}

		$count = 0;
		$meta  = get_post_meta( $id, '_product_image_gallery', true );
		if ( ! empty( $meta ) ) {
			$list = explode( ',', $meta );
			foreach ( $list as $iid ) {
				$iid = (int) $iid;
				if ( ! in_array( $iid, $all ) ) {
					$filename = wp_get_attachment_image_src( $iid, 'full' );
					$items[]  = array(
						'type'     => 'gallery image',
						'id'        => $iid,
						'count'     => ++ $count,
						'new_title' => $title,
						'filename'  => ( ! empty( $filename[0] ) ) ? str_replace( $base, '', $filename[0] ) : '',
					);
					$all[] = $iid;
				}
			}
		}

		$meta  = get_attached_media( '', $id );
		if ( ! empty( $meta ) ) {
			foreach ( $meta as $obj ) {
				$iid = (int) $obj->ID;
				if ( ! in_array( $iid, $all ) ) {
					$filename = wp_get_attachment_image_src( $iid, 'full' );
					$items[]  = array(
						'type'      => 'media',
						'id'        => $iid,
						'count'     => ++ $count,
						'new_title' => $title,
						'filename'  => ( ! empty( $filename[0] ) ) ? str_replace( $base, '', $filename[0] ) : '',
					);
					$all[] = $iid;
				}
			}
		}

		return $items;
	}

	/**
	 * Identify filenames by post attachment id.
	 *
	 * @param  integer $id Attachment ID.
	 * @return array
	 */
	public static function get_attachments_by_id( $id ) {
		if ( ! empty( $id ) ) {
			$upls     = wp_upload_dir();
			$base     = trailingslashit( $upls['baseurl'] );
			$items    = array();
			$title    = get_the_title( $id );
			$filename = wp_get_attachment_image_src( (int) $id, 'full' );
			$items[]  = array(
				'type'      => 'attachment',
				'id'        => (int) $id,
				'count'     => 0,
				'new_title' => $title,
				'filename'  => ( ! empty( $filename[0] ) ) ? str_replace( $base, '', $filename[0] ) : '',
			);
		}

		return $items;
	}
}

// Instantiate the class.
SIRSC_Adons_Images_SEO::get_instance();
