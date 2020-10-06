<?php
/**
 * Description: The WP-CLI component of the Image Regenerate & Select Crop plugin.
 *
 * @package sirsc
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Quick WP-CLI command to for SIRSC plugin that allows to regenerate and remove images.
	 */
	class SIRSC_Image_Regenerate_Select_Crop_CLI_Command extends WP_CLI_Command {
		/**
		 * Prepare command arguments.
		 *
		 * @param array $args Command default arguments.
		 */
		private static function prepare_args( $args ) {
			$rez = array(
				'site_id'   => 1,
				'post_type' => '',
				'size_name' => '',
				'parent_id' => '',
				'all_sizes' => array(),
			);
			if ( ! isset( $args[0] ) ) {
				WP_CLI::error( esc_html__( 'Please specify the site id (1 if not multisite).', 'sirsc' ) );
				return;
			} else {
				$rez['site_id'] = intval( $args[0] );
			}

			if ( is_multisite() ) {
				switch_to_blog( $rez['site_id'] );
			}
			WP_CLI::line( '******* SIRSC EXECUTE OPERATION ON SITE ' . $rez['site_id'] . ' *******' );
			if ( ! isset( $args[1] ) ) {
				$pt = get_option( 'sirsc_types_options', array() );
				if ( ! empty( $pt ) ) {
					$av = '';
					foreach ( $pt as $k => $v ) {
						$av .= ( '' === $av ) ? '' : ', ';
						$av .= $v;
					}
				} else {
					$pt = SIRSC_Image_Regenerate_Select_Crop::get_all_post_types_plugin();
					$av = '';
					foreach ( $pt as $k => $v ) {
						$av .= ( '' === $av ) ? '' : ', ';
						$av .= $k;
					}
				}
				WP_CLI::error( 'Please specify the post type (one of: ' . $av . ', etc).' );
				return;
			} else {
				$rez['post_type'] = trim( $args[1] );
			}

			if ( ! empty( $args['the_command'] ) && ( 'rawcleanup' === $args['the_command'] || 'resetcleanup' === $args['the_command'] ) ) {
				// This is always all.
				$args[2] = 'all';
			}
			$all_sizes        = SIRSC_Image_Regenerate_Select_Crop::get_all_image_sizes();
			$rez['all_sizes'] = $all_sizes;
			if ( ! isset( $args[2] ) ) {
				$ims = '';
				foreach ( $all_sizes as $k => $v ) {
					$ims .= ( '' === $ims ) ? '' : ', ';
					$ims .= $k;
				}
				WP_CLI::error( 'Please specify the image size name (one of: ' . $ims . ').' );
				return;
			} else {
				if ( 'all' === $args[2] || ! empty( $all_sizes[ $args[2] ] ) || ! empty( $args['is_cleanup'] ) ) {
					$rez['size_name'] = trim( $args[2] );
				} else {
					WP_CLI::error( 'Please specify a valid image size name.' );
					return;
				}
			}
			if ( isset( $args[3] ) ) {
				$rez['parent_id'] = (int) $args[3];
			}

			return $rez;
		}

		/**
		 * Assess the command arguments to see if this should be verbose.
		 *
		 * @param array $assoc_args Command associated arguments.
		 * @return  boolean
		 */
		private static function is_verbose( $assoc_args ) {
			if ( ! empty( $assoc_args['verbose'] ) ) {
				return true;
			}
			if ( ! empty( $assoc_args['v'] ) ) {
				return true;
			}
			if ( ! empty( $assoc_args['show-info'] ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Arguments order and types : (int)site_id (string)post_type (string)size_name (int)parent_id
		 *
		 * @param array $args       Command default arguments.
		 * @param array $assoc_args Command associated arguments.
		 */
		public function regenerate( $args, $assoc_args ) {
			$config = self::prepare_args( $args );
			if ( ! is_array( $config ) ) {
				return;
			}

			if ( ! defined( 'DOING_SIRSC' ) ) {
				// Maybe indicate to other scrips/threads that SIRSC is processing.
				define( 'DOING_SIRSC', true );
			}

			$verbose = self::is_verbose( $assoc_args );
			extract( $config ); //phpcs:ignore
			if ( ! empty( $post_type ) && ! empty( $size_name ) && ! empty( $all_sizes ) ) {
				global $wpdb;
				$execute_sizes = array();
				if ( 'all' === $size_name ) {
					$execute_sizes = $all_sizes;
				} else {
					if ( ! empty( $all_sizes[ $size_name ] ) ) {
						$execute_sizes[ $size_name ] = $size_name;
					}
				}
				$rows = self::make_query( $post_type, $parent_id, 'REGENERATE' );
				if ( ! empty( $rows ) && is_array( $rows ) ) {
					if ( ! empty( $execute_sizes ) ) {
						foreach ( $execute_sizes as $sn => $sv ) {
							$progress = \WP_CLI\Utils\make_progress_bar( '------- REGENERATE ' . $sn, count( $rows ) );
							foreach ( $rows as $v ) {

								SIRSC_Image_Regenerate_Select_Crop::load_settings_for_post_id( $v['ID'] );
								if ( ! empty( SIRSC_Image_Regenerate_Select_Crop::$settings['restrict_sizes_to_these_only'] )
									&& ! in_array( $sn, SIRSC_Image_Regenerate_Select_Crop::$settings['restrict_sizes_to_these_only'] ) ) {
									// This might be restricted from the theme or the plugin custom rules.
									continue;
								}

								$filename = get_attached_file( $v['ID'] );
								if ( ! empty( $filename ) && file_exists( $filename ) ) {
									SIRSC_Image_Regenerate_Select_Crop::make_images_if_not_exists( $v['ID'], $sn );
									// $image = wp_get_attachment_metadata( $v['ID'] );
									$th = wp_get_attachment_image_src( $v['ID'], $sn );
									if ( ! empty( $th[0] ) ) {
										if ( $verbose ) {
											WP_CLI::success( $th[0] );
											do_action( 'sirsc_image_processed', $v['ID'], $sn );
										}
									} else {
										WP_CLI::line( esc_html__( 'Could not generate, the original is too small.', 'sirsc' ) );
									}
								} else {
									WP_CLI::line( esc_html__( 'Could not generate, the original file is missing', 'sirsc' ) . ' ' . $filename . ' !' );
								}
								$progress->tick();
							}
							$progress->finish();
						}
					}
				}
				WP_CLI::success( 'ALL DONE!' );
			} else {
				WP_CLI::error( 'Unexpected ERROR' );
			}
		}
		/**
		 * Arguments order and types : (int)site_id (string)post_type (string)size_name (int)parent_id.
		 *
		 * @param array $args       Command default arguments.
		 * @param array $assoc_args Command associated arguments.
		 */
		public function cleanup( $args, $assoc_args ) {
			$is_forced = ( ! empty( $assoc_args['force'] ) ) ? true : false;
			$config    = self::prepare_args( array_merge( $args, array( 'is_cleanup' => true ) ) );
			if ( ! is_array( $config ) ) {
				return;
			}

			$verbose = self::is_verbose( $assoc_args );
			extract( $config ); //phpcs:ignore
			if ( ! empty( $post_type ) && ! empty( $size_name ) && ! empty( $all_sizes ) ) {
				global $wpdb;
				$execute_sizes = array();
				if ( 'all' === $size_name ) {
					$execute_sizes = $all_sizes;
				} else {
					if ( ! empty( $all_sizes[ $size_name ] ) || $is_forced ) {
						$execute_sizes[ $size_name ] = $size_name;
					}
				}

				$rows = self::make_query( $post_type, $parent_id, 'REMOVE' );
				if ( ! empty( $rows ) && is_array( $rows ) ) {
					if ( ! empty( $execute_sizes ) ) {
						foreach ( $execute_sizes as $sn => $sv ) {
							$progress = \WP_CLI\Utils\make_progress_bar( '------- REMOVE ' . $sn, count( $rows ) );
							foreach ( $rows as $v ) {
								$image_meta = wp_get_attachment_metadata( $v['ID'] );
								if ( ! empty( $image_meta['sizes'][ $sn ] ) ) {
									$filename = realpath( get_attached_file( $v['ID'] ) );
									if ( ! empty( $filename ) ) {
										$string = ( ! empty( $image_meta['sizes'][ $sn ]['file'] ) ) ? $image_meta['sizes'][ $sn ]['file'] : '';
										$file   = str_replace( basename( $filename ), $string, $filename );
										$file   = realpath( $file );
										if ( ! empty( $file ) ) {
											if ( file_exists( $file ) && $file !== $filename ) {
												// Make sure not to delete the original file.
												if ( $verbose ) {
													WP_CLI::success( $file . ' ' . esc_html__( 'was removed', 'sirsc' ) );
													do_action( 'sirsc_image_file_deleted', $v['ID'], $file );
												}
												@unlink( $file );
											} else {
												WP_CLI::line( esc_html__( 'Could not remove', 'sirsc' ) . ' ' . $file . '. ' . esc_html__( 'The image is missing or it is the original file.', 'sirsc' ) );
											}
										}
									}
									unset( $image_meta['sizes'][ $sn ] );
									wp_update_attachment_metadata( $v['ID'], $image_meta );

									// Re-fetch the meta.
									$image_meta = wp_get_attachment_metadata( $v['ID'] );
									do_action( 'sirsc_attachment_images_ready', $image_meta, $v['ID'] );
								}
								$progress->tick();
							}
							$progress->finish();
						}
					}
				}
				WP_CLI::success( 'ALL DONE!' );
			} else {
				WP_CLI::error( 'Unexpected ERROR' );
			}
		}

		/**
		 * Arguments order and types : (int)site_id (string)post_type.
		 *
		 * @param array $args       Command default arguments.
		 * @param array $assoc_args Command associated arguments.
		 */
		public function rawcleanup( $args, $assoc_args ) {
			$is_forced = ( ! empty( $assoc_args['force'] ) ) ? true : false;
			$config = self::prepare_args(
				array_merge(
					$args,
					array(
						'is_cleanup'  => true,
						'the_command' => 'rawcleanup',
					)
				)
			);
			if ( ! is_array( $config ) ) {
				return;
			}

			$verbose = self::is_verbose( $assoc_args );
			extract( $config ); //phpcs:ignore
			if ( ! empty( $post_type ) ) {
				$rows = self::make_query( $post_type, $parent_id, 'REMOVE' );
				if ( ! empty( $rows ) && is_array( $rows ) ) {
					$progress = \WP_CLI\Utils\make_progress_bar( '------- RAW REMOVE FILES (keep only originals)', count( $rows ) );
					foreach ( $rows as $v ) {
						$meta = wp_get_attachment_metadata( $v['ID'] );
						$list = SIRSC_Image_Regenerate_Select_Crop::assess_files_for_attachment_original( $v['ID'], $meta );
						if ( ! empty( $list['paths']['generated'] ) ) {
							foreach ( $list['paths']['generated'] as $c => $removable ) {
								if ( file_exists( $removable ) ) {
									@unlink( $removable );
									// Make sure not to delete the original file.
									if ( $verbose ) {
										WP_CLI::success( $removable . ' ' . esc_html__( 'was removed', 'sirsc' ) );
										do_action( 'sirsc_image_file_deleted', $v['ID'], $removable );
									}
								} else {
									WP_CLI::line( esc_html__( 'Could not remove', 'sirsc' ) . ' ' . $removable . '. ' . esc_html__( 'The image is missing or it is the original file.', 'sirsc' ) );
								}
							}
							// Update the cleaned meta.
							$meta['sizes'] = array();
							wp_update_attachment_metadata( $v['ID'], $meta );

							// Re-fetch the meta.
							$image_meta = wp_get_attachment_metadata( $v['ID'] );
							do_action( 'sirsc_attachment_images_ready', $image_meta, $v['ID'] );
						}
						$progress->tick();
					}
					$progress->finish();
				}
				WP_CLI::success( 'ALL DONE!' );
			} else {
				WP_CLI::error( 'Unexpected ERROR' );
			}
		}

		/**
		 * Arguments order and types : (int)site_id (string)post_type.
		 *
		 * @param array $args       Command default arguments.
		 * @param array $assoc_args Command associated arguments.
		 */
		public function resetcleanup( $args, $assoc_args ) {
			$is_forced = ( ! empty( $assoc_args['force'] ) ) ? true : false;
			$config = self::prepare_args(
				array_merge(
					$args,
					array(
						'is_cleanup'  => true,
						'the_command' => 'resetcleanup',
					)
				)
			);
			if ( ! is_array( $config ) ) {
				return;
			}

			$verbose = self::is_verbose( $assoc_args );
			extract( $config ); // phpcs:ignore
			if ( ! empty( $post_type ) ) {
				global $wpdb;

				$rows = self::make_query( $post_type, $parent_id, 'REMOVE' );
				if ( ! empty( $rows ) && is_array( $rows ) ) {
					$progress = \WP_CLI\Utils\make_progress_bar( '------- RESET REMOVE FILES (keep only registered image sizes)', count( $rows ) );
					$reg  = get_intermediate_image_sizes();
					$upls = wp_upload_dir();
					$pref = trailingslashit( $upls['basedir'] );

					foreach ( $rows as $v ) {
						$compute = SIRSC_Image_Regenerate_Select_Crop::compute_image_paths( $v['ID'], '', $upls );
						$meta    = ( ! empty( $compute['metadata'] ) )
							? $compute['metadata'] : wp_get_attachment_metadata( $v['ID'] );
						$initial = $meta;
						$summary = SIRSC_Image_Regenerate_Select_Crop::general_sizes_and_files_match( $v['ID'], $meta, $compute );
						if ( ! empty( $summary ) ) {
							foreach ( $summary as $sfn => $info ) {
								if ( empty( $info['registered'] ) ) {
									if ( ! empty( $info['match'] ) ) {
										foreach ( $info['match'] as $sn ) {
											if ( isset( $meta['sizes'][ $sn ] ) ) {
												unset( $meta['sizes'][ $sn ] );
											}
										}
									}
									$removable = $pref . $sfn;
									if ( file_exists( $removable ) ) {
										@unlink( $removable );
										// Make sure not to delete the original file.
										if ( $verbose ) {
											WP_CLI::success( $removable . ' ' . esc_html__( 'was removed', 'sirsc' ) );
											do_action( 'sirsc_image_file_deleted', $v['ID'], $removable );
										}
									} else {
										if ( $verbose ) {
											WP_CLI::line( esc_html__( 'Could not remove', 'sirsc' ) . ' ' . $removable . '. ' . esc_html__( 'The image is missing or it is the original file.', 'sirsc' ) );
										}
									}
								} else {
									if ( $verbose ) {
										WP_CLI::line( esc_html__( 'No cleanup necessary for', 'sirsc' ) . ' ' . $v['ID'] . '.' );
									}
								}
							}
						} else {
							if ( $verbose ) {
								WP_CLI::line( esc_html__( 'No cleanup necessary for', 'sirsc' ) . ' ' . $v['ID'] . '.' );
							}
						}

						if ( $initial != $meta ) {
							// Update the cleaned meta.
							wp_update_attachment_metadata( $v['ID'], $meta );

							// Re-fetch the meta.
							$image_meta = wp_get_attachment_metadata( $v['ID'] );
							do_action( 'sirsc_attachment_images_ready', $image_meta, $v['ID'] );
						}
						$progress->tick();
					}
					$progress->finish();
				}
				WP_CLI::success( 'ALL DONE!' );
			} else {
				WP_CLI::error( 'Unexpected ERROR' );
			}
		}

		/**
		 * Retrun the posts that match the SIRSC criteria.
		 *
		 * @param string         $post_type Maybe a post type.
		 * @param string|integer $parent_id Attachment parents (numeric or * for all).
		 * @param string         $action    Action title, regenerate or remove.
		 */
		private function make_query( $post_type = '', $parent_id = 0, $action = 'REGENERATE' ) {
			global $wpdb;
			$args  = array();
			$query = ' SELECT p.ID FROM ' . $wpdb->posts . ' as p ';
			if ( ! empty( $post_type ) && 'all' !== $post_type ) {
				$query .= ' INNER JOIN ' . $wpdb->posts . ' as parent ON( parent.ID = p.post_parent ) ';
			}
			$query .= ' WHERE ( p.post_mime_type like %s OR p.post_mime_type like %s OR p.post_mime_type like %s ) ';
			$args[] = '%' . $wpdb->esc_like( 'image/gif' ) . '%';
			$args[] = '%' . $wpdb->esc_like( 'image/jpeg' ) . '%';
			$args[] = '%' . $wpdb->esc_like( 'image/png' ) . '%';

			if ( ! empty( $post_type ) && 'all' !== $post_type ) {
				$query .= ' AND parent.post_type = %s ';
				$args[] = $post_type;
				if ( ! empty( $parent_id ) ) {
					$query .= ' AND parent.ID = %d ';
					$args[] = $parent_id;
					WP_CLI::line( '------- EXECUTE ' . $action . ' FOR IMAGES ASSOCIATED TO ' . $post_type . ' WITH ID = ' . $parent_id . ' -------' );
				} else {
					$query .= ' AND parent.ID IS NOT NULL ';
					WP_CLI::line( '------- EXECUTE ' . $action . ' FOR ALL IMAGES ASSOCIATED TO ' . $post_type . ' -------' );
				}
			}

			if ( 'all' === $post_type ) {
				WP_CLI::line( '------- EXECUTE ' . $action . ' FOR ALL IMAGES ASSOCIATED TO ALL TYPES -------' );
			}

			$query .= ' ORDER BY p.ID ASC LIMIT 0, 50000';
			$rows   = $wpdb->get_results( $wpdb->prepare( $query, $args ), ARRAY_A ); //phpcs:ignore
			return $rows;
		}
	}

	WP_CLI::add_command( 'sirsc', 'SIRSC_Image_Regenerate_Select_Crop_CLI_Command' );
}
