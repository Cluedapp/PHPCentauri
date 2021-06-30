<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file wraps the Http1 API protocol
	 * Used by the He3 API, Leadman API, QMM API
	 */

	function collect1() {
		return array_reduce(func_get_args(), function($carry, $item) {
			if (input_exists($item))
				$carry[$item] = input($item);
			return $carry;
		}, []);
	}

	function fail1($error, $data = []) {
		log_error('fail1', $error, $data);
		if (is_string($error))
			$str = json_encode(array_merge($data, ['Error' => $error]));
		else
			$str = json_encode($error);
		output($str);

		/*
		http_response_code(400);
		switch (get_response_mime_type()) {
			case 'json':
				die(json_encode(['error' => $error]));
				break;
			default:
				die(!!$error ? $error : 'Error');
		}
		*/
	}

	function fail_message1($message) {
		fail(APIError::ErrorMessage, ['Message' => $message]);
	}

	function get_permissions1() {
		return get_user_session()->UserPermission;
	}

	function get_user_session1($session_id = null) {
		if ($session_id === null) $session_id = user_id();
		$normalized = str_replace(']"', ']', str_replace('"[', '[', str_replace('}"', '}', str_replace('"{', '{', str_replace('\\"', '"', get_session_store()->read($session_id))))));
		$session = json_decode($normalized);
		return $session;
	}

	function get_user_sessions1() {
		$ret = [];
		foreach (redis_session_client()->keys('*') as $session_id) {
			$ret[$session_id] = get_user_session(substr($session_id, strlen(AppSettings['SessionKeyPrefix'])));
		}
		return $ret;
	}

	function input1($name) {
		return input_exists($name) ? input_payload()->$name : null;
	}

	function input_exists1($name) {
		$json = input_payload();
		return isset($json->$name);
	}

	function input_payload1() {
		static $json = null;
		if ($json === null) {
			$temp = json_decode(@raw_input('J'));
			if ($temp !== false) $json = $temp;
		}
		return $json;
	}

	function logged_in1() {
		return session_exists(user_id());
	}

	function login1() {
		# Collect input
		$platform = input('Platform');

		# Set login database settings
		tableStorageConnectionString(AppSettings['LoginTableStorageConnectionString']);

		switch ($platform) {
			case 'Google':
				# Collect input
				$idToken = input('Token');

				log_error('login - using platform google');
				# Extract Google details from the OpenID Connect's JWT token, sent to the browser in Google's access token response body, and finally posted to the API
				$retryCount = 0;
				$payload = null;
				while (!$payload && $retryCount < 5) {
					try {
						log_error('login - creating google client');
						$client = new Google_Client(['client_id' => AppSettings['GoogleClientID']]);
						log_error('login - verifying id token and getting payload');
						$payload = $client->verifyIdToken($idToken);
						log_error('login - google id token verify result', $payload);
						if ($payload) {
							$email = $payload['email'];
						} else {
							fail(APIError::Format);
						}
					}
					catch (Exception $e) {
						log_error('login - error while verifying id token, exception =', $e->getMessage());
						++$retryCount;
					}
				}
				if (!$payload)
					fail(APIError::Format);

				# Read from database
				log_error("fetching user by email $email");
				$user = item_cache_get(User::class, ['PartitionKey' => AppSettings['LoginPartitionKey'], 'UserEmail' => $email]);
				log_error('the fetched user =', $user);

				# Error
				if (!$user) {
					fail(APIError::Unknown);
				}

				# Error
				if (!$user->UserStatus) {
					fail(APIError::Lost);
				}

				# Error
				if (!$user->UserPIN) {
					fail(APIError::PIN);
				}

				# Error
				if ($user->UserPlatform !== 'Google') {
					fail('Platform', ['For' => 'Google']);
				}

				# Generate permissions
				$roles = json_decode($user->UserRole);
				log_error('got roles', $roles);
				# $permission = Role::all(['PartitionKey' => AppSettings['LoginPartitionKey'], 'RoleID' => $roles, 'RoleStatus' => true], ['RolePermission']);
				$permission = pick(item_cache_get(Role::class, ['PartitionKey' => AppSettings['LoginPartitionKey'], 'RoleID' => in($roles), 'RoleStatus' => true]), ['RolePermission']);
				log_error('got raw permission from db', $permission);
				$permission = pick($permission, ['RolePermission', 0]);
				log_error('got picked permission', $permission);
				$permission = json_merge($permission);
				log_error('got permission json strings', $permission);
				log_error('the php type of User.UserStatus =', gettype($user->UserStatus));
				if (!$permission) {
					fail(APIError::Permission);
				}

				# Set unique user ID
				do {
					# Get lock
					log_error('opening lock file');
					$f = fopen('../lockfile', 'w');
					log_error('locking lock file');
					if (!flock($f, LOCK_EX)) continue;
					log_error('register shutdown function');
					register_shutdown_function(function() use (&$f) { if ($f !== null) fclose($f); });

					# Begin critical section

					# Create new session ID (user ID)
					do {
						$sessionID = substr(unique_id(), 0, 8);
						log_error('generated new unique user ID', $sessionID);
					} while (session_exists($sessionID));
					log_error('accepted unique user ID', $sessionID);
					user_id($sessionID);

					# End critical section

					# Release lock
					log_error('unlocking lock file');
					flock($f, LOCK_UN);
					log_error('closing lock file');
					fclose($f);
					$f = null;

					# Exit infinite loop
					break;
				} while (true);

				# Set user key
				log_error('login got key', input('Key'));
				if (!$userKey = rsa_decrypt(input('Key'))) {
					fail(APIError::Key);
				}
				log_error('login got decrypted key', $userKey);
				user_key($userKey);

				# Add user session
				$userAgent = server('HTTP_USER_AGENT');
				log_error('saving user session', 'user id = ', user_id(), 'user agent =', $userAgent, $_SERVER);
				save_user_session([
					'SessionValid' => true,
					'UserAgent' => md5($userAgent),
					'UserEmail' => $email,
					'UserGeo' => md5(geoip()),
					'UserID' => $user->UserID,
					'UserIP' => get_ip(),
					'UserKey' => $userKey,
					'UserPermission' => $permission,
					'UserTime' => (int)input('Time'),
					'UserRole' => json_encode($roles)
				]);
				log_error('user sessions are now', get_user_sessions());

				# Verify request
				verify_request(true);

				# Return result
				success(['UserID' => $sessionID, 'UserPIN' => $user->UserPIN, 'UserName' => $user->UserName, 'UserPermission' => $permission]);
				break;

			default:
				fail(APIError::Platform);
		}
	}

	function logout1($session_id = null) {
		if ($session_id === null) $session_id = user_id();
		get_session_store()->destroy($session_id);
	}

	function output1($str) {
		if (input('Form'))
			die('<html><body><script type="text/javascript">window.parent.postMessage("' . addslashes($str) . '", "*")</script></body></form>');
		else
			die($str);
	}

	function permission1($permissions) {
		if (!has_permission($permissions)) {
			log_error('permission1 no permission');
			fail(APIError::Lost);
		}
	}

	function save_user_session1($session, $session_id = null) {
		if ($session_id === null) $session_id = user_id();
		return get_session_store()->write($session_id, json_encode($session));
	}

	# true if user session exists locally
	# if false, then it might exist remotely, or it might not, it is impossible to say without fetching it from e.g. Azure in that case
	function session_exists1($session_id) {
		$session = get_session_store()->read($session_id);
		return !!$session;
	}

	function success1($obj = []) {
		$json = json_encode($obj);
		$str = json_encode((object)['J' => $json, 'H' => md5(user_key() . $json)]);
		log_error('success1', $str);
		output($str);
	}

	# user_id() should be session_id(), but of course PHP already uses session_id() for itself
	function user_id1($newValue = null, $force = false) {
		static $value = null;
		if ($newValue !== null || $force) $value = $newValue;
		elseif ($value === null) $value = input('ID');
		return $value;
	}

	# user_key() should be session_key(), but since session_id() is user_id(), we keep the name symmetric as user_key()
	function user_key1($newValue = null, $force = false) {
		static $value = null;
		if ($newValue !== null || $force) $value = $newValue;
		elseif ($value === null) {
			$session = get_user_session();
			if ($session) $value = $session->UserKey;
		}
		return $value;
	}

	function verify_request1() {
		if (isset(AppSettings['VerifyRequest']) && !AppSettings['VerifyRequest']) {
			return;
		}

		# Only allow POST requests, not GET, PUT, etc.
		if (server('REQUEST_METHOD') !== 'POST') {
			log_error('verify_request1 invalid method');
			fail(APIError::Method);
		}

		# Discard POST input > 50KB
		$input = json_encode($_POST);
		if (!$input || strlen($input) > 50000) {
			log_error('verify_request1 input too large');
			fail(APIError::Input);
		}

		# Only allow HTTPS protocol, not HTTP
		if (server('HTTPS') !== 'on') {
			log_error('verify_request1 only https allowed');
			fail(APIError::HTTPS);
		}

		# Error - session timeout
		if (!logged_in()) {
			log_error('verify_request1 not logged in');
			fail(APIError::Session);
		}

		# Current server time
		$time = time_ms();

		# Collect input
		$userID = user_id();
		$userKey = user_key();
		$userTime = (int)input('Time');
		$userJson = raw_input('J');
		$userHash = raw_input('H');
		$userAgent = server('HTTP_USER_AGENT');
		$userIP = get_ip();

		log_error('verify_request1', 'key', $userKey, 'user json', $userJson, 'got hash', $userHash, 'expected hash', md5("$userKey$userJson"));

		if ($userHash != md5("$userKey$userJson")) {
			log_error('verify_request1 error', 'user id = ', $userID, '$userHash != md5("$userKey$userJson")');
			fail(APIError::Hash);
		}

		if ($userID !== null && !preg_match('/[a-zA-Z0-9]{8}/', $userID))  {
			log_error('verify_request1 error', 'user id = ', $userID, '$userID !== null && !preg_match(\'/[a-zA-Z0-9]{8}/\', $userID)');
			fail(APIError::ID);
		}

		$timeDiff = $userTime - $time;

		# Check late-arriving requests
		if ($timeDiff < -20000 || $timeDiff > 10000) {
			logout();
			log_error('verify_request1 error', 'user id = ', $userID, '$timeDiff < -20000 || $timeDiff > 10000', 'userTime', $userTime, 'time', $time, 'timeDiff', $timeDiff);
			fail(APIError::Time, ['Time' => $time]);
		}
		if ($userTime < $time - (10 * 3600 * 1000)) {
			log_error('verify_request1 error', 'user id = ', $userID, '$userTime < $time - (10 * 3600 * 1000)', 'userTime', $userTime, 'time', $time);
			logout();
			fail(APIError::Timeout);
		}

		$userSession = get_user_session();

		if (!$userSession) {
			log_error('verify_request1 error', '!$userSession');
			fail(APIError::Nonexist);
		}

		if (!$userSession->SessionValid) {
			log_error('verify_request1 error', 'user id = ', $userID, '!$userSession->SessionValid');
			logout();
			fail(APIError::Session);
		}

		if ($userSession->UserAgent !== md5($userAgent)) {
			log_error('verify_request1 error', 'user id = ', $userID, '$userSession->UserAgent !== md5($userAgent)', '$userSession->UserAgent = ', $userSession->UserAgent, '$userAgent = ', $userAgent, 'md5($userAgent) =', md5($userAgent), $_SERVER);
			logout();
			fail(APIError::Hijack);
		}

		if ($userSession->UserIP !== $userIP) {
			log_error('verify_request1 ip change', '$userSession->UserIP !== $userIP');
			if ($userSession->UserGeo !== md5(geoip())) {
				log_error('verify_request1 error', 'user id = ', $userID, '$userSession->UserGeo !== md5(geoip())');
				logout();
				fail(APIError::Hijack);
			}
			$userSession->UserIP = $userIP;
		}

		$userSession->UserTime = $userTime;
		save_user_session($userSession);
	}
?>
