<?php
	class UserController {
		/**
		 * This controller action keeps the user's session alive
		 *
		 * @route /User/Keepalive
		 * @input Time int
		 * @login
		 */
		function keepalive($time) {
			return ['Time' => $time];
		}

		/**
		 * This controller action logs a user in
		 *
		 * @route /User/Login
		 * @model Login
		 */
		function login() {
			# Implement login logic here
			# See:
			# - The Login model class (models/login.php), and update it as needed with fields required for login in your system
			# - The login4() function in lib/http4.php
			# The below login() function doesn't need to be called, you can implement login logic in any way that you want to in your PHPCentauri API
			# The only requirement that the PHPCentauri API makes, in the latest Http4 API protocol's verify_request4() function at least, is that for there to be a valid login in the PHPCentauri API, the following expression must be truthy:
			# ((array)get_user_session())[AppSettings['SessionUserIDPropertyName']]
			# Note that, if you go the route of calling login() in your system as in the example below, then you also need to define a function called api_login() somewhere in a file in the lib_api directory

			login();
		}

		/**
		 * This controller action logs the current user out
		 *
		 * @route /User/Logout
		 * @login
		 */
		function logout() {
			logout();
		}

		/**
		 * This controller action checks if the user has the specified permissions
		 *
		 * @route /User/Permission
		 * @input Permission json_object
		 * @login
		 */
		function permission($permission) {
			# Collect input
			log_error('required permission is', $permission);
			log_error('required json permission is', $permission);
			log_error('user permissions are', get_permissions());

			# Return result
			if (permission($permission) !== false)
				return ['Success' => 1];
			else
				fail(APIError::Lost);
		}

		/**
		 * This controller action registers a new user
		 *
		 * @route /User/Register
		 * @model registration Register
		 */
		function register($registration) {
			# Implement registration logic here
			# See the Register model class (models/register.php), and update it as needed with fields required for registration in your system
			# This method is just a stub. You do not need any registration functionality in your PHPCentauri API or in your system, it is optional. The following is just an example of how you can implement it with PHPCentauri

			# This validation is (or can be) done in the Register model, but it's shown here as an example
			if (!$registration->Email)
				fail(APIError::Null, ['For' => 'Email']);

			# This validation is (or can be) done in the Register model, but it's shown here as an example
			if (!$registration->Password)
				fail(APIError::Null, ['For' => 'Password']);

			# Insert user into the database
			$user = new User();
			$user->UserEmail = $registration->Email;
			$user->UserPassword = $registration->UserPassword;
			$user->UserStatus = true;
			add_entity($user);
		}
	}
?>
