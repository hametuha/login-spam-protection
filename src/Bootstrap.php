<?php

namespace Hametuha\LoginSpamProtection;


/**
 * Plugin bootstrap.
 */
class Bootstrap extends Pattern\Singleton {

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		// Controllers.
		Controllers\Options::get_instance();
		// Services
		Services\RecaptchaV3::get_instance();
	}
}
