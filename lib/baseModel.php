<?php
	/**
	 * @package PHPCentauri
	 *
	 * Base class for PHPCentauri PIE models
	 *
	 * See PHPCentauri item\controller.php for more information.
	 */

	abstract class BaseModel {
		public static function definition() { } # override this in model classes

		public static function model($fail_if_input_invalid = true) {
			return \model(static::class, $fail_if_input_invalid);
		}
	}
?>
