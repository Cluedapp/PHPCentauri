<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file wraps the Google login API
	 *
	 * Required Composer packages:
	 *    google/apiclient
	 *
	 * Required app settings:
	 * GoogleAppName
	 * GoogleAppScopes
	 * GoogleAutoLoginRedirect
	 * GoogleClientID
	 * GoogleClientSecret
	 * GoogleLoginRedirectURL
	 * SystemCacheTTL
	 */

	# Create the base client that wraps all HTTP REST calls to Google's servers
	function google_api_client($google_login_callback = null) {
		static $client = null;
		if ($client === null) {
			$client = new Google_Client();
			google_login($google_login_callback);
		}
		return $client;
	}

	/**
	 * The following function generally doesn't need to be called directly, since it is a helper function for google_api_client()
	 * This function assumes that a Google app has been properly created, and that login credentials have been setup, in Google API Console
	 * The Google Client ID and Google Client Secret used in the function, comes from the Credentials page on the Google API Console
	 * https://console.cloud.google.com/apis
	 *
	 * @param function $callback A callback function with signature: function($refresh_token, $access_token, $email). $refresh_token and/or $email could be empty, so the callback function needs to check if they are empty, it cannot be assumed that they are non-empty. If it's a first-time valid login, $refresh_token will have a value, but for subsequent valid logins, $refresh_token will be empty, and $email will most likely have a value. For an invalid login, say an expired code is provided, this callback won't be called
	 */
	function google_login($callback = null) {
		$client = google_api_client();

		# Set app credentials (the app's identification, not the user's)
		# Using a JSON config file is exactly the same as directly giving the Google Client ID and Google Client Secret. The code is just displayed here for comparison
		#$credentialsFile = 'client_secret.json';
		#if (!file_exists($credentialsFile))
		#	throw new RuntimeException('Service account credentials not found!');
		#$client->setAuthConfig($credentialsFile);
		$client->setClientId(AppSettings['GoogleClientID']);
		$client->setClientSecret(AppSettings['GoogleClientSecret']);

		# Tell Google to return a refresh token back to the API.
		# The refresh token is used to get an access token, i.e. to generate a new access token.
		# Access tokens expire after a certain amount of time.
		# The purpose of an access token is that it is the "ID" required by Google, to access the user's account, for example uploading files to Google Drive in PHP through Google's Client API.
		# The refresh token is only returned the first time when the user grants access to our app. After that, the refresh token is never returned again, so we have to store it permanently.
		# If the refresh token gets lost, you have to completely revoke the app's access for the user, by going to https://myaccount.google.com/permissions and redo the login process again, to obtain a new refresh token.
		# That is actually the only purpose of this file, to either get a new refresh token, or use a valid existing refresh token, to just get an access token, for use in one of Google's services.
		$client->setAccessType('offline');

		# Configure the rest of the required settings to do a Google login
		$client->setApplicationName(AppSettings['GoogleAppName']);
		$client->setScopes(AppSettings['GoogleAppScopes']);
		if (isset($_GET['state']))
			$client->setState($_GET['state']);
		$client->setRedirectUri(AppSettings['GoogleLoginRedirectURL']); # come back here after authentication code was received

		# At this point, the user has either been here in this script already, or not.
		# If they have, then they should have an authentication code in $_GET['code'].
		# If it's not present, we assume they need to login now. When they come back
		# here after logging in (because they didn't have an authentication code, which
		# a correct login would give them), they should then have an authentication
		# code in $_GET['code']

		# Check if authentication code exists
		$session = get_system_cache(AppSettings['SystemCacheTTL']);
		if (isset($_GET['code'])) {
			# Exchange authentication code for access token
			# print_r($client->fetchAccessTokenWithAuthCode($code)); # not sure what this does
			log_error('Google login - client =', (array)$client);
			$client->authenticate($_GET['code']); # do the exchange
			$access_token = $client->getAccessToken();
			log_error('Google login - got access token directly from Google', $access_token);

			$refresh_token = '';
			$access_token_string = ''; # yes, this looks confusing, why is there both an $access_token and an $access_token_string? it is explained below
			$email = '';

			# Set refresh token
			if (isset($access_token['refresh_token'])) {
				$refresh_token = $access_token['refresh_token'];

				log_error('Google login - got new refresh token, updating cached refresh token =', $refresh_token);
				$session->write('google_refresh_token', $refresh_token);
			}

			# Set email address
			if (isset($access_token['access_token'])) {
				# If we are in here, then the login was valid. If we're not in here, the login was invalid
				# If we are in here, we do not necessarily have a refresh token, only an access token
				# We only get a refresh token on the first valid login, not on subsequent valid logins

				$access_token_string = $access_token['access_token']; # this looks confusing, but what's going on here is that $access_token is an array consisting of the actual contents of the decoded access_token string, and $access_token['access_token'] is the encoded access_token string, from which the $access_token array was actually populated from in the first place

				# We have to do an API call to get the email address
				$userinfo = json_decode(file_get_contents("https://www.googleapis.com/oauth2/v1/userinfo?access_token=$access_token_string"), true);
				if (is_array($userinfo) && count($userinfo)) {
					log_error('Google login - got userinfo', $userinfo);

					$email = $userinfo['email'];

					log_error('Google login - got email address, updating cached email =', $email);
					$session->write('google_login_email', $email);
				}

				# Store the access token in system cache (e.g. Redis or file system), because it expires after a certain amount of time. The refresh token (not the access token) is created and remains constant, each time the user initially grants access to the app
				# A new access token is only created if one doesn't exist yet, or when the previous access token has expired
				$session->write('google_access_token', json_encode($access_token));
				log_error('Google login - access token has been set in system cache', $access_token);

				# Call the "event handler" callback here, so that PHPCentauri API clients/users can store the obtained $refresh_token and/or $access_token, say into a database or session, for example
				if ($access_token_string) {
					# if $access_token_string is not empty, it means the login was valid, so call the callback. Thus, the callback is never called if the login was invalid
					# $refresh_token can be empty, but $access_token_string will never be empty, and $email will probably not be empty, although it could be, if Google changes their userinfo API for fetching the email address for an access token
					$callback && $callback($refresh_token, $access_token_string, $email);
				}
			}
		} else {
			# At this point, either the user already successfully logged in previously, or this is their first time here
			# If the access token exists in the cache, we will try to use it
			# If the access token is invalid (e.g. it is expired, doesn't exist or it's just wrong), we will try to refresh the access token with the refresh token
			# If the refresh fails, the user needs to login first

			log_error('Google login - code not set');
			log_error('Google login - reading access token from cache');
			$access_token = (array)json_decode($session->read('google_access_token'));
			log_error('Google login - got access token', $access_token);
			$success = false;
			if (!empty($access_token)) {
				log_error('Google login - trying to set existing access token', $access_token['access_token']);
				try {
					$client->setAccessToken($access_token);
					$success = !$client->isAccessTokenExpired(); # it is possible for an app's access to have been revoked, but the existing, "technically invalid" access token still works here, because it has not expired yet
					log_error('Google login - existing access token successfully set');
				}
				catch (Exception $e) {
					log_error('Google login - error while setting access token', $access_token);
				}
			}
			if ($success) {
				$callback && $callback($session->read('google_refresh_token'), $access_token['access_token'], $session->read('google_login_email'));
			} else {
				try {
					log_error('Google login - trying to use refresh token to obtain new access token');
					$client->refreshToken($session->read('google_refresh_token'));
					$access_token = $client->getAccessToken();
					if (!empty($access_token)) {
						log_error('Google login - got new access token from refresh token, access token = ', $access_token);
						log_error('Google login - writing new access token to cache');
						$session->write('google_access_token', json_encode($access_token));
						$success = true;
					} else {
						log_error('Google login - got bad access token, while trying to generate a new one from the refresh token. a new login and refresh token is required');
					}
				} catch (Exception $e) {
					log_error('Google login - error while trying to obtain new access token from refresh token');
				}
				if ($success) {
					$callback && $callback($session->read('google_refresh_token'), $access_token['access_token'], $session->read('google_login_email'));
				} else {
					# Redirect to login page
					log_error('Google login - redirecting to login page');
					$url = $client->createAuthUrl();
					if (AppSettings['GoogleAutoLoginRedirect'] ?? false) {
						header("Location: $url");
						echo "<html><head><meta http-equiv=\"refresh\" content=\"0;url=$url\" /></head><body><a href=\"$url\">Login to Google</a></body></html>";
						die;
					} else
						success(['RequireGoogleLogin' => 1, 'GoogleLoginURL' => $url]);
				}
			}
		}
	}
?>
