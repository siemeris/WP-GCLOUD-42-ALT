<?php

namespace simply_static_pro;

use Simply_Static;

/**
 * Class to handle comments.
 */
class Comment {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Comment.
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
	 * Constructor for Comment.
	 */
	public function __construct() {
		add_filter( 'comment_form_default_fields', array( $this, 'filter_comment_default_fields' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_comment_scripts' ) );
	}

	/**
	 * Enqueue scripts for webhooks.
	 *
	 * @return void
	 */
	public function add_comment_scripts() {
		$options      = get_option( 'simply-static' );
		$use_comments = $options['use_comments'] ?? false;

		if ( $use_comments ) {
			wp_enqueue_script( 'ssp-comment-webhook', SIMPLY_STATIC_PRO_URL . '/assets/ssp-comment-webhook.js', array(), SIMPLY_STATIC_PRO_VERSION, true );
		}
	}

	/**
	 * Filter comment form action.
	 *
	 * @param array $fields list of fields for the comment form.
	 *
	 * @return array
	 */
	public function filter_comment_default_fields( $fields ) {
		$options      = get_option( 'simply-static' );
		$use_comments = $options['use_comments'] ?? false;

		if ( $use_comments ) {
			unset( $fields['url'] );
			unset( $fields['cookies'] );
		}

		return $fields;
	}
}
