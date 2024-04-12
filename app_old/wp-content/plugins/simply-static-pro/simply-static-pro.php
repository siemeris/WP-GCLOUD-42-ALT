<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name:       Simply Static Pro
 * Plugin URI:        https://patrickposner.dev
 * Description:       Enhances Simply Static with GitHub Integration, Forms, Comments and more.
 * Version:           1.4.6
 * Update URI: https://api.freemius.com
 * Author:            Patrick Posner
 * Author URI:        https://patrickposner.dev
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       simply-static-pro
 * Domain Path:       /languages
 */

define( 'SIMPLY_STATIC_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPLY_STATIC_PRO_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'SIMPLY_STATIC_PRO_VERSION', '1.4.6' );

// localize.
$textdomain_dir = plugin_basename( dirname( __FILE__ ) ) . '/languages';
load_plugin_textdomain( 'simply-static-pro', false, $textdomain_dir );

// load Freemius.
require_once( SIMPLY_STATIC_PRO_PATH . 'inc/setup.php' );

// Bootmanager for Simply Static Pro plugin.
if ( ! function_exists( 'ssp_run_plugin' ) ) {

	// autoload files.
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require __DIR__ . '/vendor/autoload.php';
	}

	add_action( 'plugins_loaded', 'ssp_run_plugin' );

	/**
	 * Run plugin
	 *
	 * @return void
	 */
	function ssp_run_plugin() {
		if ( function_exists( 'simply_static_run_plugin' ) ) {
			if ( ssp_fs()->is_plan_or_trial__premium_only( 'pro' ) ) {
				// We need the task class from Simply Static to integrate our job.
				require_once SIMPLY_STATIC_PATH . 'src/tasks/class-ss-task.php';
				require_once SIMPLY_STATIC_PATH . 'src/tasks/class-ss-fetch-urls-task.php';
				require_once SIMPLY_STATIC_PATH . 'src/class-ss-plugin.php';
				require_once SIMPLY_STATIC_PATH . 'src/class-ss-util.php';

				// Helper.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/class-ssp-helper.php';
				simply_static_pro\Helper::get_instance();

				// Filter.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/class-ssp-filter.php';
				simply_static_pro\Filter::get_instance();

				// Deployment.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/github/class-ssp-github-repository.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/github/class-ssp-github-database.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/github/class-ssp-github-commit-task.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/bunny-cdn/class-ssp-bunny-updater.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/bunny-cdn/class-ssp-bunny-deploy-task.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/tiiny-host/class-ssp-tiiny-updater.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/tiiny-host/class-ssp-tiiny-deploy-task.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/aws-s3/class-ssp-s3-client.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/aws-s3/class-ssp-aws-deploy-task.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/aws-s3/class-ssp-aws-empty-task.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/deployment/digitalocean/class-ssp-digitalocean-deploy-task.php';

				// Builds.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/build/class-ssp-build-settings.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/build/class-ssp-build-meta.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/build/class-ssp-build.php';

				simply_static_pro\Build_Settings::get_instance();
				simply_static_pro\Build_Meta::get_instance();
				simply_static_pro\Build::get_instance();

				// Single.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/single/class-ssp-single-meta.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/single/class-ssp-single.php';

				simply_static_pro\Single_Meta::get_instance();
				simply_static_pro\Single::get_instance();

				// Forms.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/form/class-ssp-form-settings.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/form/class-ssp-form-meta.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/form/class-ssp-form-webhook.php';

				simply_static_pro\Form_Settings::get_instance();
				simply_static_pro\Form_Meta::get_instance();
				simply_static_pro\Form_Webhook::get_instance();

				// Comments.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/comment/class-ssp-comment.php';
				simply_static_pro\Comment::get_instance();

				// Cors.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/cors/class-ssp-cors.php';

				simply_static_pro\CORS::get_instance();

				// Search.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/search/class-ssp-search-algolia.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/search/class-ssp-search-fuse.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/search/class-ssp-search-task.php';

				simply_static_pro\Search_Algolia::get_instance();
				simply_static_pro\Search_Fuse::get_instance();

				if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
					require_once SIMPLY_STATIC_PRO_PATH . 'src/wp-cli/class-ssp-commands.php';
					new simply_static_pro\commands\Commands();
				}

				// Minifer.
				require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minify-task.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minifer-interface.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minifer-css.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minifer-js.php';
				require_once SIMPLY_STATIC_PRO_PATH . 'src/minifier/class-ssp-minifer-html.php';

				add_action( 'admin_enqueue_scripts', 'ssp_add_admin_styles' );
				add_action( 'ss_integrations_before_load', 'ssp_include_pro_integrations' );
				add_action( 'simply_static_integrations', 'ssp_register_integrations' );
			}

			if ( defined( 'SIMPLY_STATIC_VERSION' ) && version_compare( SIMPLY_STATIC_VERSION, '3.0', '<' ) ) {
				add_action(
					'admin_notices',
					function () {
						$message = esc_html__( 'You need to update Simply Static to version 3.0 before continuing to use Simply Static Pro, as we made significant changes requiring an upgrade.', 'simply-static-pro' );
						echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
					}
				);
			}
		} else {
			add_action( 'admin_notices', 'ssp_show_requirements' );
		}
	}
}

/**
 * Show conditional message for requirements.
 *
 * @return void
 */
function ssp_show_requirements() {
	$message = sprintf( __( 'The free version of Simply Static is required to use Simply Static Pro. You can get it %s.', 'simply-static-pro' ), '<a target="_blank" href="https://wordpress.org/plugins/simply-static/">here</a>' );
	echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
}

/**
 * Enqueue admin scripts.
 */
function ssp_add_admin_styles() {
	wp_enqueue_style( 'ssp-admin', SIMPLY_STATIC_PRO_URL . '/assets/ssp-admin.css', false, SIMPLY_STATIC_PRO_VERSION );
}

/**
 * Include pro integrations.
 *
 * @return void
 */
function ssp_include_pro_integrations() {
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-multisite.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-multilingual.php';
	require_once trailingslashit( SIMPLY_STATIC_PRO_PATH ) . 'src/integrations/class-ssp-github.php';
}

/**
 * Register integrations.
 *
 * @param array $integrations List of integrations.
 *
 * @return array
 */
function ssp_register_integrations( array $integrations ): array {
	$integrations['multisite']    = simply_static_pro\SS_Multisite::class;
	$integrations['multilingual'] = simply_static_pro\SS_Multilingual::class;
	$integrations['github']       = simply_static_pro\Github::class;

	return $integrations;
}

