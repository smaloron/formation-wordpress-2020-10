<?php
/**
 * Description: The image placeholder component of the Image Regenerate & Select Crop plugin.
 *
 * @package sirsc
 */

/**
 * Image placeholder class for SIRSC plugin.
 */
class SIRSC_Image_Placeholder {

	/**
	 * Class instance
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Get the class current instance.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new SIRSC_Image_Placeholder();
		}
		return self::$instance;
	}

	/**
	 * Class constructor
	 *
	 * @return void
	 */
	public function __construct() {
		self::init();
	}

	/**
	 * The class init.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! defined( 'SIRSC_PLACEHOLDER_FOLDER' ) ) {
			$dest_url  = plugin_dir_url( '/assets/placeholders', __FILE__ );
			$dest_path = plugin_dir_path( '/assets/placeholders', __FILE__ );
			if ( ! file_exists( $dest_path ) ) {
				@wp_mkdir_p( $dest_path );
			}
			define( 'SIRSC_PLACEHOLDER_FOLDER', realpath( $dest_path ) );
			define( 'SIRSC_PLACEHOLDER_URL', esc_url( $dest_url ) );
		}
	}

	/**
	 * Make placeholder with imagettftext.
	 *
	 * @param  string  $dest Images destination.
	 * @param  integer $iw   Image width.
	 * @param  integer $ih   Image height.
	 * @param  string  $name Image size name.
	 * @param  integer $sw   Width text.
	 * @param  integer $sh   Height text.
	 * @return void
	 */
	public static function make_placeholder_imagettftext( $dest, $iw, $ih, $name, $sw, $sh ) {
		$im    = @imagecreatetruecolor( $iw, $ih );
		$white = @imagecolorallocate( $im, 255, 255, 255 );
		$rand  = @imagecolorallocate( $im, mt_rand( 0, 150 ), mt_rand( 0, 150 ), mt_rand( 0, 150 ) );
		@imagefill( $im, 0, 0, $rand );
		$font = @realpath( SIRSC_PLUGIN_FOLDER . '/assets/fonts' ) . '/arial.ttf';
		@imagettftext( $im, 6.5, 0, 2, 10, $white, $font, 'placeholder' );
		@imagettftext( $im, 6.5, 0, 2, 20, $white, $font, $name );
		@imagettftext( $im, 6.5, 0, 2, 30, $white, $font, $sw . 'x' . $sh );
		@imagepng( $im, $dest, 9 );
		@imagedestroy( $im );
	}

	/**
	 * Make placeholder with Imagick.
	 *
	 * @param  string  $dest Images destination.
	 * @param  integer $iw   Image width.
	 * @param  integer $ih   Image height.
	 * @param  string  $name Image size name.
	 * @param  integer $sw   Width text.
	 * @param  integer $sh   Height text.
	 * @return void
	 */
	public static function make_placeholder_imagick( $dest, $iw, $ih, $name, $sw, $sh ) {
		$im    = new Imagick();
		$draw  = new ImagickDraw();
		$pixel = new ImagickPixel( '#' . mt_rand( 10, 99 ) . mt_rand( 10, 99 ) . mt_rand( 10, 99 ) );
		$im->newImage( $iw, $ih, $pixel );
		$draw->setFillColor( '#FFFFFF' );
		$draw->setFont( SIRSC_PLUGIN_FOLDER . '/assets/fonts/arial.ttf' );
		$draw->setFontSize( 12 );
		$draw->setGravity( Imagick::GRAVITY_CENTER );
		$im->annotateImage( $draw, 0, 0, 0, $sw . 'x' . $sh );
		$im->setImageFormat( 'png' );
		$im->writeimage( $dest );
	}

	/**
	 * Make placeholder with imagestring.
	 *
	 * @param  string  $dest Images destination.
	 * @param  integer $iw   Image width.
	 * @param  integer $ih   Image height.
	 * @param  string  $name Image size name.
	 * @param  integer $sw   Width text.
	 * @param  integer $sh   Height text.
	 * @return void
	 */
	public static function make_placeholder_imagestring( $dest, $iw, $ih, $name, $sw, $sh ) {
		$im    = imagecreatetruecolor( $iw, $ih );
		$white = imagecolorallocate( $im, 255, 255, 255 );
		$rand  = imagecolorallocate( $im, mt_rand( 0, 150 ), mt_rand( 0, 150 ), mt_rand( 0, 150 ) );
		imagefill( $im, 0, 0, $rand );
		imagestring( $im, 2, 2, 2, 'placeholder', $white );
		imagestring( $im, 2, 2, 12, $name, $white );
		if ( $name !== $sw . 'x' . $sh ) {
			imagestring( $im, 2, 2, 22, $sw . 'x' . $sh, $white );
		}
		imagepng( $im, $dest, 9 );
		imagedestroy( $im );
	}

	/**
	 * Make placeholder url.
	 *
	 * @param  integer $iw   Image width.
	 * @param  integer $ih   Image height.
	 * @param  integer $sw   Width text.
	 * @param  integer $sh   Height text.
	 * @return string
	 */
	public static function make_placeholder_dummy( $iw, $ih, $sw, $sh ) {
		return 'https://dummyimage.com/' . $iw . 'x' . $ih . '/' . mt_rand( 10, 99 ) . mt_rand( 10, 99 ) . mt_rand( 10, 99 ) . '/ffffff&fsize=7&size=7&text=+++placeholder+' . $sw . 'x' . $sh . '++';
	}

	/**
	 * Compute placeholder url.
	 *
	 * @param  array  $alls          All image sizes.
	 * @param  string $dest          The destination path.
	 * @param  string $dest_url      The destination url.
	 * @param  string $selected_size The intended image size.
	 * @param  string $alternative   The alternative image size.
	 * @return string
	 */
	public static function compute_placeholder_url( $alls, $dest, $dest_url, $selected_size, $alternative ) {
		if ( empty( $alls ) ) {
			if ( class_exists( 'SIRSC_Image_Regenerate_Select_Crop' ) ) {
				$alls = SIRSC_Image_Regenerate_Select_Crop::get_all_image_sizes_plugin();
			}
		}

		$iw = 0;
		$ih = 0;
		$ew = 0;
		$eh = 0;

		$size_sel = $selected_size;
		if ( 'full' === $selected_size ) {
			// Compute the full fallback for a width and height.
			$size_sel = 'full';
			if ( ! empty( $alternative ) && 'full' != $alternative ) {
				$size_sel = $alternative;
			} elseif ( ! empty( $alls['large'] ) ) {
				$size_sel = 'large';
			} elseif ( ! empty( $alls['medium_large'] ) ) {
				$size_sel = 'medium_large';
			}
		}
		if ( ! empty( $alls[ $size_sel ] ) ) {
			$size = $alls[ $size_sel ];
			$iw   = (int) $size['width'];
			$ih   = (int) $size['height'];
			if ( ! empty( $size['width'] ) && empty( $size['height'] ) ) {
				$ih = $iw;
			} elseif ( empty( $size['width'] ) && ! empty( $size['height'] ) ) {
				$iw = $ih;
			}
		} else {
			$s  = explode( 'x', $size_sel );
			$iw = ( ! empty( $s[0] ) ) ? (int) $s[0] : 0;
			$iw = ( empty( $iw ) ) ? SIRSC_Image_Regenerate_Select_Crop::$limit9999 : $iw;
			$ih = ( ! empty( $s[1] ) ) ? (int) $s[1] : 0;
			$ih = ( empty( $ih ) ) ? SIRSC_Image_Regenerate_Select_Crop::$limit9999 : $ih;
		}

		$ew = $iw;
		$eh = $ih;
		if ( $iw >= 9999 ) {
			$iw = SIRSC_Image_Regenerate_Select_Crop::$limit9999;
			$ew = '0';
		}
		if ( $ih >= 9999 ) {
			$ih = SIRSC_Image_Regenerate_Select_Crop::$limit9999;
			$eh = '0';
		}

		if ( ! wp_is_writable( realpath( SIRSC_PLACEHOLDER_FOLDER ) ) ) {
			// By default set the dummy, the folder is not writtable.
			$dest_url = self::make_placeholder_dummy( $iw, $ih, $ew, $eh );
			return $dest_url;
		}

		if ( function_exists( 'imagettfbbox' ) ) {
			self::make_placeholder_imagettftext( $dest, $iw, $ih, $selected_size, $ew, $eh );
		} elseif ( class_exists( 'Imagick' ) ) {
			self::make_placeholder_imagick( $dest, $iw, $ih, $selected_size, $ew, $eh );
		} elseif ( function_exists( 'imagestring' ) ) {
			self::make_placeholder_imagestring( $dest, $iw, $ih, $selected_size, $ew, $eh );
		} else {
			$dest_url = self::make_placeholder_dummy( $iw, $ih, $ew, $eh );
		}

		return $dest_url;
	}

}

// Initialize the class.
SIRSC_Image_Placeholder::get_instance();
