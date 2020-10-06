<?php
/**
 * Uploads inspector extension.
 *
 * @package sirsc
 * @version 1.0
 */

/**
 * Class for Image Regenerate & Select Crop plugin adon Uploads Inspector.
 */
class SIRSC_Adons_Uploads_Inspector {

	const PLUGIN_VER        = 1.00;
	const PLUGIN_ASSETS_VER = '20190814.1527';
	const PLUGIN_TRANS      = 'sirsc_adon_uploads_inspector';
	const PLUGIN_TABLE      = 'sirsc_adon_uploads_inspector';
	const PLUGIN_BATCH_SIZE = 20;
	const ADON_PAGE_SLUG    = 'sirsc-adon-uploads-inspector';
	const ADON_SLUG         = 'uploads-inspector';

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
			self::$instance = new SIRSC_Adons_Uploads_Inspector();
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
		if ( is_admin() ) {
			add_action( 'admin_menu', array( get_called_class(), 'adon_admin_menu' ), 20 );
			add_action( 'plugins_loaded', array( $called, 'load_textdomain' ) );
			add_action( 'admin_enqueue_scripts', array( $called, 'load_assets' ) );
			add_action( 'sirsc_folder_assess_images_button', array( $called, 'folder_assess_images_button' ) );
			add_action( 'sirsc_folder_assess_images_stats', array( $called, 'folder_assess_images_stats' ) );
			add_action( 'wp_ajax_sirsc_impro_assess_images_in_folder', array( $called, 'assess_folders_images' ) );
			add_action( 'wp_ajax_sirsc_reset_assess', array( $called, 'sirsc_reset_assess' ) );
			add_action( 'wp_ajax_sirsc_impro_load_list_page', array( $called, 'stats_load_list_page' ) );

			// Check extension version.
			add_action( 'init', array( $called, 'adon_ver_check' ), 30 );
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
			__( 'Uploads Inspector', 'sirsc' ),
			'<span class="dashicons dashicons-admin-plugins sirsc-mini"></span> ' . __( 'Uploads Inspector', 'sirsc' ),
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
		SIRSC_Adons::check_adon_valid( self::ADON_SLUG );
		$desc = SIRSC_Adons::get_adon_details( self::ADON_SLUG, 'description' );
		?>
		<div class="wrap sirsc-settings-wrap">
			<h1>
				<?php SIRSC_Image_Regenerate_Select_Crop::show_plugin_icon(); ?> <?php esc_html_e( 'Image Regenerate & Select Crop', 'sirsc' ); ?>
			</h1>

			<?php SIRSC_Image_Regenerate_Select_Crop::maybe_all_features_tab(); ?>
			<div class="sirsc-tabbed-menu-content">
				<h1>
					<span class="dashicons dashicons-admin-plugins"></span>
					<?php esc_html_e( 'Uploads Inspector', 'sirsc' ); ?>
				</h1>
				<br>

				<table class="widefat sirsc-striped">
					<tr>
						<td>
							<?php echo wp_kses_post( $desc ); ?>
						</td>
					</tr>
				</table>

				<br>
				<?php do_action( 'sirsc_folder_assess_images_button', '*' ); ?>
				<?php esc_html_e( 'Click to', 'sirsc' ); ?> <a id="js-sirsc-improf-trigger-summary"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'refresh summary', 'sirsc' ); ?></a> (<?php esc_html_e( 'this will refresh the totals and counts if something was updated in the meanwhile', 'sirsc' ); ?>).

				<div id="js-sirsc-compsize" class="sirsc-folders-info">
					<?php self::sirsc_reset_assess(); ?>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Stats load list page.
	 *
	 * @return void
	 */
	public static function stats_load_list_page() {
		$page  = filter_input( INPUT_POST, 'page', FILTER_VALIDATE_INT );
		$page  = ( empty( $page ) ) ? 1 : abs( $page );
		$max   = filter_input( INPUT_POST, 'maxpage', FILTER_VALIDATE_INT );
		$size  = filter_input( INPUT_POST, 'sizename', FILTER_DEFAULT );
		$mime  = filter_input( INPUT_POST, 'mimetype', FILTER_DEFAULT );
		$valid = filter_input( INPUT_POST, 'valid', FILTER_VALIDATE_INT );
		$aid   = filter_input( INPUT_POST, 'aid', FILTER_DEFAULT );
		$title = filter_input( INPUT_POST, 'title', FILTER_DEFAULT );

		$args    = array(
			'base'      => '%_%',
			'format'    => '?p=%#%',
			'total'     => $max,
			'current'   => $page,
			'show_all'  => false,
			'end_size'  => 1,
			'mid_size'  => 2,
			'prev_next' => false,
			'prev_text' => __( '&laquo;' ),
			'next_text' => __( '&raquo;' ),
			'before_page_number' => '<span class="page-item button js-sirsc-adon-improf-list-pagination" data-parentaid="' . $aid . '">',
			'after_page_number'  => '</span>',
			'add_args'           => false,
		);
		$pagination = '<div class="pagination">' . paginate_links( $args ) . '</div>';
		$pagination = preg_replace( '/\s+/', ' ', $pagination );

		$pagination = str_replace( '<span aria-current=\'page\' class=\'page-numbers current\'><span class="page-item button ', '<span aria-current="page" class="page-numbers current"><span class="page-item button button-primary ', $pagination );
		$pagination = str_replace( '<span aria-current="page" class="page-numbers current"><span class="page-item button ', '<span aria-current="page" class="page-numbers current"><span class="page-item button button-primary ', $pagination );
		?>
		<br>
		<table class="wp-list-table widefat striped fixed">
			<tr>
				<td><b><?php echo esc_html( $title ); ?></b></td>
				<td>
					<?php
					echo wp_kses_post( sprintf(
						// Translators: %1$s - current page, %2$s - total pages.
						__( 'Page %1$s of %2$s', 'sirsc' ),
						'<b>' . $page . '</b>',
						'<b>' . $max . '</b>'
					) );
					?>
				</td>
				<td><?php echo wp_kses_post( $pagination ); ?></td>
			</tr>
		</table>
		<?php
		global $wpdb;
		$perpag = get_option( 'posts_per_page' );
		$perpag = ( empty( $perpag ) ) ? 10 : abs( $perpag );
		$offset = ( $page - 1 ) * $perpag;
		$args   = array();
		$tquery = ' SELECT * FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s ';
		$args[] = 'file';
		if ( ! empty( $valid ) ) {
			$tquery .= ' and valid = %d ';
			$args[]  = 1;
		}
		if ( ! empty( $size ) ) {
			$tquery .= ' and size_name = %s ';
			$args[]  = ( 'na' !== $size ) ? $size : '';
			$tquery .= ' and mimetype like %s ';
			$args[]  = 'image/%';
		} elseif ( ! empty( $mime ) ) {
			$tquery .= ' and mimetype like %s ';
			$args[]  = ( 'na' !== $mime ) ? '%/' . $mime : '';
		}
		$tquery .= ' order by id limit %d,%d ';
		$args[]  = $offset;
		$args[]  = $perpag;

		$query = $wpdb->prepare( $tquery, $args ); // WPCS: Unprepared SQL OK.
		$items = $wpdb->get_results( $query ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
		if ( ! empty( $items ) ) {
			$upls = wp_upload_dir();
			$base = trailingslashit( $upls['baseurl'] );
			?>
			<br>
			<table class="wp-list-table widefat striped fixed">
				<thead>
					<tr>
						<td width="40"></td>
						<td><?php esc_html_e( 'File', 'sirsc' ); ?></td>
						<td width="10%"><?php esc_html_e( 'MIME Type', 'sirsc' ); ?></td>
						<td width="10%" align="right" class="right"><?php esc_html_e( 'File size', 'sirsc' ); ?></td>
						<td width="10%" align="right" class="right"><?php esc_html_e( 'Size', 'sirsc' ); ?></td>
						<td width="10%"><?php esc_html_e( 'Attachment', 'sirsc' ); ?></td>
					</tr>
				</thead>
				<?php
				foreach ( $items as $item ) {
					$url = $base . $item->path;

					$sizename = ( empty( $item->size_name ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->size_name;
					$mimetype = ( empty( $item->mimetype ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->mimetype;
					?>
					<tr>
						<td><?php echo ++ $offset; // PHPCS:ignore ?></td>
						<td>
							<div class="thumb">
								<?php if ( substr_count( $item->mimetype, 'image/' ) ) : ?>
									<img src="<?php echo esc_url( $url ); ?>">
								<?php endif; ?>
							</div>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank"><div class="dashicons dashicons-admin-links"></div></a>
							<?php echo esc_html( $item->path ); ?>
							<?php if ( ! empty( $item->in_option ) ) : ?>
								<div class="sirsc-small-font"><?php esc_html_e( 'In option', 'sirsc' ); ?> <?php echo esc_html( $item->in_option ); ?></div>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $mimetype ); ?></td>
						<td align="right"><b><?php echo esc_html( self::human_filesize( $item->filesize ) ); ?></b>
							<div class="sirsc-small-font">(<?php echo esc_html( $item->filesize ); ?> <?php esc_html_e( 'bytes', 'sirsc' ); ?>)</div>
						</td>
						<td align="right">
							<?php
							if ( ! substr_count( $item->mimetype, 'image/' ) ) {
								esc_html_e( 'N/A', 'sirsc' );
							} else {
								if ( ! empty( $item->size_width ) ) {
									?>
									<b><?php echo esc_html( $item->size_width ); ?></b><span class="sirsc-small-font">x</span><b><?php echo esc_html( $item->size_height ); ?></b><span class="sirsc-small-font">px</span>
									<div class="sirsc-small-font">(<?php echo esc_html( $sizename ); ?>)</div>
									<?php
								} else {
									echo esc_html( $sizename );
								}
							}
							?>
						</td>
						<td>
							<?php if ( ! empty( $item->attachment_id ) ) : ?>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $item->attachment_id . '&action=edit' ) ); ?>" target="_blank"><div class="dashicons dashicons-admin-links"></div></a>
								<?php echo (int) $item->attachment_id; ?>
							<?php else : ?>
								<div class="sirsc-small-font">~<?php esc_html_e( 'unknown', 'sirsc' ); ?>~</div>
							<?php endif; ?>
						</td>
					</tr>
					<?php
				}
				?>
			</table>
			<br>
			<?php
		}
		wp_die();
		die();
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
	 * Show the folder info and maybe reset the assess processing on ajax.
	 *
	 * @return void
	 */
	public static function sirsc_reset_assess() {
		$is_ajax = false;
		$act = filter_input( INPUT_POST, 'action', FILTER_DEFAULT );
		if ( ! empty( $act ) && ( 'sirsc_compute_upload_folder_size' === $act || 'sirsc_reset_assess' === $act ) ) {
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
		<br>
		<div class="sirsc_folders-info-menu-buttons">
			<a class="button button-primary"><?php esc_html_e( 'Folder Summary', 'sirsc' ); ?></a>
		</div>
		<div class="sirsc-adon-folders-info-wrap">
			<?php self::output_root_summary( $info ); ?>
		</div>
		<?php
		if ( true === $is_ajax ) {
			self::cleanup_not_found();
			update_option( self::PLUGIN_TABLE . '_last_proc', current_time( 'timestamp' ) );
		}
		do_action( 'sirsc_folder_assess_images_stats' );

		if ( true === $is_ajax ) {
			update_option( self::PLUGIN_TABLE . '_proc_dir', 0 );
			update_option( self::PLUGIN_TABLE . '_proc_item', '' );
			update_option( self::PLUGIN_TABLE . '_proc_time', 0 );
			wp_die();
			die();
		}
	}

	/**
	 * Cleanup not found.
	 *
	 * @return void
	 */
	public static function cleanup_not_found() {
		$time = get_option( self::PLUGIN_TABLE . '_proc_time', 0 );
		if ( ! empty( $time ) ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( ' DELETE FROM ' . self::PLUGIN_TABLE . ' WHERE date < %d ', $time ) ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
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
		wp_enqueue_style(
			'sirsc-adons-improf',
			plugins_url( '/assets/style.css', __FILE__ ),
			array(),
			self::PLUGIN_ASSETS_VER,
			false
		);

		wp_register_script(
			'sirsc-adons-improf',
			plugins_url( '/assets/script.js', __FILE__ ),
			array( 'jquery' ),
			self::PLUGIN_ASSETS_VER,
			false
		);
		wp_localize_script( 'sirsc-adons-improf', 'SIRSC_Adons_Improf', array(
			'ajaxUrl'      => esc_url( admin_url( 'admin-ajax.php' ) ),
			'listBoxTitle' => __( 'List', 'sirsc' ),
		) );
		wp_enqueue_script( 'sirsc-adons-improf' );
	}

	/**
	 * The actions to be executed when the plugin is updated.
	 *
	 * @return void
	 */
	public static function adon_ver_check() {
		$opt = str_replace( '-', '_', self::PLUGIN_TRANS ) . '_db_ver';
		$dbv = get_option( $opt, 0 );
		if ( self::PLUGIN_VER !== (float) $dbv ) {
			self::maybe_upgrade_db();
			set_transient( self::PLUGIN_TRANS, true );
		}
	}

	/**
	 * Maybe upgrade the table structure.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_db() {
		global $wpdb;
		$opt = str_replace( '-', '_', self::PLUGIN_TRANS ) . '_db_ver';
		$dbv = get_option( $opt, 0 );
		if ( self::PLUGIN_VER !== (float) $dbv ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$sql = ' CREATE TABLE ' . self::PLUGIN_TABLE . ' (
				`id` bigint(20) AUTO_INCREMENT,
				`date` bigint(20),
				`type` varchar(15),
				`path` varchar(255),
				`attachment_id` bigint(20),
				`size_name` varchar(255),
				`size_width` int(11),
				`size_height` int(11),
				`mimetype` varchar(32),
				`filesize` bigint(20),
				`in_option` varchar(255),
				`valid` tinyint(1) default 0,
				`assessed` tinyint(1) default 0,
				`count_files` bigint(20),
				UNIQUE KEY `id` (id),
				KEY `type` (`type`),
				KEY `size_name` (`size_name`),
				KEY `mimetype` (`mimetype`),
				KEY `path` (`path`),
				KEY `date` (`date`),
				KEY `valid` (`valid`)
			) CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT = \'Table created by Image Regenerate & Select Crop Adon for Uploads Inspector\'';
			dbDelta( $sql );
			update_option( $opt, (float) self::PLUGIN_VER );
		}
	}

	/**
	 * Show an images assess trigger button markup.
	 *
	 * @param  string $path Path of a folder.
	 * @return void
	 */
	public static function folder_assess_images_button( $path ) {
		?>
		<?php esc_html_e( 'Click to', 'sirsc' ); ?> <a id="js-sirsc-improf-trigger-assess" data-path="<?php echo esc_attr( $path ); ?>"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'assess files from uploads folder & refresh the info', 'sirsc' ); ?></a>.
		<?php
	}

	/**
	 * Show an images assess profile stats.
	 *
	 * @return void
	 */
	public static function folder_assess_images_stats() {
		echo '<br>';
		self::stats();
	}

	/**
	 * Images stats.
	 *
	 * @return void
	 */
	public static function stats() {
		global $wpdb;
		$perpag = get_option( 'posts_per_page' );
		$perpag = ( empty( $perpag ) ) ? 10 : abs( $perpag );

		$last_proc = get_option( self::PLUGIN_TABLE . '_last_proc', 0 );
		if ( empty( $last_proc ) ) {
			return;
		}
		?>

		<div class="sirsc_folders-info-menu-buttons">
			<a class="button button-primary"><?php esc_html_e( 'Files Info', 'sirsc' ); ?></a>
		</div>
		<div class="sirsc-adon-folders-info-wrap">
			<?php
			echo wp_kses_post( sprintf(
				// Translators: %1$s - current page, %2$s - total pages.
				__( 'The most recent files assessment was executed on %1$s.', 'sirsc' ),
				'<b>' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_proc, true ) . '</b>'
			) );
			?>

			<table class="fixed" width="100%">
				<tr>
				<?php
				$query = $wpdb->prepare( ' SELECT mimetype, COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s GROUP BY mimetype ', 'file' ); // WPCS: Unprepared SQL OK.
				$items = $wpdb->get_results( $query ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
				if ( ! empty( $items ) ) {
					?>
					<td valign="top">
						<h3><?php esc_html_e( 'MIME Type', 'sirsc' ); ?></h3><hr>
						<ul>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$max_page = ceil( (int) $item->total_files / $perpag );
								$mimetype = ( empty( $item->mimetype ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->mimetype;
								$v_mtype  = ( empty( $item->mimetype ) ) ? 'na' : ltrim( strstr( $item->mimetype, '/', false ), '/' );
								?>
								<li>
									<?php if ( empty( $item->mimetype ) ) : ?>
										<em>~<?php esc_html_e( 'unknown', 'sirsc' ); ?>~</em>
									<?php else : ?>
										<b><?php echo esc_html( $item->mimetype ); ?></b>
									<?php endif; ?>
									<a id="js-sirsc-adon-improf-list-mime-<?php echo esc_attr( $v_mtype . '-0' ); ?>"
										class="js-sirsc-adon-improf-list"
										data-page="1"
										data-maxpage="<?php echo (int) $max_page; ?>"
										data-sizename=""
										data-mimetype="<?php echo esc_attr( $v_mtype ); ?>"
										data-valid="0"
										data-title="<?php echo esc_attr( __( 'MIME Type', 'sirsc' ) . ': ' . $mimetype ); ?>"
										>(<?php echo (int) $item->total_files; ?> <?php esc_html_e( 'files', 'sirsc' ); ?>)</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</td>
					<?php
				}

				$query = $wpdb->prepare( ' SELECT size_name, COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s and mimetype like %s  GROUP BY size_name', 'file', 'image/%' ); // WPCS: Unprepared SQL OK.
				$items = $wpdb->get_results( $query ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
				if ( ! empty( $items ) ) {
					?>
					<td valign="top">
						<h3><?php esc_html_e( 'Images Sizes', 'sirsc' ); ?></h3><hr>
						<ul>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$max_page  = ceil( (int) $item->total_files / $perpag );
								$size_name = ( empty( $item->size_name ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->size_name;
								$v_sname   = ( empty( $item->size_name ) ) ? 'na' : $item->size_name;
								?>
								<li>
									<?php if ( empty( $item->size_name ) ) : ?>
										<em>~<?php esc_html_e( 'unknown', 'sirsc' ); ?>~</em>
									<?php else : ?>
										<b><?php echo esc_html( $item->size_name ); ?></b>
									<?php endif; ?>
									<a id="js-sirsc-adon-improf-list-size-<?php echo esc_attr( $v_sname . '-0' ); ?>"
										class="js-sirsc-adon-improf-list"
										data-page="1"
										data-maxpage="<?php echo (int) $max_page; ?>"
										data-sizename="<?php echo esc_attr( $v_sname ); ?>"
										data-mimetype=""
										data-valid="0"
										data-title="<?php echo esc_attr( __( 'Images Sizes', 'sirsc' ) . ': ' . $size_name ); ?>"
										>(<?php echo (int) $item->total_files; ?> <?php esc_html_e( 'files', 'sirsc' ); ?>)</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</td>
					<?php
				}

				$query = $wpdb->prepare( ' SELECT size_name, COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s AND valid = 1 and mimetype like %s  GROUP BY size_name', 'file', 'image/%' ); // WPCS: Unprepared SQL OK.
				$items = $wpdb->get_results( $query ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
				if ( ! empty( $items ) ) {
					?>
					<td valign="top">
						<h3><?php esc_html_e( 'Valid Images', 'sirsc' ); ?></h3><hr>
						<ul>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$max_page  = ceil( (int) $item->total_files / $perpag );
								$size_name = ( empty( $item->size_name ) ) ? '~' . __( 'unknown', 'sirsc' ) . '~' : $item->size_name;
								$v_sname   = ( empty( $item->size_name ) ) ? 'na' : $item->size_name;
								?>
								<li>
									<?php if ( empty( $item->size_name ) ) : ?>
										<em>~<?php esc_html_e( 'unknown', 'sirsc' ); ?>~</em>
									<?php else : ?>
										<?php echo esc_html( $item->size_name ); ?>
									<?php endif; ?>
									<a id="js-sirsc-adon-improf-list-size-<?php echo esc_attr( $v_sname . '-1' ); ?>"
										class="js-sirsc-adon-improf-list"
										data-page="1"
										data-maxpage="<?php echo (int) $max_page; ?>"
										data-sizename="<?php echo esc_attr( $v_sname ); ?>"
										data-mimetype=""
										data-valid="0"
										data-title="<?php echo esc_attr( __( 'Valid Images', 'sirsc' ) . ': ' . $size_name ); ?>"
										>(<?php echo (int) $item->total_files; ?> <?php esc_html_e( 'files', 'sirsc' ); ?>)</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</td>
					<?php
				}
				?>
			</tr>
		</table>

		</div>
		<?php
	}

	/**
	 * Get assessed folders.
	 *
	 * @param  integer $id     Folder id.
	 * @param  boolean $use_id True to use the id for compare.
	 * @return array|object
	 */
	public static function get_assessed_folders( $id = 0, $use_id = false ) {
		global $wpdb;
		$folders = array();
		if ( true === $use_id ) {
			$query = $wpdb->prepare( ' SELECT * FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s and id > %d ORDER BY id ASC LIMIT 0,1', 'folder', $id ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
			$rows = $wpdb->get_row( $query ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
		} else {
			$query = $wpdb->prepare( ' SELECT * FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s ORDER BY id ASC ', 'folder' ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
			$rows    = $wpdb->get_results( $query ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
		}

		if ( ! empty( $rows ) ) {
			$folders = $rows;
		}
		return $folders;
	}

	/**
	 * Get the size of the directory.
	 *
	 * @param string  $type        Item type (folder|file).
	 * @param string  $path        The item path.
	 * @param integer $size        The item size.
	 * @param integer $count_files The items count.
	 * @return void
	 */
	public static function record_item( $type, $path, $size, $count_files = 0 ) {
		global $wpdb;

		$attachment_id = 0;
		$mimetype      = '';
		$size_name     = '';
		$size_width    = 0;
		$size_height   = 0;
		$valid         = 0;
		$in_option     = '';

		if ( 'file' === $type ) {
			update_option( self::PLUGIN_TABLE . '_proc_item', $path );

			$original  = $path;
			$size_file = basename( $original );
			$tmp_query = $wpdb->prepare( '
				SELECT a.ID, group_concat( concat( am.meta_key, \'[#$#]\', am.meta_value ) separator \'[#@#]\' ) as str_meta FROM ' . $wpdb->posts . ' as a
				LEFT JOIN ' . $wpdb->postmeta . ' as am ON(am.post_id = a.ID)
				WHERE (am.meta_key like %s OR am.meta_key like %s ) AND ( am.meta_value like %s OR am.meta_value like %s )
				GROUP BY a.id
				ORDER BY a.ID LIMIT 0,1',
				'_wp_attachment_metadata',
				'_wp_attached_file',
				'%' . $original . '%',
				'%' . $size_file . '%'
			);
			$row = $wpdb->get_row( $tmp_query ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
			if ( ! empty( $row ) ) {
				$attachment_id = $row->ID;
				if ( ! empty( $row->str_meta ) ) {
					$meta = '';
					if ( substr_count( $row->str_meta, '_wp_attachment_metadata' ) ) {
						// Potential image.
						$p = explode( '[#@#]', $row->str_meta );
						if ( ! empty( $p[0] ) && substr_count( $p[0], '_wp_attachment_metadata' ) ) {
							$meta = $p[0];
						} elseif ( ! empty( $p[1] ) && substr_count( $p[1], '_wp_attachment_metadata' ) ) {
							$meta = $p[1];
						}

						if ( ! empty( $meta ) ) {
							$meta = trim( str_replace( '_wp_attachment_metadata[#$#]', '', $meta ) );
							$meta = maybe_unserialize( trim( $meta ) );
							if ( ! is_array( $meta ) ) {
								// Fallback to the wp function.
								$meta = wp_get_attachment_metadata( $attachment_id );
							}
						}
					}

					if ( ! empty( $meta ) && is_array( $meta ) ) {
						$mt       = wp_check_filetype( $size_file );
						$mimetype = $mt['type'];
						if ( ! empty( $meta['file'] ) && $original === $meta['file'] ) {
							$size_name   = 'full';
							$size_width  = $meta['width'];
							$size_height = $meta['height'];
							$maybe_type  = wp_check_filetype( $meta['file'] );
							$mimetype    = ( ! empty( $maybe_type['type'] ) ) ? $maybe_type['type'] : $mimetype;
							$valid       = 1;
						} elseif ( ! empty( $meta['sizes'] ) ) {
							foreach ( $meta['sizes'] as $key => $value ) {
								if ( $size_file === $value['file'] ) {
									$size_name   = $key;
									$size_width  = $value['width'];
									$size_height = $value['height'];
									$mimetype    = $value['mime-type'];
									$valid       = 1;
									break;
								}
							}
						}
					} else {
						$mt = wp_check_filetype( $path );
						$mimetype = $mt['type'];
					}
				}
			} else {
				$mt = wp_check_filetype( $path );
				$mimetype = $mt['type'];
			}

			$in_option = '';
			$tmp_query2 = $wpdb->prepare( '
				SELECT group_concat(option_name separator \', \') FROM ' . $wpdb->options . '
				WHERE option_value like %s AND option_name not like %s
				GROUP BY option_name
				ORDER BY option_name LIMIT 0,1',
				'%' . $path . '%',
				'%sirsc_adon%'
			);
			$row2 = $wpdb->get_var( $tmp_query2 ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
			if ( ! empty( $row2 ) ) {
				$in_option = $row2;
			}
		}

		$array_data = array(
			'date'          => current_time( 'timestamp' ),
			'type'          => $type,
			'path'          => $path,
			'filesize'      => $size,
			'attachment_id' => $attachment_id,
			'mimetype'      => $mimetype,
			'size_name'     => $size_name,
			'size_width'    => $size_width,
			'size_height'   => $size_height,
			'valid'         => $valid,
			'count_files'   => $count_files,
			'in_option'     => $in_option,
		);
		$array_type = array(
			'%d',
			'%s',
			'%s',
			'%d',
			'%d',
			'%s',
			'%s',
			'%d',
			'%d',
			'%d',
			'%d',
			'%s',
		);

		$tmp_query = $wpdb->prepare( '
			SELECT id FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s AND path = %s ORDER BY id LIMIT 0,1',
			$type,
			$path
		); // WPCS: Unprepared SQL OK.
		$id = $wpdb->get_var( $tmp_query ); // PHPCS:ignore WordPress.WP.PreparedSQL.NotPrepared
		if ( ! empty( $id ) ) {
			$wpdb->update( self::PLUGIN_TABLE, $array_data, array( 'id' => $id ), $array_type, array( '%d' ) );
		} else {
			$wpdb->insert( self::PLUGIN_TABLE, $array_data, $array_type );
		}
	}

	/**
	 * Compute progress bar.
	 *
	 * @return void
	 */
	public static function compute_progress_bar() {
		global $wpdb;

		$total     = get_option( 'sirsc_adon_uploads_files_count', 0 );
		$time      = get_option( self::PLUGIN_TABLE . '_proc_time', 0 );
		$processed = $wpdb->get_var( $wpdb->prepare( ' SELECT COUNT(id) as total_files FROM ' . self::PLUGIN_TABLE . ' WHERE type = %s and date >= %d', 'file', $time ) ); // PHPCS:ignore
		$proc = 0;
		if ( ! empty( $total ) ) {
			$proc = ceil( $processed * 100 / $total );
		}
		$proc = ( $proc > 100 ) ? 100 : $proc;
		?>
		<table class="fixed" width="100%">
			<tr>
				<td width="40%">
					<?php
					echo esc_html( sprintf(
						// Translators: %1$d - count products, %2$d - total.
						__( 'There are %1$d items assessed out of %2$d.', 'sirsc' ),
						$processed,
						$total
					) );
					?>
				</td>
				<td>
					<div class="js-sirsc-adon-improf-progress">
						<div class="processed" style="width:<?php echo (int) $proc; ?>%"><?php echo (int) $proc; ?>%</div>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Get the size of the directory.
	 *
	 * @return void
	 */
	public static function assess_folders_images() {

		$last_item = get_option( self::PLUGIN_TABLE . '_proc_item', '' );
		$dir_id    = get_option( self::PLUGIN_TABLE . '_proc_dir', 0 );
		$upls      = wp_upload_dir();
		$base      = trailingslashit( $upls['basedir'] );

		if ( empty( $dir_id ) ) {
			$trid = 'sirsc_adon_uploads_folder_summary';
			$info = get_transient( $trid );
			if ( ! empty( $info ) ) {
				foreach ( $info as $k => $folder ) {
					if ( $k > 0 ) {
						$p = str_replace( $base, '', $folder['path'] );
						self::record_item( 'folder', $p, $folder['totals']['files_size'], $folder['totals']['files_count'] );
					}
				}
			}
		}

		$time = get_option( self::PLUGIN_TABLE . '_proc_time', 0 );
		if ( empty( $time ) ) {
			update_option( self::PLUGIN_TABLE . '_proc_time', current_time( 'timestamp' ) );
		}

		$maybe_dir = self::get_assessed_folders( (int) $dir_id, true );
		if ( ! empty( $maybe_dir->path ) ) {
			self::compute_progress_bar();
			?>
			<table class="fixed widefat">
				<tr>
					<td>
						<h1><?php esc_html_e( 'Processing the request for', 'sirsc' ); ?> <b><?php echo esc_html( $maybe_dir->path ); ?></b></h1>
						<div style="min-height: 220px">
							<?php
							$dir  = $base . $maybe_dir->path;

							$last_path = $base . $last_item;
							$search    = true;
							$record    = ( empty( $last_item ) ) ? true : false;
							$count     = 0;
							$all       = 0;
							foreach ( glob( rtrim( $dir, '/' ) . '/*', GLOB_NOSORT ) as $each ) { // | GLOB_NOSORT
								if ( is_file( $each ) ) {
									if ( true === $search ) {
										if ( $each === $last_path ) {
											$record = true;
											$search = false; // This was found, rely only on the counts.
										}
									}
									if ( true === $record ) {
										if ( $count < self::PLUGIN_BATCH_SIZE ) {
											$p = str_replace( $base, '', $each );
											self::record_item( 'file', $p, filesize( $each ) );
											echo wp_kses_post( '<br>&bull; <em>' . esc_html( $p ) . '</em>' );

											++ $count;
										} else {
											break 1;
										}
									}

									++ $all;
								}
							}
							?>
						</div>
					</td>
				</tr>
			</table>
			<?php
			do_action( 'sirsc_folder_assess_images_stats' );

			if ( $count <= 1 && ! empty( $record ) ) {
				// This means that maybe the folder was all processed.
				update_option( self::PLUGIN_TABLE . '_proc_dir', (int) $maybe_dir->id );
				update_option( self::PLUGIN_TABLE . '_proc_item', '' );

			}
		}

		$act = filter_input( INPUT_POST, 'action', FILTER_DEFAULT );
		if ( ! empty( $act ) && 'sirsc_impro_assess_images_in_folder' === $act ) {
			if ( ! empty( $maybe_dir ) ) {
				?>
				<script>
					jQuery(document).ready(function() {
						sirsc_improf_continue_each_folder_assess();
					});
				</script>
				<?php
			} else {
				?>
				<script>
					jQuery(document).ready(function() {
						// Done, reload folders.
						jQuery('#js-sirsc-improf-trigger-summary').trigger('click');
						jQuery(document).trigger('js-sirsc-done');
					});
				</script>
				<?php
				self::sirsc_reset_assess();
			}

			wp_die();
			die();
		}
	}
}

// Instantiate the class.
SIRSC_Adons_Uploads_Inspector::get_instance();
