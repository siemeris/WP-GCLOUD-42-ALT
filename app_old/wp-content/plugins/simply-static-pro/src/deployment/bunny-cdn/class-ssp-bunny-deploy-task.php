<?php

namespace simply_static_pro;

use Exception;
use Simply_Static;
use Simply_Static\Options;
use Simply_Static\Page;
use Simply_Static\Util;

/**
 * Class which handles BunnyCDN deployments.
 */
class Bunny_Deploy_Task extends Simply_Static\Task {
	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'bunny_deploy';

	/**
	 * Given start time for the export.
	 *
	 * @var string
	 */
	private $start_time;

	/**
	 * Temp directory.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$options = Options::instance();

		$this->options    = $options;
		$this->temp_dir   = $options->get_archive_dir();
		$this->start_time = $options->get( 'archive_start_time' );
	}


	/**
	 * Copy a batch of files from the temp dir to the destination dir
	 *
	 * @return boolean true if done, false if not done.
	 */
	public function perform() {
		list( $pages_processed, $total_pages ) = $this->upload_static_files( $this->temp_dir );

		if ( $pages_processed !== 0 ) {
			$message = sprintf( __( "Uploading %d of %d pages/files", 'simply-static' ), $pages_processed, $total_pages );
			$this->save_status_message( $message );
		}

		if ( $pages_processed >= $total_pages ) {
			if ( $this->options->get( 'destination_url_type' ) == 'absolute' ) {
				$destination_url = trailingslashit( $this->options->get_destination_url() );
				$message         = __( 'Destination URL:', 'simply-static' ) . ' <a href="' . $destination_url . '" target="_blank">' . $destination_url . '</a>';
				$this->save_status_message( $message, 'destination_url' );
			} else {
				$message = sprintf( __( "Uploaded %d of %d pages/files", 'simply-static' ), $pages_processed, $total_pages );
				$this->save_status_message( $message );
			}
		}

		// return true when done (no more pages).
		if ( $pages_processed >= $total_pages ) {
			do_action( 'ssp_finished_bunnycdn_transfer', $this->temp_dir );

			// Maybe add 404.
			$this->add_404();

			// Clear cache.
			Bunny_Updater::purge_cache();
		}

		return $pages_processed >= $total_pages;
	}

	/**
	 * Upload files to BunnyCDN.
	 *
	 * @param string $destination_dir The directory to put the files.
	 *
	 * @return array
	 * @throws Exception When the upload fails.
	 */
	public function upload_static_files( string $destination_dir ): array {
		$batch_size = apply_filters( 'ssp_upload_files_bunnycdn_batch_size', 25 );
		$options    = get_option( 'simply-static' );

		// Subdirectory?
		$cdn_path = '';

		if ( ! empty( $options['cdn_directory'] ) ) {
			$cdn_path = $options['cdn_directory'] . '/';
		}

		// last_modified_at > ? AND.
		$static_pages    = Page::query()
		                       ->where( "file_path IS NOT NULL" )
		                       ->where( "file_path != ''" )
		                       ->where( "( last_transferred_at < ? OR last_transferred_at IS NULL )", $this->start_time )
		                       ->limit( $batch_size )
		                       ->find();
		$pages_remaining = count( $static_pages );
		$total_pages     = Page::query()
		                       ->where( "file_path IS NOT NULL" )
		                       ->where( "file_path != ''" )
		                       ->count();

		$pages_processed = $total_pages - $pages_remaining;
		Util::debug_log( 'Total pages: ' . $total_pages . '; Pages remaining: ' . $pages_remaining );

		while ( $static_page = array_shift( $static_pages ) ) {
			$file_path = $this->temp_dir . $static_page->file_path;

			if ( ! is_dir( $file_path ) && file_exists( $file_path ) ) {
				Bunny_Updater::upload_file( $file_path, $cdn_path . $static_page->file_path );
			}

			do_action( 'ssp_file_transfered_to_bunnycdn', $static_page, $destination_dir );

			$static_page->last_transferred_at = Util::formatted_datetime();
			$static_page->save();
		}

		return array( $pages_processed, $total_pages );
	}

	/**
	 * Maybe add a custom 404 page.
	 *
	 * @return void
	 */
	public function add_404() {
		global $wp_filesystem;

		// Initialize the WP filesystem.
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();
		}

		$options = get_option( 'simply-static' );

		// Maybe use constant instead of options.
		if ( defined( 'SSP_BUNNYCDN' ) ) {
			$options = SSP_BUNNYCDN;
		}

		if ( isset( $options['cdn_404'] ) && realpath( $this->temp_dir . untrailingslashit( $options['cdn_404'] ) . '/index.html' ) ) {
			// Rename and copy file.
			$src_error_file  = $this->temp_dir . untrailingslashit( $options['cdn_404'] ) . '/index.html';
			$dst_error_file  = $this->temp_dir . 'bunnycdn_errors/404.html';
			$error_directory = dirname( $dst_error_file );

			if ( ! is_dir( $error_directory ) ) {
				wp_mkdir_p( $error_directory );
				chmod( $error_directory, 0777 );
			}

			$wp_filesystem->copy( $src_error_file, $dst_error_file, true );

			// Upload 404 template file.
			$error_file_path     = realpath( $this->temp_dir . 'bunnycdn_errors/404.html' );
			$error_relative_path = str_replace( $this->temp_dir, '', $error_file_path );

			if ( $error_file_path ) {
				Bunny_Updater::upload_file( $error_file_path, $error_relative_path );
			}
		}
	}
}
