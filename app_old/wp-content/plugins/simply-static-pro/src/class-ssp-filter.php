<?php

namespace simply_static_pro;

use Simply_Static;

/**
 * Class to handle settings for fuse.
 */
class Filter {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Search_Settings.
	 *
	 * @return object
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor for Search_Settings.
	 */
	public function __construct() {
		add_filter( 'ss_settings_args', array( $this, 'modify_settings_args' ) );
		add_filter( 'simplystatic.archive_creation_job.task_list', array( $this, 'modify_task_list' ), 20, 2 );
		add_filter( 'simply_static_class_name', array( $this, 'check_class_name' ), 10, 2 );
	}

	/**
	 * Modify settings args for pro.
	 *
	 * @param array $args given list of arguments.
	 *
	 * @return array
	 */
	public function modify_settings_args( array $args ): array {
		$args['plan'] = 'pro';

		return $args;
	}

	/**
	 * Add tasks to Simply Static task list.
	 *
	 * @param array $task_list current task list.
	 * @param string $delivery_method current delivery method.
	 *
	 * @return array
	 */
	public function modify_task_list( $task_list, $delivery_method ) {
		$options = get_option( 'simply-static' );
        $use_search = $options['use_search'] ?? false;
        $use_minify = $options['use_minify'] ?? false;
        $aws_empty = $options['aws_empty'] ?? false;
		$generate_404 = $options['generate_404'] ?? false;

		// Reset original task list.
		$task_list = array( 'setup', 'fetch_urls' );

		// Add 404 task
		if ( $generate_404 ) {
			$task_list[] = 'generate_404';
		}

		// Add search task.
		if ( $use_search ) {
			$task_list[] = 'search';
		}

		// Add minify task.
		if ( $use_minify ) {
			$task_list[] = 'minify';
		}

        // Add AWS S3 empty task.
		if ( $aws_empty && $delivery_method === 'aws_s3' ) {
			$task_list[] = 'aws_empty';
		}

		// Add deployment tasks.
		switch ( $delivery_method ) {
			case 'zip':
				$task_list[] = 'create_zip_archive';
				break;
			case 'local':
				$task_list[] = 'transfer_files_locally';
				break;
			case 'simply-cdn':
				$task_list[] = 'simply_cdn';
				break;
			case 'github':
				$task_list[] = 'github_commit';
				break;
			case 'cdn':
				$task_list[] = 'bunny_deploy';
				break;
			case 'tiiny':
				$task_list[] = 'tiiny_deploy';
				break;
			case 'aws-s3':
				$task_list[] = 'aws_deploy';
				break;
			case 'digitalocean':
				$task_list[] = 'digitalocean_deploy';
				break;
		}

		// Add wrapup task.
		$task_list[] = 'wrapup';

		return $task_list;
	}

	/**
	 * Modify task class name in Simply Static.
	 *
	 * @param string $class_name current class name.
	 * @param string $task_name current task name.
	 *
	 * @return string
	 */
	public function check_class_name( $class_name, $task_name ) {
		if ( 'github_commit' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'bunny_deploy' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'tiiny_deploy' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'search' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'minify' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'aws_deploy' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'aws_empty' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		if ( 'digitalocean_deploy' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		return $class_name;
	}

}
