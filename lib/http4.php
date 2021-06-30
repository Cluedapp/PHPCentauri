<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file wraps the Http4 API protocol
	 */

	function api_hash4($string) {
		return hash('sha512', $string);
	}

	function create_user_session4() {
		log_error('in create_user_session4');
		$sessionID = '';
		atomic('../cache/lockfile', function() use (&$sessionID) {
			do {
				$sessionID = substr(unique_id(), 0, AppSettings['SessionIDLength']);
			} while (session_exists($sessionID));
			user_id($sessionID);
			save_user_session([
				'SessionAcceptHash' => md5(server('HTTP_ACCEPT')),
				'SessionAgentHash' => md5(server('HTTP_USER_AGENT')),
				'SessionGeoHash' => md5(geoip()),
				'SessionID' => $sessionID,
				'SessionIP' => get_ip(),
				'SessionKey' => user_key(),
				'SessionLastActivityTime' => time(),
				'SessionTime' => (int)input('Time'),
				'SessionValid' => true,
			]);
		});

		return $sessionID;
	}

	function fail4($error, $data = []) {
		if (input('ID') === null) {
			$data = (array)$data;
			$data['ID'] = user_id();
		}
		fail1($error, $data);
	}

	function get_permissions4() {
		return get_user_session()->AccountPermission ?? [];
	}

	function get_user_session4($session_id = null) {
		if ($session_id === null) $session_id = user_id();
		$normalized = str_replace(']"', ']', str_replace('"[', '[', str_replace('}"', '}', str_replace('"{', '{', str_replace('\\"', '"', get_session_store()->read($session_id))))));
		$session = json_decode($normalized);
		log_error('get_user_session4', $session);
		return $session;
	}

	function get_user_sessions4() {
		$ret = [];
		foreach (get_session_store_client()->keys('*') as $session_id) {
			$ret[$session_id] = get_user_session(substr($session_id, strlen(AppSettings['SessionKeyPrefix'])));
		}
		return $ret;
	}

	function login4() {
		# This call assumes a few things:
		# a) An api_login() function is defined in (any) file in the lib_api directory
		# b) The api_login() should call $login = Login::model() to get the user-provided login credentials
		# c) A Login model exists in the models directory, containing the fields that the user must provide to login
		# d) api_login() must check if the user exists in the database
		# e) If user does not exist, api_login() should call fail(APIError::Login)
		# f) If user does exist, api_login() must call save_user_session(array_merge(get_user_session(), <object with more user details, now that they are logged in>)) and success(<object with a pick()'ed subset of user details to send to the browser>)

		# That being said - you do not need to use the login(), login4() or api_login() functions in PHPCentauri
		# Just don't call login() anywhere in your API, then this method will never be called
		# The way that PHPCentauri decides whether a user is logged in or not, isn't whether this method was called, but rather if if the following expression is not falsy: ((array)get_user_session())[AppSettings['SessionUserIDPropertyName']
		# So as long as you have a save_user_session() somewhre in your login logic/pipeline, that calls save_user_session($array) with a truthy value for $array[AppSettings['SessionUserIDPropertyName']], then you are logged in, as far as PHPCentauri is concerned
		# The "login" concept is just so pervasive in APIs, that the author of the PHPCentauri API felt it was necessary to codify knowledge of "logged-in'edness" into the API, which can be implemented in any way, as long as the "convention over configuration" (with $array[AppSettings['SessionUserIDPropertyName']] being truthy) is adhered to

		# The way that the PHPCentauri API author implements login logic, is:
		# - Ensure PCE is setup in the web server configuration for the project
		# - Ensure JSCentauri is loaded into the website frontend for the project
		# - Create a UserController class in api/controllers/user.php
		# - Add a method called login() into the UserController class (see the scaffold/controllers/user.php file for an example)
		# - Call the method using JSCentauri in the browser, e.g. api.controller('user/login', { Email: emailAddress, Password: password }, () => alert('Logged in!'), () => alert('Not logged in!'));
		# - Just watch out for case-senitivity issues if you are on a Unix-based system. There shouldn't be any issues on a Windows system

		api_login();
	}

	function logout4($session_id = null) {
		if ($session_id === null) $session_id = user_id();
		get_session_store()->destroy($session_id);
	}

	function save_user_session4($session, $session_id = null) {
		log_error('save_user_session4');
		if ($session_id === null) $session_id = user_id();
		return get_session_store()->write($session_id, json_encode($session));
	}

	function session_exists4($session_id) {
		log_error('session_exists4');
		$session = get_session_store()->read($session_id);
		return !!$session;
	}

	function success4($data = []) {
		if (input('ID') === null) {
			$data = is_array($data) || is_object($data) ? (array)$data : ['Data' => $data];
			$data['ID'] = user_id();
		}
		$json = json_encode($data);
		$str = json_encode((object)['J' => $json, 'H' => api_hash(user_key() . $json)]);
		log_error('success4', 'to hash', user_key() . $json, 'hash', api_hash(user_key() . $json), 'response', $str);
		die($str);
	}

	# user_id() should be session_id(), but of course PHP already uses session_id() for itself
	function user_id4($newValue = null, $force = false) {
		log_error('in user_id4');
		static $value = null;
		if ($newValue !== null || $force) $value = $newValue;
		elseif ($value === null)
			# In Http4, we create a basic user session if one doesn't exist yet. In other words, the user doesn't have to log in to have a session,
			# a session is created on their first request, if the request doesn't contain a session ID. Note that even if a user has a session, it
			# doesn't necessarily mean they are logged in yet. After they have logged in, their session data can be amended with further details
			$value = input('ID') ?? create_user_session();
		return $value;
	}

	# user_key() should be session_key(), but since session_id() is user_id(), we keep the name symmetric as user_key()
	function user_key4($newValue = null, $force = false) {
		static $value = null;
		if ($newValue !== null || $force) $value = $newValue;
		elseif ($value === null)
			$value = get_user_session()->SessionKey ?? input('Key');
		return $value;
	}

	function verify_request4($verify_logged_in = false) {
		log_error('in verify_request4');

		if (isset(AppSettings['VerifyRequest']) && !AppSettings['VerifyRequest']) {
			return;
		}

		# Check client abuse
		$ip_monitor = get_system_cache(AppSettings['AbuseWindowTime']);
		$ip_ban_list = get_system_cache(AppSettings['AbuseBanTime']);
		$ip = get_ip();
		$ip_data = (object)json_decode($ip_monitor->read("ip-monitor-$ip"));
		if (!$ip_data) {
			$ip_monitor->write("ip-monitor-$ip", json_encode(['attempts' => 1]));
		} else {
			$ip_data->attempts = property_exists($ip_data, 'attempts') ? ++$ip_data->attempts : 1;
			$ip_monitor->write("ip-monitor-$ip", json_encode($ip_data));
			if ($ip_data->attempts > AppSettings['AbuseWindowAttempts']) {
				$ip_ban_list->write("ip-ban-$ip", '1');
			}
		}

		log_error('verify_request4', 'ban status =', $ip_ban_list->read("ip-ban-$ip"));
		if ($ip_ban_list->read("ip-ban-$ip")) {
			log_error('verify_request4 error', 'Busy', 'Abuse detected');
			fail(APIError::Busy);
		}

		# Only allow POST requests, not GET, PUT, etc.
		if (server('REQUEST_METHOD') !== 'POST') {
			log_error('verify_request4 invalid method');
			fail(APIError::Method);
		}

		# Discard POST input > 50KB
		$input = json_encode($_POST);
		if (!$input || strlen($input) > AppSettings['MaxPostSize']) {
			log_error('verify_request4 input empty or too large');
			fail(APIError::Input);
		}

		# Only allow HTTPS protocol, not HTTP
		if ((AppSettings['RequireHTTPS'] ?? false) && server('HTTPS') !== 'on') {
			log_error('verify_request4 only https allowed');
			fail(APIError::HTTPS);
		}

		# Error - session timeout
		user_id(); # generate new session ID if needed
		if (!logged_in()) {
			log_error('verify_request4 not logged in');
			fail(APIError::Session);
		}

		# Current server time
		$time = time_ms();

		# Collect input
		$sessionAccept = server('HTTP_ACCEPT');
		$sessionAgent = server('HTTP_USER_AGENT');
		$sessionID = user_id();
		$sessionKey = user_key();
		$sessionTime = (int)input('Time');
		$sessionJson = raw_input('J');
		$sessionHash = raw_input('H');
		$sessionIP = get_ip();

		log_error('verify_request4', 'key', $sessionKey, 'session json', $sessionJson, 'got hash', $sessionHash, 'expected hash', api_hash("$sessionKey$sessionJson"));

		# Check input hash (client verification)
		if ($sessionHash != api_hash("$sessionKey$sessionJson")) {
			log_error('verify_request4 error', 'session id = ', $sessionID, '$sessionHash != api_hash("$sessionKey$sessionJson")');
			fail(APIError::Hash);
		}

		# Check session ID format is valid
		if ($sessionID !== null && !preg_match(sprintf('/[a-zA-Z0-9]{%s}/', AppSettings['SessionIDLength']), $sessionID))  {
			log_error('verify_request4 error', 'session id = ', $sessionID, '$sessionID !== null && !preg_match(sprintf(\'/[a-zA-Z0-9]{%s}/\', AppSettings[\'SessionIDLength\']), $sessionID)');
			fail(APIError::ID);
		}

		$timeDiff = $sessionTime - $time;

		# Check late-arriving requests
		if ((AppSettings['VerifyRequestTime'] ?? true)) {
			if ($timeDiff < -20000 || $timeDiff > 10000) {
				logout();
				log_error('verify_request4 error', 'session id = ', $sessionID, '$timeDiff < -20000 || $timeDiff > 10000', 'sessionTime', $sessionTime, 'time', $time, 'timeDiff', $timeDiff);
				fail('Time', ['Time' => $time]);
			}
			if ($sessionTime < $time - (10 * 3600 * 1000)) {
				log_error('verify_request4 error', 'session id = ', $sessionID, '$sessionTime < $time - (10 * 3600 * 1000)', 'sessionTime', $sessionTime, 'time', $time);
				logout();
				fail(APIError::Timeout);
			}
		}

		$session = get_user_session();

		if (!$session || !is_object($session)) {
			log_error('verify_request4 error', 'Nonexist', '!$session || !is_object($session)');
			fail(APIError::Nonexist);
		}

		log_error('verify_request4 session', $session);

		if ($verify_logged_in) {
			# Check if session invalidated
			if (!$session->SessionValid ?? true) {
				log_error('verify_request4 error', 'Session', 'session id = ', $sessionID, '!$session->SessionValid');
				logout();
				fail(APIError::Session);
			}

			if (!array_key_exists(AppSettings['SessionUserIDPropertyName'], (array)$session)) {
				log_error('verify_request4 error', 'Lost', 'session id = ', $sessionID, '!$session->' . AppSettings['SessionUserIDPropertyName']);
				logout();
				fail(APIError::Lost);
			}

			# Check if session expired
			if ($session->SessionLastActivityTime < time() - AppSettings['SessionTTLInSeconds']) {
				log_error('verify_request4 error', 'Session', '$session->SessionLastActivityTime < time() - AppSettings[\'SessionTTLInSeconds\']');
				logout();
				fail(APIError::Session);
			}
		}

		# Check session hijacking
		/*
		if ($session->SessionAcceptHash !== md5($sessionAccept)) {
			log_error('verify_request4 error', 'Session', 'session hijack - accept', '$session->SessionAcceptHash !== md5($sessionAccept)');
			logout();
			fail(APIError::Hijack);
		}
		*/

		if ($session->SessionAgentHash !== md5($sessionAgent)) {
			log_error('verify_request4 error', 'session id = ', $sessionID, '$session->SessionAgent !== md5($sessionAgent)', '$session->SessionAgent = ', $session->SessionAgent, '$sessionAgent = ', $sessionAgent, 'md5($sessionAgent) =', md5($sessionAgent), $_SERVER);
			logout();
			fail(APIError::Hijack);
		}

		if ($session->SessionIP !== $sessionIP) {
			log_error('verify_request4 ip change', '$session->SessionIP !== $sessionIP');
			if ($session->SessionGeoHash !== md5(geoip())) {
				log_error('verify_request4 error', 'session id = ', $sessionID, '$session->SessionGeoHash !== md5(geoip())');
				logout();
				fail(APIError::Hijack);
			}
			$session->SessionIP = $sessionIP;
		}

		# Update session
		$session->SessionTime = $sessionTime;
		$session->SessionLastActivityTime = time();
		save_user_session($session);
	}
?>