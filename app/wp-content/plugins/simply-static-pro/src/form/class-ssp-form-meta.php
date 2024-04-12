<?php

namespace simply_static_pro;

use Simply_Static;
use Simply_Static\Util;

/**
 * Class to handle meta for forms.
 */
class Form_Meta {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Form_Meta.
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
	 * Constructor for Form_Meta.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post', array( $this, 'save_metaboxes' ) );
	}

	/**
	 * Adds the meta box container.
	 *
	 * @param array $post_type array of post types.
	 *
	 * @return void
	 */
	public function add_metaboxes( $post_type ) {
		add_meta_box( 'form-configuration', __( 'Form Configuration', 'simply-static-pro' ), array(
			$this,
			'render_form_configuration'
		), 'ssp-form', 'normal', 'high' );
	}

	/**
	 * Render form configuration metabox.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_form_configuration( $post ) {
		wp_nonce_field( 'simply_static_form_nonce_check', 'simply_static_form_nonce_check_value' );

		$meta_fields = array(
			'tool'               => get_post_meta( $post->ID, 'tool', true ),
			'email'              => get_post_meta( $post->ID, 'email', true ),
			'form-id'            => get_post_meta( $post->ID, 'form_id', true ),
			'subject'            => get_post_meta( $post->ID, 'subject', true ),
			'endpoint'           => get_post_meta( $post->ID, 'endpoint', true ),
			'redirect-url'       => get_post_meta( $post->ID, 'redirect_url', true ),
			'name-attributes'    => get_post_meta( $post->ID, 'name_attributes', true ),
			'message'            => get_post_meta( $post->ID, 'message', true ),
			'additional-headers' => get_post_meta( $post->ID, 'additional_headers', true ),
		);

		$tools = array(
			'cf7'           => __( 'Contact Form 7', 'simply-static-pro' ),
			'gravity_forms' => __( 'Gravity Forms', 'simply-static-pro' ),
			'external'      => __( 'External Service', 'simply-static-pro' ),
		);

		$options          = Simply_Static\Options::instance();
		$auth_username    = $options->get( 'http_basic_auth_username' );
		$auth_password    = $options->get( 'http_basic_auth_password' );
		$default_endpoint = get_bloginfo( 'url' );


		if ( $auth_username && $auth_password ) {
			$default_endpoint = ( is_ssl() ? 'https://' : 'http://' ) . $auth_username . ':' . $auth_password . '@' . Util::strip_protocol_from_url( $default_endpoint );
		}
		?>
        <div class="ssp-meta ssp-admin">
            <div>
                <p>
                    <span><?php esc_html_e( 'Choose A Tool', 'simply-static-pro' ); ?></span><br>
                    <select name="tool" id="tool">
						<?php foreach ( $tools as $tool => $name ) : ?>
							<?php if ( $meta_fields['tool'] == $tool ) : ?>
                                <option selected="selected"
                                        value="<?php echo esc_attr( $tool ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php else : ?>
                                <option value="<?php echo esc_attr( $tool ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
                    </select>
                <p class="description"><?php esc_html_e( 'Choose the tool to process the form (Plugin or external service).', 'simply-static-pro' ); ?></p>
                </p>
                <p>
                    <span><?php esc_html_e( 'Form ID', 'simply-static-pro' ); ?></span><br>
                    <input id="form-id" name="form-id" type="text"
                           value="<?php echo esc_html( $meta_fields['form-id'] ); ?>">
                <p class="description"><?php esc_html_e( 'Optional: Only required when using a form plugin.', 'simply-static-pro' ); ?></p>
                </p>
                <p>
                    <span><?php esc_html_e( 'Subject Attribute', 'simply-static-pro' ); ?></span><br>
                    <input id="subject" name="subject" type="text"
                           value="<?php echo esc_html( $meta_fields['subject'] ); ?>">
                <p class="description"><?php esc_html_e( 'Optional: Only required when using a form plugin. Add the form attribute for the subject of your mail.', 'simply-static-pro' ); ?></p>
                </p>
                <p>
                    <span><?php esc_html_e( 'Name Attributes', 'simply-static-pro' ); ?></span><br>
                    <input id="name-attributes" name="name-attributes" type="text"
                           value="<?php echo esc_html( $meta_fields['name-attributes'] ); ?>">
                <p class="description"><?php esc_html_e( 'Add a comma-separated list of the name attributes of your form.', 'simply-static-pro' ); ?></p>
                </p>
                <p>
                    <span><?php esc_html_e( 'Message', 'simply-static-pro' ); ?></span><br>
                    <textarea rows="10" cols="15" id="message" name="message"
                              placeholder="<p>Thanks for your message. Here are your details:</p>&#10;&#10;<p>First Name: your-name</p>&#10;<p>E-Mail: your-email</p>"><?php echo esc_textarea( $meta_fields['message'] ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Use HTML to format your message. You can use the provided name attributes as they will automatically replaced with the added values from the submitted form.', 'simply-static-pro' ); ?></p>
                </p>
            </div>
            <div>
                <p>
                    <span><?php esc_html_e( 'E-Mail Address', 'simply-static-pro' ); ?></span><br>
                    <input id="email" name="email" type="email" value="<?php echo esc_html( $meta_fields['email'] ); ?>"
                           placeholder="mail@domain.com">
                <p class="description"><?php esc_html_e( 'Add the e-mail where the e-mail should be sent to.', 'simply-static-pro' ); ?></p>
                </p>
                <p>
                    <span><?php esc_html_e( 'Additional Headers', 'simply-static-pro' ); ?></span><br>
                    <input id="additional-headers" name="additional-headers" type="text"
                           placeholder="Cc: cc@domain.com,Bcc: bcc@domain.com"
                           value="<?php echo esc_html( $meta_fields['additional-headers'] ); ?>">
                <p class="description"><?php esc_html_e( 'Optional: You can add addtional headers to add CC, BCC or Reply to e-mails here. You can add multiple headers here by separating them with comma.', 'simply-static-pro' ); ?></p>
                </p>
                <p>
                    <span><?php esc_html_e( 'Endpoint', 'simply-static-pro' ); ?></span><br>
                    <input id="endpoint" name="endpoint" type="url"
                           value="<?php echo esc_html( $meta_fields['endpoint'] ); ?>"
                           placeholder="<?php echo esc_url( get_bloginfo( 'url' ) ); ?>">
                <p class="description"><?php _e( 'Add an endpoint URL from <a target="_blank" href="https://zapier.com/">Zapier</a>, <a target="_blank" href="https://ifttt.com/">IFTT</a> or use the Simply Static endpoint.', 'simply-static-pro' ); ?></p>
                <p class="description"><b><?php esc_html_e( 'Simply Static Endpoint', 'simply-static-pro' ); ?>:</b>
                    <code><?php echo esc_html( $default_endpoint ); ?>?mailme</code></p>
                </p>
                <p>
                    <span><?php esc_html_e( 'Redirect URL', 'simply-static-pro' ); ?></span><br>
                    <input id="redirect-url" name="redirect-url" type="url"
                           value="<?php echo esc_html( $meta_fields['redirect-url'] ); ?>"
                           placeholder="<?php echo esc_url( get_bloginfo( 'url' ) ); ?>">
                <p class="description"><?php esc_html_e( 'Redirect a user after successful submission to this URL.', 'simply-static-pro' ); ?></p>
                </p>
            </div>
        </div>
        <style>
            #form-configuration {
                float: left;
                width: 100%;
            }

            .ssp-meta > div {
                display: block;
                padding: 15px;
                float: left;
                width: 50%;
                box-sizing: border-box;
            }

            .ssp-meta > div input, .ssp-meta > div textarea, .ssp-meta > div select {
                width: 100%;
            }
        </style>
		<?php
	}

	/**
	 * Save the meta when the post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_metaboxes( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['simply_static_form_nonce_check_value'] ) ) {
			return $post_id;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['simply_static_form_nonce_check_value'], 'simply_static_form_nonce_check' ) ) {
			return $post_id;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		if ( isset( $_POST['tool'] ) ) {
			update_post_meta( $post_id, 'tool', $_POST['tool'] );
		} else {
			delete_post_meta( $post_id, 'tool' );
		}

		if ( isset( $_POST['email'] ) ) {
			update_post_meta( $post_id, 'email', $_POST['email'] );
		} else {
			delete_post_meta( $post_id, 'email' );
		}

		if ( isset( $_POST['additional-headers'] ) ) {
			update_post_meta( $post_id, 'additional_headers', $_POST['additional-headers'] );
		} else {
			delete_post_meta( $post_id, 'additional_headers' );
		}

		if ( isset( $_POST['form-id'] ) ) {
			update_post_meta( $post_id, 'form_id', $_POST['form-id'] );
		} else {
			delete_post_meta( $post_id, 'form_id' );
		}

		if ( isset( $_POST['subject'] ) ) {
			update_post_meta( $post_id, 'subject', $_POST['subject'] );
		} else {
			delete_post_meta( $post_id, 'subject' );
		}

		if ( isset( $_POST['endpoint'] ) ) {
			update_post_meta( $post_id, 'endpoint', $_POST['endpoint'] );
		} else {
			delete_post_meta( $post_id, 'endpoint' );
		}

		if ( isset( $_POST['redirect-url'] ) ) {
			update_post_meta( $post_id, 'redirect_url', $_POST['redirect-url'] );
		} else {
			delete_post_meta( $post_id, 'redirect_url' );
		}

		if ( isset( $_POST['name-attributes'] ) ) {
			$attributes = str_replace( ' ', '', $_POST['name-attributes'] );
			$attributes = rtrim( $attributes, ',' );
			update_post_meta( $post_id, 'name_attributes', $attributes );
		} else {
			delete_post_meta( $post_id, 'name_attributes' );
		}

		if ( isset( $_POST['message'] ) ) {
			update_post_meta( $post_id, 'message', $_POST['message'] );
		} else {
			delete_post_meta( $post_id, 'message' );
		}
	}
}
