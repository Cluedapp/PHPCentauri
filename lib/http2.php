<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file wraps the Http2 API protocol
	 */

	function collect2() {
		return array_reduce(func_get_args(), function($carry, $item) {
			if (input_exists($item)) {
				$carry[$item] = input($item);
			}
			return $carry;
		}, []);
	}

	function fail2($error, $data = []) {
		if (is_string($error))
			$obj = array_merge($data, ['Error' => $error]);
		else
			$obj = $error;
		if (user_key())
			$str = trim((new AesCtr)->encrypt(json_encode($obj), user_key(), 256));
		else
			$str = 'Error';
		die($str);
	}

	function get_user_session2($session_id = null) {
		if ($session_id === null) $session_id = user_id();
		if (session_exists($session_id)) {
			$normalized = str_replace(']"', ']', str_replace('"[', '[', str_replace('}"', '}', str_replace('"{', '{', str_replace('\\"', '"', get_session_store()->read($session_id))))));
			log_error('get_user_session2 normalized', $session_id, $normalized);
			$session = json_decode($normalized);
			log_error('get_user_session2 json decoded', $session_id, $session);
		} elseif (!empty($session_id)) {
			require_once '../entities/visitorSession.php';
			$session = VisitorSession::get(['VisitorSessionID' => $session_id]);
			if ($session) save_user_session($session, $session_id);
			log_error('fetched user session from db', $session_id, $session);
		} else
			$session = null;
		return $session;
	}

	# fetch local sessions only, there may exist others remotely
	function get_user_sessions2() {
		$ret = [];
		foreach (redis_session_client()->keys('*') as $session_id) {
			$ret[$session_id] = get_user_session(substr($session_id, strlen(AppSettings['SessionKeyPrefix'])));
		}
		return $ret;
	}

	function input2($name) {
		return input_exists($name) ? input_payload()->$name : null;
	}

	function input_exists2($name) {
		$json = input_payload();
		return isset($json->$name);
	}

	function input_payload2() {
		static $json = null;
		if ($json === null && strlen(raw_post_input()) > AppSettings['SessionIDLength']) {
			log_error('input_payload2 - got POST input', raw_post_input());
			$temp = (new AesCtr)->decrypt(substr(raw_post_input(), AppSettings['SessionIDLength']), user_key(), 256);
			log_error('input_payload2 - got decrypted payload', print_r($temp, true));
			$temp = json_decode($temp);
			log_error('input_payload2 - got decrypted payload object', $temp);
			if ($temp !== false) $json = $temp;
		}
		return $json;
	}

	function logged_in2() {
		return !!get_user_session();
	}

	function save_user_session2($session, $session_id = null) {
		if ($session_id === null) $session_id = user_id();
		if (empty($session_id)) {
			log_error('save_user_session2, empty session id, cannot save');
			return false;
		} else {
			log_error('save_user_session2 writing session', $session_id, json_encode($session));
			return get_session_store()->write($session_id, json_encode($session));
		}
	}

	function success2($data = []) {
		die(trim((new AesCtr)->encrypt(json_encode($data), user_key(), 256)));
	}

	# user_id() should be session_id(), but of course PHP already uses session_id() for itself
	function user_id2($newValue = null, $force = false) {
		static $value = null;
		if ($newValue !== null || $force) $value = $newValue;
		elseif ($value === null) $value = substr(raw_post_input(), 0, AppSettings['SessionIDLength']);
		return $value;
	}

	# user_key() should be session_key(), but since session_id() is user_id(), we keep the name symmetric as user_key()
	function user_key2($newValue = null, $force = false) {
		static $value = null;
		if ($newValue !== null || $force) $value = $newValue;
		elseif ($value === null) {
			$session = get_user_session();
			if ($session) $value = $session->VisitorSessionKey;
		}
		log_error('user_key2 returns key', $value);
		return $value;
	}

	function verify_request2($verify_session = true) {
		if (isset(AppSettings['VerifyRequest']) && !AppSettings['VerifyRequest']) {
			return;
		}

		# Check client abuse
		$ip_monitor = new Predis\Session\Handler(new Predis\Client(AppSettings['RedisConnectionString'], ['prefix' => 'ip-abuse-window:']), ['gc_maxlifetime' => AppSettings['AbuseWindowTime']]);
		$ip_ban_list = new Predis\Session\Handler(new Predis\Client(AppSettings['RedisConnectionString'], ['prefix' => 'ip-ban-list:']), ['gc_maxlifetime' => AppSettings['AbuseBanTime']]);
		$ip = get_ip();
		$ip_data = (object)json_decode($ip_monitor->read($ip));
		if ($ip_data === false) {
			$ip_monitor->write(json_encode(['attempts' => 1]));
		} else {
			$ip_data->attempts = property_exists($ip_data, 'attempts') ? ++$ip_data->attempts : 1;
			$ip_monitor->write($ip, json_encode($ip_data));
			if ($ip_data->attempts > AppSettings['AbuseWindowAttempts']) {
				$ip_ban_list->write($ip, '1');
			}
		}
		log_error('verify_request2', 'ban status =', $ip_ban_list->read($ip));
		if ($ip_ban_list->read($ip)) {
			log_error('verify_request2 error', 'Busy', 'Abuse detected');
			fail(APIError::Busy);
		}

		# Only allow POST requests, not GET, PUT, etc.
		if (server('REQUEST_METHOD') !== 'POST') {
			log_error('verify_request2 error', 'Method');
			fail(APIError::Method);
		}

		# Discard POST input > 50KB
		$input = raw_post_input();
		if (strlen($input) > AppSettings['MaxPostSize']) {
			log_error('verify_request2 error', 'Gibberish');
			fail(APIError::Gibberish);
		}

		if ($verify_session) {
			$sessionID = user_id();

			# Check session ID (user ID) format is valid
			if (!validate_user_id($sessionID))  {
				log_error('verify_request2 error', 'ID', '!validate_user_id($sessionID)');
				fail(APIError::ID);
			}

			# Check session exists
			if (!logged_in()) {
				log_error('verify_request2 error', 'Session', '!logged_in()');
				fail(APIError::Session);
			}

			$session = get_user_session();

			# Check if session expired
			if ($session->VisitorSessionLastActivity < time() - AppSettings['SessionTTLInSeconds']) {
				log_error('verify_request2 error', 'Session', '$session->VisitorSessionLastActivity < time() - AppSettings[\'SessionTTLInSeconds\']');
				fail(APIError::Session);
			}

			# Check if session invalidated
			if (!$session->SessionValid) {
				log_error('verify_request2 error', 'session id = ', $sessionID, '!$session->SessionValid');
				fail(APIError::Session);
			}

			# Check session hijacking
			if ($session->VisitorSessionAgentHash !== md5(server('HTTP_USER_AGENT'))) {
				log_error('verify_request2 error', 'Session', 'session hijack - user agent', $session->VisitorSessionAgentHash, '!=', md5(server('HTTP_USER_AGENT')));
				fail(APIError::Session);
			}
			if ($session->VisitorSessionAcceptHash !== md5(server('HTTP_ACCEPT'))) {
				log_error('verify_request2 error', 'Session', 'session hijack - accept');
				fail(APIError::Session);
			}
			/*
			if ($session->VisitorSessionAcceptHash !== md5(array_reduce(multisort(array_values(array_filter(array_keys($_SERVER), function($header) { return preg_match('/^HTTP_ACCEPT(_|$)/', $header); })), 0), function($carry, $item) { return "$carry{$_SERVER[$item]}"; }, ''))) {
				log_error('verify_request2 error', 'Session', 'session hijack - accept');
				fail(APIError::Session);
			}
			*/

			# Check same API version
			if ($session->VisitorSessionAPIVersion != APIVersion) {
				log_error('verify_request2 error', 'Old');
				fail(APIError::Old);
			}

			# Check valid input JSON data
			if (strlen(raw_post_input()) > AppSettings['SessionIDLength'] && input_payload() === null) {
				log_error('verify_request2 error', 'Gibberish');
				fail(APIError::Gibberish);
			}

			# Update session
			$session->VisitorSessionLastActivity = time();
			save_user_session($session);
		}
	}
?>
