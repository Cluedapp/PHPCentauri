<?php
	class Register extends BaseModel {
		public static function definition() {
			return [
				'Email' => i('email'),
				'Password' => i('regex("^[a-zA-Z0-9_]+$")'),
			];
		}
	}
?>
