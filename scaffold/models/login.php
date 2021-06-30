<?php
	class Login extends BaseModel {
		public static function definition() {
			return [
				'Login' => i('email or regex("^[a-zA-Z0-9]+$")'),
				'Password' => i('regex("^[a-zA-Z0-9_]+$")'),
			];
		}
	}
?>
