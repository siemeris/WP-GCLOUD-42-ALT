<?php

namespace simply_static_pro;

use Simply_Static;

/**
 * Class to handle admin for forms.
 */
class Form_Settings {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Form_Settings.
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
	 * Constructor for Form_Settings.
	 */
	public function __construct() {
		$options = get_option( 'simply-static' );

		if ( ! empty( $options['use_forms'] ) ) {
			add_action( 'init', array( $this, 'add_forms_post_type' ) );
			add_action( 'save_post_ssp-form', array( $this, 'update_config' ), 10, 3 );
			add_action( 'admin_menu', array( $this, 'add_submenu_page' ), 50 );
			add_filter( 'simply_static_class_name', array( $this, 'check_class_name' ), 30, 2 );
			add_filter( 'parent_file', array( $this, 'show_parent_menu' ) );
			add_action( 'upgrader_process_complete', array( $this, 'update_form_config' ), 10, 2 );

			// Plugin specifics.
			add_filter( 'gform_form_args', array( $this, 'disable_gravity_forms_ajax' ), 10, 1 );
			add_filter( 'wpcf7_load_js', '__return_false' );
		}
	}

	/**
	 * Update form config on plugin update.
	 *
	 * @param object $upgrader_object given upgrader object.
	 * @param array $options given options.
	 *
	 * @return void
	 */
	public function update_form_config( $upgrader_object, $options ) {
		$current_plugin_path_name = plugin_basename( __FILE__ );

		if ( $options['action'] == 'update' && $options['type'] == 'plugin' ) {
			foreach ( $options['plugins'] as $each_plugin ) {
				if ( $each_plugin == $current_plugin_path_name ) {
					$this->create_config_file();
				}
			}
		}
	}

	/**
	 * Highlight parent menu when editing ssp form post.
	 *
	 * @param string $parent given parent.
	 *
	 * @return string
	 */
	public function show_parent_menu( $parent = '' ) {
		global $pagenow, $typenow, $taxnow;

		// If we're editing the form settings, we must be within the SS menu, so highlight that.
		if ( ( $pagenow === 'post.php' ) && ( $typenow === 'ssp-form' ) ) {
			$parent = 'simply-static-generate';
		}

		return $parent;
	}

	/**
	 * Disable ajax in Gravity Forms form.
	 *
	 * @param array $args array of arguments.
	 *
	 * @return array
	 */
	public function disable_gravity_forms_ajax( $args ) {
		$args['ajax'] = false;

		return $args;
	}

	/**
	 * Add submenu page for builds taxonomy.
	 *
	 * @return void
	 */
	public function add_submenu_page() {
		add_submenu_page(
			'simply-static-generate',
			__( 'Forms', 'simply-static-pro' ),
			__( 'Forms', 'simply-static-pro' ),
			apply_filters( 'ss_user_capability', 'publish_pages', 'forms' ),
			'edit.php?post_type=ssp-form',
			false
		);
	}

	/**
	 * Create forms custom post type.
	 *
	 * @see register_post_type() for registering custom post types.
	 */
	public function add_forms_post_type() {
		$labels = array(
			'name'                  => _x( 'Forms', 'Post type general name', 'simply-static-pro' ),
			'singular_name'         => _x( 'Form', 'Post type singular name', 'simply-static-pro' ),
			'menu_name'             => _x( 'Forms', 'Admin Menu text', 'simply-static-pro' ),
			'name_admin_bar'        => _x( 'Form', 'Add New on Toolbar', 'simply-static-pro' ),
			'add_new'               => __( 'Add New', 'simply-static-pro' ),
			'add_new_item'          => __( 'Add New Form', 'simply-static-pro' ),
			'new_item'              => __( 'New Form', 'simply-static-pro' ),
			'edit_item'             => __( 'Edit Form', 'simply-static-pro' ),
			'view_item'             => __( 'View Form', 'simply-static-pro' ),
			'all_items'             => __( 'All Forms', 'simply-static-pro' ),
			'search_items'          => __( 'Search Forms', 'simply-static-pro' ),
			'parent_item_colon'     => __( 'Parent Forms:', 'simply-static-pro' ),
			'not_found'             => __( 'No forms found.', 'simply-static-pro' ),
			'not_found_in_trash'    => __( 'No forms found in Trash.', 'simply-static-pro' ),
			'featured_image'        => _x( 'Form Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'simply-static-pro' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'simply-static-pro' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'simply-static-pro' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'simply-static-pro' ),
			'archives'              => _x( 'Form archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'simply-static-pro' ),
			'insert_into_item'      => _x( 'Insert into form', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'simply-static-pro' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this form', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'simply-static-pro' ),
			'filter_items_list'     => _x( 'Filter forms list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'simply-static-pro' ),
			'items_list_navigation' => _x( 'Forms list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'simply-static-pro' ),
			'items_list'            => _x( 'Forms list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'simply-static-pro' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title' ),
		);

		register_post_type( 'ssp-form', $args );
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
		if ( 'form_config' === $task_name ) {
			return 'simply_static_pro\\' . ucwords( $task_name ) . '_Task';
		}

		return $class_name;
	}

	/**
	 * Update form config if ssp-form post is saved.
	 *
	 * @param int $post_id given post id.
	 * @param object $post given post object.
	 * @param bool $update updated or not.
	 *
	 * @return void
	 */
	public function update_config( $post_id, $post, $update ) {
		$this->create_config_file();
	}

	/**
	 * Create JSON file for forms config.
	 *
	 * @return string;
	 */
	public function create_config_file() {
		global $wp_filesystem;

		// Initialize the WP filesystem.
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();
		}

		// Check file writing method.
		if ( get_filesystem_method() !== 'direct' ) {
			Simply_Static\Util::debug_log( __( 'The config file could not be created. Please make sure that the config directory is writeable.', 'simply-static-pro' ) );

			return false;
		}

		// Get config file path.
		$upload_dir  = wp_upload_dir();
		$config_dir  = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR;
		$config_file = $config_dir . 'forms.json';

		// Delete old index.
		if ( file_exists( $config_file ) ) {
			wp_delete_file( $config_file );
		}

		// Get static form configurations.
		$args      = array( 'numberposts' => - 1, 'post_type' => 'ssp-form', 'fields' => 'ids' );
		$ssp_forms = get_posts( $args );
		$forms     = array();

		if ( ! empty( $ssp_forms ) ) {
			foreach ( $ssp_forms as $form_id ) {
				$form               = new \stdClass();
				$form->id           = get_post_meta( $form_id, 'form_id', true );
				$form->tool         = get_post_meta( $form_id, 'tool', true );
				$form->endpoint     = get_post_meta( $form_id, 'endpoint', true );
				$form->redirect_url = get_post_meta( $form_id, 'redirect_url', true );
				$forms[]            = $form;
			}
		}

		// Now create the json file.
		$json = wp_json_encode( $forms );

		// Check if directory exists.
		if ( ! is_dir( $config_dir ) ) {
			wp_mkdir_p( $config_dir );
		}

		$wp_filesystem->put_contents( $config_file, $json );

		return $config_file;
	}
}
