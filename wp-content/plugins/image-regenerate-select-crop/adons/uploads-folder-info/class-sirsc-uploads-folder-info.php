<?php
/**
 * Uploads folder info extension.
 *
 * @package sirsc
 * @version 1.0
 */

/**
 * Class for Image Regenerate & Select Crop plugin adon Upload Folder Info.
 */
class SIRSC_Adons_Uploads_Folder_Info {

	const ADON_PAGE_SLUG = 'sirsc-adon-uploads-folder-info';
	const ADON_SLUG      = 'uploads-folder-info';

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
			self::$instance = new SIRSC_Adons_Uploads_Folder_Info();
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
			add_action( 'admin_menu', array( get_called_class(), 'adon_admin_menu' ), 20 );
			add_action( 'wp_ajax_sirsc_compute_upload_folder_size', array( get_called_class(), 'compute_size' ) );
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
			__( 'Uploads Folder Info', 'sirsc' ),
			'<span class="dashicons dashicons-admin-plugins sirsc-mini"></span> ' . __( 'Uploads Folder Info', 'sirsc' ),
			'manage_options',
			self::ADON_PAGE_SLUG,
			array( get_called_class(), 'adon_page' )
		);
	}

	/**
	 * Get the size of the directory.
	 *
	 * @param string  $dir  Directory path.
	 * @param integer $cdir The current directory index.
	 * @param array   $info The overall info.
	 * @return integer
	 */
	public static function folder_summary( $dir, &$cdir = 0, &$info = array() ) {
		if ( empty( $info ) ) {
			$info = array(
				'folders'     => array(
					$cdir => array(
						'name'          => $dir,
						'count'         => 0,
						'size'          => 0,
						'parent'        => '',
						'folders_count' => 0,
						'total_size'    => 0,
					),
				),
				'files_count' => 0,
			);
		}
		$size = 0;
		foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) {
			if ( is_file( $each ) ) {
				$info['files_count']               += 1;
				$info['folders'][ $cdir ]['count'] += 1;
				$info['folders'][ $cdir ]['size']  += filesize( $each );
				$size += filesize( $each );
			} else {
				++ $cdir;
				$info['folders'][ $cdir ] = array(
					'name'          => $each,
					'count'         => 0,
					'size'          => 0,
					'parent'        => dirname( $each ),
					'folders_count' => 0,
					'sub_folders'   => array(),
					'total_size'    => 0,
				);
				$paths = glob( rtrim( $each, '/' ) . '/**', GLOB_ONLYDIR );
				$info['folders'][ $cdir ]['sub_folders'] = $paths;
				$info['folders'][ $cdir ]['folders_count'] = count( $paths );

				$size += self::folder_summary( $each, $cdir, $info );
			}
		}
		return $size;
	}

	/**
	 * Get the count and size of the directory files.
	 *
	 * @param string $dir Directory path.
	 * @return array
	 */
	public static function folder_files_count( $dir ) {
		$info = array(
			'count'   => 0,
			'size'    => 0,
			'folders' => 0,
		);
		foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) {
			if ( is_file( $each ) ) {
				$info['count'] += 1;
				$info['size']  += filesize( $each );
			} else {
				$info['folders'] += 1;
			}
		}
		return $info;
	}

	/**
	 * Get the count and size of the directory files.
	 *
	 * @param string  $dir Directory name.
	 * @param integer $poz Position in the list.
	 * @param array   $all All previousliy computed list.
	 * @return array
	 */
	public static function get_folder_totals( $dir, $poz, $all ) {
		$size   = $all[ $poz ]['files_size'];
		$countf = $all[ $poz ]['files_count'];
		$countd = $all[ $poz ]['folders_count'];
		foreach ( $all as $key => $value ) {
			if ( $dir === $value['parent'] ) {
				$size   += $value['totals']['files_size'];
				$countf += $value['totals']['files_count'];
				$countd += $value['totals']['folders_count'];
			}
		}
		return array(
			'files_size'    => $size,
			'files_count'   => $countf,
			'folders_count' => $countd,
		);
	}

	/**
	 * Return humain readable files size.
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
		return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$sz[ $factor ];
	}

	/**
	 * Get folders list.
	 *
	 * @param  string $base The root folder for computation.
	 * @return array
	 */
	public static function get_folders_list( $base ) {
		$dir = $base;
		$all = array();
		while ( $dirs = glob( rtrim( $dir, '/' ) . '/*', GLOB_ONLYDIR ) ) {
			if ( is_array( $dirs ) ) {
				$all = array_merge( $all, $dirs );
			} else {
				array_push( $all, $dirs );
			}

			$dir = rtrim( $dir, '/' ) . '/*';
		}
		sort( $all );

		$diff       = substr_count( $base, '/' ) - 1;
		$sum_fsize  = 0;
		$sum_fcount = 0;

		// Parent and direct files count and files size and direct folders.
		foreach ( $all as $key => $value ) {
			$dinf = self::folder_files_count( $value );
			$all[ $key ] = array(
				'name'          => rtrim( $value ),
				'parent'        => dirname( $value ),
				'position'      => $key + 1,
				'level'         => substr_count( $value, '/' ) - $diff,
				'files_count'   => $dinf['count'],
				'files_size'    => $dinf['size'],
				'folders_count' => $dinf['folders'],
				'totals'        => array(
					'files_count'   => 0,
					'files_size'    => 0,
					'folders_count' => 0,
					'all_size'      => 0,
				),
			);

			$sum_fsize  += $dinf['size'];
			$sum_fcount += $dinf['count'];
		}

		$seri = serialize( $all );
		$info = array();
		$dinf = self::folder_files_count( $base );
		$info[] = array(
			'name'          => rtrim( $base, '/' ),
			'parent'        => '',
			'position'      => 0,
			'level'         => 0,
			'files_count'   => $dinf['count'],
			'files_size'    => $dinf['size'],
			'folders_count' => $dinf['folders'],
			'totals'        => array(
				'files_count'   => $sum_fsize,
				'files_size'    => $sum_fcount,
				'folders_count' => 0,
				'all_size'      => 0,
			),
		);
		foreach ( $all as $key => $value ) {
			$info[] = $value;
		}

		// This is the real trick to retro compute.
		$tmp = $info;
		usort( $tmp, function( $item1, $item2 ) {
			return $item2['level'] <=> $item1['level'];
		} );
		foreach ( $tmp as $value ) {
			$v = self::get_folder_totals( $value['name'], $value['position'], $info );
			$info[ $value['position'] ]['totals'] = $v;
		}

		// Simplify paths.
		$root_base = dirname( $base );
		foreach ( $info as $key => $value ) {
			$info[ $key ]['path']   = $value['name'];
			$info[ $key ]['name']   = basename( $value['name'] );
			$info[ $key ]['parent'] = ltrim( str_replace( $root_base, '', $value['parent'] ), '/' );
		}

		if ( ! empty( $info[0] ) ) {
			$info[0]['totals']['files_count'] = $info[0]['totals']['files_count'] - $info[0]['totals']['folders_count'];
		}

		return $info;
	}

	/**
	 * Output the root folder summary from info.
	 *
	 * @param  array $info Folders computed info.
	 * @return void
	 */
	public static function output_root_summary( $info ) {
		if ( ! empty( $info ) ) {
			$root = $info[0];
			?>
			<div class="sirsc-folders-info-wrap">
				<table>
					<tr>
						<td><?php esc_html_e( 'Upload folder', 'sirsc' ); ?>: </td>
						<td><b><?php echo esc_html( $root['name'] ); ?></b></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Size', 'sirsc' ); ?>: </td>
						<td><b><?php echo esc_html( self::human_filesize( $root['totals']['files_size'] ) ); ?></b>
							(<?php echo (int) $root['totals']['files_size']; ?>)</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Total folders', 'sirsc' ); ?>: </td>
						<td><b><?php echo (int) $root['totals']['folders_count']; ?></b></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Total files', 'sirsc' ); ?>: </td>
						<td><b><?php echo (int) $root['totals']['files_count']; ?></b></td>
					</tr>
				</table>
			</div>
			<?php
		}
	}

	/**
	 * Output folders details from info.
	 *
	 * @param  array $info Folders computed info.
	 * @return void
	 */
	public static function output_folders_details( $info ) {
		if ( ! empty( $info ) ) {
			$root = $info[0];
			?>
			<div class="sirsc-folders-info-wrap">
				<table>
					<thead>
						<tr>
							<td><?php esc_html_e( 'Folder', 'sirsc' ); ?></td>
							<td class="right"><?php esc_html_e( 'Total Folders', 'sirsc' ); ?></td>
							<td class="right"><?php esc_html_e( 'Total Files', 'sirsc' ); ?></td>
							<td class="right"><?php esc_html_e( 'Total Size', 'sirsc' ); ?></td>
							<td class="right"><?php esc_html_e( 'Total Bytes', 'sirsc' ); ?></td>
						</tr>
					</thead>
				<?php foreach ( $info as $folder ) : ?>
					<?php $s = 'padding-left: ' . ( ( $folder['level'] * 32 ) + 48 ) . 'px'; ?>
					<tr>
						<td class="name" style="<?php echo esc_attr( $s ); ?>">
							<b><?php echo esc_html( $folder['name'] ); ?></b>
						</td>
						<td class="right">
							<?php if ( ! empty( $folder['totals']['folders_count'] ) ) : ?>
								<?php echo (int) $folder['totals']['folders_count']; ?>
							<?php endif; ?>
						</td>
						<td class="right">
							<?php if ( ! empty( $folder['totals']['files_count'] ) ) : ?>
								<?php echo (int) $folder['totals']['files_count']; ?>
							<?php endif; ?>
						</td>
						<td class="right">
							<?php if ( ! empty( $folder['totals']['files_size'] ) ) : ?>
								<b><?php echo esc_html( self::human_filesize( $folder['totals']['files_size'] ) ); ?></b>
							<?php endif; ?>
						</td>
						<td class="right">
							<?php if ( ! empty( $folder['totals']['files_size'] ) ) : ?>
								<?php echo (int) $folder['totals']['files_size']; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</table>
			</div>
			<?php
		}
	}
	/**
	 * Compute size.
	 *
	 * @return void
	 */
	public static function compute_size() {
		$is_ajax = false;
		$act = filter_input( INPUT_POST, 'action', FILTER_DEFAULT );
		if ( ! empty( $act ) && 'sirsc_compute_upload_folder_size' === $act ) {
			$is_ajax = true;
		}

		$upls = wp_upload_dir();
		$base = trailingslashit( $upls['basedir'] );
		$trid = 'sirsc_adon_uploads_folder_summary';

		if ( true === $is_ajax ) {
			// Force recompute the transient on ajax too.
			$info = false;
		} else {
			$info = get_transient( $trid );
		}
		if ( false === $info ) {
			$info = self::get_folders_list( $base );
			set_transient( $trid, $info, 1 * HOUR_IN_SECONDS );
			update_option( 'sirsc_adon_uploads_files_count', $info[0]['totals']['files_count'] );
		}

		?>
		<div class="sirsc_folders-info-menu-buttons">
			<a class="button button-primary"><?php esc_html_e( 'Folder Summary', 'sirsc' ); ?></a>
		</div>
		<div class="sirsc-adon-folders-info-wrap">
			<?php self::output_root_summary( $info ); ?>
		</div>

		<br>
		<div class="sirsc_folders-info-menu-buttons">
			<a class="button button-primary"><?php esc_html_e( 'Folder Details', 'sirsc' ); ?></a>
		</div>
		<div class="sirsc-adon-folders-info-wrap">
			<?php self::output_folders_details( $info ); ?>
		</div>

		<?php
		if ( true === $is_ajax ) {
			if ( class_exists( 'SIRSC_Adons_Images_Profiler' ) ) {
				update_option( SIRSC_Adons_Images_Profiler::PLUGIN_TABLE . '_proc_dir', '' );
				update_option( SIRSC_Adons_Images_Profiler::PLUGIN_TABLE . '_proc_item', '' );
			}

			wp_die();
			die();
		}
	}

	/**
	 * Add the plugin menu.
	 *
	 * @return void
	 */
	public static function adon_page() {
		SIRSC_Adons::check_adon_valid( self::ADON_SLUG );
		$desc = SIRSC_Adons::get_adon_details( self::ADON_SLUG, 'description' );
		?>
		<style>
		#js-sirsc-upload-details-trigger-info {cursor:pointer;}
		.sirsc_folders-info-menu-buttons {display: block; position: relative; z-index: 0;}
		.sirsc_folders-info-action-wrap {border: 1px solid #FFF; background: #FFF; position: relative; z-index: 2; padding: 20px; margin-top:0; margin-bottom: 10px;}
		.sirsc-tabbed-menu-content .sirsc_folders-info-action-wrap {padding: 0; border: 1px solid #45AFD1;}
		.sirsc_folders-info-menu-buttons .button {border:0; text-transform: uppercase; background-color: #DDD; height:32px; line-height: 32px; border-radius:0; border:0; margin-right: 5px; text-align: center;}
		.sirsc_folders-info-menu-buttons .button.button-primary {background-color: #45AFD1; color: #FFF; text-shadow: none; }
		.sirsc-tabbed-menu-content .sirsc-adon-folders-info-wrap {border: 1px solid #45AFD1; padding: 10px 20px}
		.sirsc-folders-info-wrap {background-color: #FFF; padding: 20px}
		.sirsc-folders-info-wrap table thead tr td {#FFF; line-height: 2em; font-size: 1.5em; font-weight: bold}
		.sirsc-folders-info table {border-collapse:collapse;}
		.sirsc-folders-info table .right {text-align:right; padding:0 10px;}
		.sirsc-folders-info .name {position:relative; line-height:24px; height:24px;}
		.sirsc-folders-info .name b, .sirsc-folders-info b.label {display:inline-block; height:24px; line-height:24px; padding:0 10px; background:#F4F4F4; border:1px dotted #CCC; position:relative; border-radius:3px; z-index:10;}
		.sirsc-folders-info .name:before{display:block; position: absolute; top:12px; margin-left:-24px; width:24px; height:24px; content:' '; border-top:1px dotted #CCC; z-index:1;}
		.sirsc-folders-info .name:after{display:block; position:absolute; top:-16px; margin-left:-24px; width:24px; height:29px; content:' '; border-left:1px dotted #CCC; z-index:1;}
		.js-sirsc-adon-upfode {display: block; position: relative;}
		.js-sirsc-adon-upfode.processing:before { display:block; z-index:20; position:absolute; left:0; top:0; width:100%; height:100%; background: url('../wp-admin/images/spinner-2x.gif') no-repeat 50% 50% rgba(255,255,255,0.5); background-size:32px 32px; content:' ';}
		</style>

		<div class="wrap sirsc-settings-wrap">
			<h1>
				<?php SIRSC_Image_Regenerate_Select_Crop::show_plugin_icon(); ?> <?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</h1>

			<?php SIRSC_Image_Regenerate_Select_Crop::maybe_all_features_tab(); ?>
			<div class="sirsc-tabbed-menu-content">
				<h1>
					<span class="dashicons dashicons-admin-plugins"></span>
					<?php esc_html_e( 'Uploads Folder Info', 'sirsc' ); ?>
				</h1>
				<br>

				<table class="widefat sirsc-striped">
					<tr>
						<td>
							<?php echo wp_kses_post( $desc ); ?>
							<br><?php esc_html_e( 'Click to', 'sirsc' ); ?> <a id="js-sirsc-upload-details-trigger-info"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'refresh summary & folder details', 'sirsc' ); ?></a> (<?php esc_html_e( 'this will refresh the totals and counts if something was updated in the meanwhile', 'sirsc' ); ?>).
						</td>
					</tr>
				</table>
				<br>

				<div id="js-sirsc-compsize" class="sirsc-folders-info">
					<?php self::compute_size(); ?>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function() {
			jQuery('#js-sirsc-upload-details-trigger-info').on('click', function() {
				var $tar = jQuery('#js-sirsc-compsize');
				$tar.addClass('js-sirsc-adon-upfode processing');
				jQuery.post('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
					action:'sirsc_compute_upload_folder_size',
					data:'compute',
				}, function(response) {
					if (response) {
						$tar.html(response);
					}
					jQuery(document).trigger('js-sirsc-done');
					$tar.removeClass('js-sirsc-adon-upfode processing');
				}).fail(function(response) {
					if (response) {
						$tar.html('error' + response);
					}
					jQuery(document).trigger('js-sirsc-done');
					$tar.removeClass('js-sirsc-adon-upfode processing');
				}, 'html');
			});
		});
		</script>
		<?php
	}
}

// Instantiate the class.
SIRSC_Adons_Uploads_Folder_Info::get_instance();
