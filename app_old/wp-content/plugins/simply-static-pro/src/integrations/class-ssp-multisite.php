<?php

namespace simply_static_pro;

use Simply_Static\Archive_Creation_Job;
use Simply_Static\Admin_Settings;
use Simply_Static\Integration;
use Simply_Static\Plugin;
use Simply_Static\Util;

class SS_Multisite extends Integration {

	/**
	 * A string ID of integration.
	 *
	 * @var string
	 */
	protected $id = 'multisite';

	protected $switched = 0;

	public function can_run() {
		return is_multisite();
	}

	/**
	 * Run the integration.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'ss_archive_creation_job_before_start', [ $this, 'switch_to_blog' ] );
		add_action( 'ss_before_perform_archive_action', [ $this, 'before_perform_action' ], 20, 3 );
		add_action( 'ss_after_perform_archive_action', [ $this, 'after_perform_action' ], 20, 2 );
		add_action( 'ss_before_render_activity_log', [ $this, 'switch_to_blog' ] );
		add_action( 'ss_before_render_export_log', [ $this, 'switch_to_blog' ] );
		add_action( 'ss_after_render_export_log', [ $this, 'restore_blog' ] );
		add_action( 'ss_before_sending_response_for_static_archive', [ $this, 'restore_blog' ] );
		add_action( 'ss_after_render_activity_log', [ $this, 'restore_blog' ] );
		add_action( 'ss_archive_creation_job_after_start_queue', [ $this, 'restore_blog' ] );
		add_action( 'ss_archive_creation_job_already_running', [ $this, 'restore_blog' ] );
		add_action( 'ss_completed', [ $this, 'restore_origin_options' ] );
		add_action( 'ss_completed', [ $this, 'reset_network_options' ] );
		add_filter( 'ss_get_options', [ $this, 'maybe_use_network_options' ] );
		add_filter( 'ss_can_delete_file', [ $this, 'can_delete_file' ], 20, 3 );
		add_filter( 'ss_hide_admin_menu', [ $this, 'maybe_hide_admin_menu' ] );
		add_action( 'admin_footer', [ $this, 'hide_top_menu' ] );
		add_action( 'network_admin_menu', array( Admin_Settings::get_instance(), 'add_menu' ), 2 );
	}

	public function after_perform_action( $blog_id, $action ) {
		if ( 'start' !== $action ) {
			return;
		}

		if ( ! isset( $_REQUEST['blog_id'] ) || ! isset( $_REQUEST['is_network_admin'] ) ) {
			return;
		}

		$this->restore_blog();
	}

	/**
	 * @param integer $blog_id
	 * @param string $action
	 * @param Archive_Creation_Job $archive_creation_job
	 *
	 * @return void
	 */
	public function before_perform_action( $blog_id, $action, $archive_creation_job ) {
		if ( 'start' !== $action ) {
			return;
		}

		if ( ! isset( $_REQUEST['blog_id'] ) || ! isset( $_REQUEST['is_network_admin'] ) ) {
			return;
		}

		update_site_option( Plugin::SLUG . '_blog_exported', absint( $_REQUEST['blog_id'] ) );
		Util::debug_log( 'Last export: ' . absint( $_REQUEST['blog_id'] ) );

		$this->switch_to_blog( absint( $_REQUEST['blog_id'] ) );
		$db_options = get_option( Plugin::SLUG );

		if ( $this->should_set_network_options_instead_of_subsite( $db_options ) ) {
			$this->set_network_options_instead_of_subsite( $db_options, $archive_creation_job );
		}
	}

	/**
	 * Hide the "Simply Static Pro" top level menu on Multisite through CSS.
	 * For some reason, it's still showing even though it outputs nothing.
	 *
	 * @return void
	 */
	public function hide_top_menu() {
		if ( ! is_network_admin() ) {
			return;
		}
		?>
        <style>
            .toplevel_page_simply-static-pro {
                display: none;
            }
        </style>
		<?php
	}

	/**
	 * Hiding admin menu on subsites if network admin doesn't allow it.
	 *
	 * @param boolean $bool
	 *
	 * @return true
	 */
	public function maybe_hide_admin_menu( $bool ) {

		if ( is_network_admin() ) {
			return $bool;
		}

		$options = get_site_option( Plugin::SLUG );

		if ( isset( $options['allow_subsites'] ) ) {
			return $options['allow_subsites'];
		}

		return $bool;
	}

	/**
	 * Can delete a file?
	 *
	 * @param boolean $bool False by default.
	 * @param \SplFileInfo $file File object.
	 * @param string $temp_dir Temporary directory.
	 *
	 * @return bool
	 */
	public function can_delete_file( $bool, $file, $temp_dir ) {
		$lookup = untrailingslashit( $temp_dir ) . DIRECTORY_SEPARATOR . Plugin::SLUG . '-' . get_current_blog_id() . '-';

		if ( 0 === strpos( $file->getRealPath(), $lookup ) ) {
			return true;
		}

		// Can delete only current blog ID files.
		return 0 === strpos( $file->getFilename(), Plugin::SLUG . '-' . get_current_blog_id() . '-' );
	}

	/**
	 * Use Network options if it's on network admin,
	 * unless it's through AJAX when exporting static sites.
	 *
	 * @param array $options Options array.
	 *
	 * @return false|mixed|null
	 */
	public function maybe_use_network_options( $options ) {
		$options = is_network_admin() ? get_site_option( Plugin::SLUG ) : $options;

		// AJAX when starting a job through network admin area.
		if ( isset( $_REQUEST['blog_id'] ) && isset( $_REQUEST['is_network_admin'] ) ) {
			$this->switch_to_blog( $_REQUEST['blog_id'] );
			$options = get_option( Plugin::SLUG );
			$this->restore_blog();
		}

		return $options;
	}

	/**
	 * Some network options should always be cleared after a job is done.
	 *
	 * @return void
	 */
	public function reset_network_options() {
		$site_options                            = get_site_option( Plugin::SLUG );
		$site_options['archive_start_time']      = null;
		$site_options['archive_end_time']        = null;
		$site_options['archive_name']            = null;
		$site_options['archive_status_messages'] = [];
		update_site_option( Plugin::SLUG, $site_options );
	}

	/**
	 * Restore Origin Options
	 *
	 * @return void
	 */
	public function restore_origin_options() {
		$origin_options = get_option( Plugin::SLUG . '_origin' );

		if ( ! $origin_options ) {
			return;
		}

		$options = get_option( Plugin::SLUG );

		// Making sure we retain such info here for logs.
		$origin_options['archive_status_messages'] = $options['archive_status_messages'];
		$origin_options['archive_name']            = $options['archive_name'];
		$origin_options['archive_start_time']      = $options['archive_start_time'];
		$origin_options['archive_end_time']        = $options['archive_end_time'];

		update_option( Plugin::SLUG, $origin_options );
		delete_option( Plugin::SLUG . '_origin' );
	}

	/**
	 * Return if we should use Network options or not.
	 *
	 * If there are no settings in the subsite, we'll use network options instead, even if "Site settings" selected.
	 *
	 * @param $subsite_options
	 *
	 * @return bool
	 */
	protected function should_set_network_options_instead_of_subsite( $subsite_options ) {
		if ( isset( $_REQUEST['settings_type'] ) && 'network' === $_REQUEST['settings_type'] ) {
			return true;
		}

		// Options not set at all? Yes, use network settings.
		if ( ! $subsite_options ) {
			return true;
		}

		if ( ! isset( $subsite_options['destination_url_type'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Set Network Options instead of the Subsite.
	 *
	 * @param $origin_options
	 * @param $archive_job
	 *
	 * @return void
	 */
	protected function set_network_options_instead_of_subsite( $origin_options, $archive_job ) {
		$backup_exists = get_option( Plugin::SLUG . '_origin' );
		// Just in case as we don't want to do it twice for one export.
		if ( ! $backup_exists ) {

			$network_options = get_site_option( Plugin::SLUG );

			update_option( Plugin::SLUG . '_origin', $origin_options );
			update_option( Plugin::SLUG, $network_options );

			$archive_job->get_options()->set_options( $network_options );
		}
	}

	/**
	 * Switch to blog.
	 *
	 * @param integer $blog_id Blog ID.
	 *
	 * @return void
	 */
	public function switch_to_blog( $blog_id ) {
		if ( $blog_id === $this->switched ) {
			return;
		}

		switch_to_blog( $blog_id );
		Util::debug_log( "Switched to blog: " . get_current_blog_id() );
	}

	/**
	 * Restore the blog.
	 *
	 * @return void
	 */
	public function restore_blog() {
		if ( get_current_blog_id() !== $this->switched ) {
			return;
		}

		restore_current_blog();
		Util::debug_log( "Restored to blog: " . get_current_blog_id() );
		$this->switched = get_current_blog_id();
	}
}