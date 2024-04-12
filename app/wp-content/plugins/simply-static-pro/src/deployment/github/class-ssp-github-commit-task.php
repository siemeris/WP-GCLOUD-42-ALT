<?php

namespace simply_static_pro;

use Exception;
use Simply_Static;
use Simply_Static\Options;
use Simply_Static\Page;
use Simply_Static\Util;

/**
 * Class which handles GitHub commits.
 */
class Github_Commit_Task extends Simply_Static\Task {
	/**
	 * The task name.
	 *
	 * @var string
	 */
	protected static $task_name = 'github_commit';

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
	 * Push a batch of files from the temp dir to GitHub.
	 *
	 * @return boolean true if done, false if not done.
	 * @throws Exception When the GitHub API returns an error.
	 */
	public function perform(): bool {
		// Prepare default option state.
		$options = get_option( 'simply-static' );

		// Prepare WP Filesystem.
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		if ( is_null( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		// Handle repository.
		$repository = Github_Repository::get_instance();

		// Clear repository?
		$clear_repository = apply_filters( 'ssp_clear_repository', false );

		if ( $clear_repository ) {
			// Check if it's a full static export.
			$use_single = get_option( 'simply-static-use-single' );
			$use_build  = get_option( 'simply-static-use-build' );

			if ( ! isset( $use_build ) && ! isset( $use_single ) ) {
				$repository->delete_repository();
				$repository->add_repository();
				$repository->add_file( 'simply-static.txt', 'This file was created by Simply Static Pro.', __( 'Added the sample file.', 'simply-static-pro' ) );
			}
		}

		// Add or update repository.
		if ( isset( $options['github_account_type'] ) ) {
			if ( 'organization' === $options['github_account_type'] ) {
				$repository->add_file( 'simply-static.txt', 'This file was created by Simply Static Pro.', __( 'Added the sample file.', 'simply-static-pro' ) );
			} else {
				$repository->add_repository();
				$repository->add_file( 'simply-static.txt', 'This file was created by Simply Static Pro.', __( 'Added the sample file.', 'simply-static-pro' ) );
			}
		}

		// Prepare GitHub Database API.
		$database = Github_Database::get_instance();

		// Handle rate limits.
		$rate_limit   = $database->get_rate_limits();
		$should_sleep = get_transient( 'ssp_github_should_sleep' );

		list( $pages_processed, $total_pages ) = $this->upload_static_files( $this->temp_dir, $database, $wp_filesystem );

		$blobs = get_transient( 'ssp_github_blobs' );

		if ( $rate_limit['remaining'] < $total_pages && false === $should_sleep ) {
			set_transient( 'ssp_github_should_sleep', true, 0 );

			// Calcute time to wait.
			$now     = time();
			$seconds = ( $rate_limit['reset'] - $now );

			Util::debug_log( 'You exceeded the GitHub API rate limit. We need to wait for ' . $seconds . ' seconds.' );
			sleep( $seconds );
		}

		if ( $pages_processed !== 0 ) {
			$message = sprintf( __( 'Comitting %d of %d pages/files (Your hourly GitHub API rate limit: %d/%d requests left)', 'simply-static-pro' ), $pages_processed, $total_pages, $rate_limit['remaining'], $rate_limit['core'] );
			$this->save_status_message( $message );
		}

		// return true when done (no more pages).
		if ( $pages_processed >= $total_pages && ! empty( $blobs ) ) {
			// Create new tree with blobs.
			$tree_data = $database->create_tree( $blobs );

 			if ( is_array( $tree_data ) ) {
				// Now create a new commit with the tree.
				$commit_message = apply_filters( 'ssp_github_commit_message', 'Updated/Added ' . $this->options->get( 'archive_name' ) );
				$database->commit( $commit_message, $tree_data );
			}

			$message = sprintf( __( 'Committed %d of %d pages/files', 'simply-static-pro' ), $pages_processed, $total_pages );
			$this->save_status_message( $message );

			$this->notify_github();
			$this->notify_external_webhook();

			// Handle cleanup.
			delete_transient( 'ssp_github_blobs' );
			delete_transient( 'ssp_github_should_sleep' );

			do_action( 'ssp_finished_github_transfer', $this->temp_dir );
		}

		return $pages_processed >= $total_pages;
	}

	/**
	 * Get the folder path.
	 *
	 * @return string
	 */
	protected function get_folder_path() {
		$folder_path = $this->options->get('github_folder_path');

		if ( ! $folder_path ) {
			return '';
		}

		return trailingslashit( $folder_path );
	}

	/**
	 * Upload files to GitHub.
	 *
	 * @param string $destination_dir The directory to put the files.
	 * @param object $database GitHub Database API class.
	 * @param object $wp_filesystem WordPress file system class.
	 *
	 * @return array
	 * @throws Exception When the upload fails.
	 */
	public function upload_static_files( string $destination_dir, object $database, object $wp_filesystem ): array {
		$batch_size       = apply_filters( 'ssp_commit_github_batch_size', 50 );
		$throttle_request = apply_filters( 'ssp_throttle_github_request', false );
		$skip_blob_create = apply_filters( 'ssp_skip_blob_create', false );
		$blobs            = get_transient( 'ssp_github_blobs' );

		if ( ! is_array( $blobs ) ) {
			$blobs = [];
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
		$github_folder_path = $this->get_folder_path();

		while ( $static_page = array_shift( $static_pages ) ) {
			$file_path = Util::combine_path( $this->temp_dir, $static_page->file_path );

			if ( ! is_dir( $file_path ) && file_exists( $file_path ) ) {
				$content = $wp_filesystem->get_contents( $file_path );

				// Prepare file for commit.
				$relative_path = str_replace( Util::normalize_slashes( $this->temp_dir ), $github_folder_path, $file_path );

				// Fixing possible empty spaces.
				$relative_path = str_replace( '//', '/', $relative_path );

				Util::debug_log( 'Creating Blob for file: ' . $file_path );
				if ( $skip_blob_create ) {
					// Add main index.html.
					$blobs[] = [
						'path'    => str_replace( Util::normalize_slashes( $this->temp_dir ), $github_folder_path, 'index.html' ),
						'mode'    => '100644',
						'type'    => 'blob',
						'content' => $wp_filesystem->get_contents( Util::combine_path( $this->temp_dir, 'index.html' ) )
					];

					if ( ! empty( $content ) ) {
						$blobs[] = [
							'path'      => $relative_path,
							'mode'      => '100644',
							'type'      => 'blob',
							'file_path' => $file_path
							//'content' => $content
						];
					} else {
						Util::debug_log( 'Empty Content for: ' . $file_path );
						continue;
					}
				} else {
					$blob = $database->create_blob( $file_path, $relative_path, $content );

					// Maybe throttle request.
					if ( $throttle_request ) {
						sleep( 1 );
					}

					if ( false !== $blob ) {
						$blobs[] = $blob;
					} else {
						continue;
					}
				}
			}
			Util::debug_log( 'Blob created for: ' . $file_path );
			do_action( 'ssp_file_transfered_to_github', $static_page, $destination_dir );

			$static_page->last_transferred_at = Util::formatted_datetime();
			$static_page->save();
		}

		$blobs = set_transient( 'ssp_github_blobs', $blobs, 0 );

		return array( $pages_processed, $total_pages, $blobs );
	}

	/**
	 * Notify external Webhook after Simply Static finished static export.
	 *
	 * @return void
	 */
	public function notify_external_webhook() {
		$options = get_option( 'simply-static' );

		// Maybe use constant instead of options.
		if ( defined( 'SSP_GITHUB' ) ) {
			$options = SSP_GITHUB;
		}

		if ( empty( $options['github_webhook_url'] ) ) {
			return;
		}

		$webhook_args = apply_filters( 'ssp_webhook_args', array() );

		wp_remote_post( esc_url( $options['github_webhook_url'] ), $webhook_args );
	}

	/**
	 * Notify GitHub after Simply Static finished static export.
	 *
	 * @return void
	 */
	public function notify_github() {
		$options = get_option( 'simply-static' );

		// Maybe use constant instead of options.
		if ( defined( 'SSP_GITHUB' ) ) {
			$options = SSP_GITHUB;
		}

		if ( 'github' !== $options['delivery_method'] ) {
			return;
		}

		$user = $options['github_user'];
		if ( empty( $user ) ) {
			return;
		}

		$access_token = $options['github_personal_access_token'];
		if ( empty( $access_token ) ) {
			return;
		}

		$repository = $options['github_repository'];
		if ( empty( $repository ) ) {
			return;
		}

		$webhook = 'https://api.github.com/repos/' . $user . '/' . $repository . '/dispatches';

		$webhook_args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/vnd.github+json',
			),
			'body'    => wp_json_encode( array( 'event_type' => 'repository dispatch' )
			)
		);

		wp_remote_post( esc_url( $webhook ), $webhook_args );
	}
}
