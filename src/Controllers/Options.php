<?php

namespace Hametuha\LoginSpamProtection\Controllers;


use Hametuha\LoginSpamProtection\Pattern\Singleton;

/**
 * Admin screen helper.
 */
class Options extends Singleton {

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
	}

	/**
	 * Register option page.
	 *
	 * @return void
	 */
	public function admin_menu() {
		$label = __( 'Login Security', 'lsp' );
		add_options_page( $label, $label, 'manage_options', 'lsp', [ $this, 'render' ] );
	}

	/**
	 * Render option screen.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Login Security', 'lsp' ); ?></h1>
			<form method="POST" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( 'lsp' );
				do_settings_sections( 'lsp' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
