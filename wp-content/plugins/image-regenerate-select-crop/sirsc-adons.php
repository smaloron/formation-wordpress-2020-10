<?php
/**
 * Description: The adons component of the Image Regenerate & Select Crop plugin.
 *
 * @package sirsc
 */

/**
 * Adons class for SIRSC plugin.
 */
class SIRSC_Adons extends SIRSC_Image_Regenerate_Select_Crop {
	/**
	 * Class instance.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Retrun the current instance.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Adons();
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
		if ( is_admin() ) {
			add_action( 'init', array( $called, 'detect_menu_items' ) );
			add_action( 'init', array( $called, 'detect_adons' ) );
			add_action( 'init', array( $called, 'maybe_deal_with_adons' ) );
			add_action( 'admin_menu', array( $called, 'admin_menu' ) );
		}
	}

	/**
	 * Detect menu items.
	 *
	 * @return void
	 */
	public static function detect_menu_items() {
		self::$menu_items = array(
			self::PLUGIN_PAGE_SLUG => array(
				'slug'  => self::PLUGIN_PAGE_SLUG,
				'title' => __( 'General Settings', 'sirsc' ),
				'url'   => admin_url( 'admin.php?page=' . self::PLUGIN_PAGE_SLUG ),
				'icon'  => '',
			),
			'sirsc-custom-rules-settings' => array(
				'slug'  => 'sirsc-custom-rules-settings',
				'title' => __( 'Advanced Rules', 'sirsc' ),
				'url'   => admin_url( 'admin.php?page=sirsc-custom-rules-settings' ),
				'icon'  => '',
			),
			'sirsc-features-manager' => array(
				'slug'  => 'sirsc-features-manager',
				'title' => __( 'Features Manager', 'sirsc' ),
				'url'   => admin_url( 'admin.php?page=sirsc-features-manager' ),
				'icon'  => '',
			),
		);
	}

	/**
	 * Detect menu items.
	 *
	 * @param array $item New menu item.
	 * @return void
	 */
	public static function sirsc_add_menu_items( $item ) {
		if ( empty( self::$menu_items[ $item['slug'] ] ) ) {
			self::$menu_items[ $item['slug'] ] = $item;
		}
	}

	/**
	 * Get adon details.
	 *
	 * @param  string $slug Adon slug.
	 * @param  string $prop Adon property.
	 * @return mixed
	 */
	public static function get_adon_details( $slug, $prop = '' ) {
		if ( empty( $slug ) || empty( self::$adons[ $slug ] ) ) {
			return;
		}
		if ( ! empty( $prop ) ) {
			if ( ! empty( self::$adons[ $slug ][ $prop ] ) ) {
				return self::$adons[ $slug ][ $prop ];
			}
			// Retrun empty.
			return;
		}
		// Return all.
		return self::$adons[ $slug ];
	}

	/**
	 * Regenerate options.
	 *
	 * @return void
	 */
	public static function regenerate_options() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				' DELETE FROM ' . $wpdb->options . ' WHERE option_name like %s or option_name like %s ',
				'%sirsc_adon%',
				'%sirsc-adon%'
			)
		);
	}

	/**
	 * Predict adons.
	 *
	 * @return array
	 */
	public static function predict_adons() {
		$default = array(
			'import-export' => array(
				'name'        => __( 'Import/Export', 'sirsc' ),
				'description' => __( 'This extension allows you to export and import the plugin settings from an instance to another. The export includes the general settings, the advanced rules, the media settings, and the additional sizes manages with this plugin.', 'sirsc' ),
				'icon'        => '<span class="dashicons dashicons-admin-plugins"></span>',
				'available'   => true,
				'active'      => false,
				'free'        => true,
				'current_ver' => 1.0,
				'price'       => 0.00,
				'license_key' => '',
				'sku'         => '',
				'buy_url'     => '',
			),
			'images-seo' => array(
				'name'        => __( 'Images SEO', 'sirsc' ),
				'description' => __( 'This extension allows you to rename the images files (bulk rename, individual rename or image rename on upload). Also, the extension provides the feature to override the attachments attributes based on the feature settings.', 'sirsc' ),
				'icon'        => '<span class="dashicons dashicons-admin-plugins"></span>',
				'available'   => false,
				'active'      => false,
				'free'        => false,
				'current_ver' => 1.0,
				'price'       => 10.00,
				'license_key' => '',
				'sku'         => 'SIRSC.01',
				'buy_url'     => 'https://iuliacazan.ro/wordpress-extension/images-seo/',
			),
			'uploads-folder-info' => array(
				'name'        => __( 'Uploads Folder Info', 'sirsc' ),
				'description' => __( 'This extension allows you to see details about your application uploads folder: the total files size, the number of folders (and sub-folders), the number of files.', 'sirsc' ),
				'icon'        => '<span class="dashicons dashicons-admin-plugins"></span>',
				'available'   => false,
				'active'      => false,
				'free'        => false,
				'current_ver' => 1.0,
				'price'       => 4.00,
				'license_key' => '',
				'sku'         => 'SIRSC.02',
				'buy_url'     => 'https://iuliacazan.ro/wordpress-extension/uploads-folder-info/',
			),
			'uploads-inspector' => array(
				'name'        => __( 'Uploads Inspector', 'sirsc' ),
				'description' => __( 'This extension allows you to analyze the files from your uploads folder (even the orphaned files, these not associated with attachment records in the database) and see details about their size, MIME type, attachment IDs, images sizes, etc.', 'sirsc' ),
				'icon'        => '<span class="dashicons dashicons-admin-plugins"></span>',
				'available'   => false,
				'active'      => false,
				'free'        => false,
				'current_ver' => 1.0,
				'price'       => 6.00,
				'license_key' => '',
				'sku'         => 'SIRSC.03',
				'buy_url'     => 'https://iuliacazan.ro/wordpress-extension/uploads-inspector/',
			),
		);
		return $default;
	}

	/**
	 * Detect adons.
	 *
	 * @return void
	 */
	public static function detect_adons() {
		$options = get_option( 'sirsc_adons_list', array() );
		$default = self::predict_adons();
		self::$adons = wp_parse_args( $options, $default );

		foreach ( self::$adons as $key => $value ) {
			self::$adons[ $key ]['name']        = $default[ $key ]['name'];
			self::$adons[ $key ]['free']        = $default[ $key ]['free'];
			self::$adons[ $key ]['description'] = $default[ $key ]['description'];
			self::$adons[ $key ]['price']       = $default[ $key ]['price'];
			self::$adons[ $key ]['buy_url']     = $default[ $key ]['buy_url'];
			if ( file_exists( SIRSC_ADONS_FOLDER . $key . '/class-sirsc-' . $key . '.php' ) ) {
				if ( ! empty( self::$adons[ $key ]['available'] ) && ! empty( self::$adons[ $key ]['active'] ) ) {
					self::sirsc_add_menu_items(
						array(
							'slug'  => 'sirsc-adon-' . $key,
							'title' => $value['name'],
							'url'   => admin_url( 'admin.php?page=sirsc-adon-' . $key ),
							'icon'  => $value['icon'],
						)
					);
					include_once SIRSC_ADONS_FOLDER . $key . '/class-sirsc-' . $key . '.php';
				}
			}
		}
	}

	/**
	 * Add the new menu in tools section that allows to configure the image sizes restrictions.
	 */
	public static function admin_menu() {
		$adons_notice = '';
		$maybe_trans  = get_transient( self::PLUGIN_TRANSIENT . '_adons_notice' );
		if ( ! empty( $maybe_trans ) ) {
			$adons_notice = '<span class="update-plugins count-4"><span class="plugin-count">4</span></span>';
		}

		add_submenu_page(
			'image-regenerate-select-crop-settings',
			__( 'Features Manager', 'sirsc' ),
			__( 'Features Manager', 'sirsc' ) . $adons_notice,
			'manage_options',
			'sirsc-features-manager',
			array( get_called_class(), 'features_manager' )
		);

		global $submenu;
		if ( ! empty( $submenu['image-regenerate-select-crop-settings'][0][0] ) ) {
			$submenu['image-regenerate-select-crop-settings'][0][0] = __( 'General Settings', 'sirsc' ); // PHPCS:ignore
		}
	}

	/**
	 * Check adon valid.
	 *
	 * @param  string $slug Adon slug.
	 * @return void
	 */
	public static function check_adon_valid( $slug ) {
		$trans_id    = 'sirsc-adon-check-' . $slug;
		$maybe_trans = get_transient( $trans_id );
		if ( empty( $maybe_trans ) ) {
			if ( ! self::get_adon_details( $slug, 'free' ) ) {
				$sku = self::get_adon_details( $slug, 'sku' );
				$key = self::get_adon_details( $slug, 'license_key' );
				$id  = self::get_adon_details( $slug, 'activation_id' );
				SIRSC_Adons_API::validate_license_key( $slug, $sku, $key, $id );
			}
			set_transient( $trans_id, current_time( 'timestamp' ), 1 * HOUR_IN_SECONDS );
		}
	}

	/**
	 * Maybe deal with adons.
	 *
	 * @return void
	 */
	public static function maybe_deal_with_adons() {
		$nonce = filter_input( INPUT_POST, '_sirsc_adon_box_nonce', FILTER_DEFAULT );
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, '_sirsc_adon_box_action' ) ) {
			$error = 0;
			if ( current_user_can( 'manage_options' ) ) {
				// Maybe update settings.
				$slug = filter_input( INPUT_POST, 'sirsc-adon-slug', FILTER_DEFAULT );
				if ( ! empty( $slug ) ) {
					$attr         = self::adon_check( $slug );
					$activate_key = filter_input( INPUT_POST, 'sirsc-save-adon-activate-license-key', FILTER_DEFAULT );
					$license_key  = filter_input( INPUT_POST, 'license-key', FILTER_DEFAULT );
					$deactivate   = filter_input( INPUT_POST, 'sirsc-save-adon-deactivate', FILTER_DEFAULT );
					$activate     = filter_input( INPUT_POST, 'sirsc-save-adon-activate', FILTER_DEFAULT );
					if ( ! empty( $activate_key ) && ! empty( $license_key ) ) {
						$sku = self::get_adon_details( $slug, 'sku' );
						SIRSC_Adons_API::activate_license_key( $slug, $sku, $license_key );
					} else {
						if ( ! empty( $deactivate ) && 'deactivate' === $attr['action'] ) {
							SIRSC_Adons_API::update_adon_property( $slug, 'active', false );
						} elseif ( ! empty( $activate ) && 'activate' === $attr['action'] ) {
							$opt = self::$adons;
							delete_transient( 'sirsc-adon-check-' . $slug );
							self::check_adon_valid( $slug );
							$ava = self::get_adon_details( $slug, 'available' );
							if ( ! empty( $ava ) ) {
								SIRSC_Adons_API::update_adon_property( $slug, 'active', true );
							}
						}
					}
					self::detect_adons();
					wp_redirect( admin_url( 'admin.php?page=sirsc-features-manager' ) . '#sirsc_adon_box_' . $slug . '_frm' );
					exit;
				}
				wp_redirect( admin_url( 'admin.php?page=sirsc-features-manager' ) );
				exit;
			}
		}

		$reset = filter_input( INPUT_GET, 'sirsc-adons-reset', FILTER_DEFAULT );
		if ( ! empty( $reset ) ) {
			self::regenerate_options();
			wp_redirect( admin_url( 'admin.php?page=sirsc-features-manager' ) );
			exit;
		}
	}

	/**
	 * Adon check.
	 *
	 * @param  string $slug Adon slug.
	 * @return array
	 */
	public static function adon_check( $slug ) {
		$attr = array(
			'class'  => '',
			'action' => 'remove',
		);
		if ( ! empty( self::$adons[ $slug ] ) ) {
			$item   = self::$adons[ $slug ];
			$class  = '';
			$class .= ( ! empty( $item['available'] ) ) ? ' available' : ' unavailable';
			$class .= ( ! empty( $item['active'] ) ) ? ' active' : '';
			$class .= ( empty( $item['free'] ) ) ? ' purchase' : '';

			$attr['class'] = $class;
			if ( ! empty( $item['active'] ) ) {
				$attr['action'] = 'deactivate';
			} else {
				if ( ! empty( $item['available'] ) ) {
					$attr['action'] = 'activate';
				} else {
					if ( ! empty( $item['free'] ) ) {
						$attr['action'] = 'download';
					} else {
						$attr['action'] = 'purchase';
					}
				}
			}
		}

		return $attr;
	}

	/**
	 * Adon files exist.
	 *
	 * @param  string $slug Adon slug.
	 * @return boolean
	 */
	public static function adon_files_exist( $slug = '' ) {
		if ( file_exists( SIRSC_ADONS_FOLDER . $slug . '/class-sirsc-' . $slug . '.php' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Button attribute.
	 *
	 * @param  string $slug Adon slug.
	 * @return array
	 */
	public static function button_processing( $slug ) {
		return array(
			'onclick' => 'jQuery(\'.sirsc-adon-box.adon-' . esc_attr( $slug ) . '\').addClass(\'js-sirsc-adon processing\');',
		);
	}

	/**
	 * Adon activate/deactivate button.
	 *
	 * @param  string $slug Adon slug.
	 * @return void
	 */
	public static function maybe_adon_button_activate_deactivate( $slug ) {
		if ( ! empty( self::$adons[ $slug ] ) && self::adon_files_exist( $slug ) ) {
			$item = self::$adons[ $slug ];
			if ( ! empty( $item['available'] ) ) {
				?>
				<div class="sirsc-save-adon-elements">
					<?php
					if ( empty( $item['active'] ) ) {
						echo '<span class="toggle-left">&#10061;</span>';
						submit_button( __( 'Disabled', 'sirsc' ), 'primary', 'sirsc-save-adon-activate', false, self::button_processing( $slug ) );
					} else {
						echo '<span class="toggle-right">&#10061;</span>';
						submit_button( __( 'Enabled', 'sirsc' ), '', 'sirsc-save-adon-deactivate', false, self::button_processing( $slug ) );
					}
					?>
				</div>
				<?php
			}
		}
	}


	/**
	 * Adon activate/deactivate button.
	 *
	 * @param  string $slug Adon slug.
	 * @return void
	 */
	public static function maybe_adon_button_buy( $slug ) {
		if ( ! empty( self::$adons[ $slug ] ) ) {
			$item = self::$adons[ $slug ];
			?>
			<a href="<?php echo esc_url( $item['buy_url'] ); ?>" target="_blank" class="sirsc-save-adon-purchase"><?php esc_html_e( 'Purchase', 'sirsc' ); ?></a>
			<?php
		}
	}

	/**
	 * Adon activate/deactivate button.
	 *
	 * @param  string $slug Adon slug.
	 * @return void
	 */
	public static function maybe_adon_button_license_key( $slug ) {
		if ( ! empty( self::$adons[ $slug ] ) ) {
			$item    = self::$adons[ $slug ];
			$message = ( ! empty( $item['key_message'] ) ) ? $item['key_message'] : '';
			if ( ! empty( $item['activation_response']->status ) && 'active' === $item['activation_response']->status ) {
				?>
				<b><?php echo esc_attr( $item['license_key'] ); ?></b>
				<br><?php echo wp_kses_post( $message ); ?>
				<?php
			} else {
				?>
				<table class="fixed sirsc-save-adon-activate-license" cellpadding="0" cellspacing="0" width="100%">
					<tr>
						<td>
							<input type="text" name="license-key" class="button" autocomplete="off" value="<?php echo esc_attr( $item['license_key'] ); ?>" placeholder="<?php esc_attr_e( 'License Key', 'sirsc' ); ?>">
						</td>
						<td width="80"><?php submit_button( __( 'Activate', 'sirsc' ), 'primary wide', 'sirsc-save-adon-activate-license-key', false, self::button_processing( $slug ) ); ?></td>
					</tr>
				</table>
				<?php echo wp_kses_post( $message ); ?>
				<?php
			}
		}
	}

	/**
	 * Adon details and purchase buttons.
	 *
	 * @param  string $slug The slug.
	 * @param  array  $item The item.
	 * @param  array  $attr The attributes.
	 * @return void
	 */
	public static function adon_details_button( $slug, $item, $attr ) {
		if ( empty( $item['price'] ) && ! empty( $item['free'] ) ) {
			if ( ! empty( $item['buy_url'] ) ) {
				?>
				<a href="<?php echo esc_url( $item['buy_url'] ); ?>" target="_blank" class="sirsc-save-adon-details"><?php esc_html_e( 'Details', 'sirsc' ); ?></a>
				<?php
			}
		} else {
			if ( ! empty( $item['buy_url'] ) ) {
				?>
				<a href="<?php echo esc_url( $item['buy_url'] ); ?>" target="_blank" class="sirsc-save-adon-details"><?php esc_html_e( 'Details', 'sirsc' ); ?></a>
				<?php
			}
			self::check_adon_valid( $slug );
			$id = self::get_adon_details( $slug, 'activation_id' );
			if ( empty( $id ) ) {
				self::maybe_adon_button_buy( $slug );
			}
		}
	}

	/**
	 * Adon price info.
	 *
	 * @param  string $slug The slug.
	 * @param  array  $item The item.
	 * @param  array  $attr The attributes.
	 * @return void
	 */
	public static function adon_price_info( $slug, $item, $attr ) {
		?>
		<b class="sirsc-adon-price">
		<?php
		if ( empty( $item['price'] ) && ! empty( $item['free'] ) ) {
			esc_html_e( 'Free', 'sirsc' );
		} else {
			echo esc_html(
				sprintf(
					// Translators: %1$s - adon price.
					__( '&euro; %1$s / year', 'sirsc' ),
					number_format( $item['price'], 2, '.', '' )
				)
			);
		}
		?>
		</b>
		<?php
	}

	/**
	 * Adon action button.
	 *
	 * @param  string $slug The slug.
	 * @param  array  $item The item.
	 * @param  array  $attr The attributes.
	 * @return void
	 */
	public static function adon_on_off_button( $slug, $item, $attr ) {
		if ( empty( $item['price'] ) && ! empty( $item['free'] ) ) {
			self::maybe_adon_button_activate_deactivate( $slug );
		} else {
			self::check_adon_valid( $slug );
			$id = self::get_adon_details( $slug, 'activation_id' );
			if ( ! empty( $id ) ) {
				self::maybe_adon_button_activate_deactivate( $slug );
			}
		}
	}

	/**
	 * Adon action button.
	 *
	 * @param  string $slug The slug.
	 * @param  array  $item The item.
	 * @param  array  $attr The attributes.
	 * @return void
	 */
	public static function adon_action_button( $slug, $item, $attr ) {
		if ( ! ( empty( $item['price'] ) && ! empty( $item['free'] ) ) ) {
			self::check_adon_valid( $slug );
			$id = self::get_adon_details( $slug, 'activation_id' );
			if ( ! empty( $id ) ) {
				self::maybe_adon_button_license_key( $slug );
			} else {
				self::maybe_adon_button_license_key( $slug );
			}
		}
	}

	/**
	 * Output adon box.
	 *
	 * @param  string $slug Adon slug.
	 * @param  array  $item Adon item.
	 * @return void
	 */
	public static function output_adon_box( $slug, $item ) {
		if ( empty( $slug ) ) {
			// Fail-fast.
			return;
		}

		$attr = self::adon_check( $slug );
		?>
		<div class="sirsc-adon-box adon-<?php echo esc_attr( $slug ); ?> <?php echo esc_attr( $attr['class'] ); ?>">
			<div class="sirsc-adon-box-img-wrap">
				<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) ); ?>assets/images/adon-<?php echo esc_attr( $slug ); ?>.png">
				<h2><?php echo esc_html( $item['name'] ); ?></h2>
				<div class="sirsc-adon-box-links"><?php self::adon_details_button( $slug, $item, $attr ); ?></div>
				<?php self::adon_price_info( $slug, $item, $attr ); ?>
			</div>
			<form id="sirsc_adon_box_<?php echo esc_attr( $slug ); ?>_frm"
				name="sirsc_adon_box_<?php echo esc_attr( $slug ); ?>_frm"
				action="" method="post">
				<?php wp_nonce_field( '_sirsc_adon_box_action', '_sirsc_adon_box_nonce' ); ?>
				<input type="hidden" name="sirsc-adon-slug" value="<?php echo esc_attr( $slug ); ?>">
				<?php self::adon_on_off_button( $slug, $item, $attr ); ?>
				<p>

					<?php echo esc_html( $item['description'] ); ?></p>
				<?php self::adon_action_button( $slug, $item, $attr ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Functionality to manage the image regenerate & select crop settings.
	 */
	public static function features_manager() {
		if ( ! current_user_can( 'manage_options' ) ) {
			// Verify user capabilities in order to deny the access if the user does not have the capabilities.
			wp_die( esc_html__( 'Action not allowed.', 'sirsc' ) );
		}
		$maybe_trans  = get_transient( self::PLUGIN_TRANSIENT . '_adons_notice' );
		if ( ! empty( $maybe_trans ) ) {
			delete_transient( self::PLUGIN_TRANSIENT . '_adons_notice' );
		}
		?>
		<style>
		.sirsc-adon-box {width:calc( ( 100% - 60px ) / 4); min-width:280px; overflow:hidden; display:inline-block; float:left;padding:0px; margin:0 0px 20px 20px; position:relative;}
		.sirsc-adon-box:nth-child(4n+1) {margin-left:0}
		.sirsc-adon-box .sirsc-adon-box-img-wrap {position: relative;}
		.sirsc-adon-box img {width: 100%;}
		.sirsc-adon-box { --thecolor: rgb(114,124,163); --thebg:#f4f4f4; --fontcolor:#444;}
		.sirsc-adon-box.active { --thecolor: rgb(114,124,163); --thebg:rgb(114,124,163); --fontcolor:#FFF;}
		.sirsc-adon-box.unavailable { --thecolor: #B8B8B8; --fontcolor:#AAA;}
		.sirsc-adon-box {border:5px solid var(--thecolor); background:var(--thebg); color:var(--fontcolor);}
		.sirsc-adon-box.unavailable img {-webkit-filter: grayscale(100%); filter: grayscale(100%); opacity: 0.5}
		.sirsc-adon-box p {display:block; min-height:115px; margin-top: 0px; font-size: 12px; line-height: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);}
		.sirsc-adon-box h2 {text-transform:uppercase; font-family:"Trebuchet MS"; color:#FFF; position:absolute; display:block; left:10px; top:0px; font-weight: bold; font-size: 20px}
		.sirsc-adon-box .sirsc-adon-box-links {position:absolute;display:block;left:10px;top:45px;color:rgba(255,255,255,0.5);}
		.sirsc-adon-box .sirsc-adon-box-links a {text-decoration:none; color:#FFF;background: rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); padding: 2px 10px}
		.sirsc-adon-box .sirsc-adon-box-links a.sirsc-save-adon-purchase{background:red; border-color:red;}
		.sirsc-adon-box .sirsc-adon-box-links a:hover {color:#000;}
		.sirsc-adon-box .error {color: #F00;}
		.sirsc-adon-box .success {color: #0A0;}
		.sirsc-adon-box.active .success {color: #FFF;}
		.sirsc-adon-box .sirsc-adon-price {position:absolute;left:10px;bottom:45px;font-size:18px;line-height:18px;color:#FFF; text-transform: uppercase;}
		.sirsc-adon-box.unavailable .sirsc-adon-price {bottom:15px;}
		.sirsc-adon-box form {display:block;position:relative;top:0;left:0;width:100%;height:100%;padding:10px;min-height:200px;}
		.sirsc-save-adon-elements {display:block; position:absolute; left:10px; top:-40px;}
		.sirsc-save-adon-elements .button {width: 110px; min-width: 110px; text-align: center; text-transform: uppercase; box-shadow: none; line-height:28px !important; padding-top:0; font-size: 12px !important; height: 28px !important;}
		.sirsc-save-adon-elements .toggle-left, .sirsc-save-adon-elements .toggle-right {font-size: 18px; display: inline-block; color: #999; position: absolute; margin-top: 5px; left: 8px;}
		.sirsc-save-adon-elements .toggle-right {left: auto; right: 8px; color: rgb(114,124,163);}
		#sirsc-save-adon-activate {border:1px solid #555; border-left:32px solid #555; background:#777; color: #EEE;}
		#sirsc-save-adon-deactivate {border:1px solid #FFF;border-right:32px solid #FFF;background:rgb(114,124,163);color:#FFF;}
		#sirsc-save-adon-activate-license-key {width:80px;min-width:80px;background:rgb(114,124,163);border:1px solid rgb(114,124,163);}
		.sirsc-save-adon-activate-license td:nth-child(1){padding-right:5px;}
		.sirsc-save-adon-activate-license input { max-width:100%; min-width:auto !important;}
		.sirsc-save-adon-activate-license input[name="license-key"] {background:#FFF !important;}
		@media all and (max-width:768px) {
			.sirsc-adon-box {width:calc( ( 100% - 40px ) / 3) !important;}
			.sirsc-adon-box:nth-child(2n+1) {margin-left:20px}
			.sirsc-adon-box:nth-child(4n+1) {margin-left:20px}
			.sirsc-adon-box:nth-child(3n+1) {margin-left:0}
		}
		@media all and (max-width:600px) {
			.sirsc-adon-box h2 {font-size: 22px;}
			.sirsc-adon-box {width:100% !important; margin-left:0 !important; height:auto}
			.sirsc-adon-box p {display:block; min-height:90px}
			.sirsc-save-adon-activate-license td:nth-child(2){width: 120px;}
			.sirsc-save-adon-activate-license input[name="license-key"] {width: 100%}
			#sirsc-save-adon-activate-license-key { width:120px; min-width:120px; line-height: 28px; padding-top: 0}
		}
		</style>

		<div class="wrap sirsc-settings-wrap">
			<h1>
				<?php self::show_plugin_icon(); ?> <?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</h1>

			<?php self::maybe_all_features_tab(); ?>
			<div class="sirsc-tabbed-menu-content">
				<h1><?php esc_html_e( 'Features Manager', 'sirsc' ); ?></h1>
				<br>

				<table class="widefat sirsc-striped">
					<tr>
						<td>
							<?php esc_html_e( 'You will see here the available extensions compatible with the installed plugin version.', 'sirsc' ); ?>
							<br><?php esc_html_e( 'You can activate and deactivate these at any time.', 'sirsc' ); ?>
						</td>
					</tr>
				</table>
				<br>

				<div class="sirsc-image-generate-functionality">
					<?php if ( ! empty( self::$adons ) ) : ?>
						<?php foreach ( self::$adons as $slug => $item ) : ?>
							<?php self::output_adon_box( $slug, $item ); ?>
						<?php endforeach; ?>
					<?php else : ?>
						<?php esc_html_e( 'No extension available at the moment.', 'sirsc' ); ?>
					<?php endif; ?>
					<div class="clear"></div>
				</div>
			</div>

			<?php self::plugin_global_footer(); ?>
		</div>
		<?php
	}
}

// Instantiate the class.
SIRSC_Adons::get_instance();

if ( file_exists( dirname( __FILE__ ) . '/sirsc-adons-api.php' ) ) {
	// Hookup the SIRSC adons API component.
	require_once dirname( __FILE__ ) . '/sirsc-adons-api.php';
}
