<?php

namespace Hametuha\LoginSpamProtection\Services;



use Hametuha\LoginSpamProtection\Pattern\Singleton;

/**
 * reCAPTCHA V3
 *
 * @package hametuha
 * @property-read string $site_key
 * @property-read string $secret_key
 * @property-read bool   $display_label
 * @property-read bool   $site_key_defined
 * @property-read bool   $secret_key_defined
 * @property-read bool   $available
 */
class RecaptchaV3 extends Singleton {

	/**
	 * Constructor
	 */
	protected function init() {
		// Add setting field.
		add_action( 'admin_init', [ $this, 'add_setting_fields' ] );
		// Register scripts.
		add_action( 'init', [ $this, 'register_script' ] );
		// Hide recaptcha.
		add_action( 'login_head', [ $this, 'hide_recaptcha_badge' ], 1000 );
		add_action( 'wp_head', [ $this, 'hide_recaptcha_badge' ], 1000 );
		// Load assets.
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		// Add in login page.
		add_action( 'login_form', [ $this, 'login_form_input' ] );
		add_action( 'login_form', [ $this, 'login_form_message' ], 1 );
		add_action( 'register_form', [ $this, 'register_form_input' ] );
		add_action( 'register_form', [ $this, 'login_form_message' ], 1 );
		// Handle login validation.
		add_filter( 'authenticate', [ $this, 'authenticate' ], 50, 3 );
		// Handle registration validation.
		add_filter( 'registration_errors', [ $this, 'registration_errors' ], 10, 3 );
		// Register message for contact form 7.
		add_action( 'wpcf7_init', [ $this, 'register_cf7_tag' ] );
	}

	/**
	 * Verify reCAPTCHA's token.
	 *
	 * @param string $token     Token generated from reCAPTCHA
	 * @param string $ip        Default user's remote IP.
	 * @param float  $threshold Threshold.
	 * @return bool|\WP_Error
	 */
	public function verify( $token, $ip = '', $threshold = 0.5 ) {
		if ( ! $this->available ) {
			return new \WP_Error( 'recaptcha_verification_failed', __( 'This site has no proper setting.', 'lsp' ) );
		}
		$result = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
			'body' => [
				'secret'   => $this->secret_key,
				'response' => $token,
				'remoteip' => $ip ?: $this->get_default_ip(),
			],
		] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = json_decode( $result['body'] );
		if ( ! $response || ! $response->success || ( $response->score < $threshold ) ) {
			return new \WP_Error( 'recaptcha_verification_failed', __( 'Our spam filter recognize your request as spam. Please retry entering the login credentials.', 'lsp' ) );
		}
		return true;
	}

	/**
	 * Get default USER IP address.
	 *
	 * @return string
	 */
	protected function get_default_ip() {
		$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
		return apply_filters( 'lsp_default_remote_ip', $remote_addr );
	}

	/**
	 * Register script.
	 *
	 * @return void
	 */
	public function register_script() {
		if ( ! $this->site_key ) {
			return;
		}
		$is_global = (bool) get_option( 'recapthca_v3_is_global', '' );
		$url       = $is_global ? 'https://www.recaptcha.net/recaptcha/api.js' : 'https://www.google.com/recaptcha/api.js';
		wp_register_script( 'google-recaptcha', add_query_arg( [
			'render' => $this->site_key,
		], $url ), [], null, true );
		// Add async callback.
		// @see https://developers.google.com/recaptcha/docs/loading
		$js = <<<JS
		if ( typeof grecaptcha === 'undefined' ) {
			grecaptcha = {};
		}
		if ( typeof grecaptcha.ready === 'undefined') {
			grecaptcha.ready = function(cb){
				if( typeof grecaptcha === 'undefined' ) {
					const c = '___grecaptcha_cfg';
					window[c] = window[c] || {};
					( window[c]['fns'] = window[c]['fns'] || [] ).push( cb );
				} else {
					cb();
				}
			}
		}
JS;
		wp_add_inline_script( 'google-recaptcha', $js, 'after' );

	}

	/**
	 * Enqueue recaptcha script.
	 *
	 * @return void
	 */
	public function enqueue_recaptcha() {
		static $done = false;
		if ( $done ) {
			// Already done. Do nothing.
			return;
		}
		$done = true;
		wp_enqueue_script( 'google-recaptcha' );

		$key = esc_js( $this->site_key );
		$js  = <<<JS
		grecaptcha.ready(function() {
			var inputs = document.getElementsByClassName( 'lsp-token-input' );
			if ( inputs.length ) {
				Array.from( inputs ).forEach( function( input ) {
					var action = input.dataset.action || 'homepage';
			 		grecaptcha.execute( '{$key}', { action: action } ).then( function( token ) {
						if ( input ) {
							input.value = token;
						}
			 		});
				} );
			}
       });
JS;
		wp_add_inline_script( 'google-recaptcha', $js );
	}

	/**
	 * Enqueue login header.
	 *
	 * @return string
	 */
	public function enqueue_assets() {
		if ( $this->available ) {
			$this->enqueue_recaptcha();
		}
	}

	/**
	 * Render recaptcha v3 token field.
	 */
	public function login_form_input() {
		$this->render_input( 'lsp-login', 'login' );
	}

	/**
	 * Render recaptcha v3 token field.
	 */
	public function register_form_input() {
		$this->render_input( 'lsp-register', 'login' );
	}

	/**
	 *
	 * @param string $id     ID of element.
	 * @param string $action Input values.
	 *
	 * @return void
	 */
	public function render_input( $id, $action ) {
		if ( $this->available ) {
			printf( '<input class="lsp-token-input" type="hidden" name="%1$s" id="%2$s" data-action="%3$s" value="" />', 'recaptcha-v3-token', esc_attr( $id ), esc_attr( $action ) );
		}
	}

	/**
	 * Render message if needed.
	 *
	 * @return void
	 */
	public function login_form_message() {
		echo $this->get_login_form_message();
	}

	/**
	 * Get login form message.
	 *
	 * @return string
	 */
	protected function get_login_form_message() {
		if ( ! $this->available ) {
			return '';
		}
		if ( (bool) get_option( 'recapthca_v3_display_label' ) ) {
			// Badge is explicitly displayed.
			return '';
		}
		$message = $this->get_recaptcha_message();
		return apply_filters( 'lsp_recaptcha_v3_message_html', sprintf( '<p class="description lsp-description">%s</p>', wp_kses_post( $message ) ) );
	}

	/**
	 * Register contact form 7 tag.
	 *
	 * @return void
	 */
	public function register_cf7_tag() {
		wpcf7_add_form_tag( 'recaptcha_txt', function() {
			return $this->get_recaptcha_message();
		}, [] );
	}

	/**
	 * Get recaptcha message.
	 *
	 * @return string
	 */
	public function get_recaptcha_message() {
		// translators: %1$s is privacy link, %2$s is terms of service link.
		$message = get_option( 'recapthca_v3_message', '' ) ?: __( 'This site is protected by reCAPTCHA and the Google <a href="%1$s">Privacy Policy</a> and <a href="%2$s">Terms of Service</a> apply.', 'lsp' );
		$message = apply_filters( 'lsp_recapthca_v3_message', $message );
		foreach ( [
			'%1$s' => 'https://policies.google.com/privacy',
			'%2$s' => 'https://policies.google.com/terms',
		] as $placeholder => $replaced ) {
			$message = str_replace( $placeholder, $replaced, $message );
		}
		return $message;
	}

	/**
	 * Login filter.
	 *
	 * @param null|\WP_User|\WP_Error $user
	 * @param string                  $username
	 * @param string                  $password
	 * @return null|\WP_Error
	 */
	public function authenticate( $user, $username, $password ) {
		if ( ! $this->available ) {
			return $user;
		}
		if ( empty( $username ) || empty( $password ) ) {
			// This is not my case.
			return $user;
		}
		// Is this login try?
		$token  = filter_input( INPUT_POST, 'recaptcha-v3-token' );
		$result = $this->verify( $token );
		if ( is_wp_error( $result ) ) {
			if ( is_wp_error( $user ) ) {
				$user->add( $result->get_error_code(), $result->get_error_message() );
			} else {
				$user = $result;
			}
		}
		return $user;
	}

	/**
	 * Verify registration information.
	 *
	 * @param \WP_Error $errors
	 * @param string    $login
	 * @param string    $email
	 * @return \WP_Error
	 */
	public function registration_errors( $errors, $login, $email ) {
		if ( ! $this->available ) {
			return $errors;
		}
		$token  = filter_input( INPUT_POST, 'recaptcha-v3-token' );
		$result = $this->verify( $token );
		if ( is_wp_error( $result ) ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}
		return $errors;
	}

	/**
	 * Add setting fields
	 */
	public function add_setting_fields() {
		add_settings_section( 'recaptcha', __( 'Google reCAPTCHA', 'lsp' ), function() {
			printf( '<p class="description">%s</p>', esc_html__( 'Add Google reCAPTCHA v3 to protect your site.', 'lsp' ) );
		}, 'lsp' );
		foreach ( [
			[ 'site_key', __( 'Site Key', 'lsp' ), $this->site_key_defined ],
			[ 'secret_key', __( 'Secret Key', 'lsp' ), $this->secret_key_defined ],
		] as list( $name, $title, $is_defined ) ) {
			// site key and fields.
			$option_name = 'recaptcha_v3_' . $name;
			add_settings_field( $option_name, $title, function() use ( $name, $is_defined, $option_name ) {
				$value = $this->{$name};
				?>
				<input name="<?php echo esc_attr( $option_name ); ?>" id="<?php echo esc_attr( $option_name ); ?>"
					type="text" class="regular-text"
					value="<?php echo esc_attr( $value ); ?>" <?php echo $is_defined ? 'readonly="readonly"' : ''; ?>
					placeholder="xxxxxxxx" />
				<?php
				if ( $is_defined ) {
					printf( '<p class="description">%s</p>', esc_html__( 'This value is defined in code.', 'lsp' ) );
				}
			}, 'lsp', 'recaptcha' );
			if ( ! $is_defined ) {
				register_setting( 'lsp', $option_name );
			}
		}
		// Display flag.
		add_settings_field( 'recapthca_v3_display_label', __( 'reCAPTCHA Badge', 'lsp' ), function() {
			$display = $this->display_label;
			foreach ( [
				__( 'Hide reCAPTCHA badge.', 'lsp' ),
				__( 'Display reCAPTCHA badge', 'lsp' ),
			] as $index => $label ) {
				printf(
					'<p><input type="radio" name="recapthca_v3_display_label" value="%s" %s/> %s</p>',
					esc_attr( $index ? '1' : '' ),
					checked( (bool) $index, $display, false ),
					esc_html( $label )
				);
			}
		}, 'lsp', 'recaptcha' );
		register_setting( 'lsp', 'recapthca_v3_display_label' );
		// Message
		add_settings_field( 'recapthca_v3_message', __( 'Attribution Message', 'lsp' ), function() {
			?>
			<textarea rows="3" name="recapthca_v3_message" style="width: 100%; box-sizing: border-box"
				placeholder='This site is protected by reCAPTCHA and the Google &lt;a href="%1$s"&gt;Privacy Policy&lt;/a&gt; and &lt;a href="%2$s"&gt;Terms of Service&lt;/a&gt; apply.'
			><?php echo esc_textarea( get_option( 'recapthca_v3_message', '' ) ); ?></textarea>
			<p class="description">
				<?php
				// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				esc_html_e( 'Google requested to display attribution text if reCAPTCHA badge is hidden. %1$s and %2$s will be replaced with URL.', 'lsp' );
				?>
			</p>
			<?php
		}, 'lsp', 'recaptcha' );
		register_setting( 'lsp', 'recapthca_v3_message' );
		// Global flag.
		add_settings_field( 'recapthca_v3_is_global', __( 'Source Domain', 'lsp' ), function() {
			$global = (bool) get_option( 'recapthca_v3_is_global', '' );
			foreach ( [
				__( 'Load from google.com', 'lsp' ),
				__( 'Load from recaptha.net', 'lsp' ),
			] as $index => $label ) {
				printf(
					'<p><input type="radio" name="recapthca_v3_is_global" value="%s" %s/> %s</p>',
					esc_attr( $index ? '1' : '' ),
					checked( (bool) $index, $global, false ),
					esc_html( $label )
				);
			}
			printf( '<p>%s</p>', esc_html__( 'If google.com is not accessible, use recaptcha.net', 'lsp' ) );
		}, 'lsp', 'recaptcha' );
		register_setting( 'lsp', 'recapthca_v3_is_global' );
	}

	/**
	 * Hide recaptcha
	 *
	 * @return void
	 */
	public function hide_recaptcha_badge() {
		if ( (bool) get_option( 'recapthca_v3_display_label', '' ) ) {
			// Explicitly display label.
			return;
		}
		?>
		<style>
			.grecaptcha-badge { visibility: hidden; }
		</style>
		<?php
	}

	/**
	 * Get defined constants.
	 *
	 * @param string $name
	 * @return string
	 */
	private function get_key( $name ) {
		$const = 'RECAPTCHA_V3_' . strtoupper( $name );
		if ( defined( $const ) ) {
			$constants = get_defined_constants();
			$value     = isset( $constants[ $const ] ) ? $constants[ $const ] : '';
		} else {
			$value = (string) get_option( 'recaptcha_v3_' . $name, '' );
		}
		return apply_filters( 'recaptcha_v3_' . $name, $value );
	}

	/**
	 * Getter
	 *
	 * @param $name string
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'site_key':
			case 'secret_key':
				return $this->get_key( $name );
			case 'display_label':
				return (bool) $this->get_key( $name );
			case 'site_key_defined':
			case 'secret_key_defined':
				return defined( 'RECAPTCHA_V3_' . strtoupper( str_replace( '_defined', '', $name ) ) );
			case 'available':
				return $this->site_key && $this->secret_key;
			default:
				return null;
		}
	}


}
