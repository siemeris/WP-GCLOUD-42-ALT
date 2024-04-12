<?php

namespace simply_static_pro;

use Exception;
use Simply_Static;
use Simply_Static\Options;
use Simply_Static\Page;
use Simply_Static\Util;

/**
 * Class which handles AWS Deployment.
 */
class AWS_Deploy_Task extends Simply_Static\Task {
	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'aws_deploy';

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

		$options          = Options::instance();
		$this->options    = $options;
		$this->temp_dir   = $options->get_archive_dir();
		$this->start_time = $options->get( 'archive_start_time' );
	}

	/**
	 * Transfer directory to S3 bucket.
	 *
	 * @return boolean true if done, false if not done.
	 * @throws Exception When the GitHub API returns an error.
	 */
	public function perform(): bool {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		list( $pages_processed, $total_pages ) = $this->transfer_directory( $this->temp_dir );

		if ( $pages_processed !== 0 ) {
			$message = sprintf( __( 'Uploading %d of %d pages/files', 'simply-static' ), $pages_processed, $total_pages );
			$this->save_status_message( $message );
		}

		// return true when done (no more pages).
		if ( $pages_processed >= $total_pages ) {
			$message = sprintf( __( 'Uploaded %d of %d pages/files', 'simply-static' ), $pages_processed, $total_pages );
			$this->save_status_message( $message );

			do_action( 'ssp_finished_aws_transfer', $this->temp_dir );
		}

		return $pages_processed >= $total_pages;
	}

	/**
	 * Transfer directory to S3.
	 *
	 * @param string $directory The directory with the files to transfer.
	 *
	 * @return array
	 * @throws Exception When the transfer fails.
	 */
	protected function transfer_directory( string $directory ) {
		$static_pages = Page::query()
		                    ->where( "file_path IS NOT NULL" )
		                    ->where( "file_path != ''" )
		                    ->where( "( last_transferred_at < ? OR last_transferred_at IS NULL )", $this->start_time )
		                    ->find();

		$pages_remaining = count( $static_pages );
		$total_pages     = Page::query()
		                       ->where( "file_path IS NOT NULL" )
		                       ->where( "file_path != ''" )
		                       ->count();

		$pages_processed = $total_pages - $pages_remaining;
		Util::debug_log( 'Total pages: ' . $total_pages . '; Pages remaining: ' . $pages_remaining );

		$bucket       = $this->options->get( 'aws_bucket' );
		$api_secret   = $this->options->get( 'aws_access_secret' );
		$api_key      = $this->options->get( 'aws_access_key' );
		$region       = $this->options->get( 'aws_region' );
		$subdirectory = $this->options->get( 'aws_subdirectory' );

		$client = new S3_Client();
		$client->set_bucket( $bucket )
		       ->set_api_secret( $api_secret )
		       ->set_api_key( $api_key )
		       ->set_region( $region );

		// Subdirectory?
		if ( $subdirectory ) {
			$client->transfer_directory( $this->temp_dir, $subdirectory );
		} else {
			$client->transfer_directory( $this->temp_dir );
		}

		while ( $static_page = array_shift( $static_pages ) ) {
			$file_path = $this->temp_dir . $static_page->file_path;

			if ( ! is_dir( $file_path ) && file_exists( $file_path ) ) {
				do_action( 'ssp_file_transferred_to_aws', $static_page, $directory );
				$static_page->last_transferred_at = Util::formatted_datetime();
				$static_page->save();
			}
		}

		return array( $pages_processed, $total_pages );
	}

}
