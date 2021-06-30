<?php
	/**
	 * @package PHPCentauri
	 *
	 * Stub proxy functions to delegate to correct Http API protocol functions
	 * Supports PHPCentauri Http 1-4 API protocols
	 */

	interface IAPIError {
		const Address = 'Address';
		const Busy = 'Busy';
		const Database = 'Database';
		const Duplicate = 'Duplicate';
		const ErrorMessage = 'ErrorMessage'; # Custom error message
		const Format = 'Format';
		const Gibberish = 'Gibberish';
		const HTTPS = 'HTTPS';
		const Hash = 'Hash';
		const Hijack = 'Hijack';
		const ID = 'ID';
		const Input = 'Input';
		const Invalid = 'Invalid';
		const Login = 'Login';
		const Lost = 'Lost';
		const Method = 'Method';
		const Nonexist = 'Nonexist';
		const Null = 'Null';
		const Old = 'Old';
		const OutputAmount = 'OutputAmount';
		const OutputInvalid = 'OutputInvalid';
		const OutputMany = 'OutputMany';
		const PIN = 'PIN';
		const Permission = 'Permission';
		const Platform = 'Platform';
		const Session = 'Session';
		const Time = 'Time';
		const Timeout = 'Timeout';
		const Unknown = 'Unknown';
		const Using = 'Using';
	}

	function api_hash($str) {
		return http_api_func('api_hash', func_get_args());
	}

	function collect() {
		return http_api_func('collect', func_get_args());
	}

	function cors($origins = ['*'], $methods = ['GET', 'POST'], $headers = ['Content-Type']) {
		# header('Access-Control-Allow-Headers: Content-Type');
		# header('Access-Control-Allow-Methods: CONNECT, DELETE, GET, HEAD, OPTIONS, PATCH, POST, PUT, TRACE');
		# header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
		header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
		header('Access-Control-Allow-Origin: ' . implode(', ', $origins));
		header('Access-Control-Max-Age: 86400');
		if (!in_array($_SERVER['REQUEST_METHOD'], $methods))
			die;
	}

	function create_user_session() {
		return http_api_func('create_user_session', func_get_args());
	}

	function fail($error, $data = []) {
		return http_api_func('fail', func_get_args());
	}

	function fail_message($message) {
		return http_api_func('fail_message', func_get_args());
	}

	function get_ip() {
		return filter_var(explode(',', server('HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR', 'REMOTE_HOST', 'HTTP_CLIENT_IP'))[0], FILTER_VALIDATE_IP);
	}

	function get_permissions() {
		return http_api_func('get_permissions', func_get_args());
	}

	function get_referer() {
		$referer = server('HTTP_HOST', 'HTTP_REFERER');
		$https = server('HTTPS');
		$protocol = (!empty($https) && $https !== 'off') || server('SERVER_PORT') == 443 ? 'https://' : 'http://';
		if (empty($referer)) $referer = AppSettings['ApiUrl'];
		return (!preg_match('/https?:\/\//i', $referer) ? $protocol : '') . $referer;
	}

	function get_response_mime_type() {
		$a = explode(',', $_SERVER['HTTP_ACCEPT']);
		if (isset($a[0])) {
			preg_match('/\/([a-z]+)/i', $a[0], $matches);
			return $matches[1];
		}
		return 'html';
	}

	function get_user_session($session_id = null) {
		return http_api_func('get_user_session', func_get_args());
	}

	function get_user_sessions() {
		return http_api_func('get_user_sessions', func_get_args());
	}

	function go($url) {
		header("Location: $url");
		die;
	}

	function has_permission($permissions) {
		return validate_object(get_permissions(), $permissions, true) === true;
	}

	function http_api_func($func, $args) {
		$protocol = defined('AppSettings') && isset(AppSettings['HttpApiProtocol']) && AppSettings['HttpApiProtocol'] ? AppSettings['HttpApiProtocol'] : 1;
		$f = "$func$protocol";
		if (function_exists($f))
			return $f(...$args);
		else
			for ($i = 1; $i < $protocol; ++$i) {
				$f = "$func$i";
				if (function_exists($f))
					return $f(...$args);
			}
	}

	function http_cache_custom($last_modified, $cache_seconds) {
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $last_modified) {
			header('HTTP/1.1 304 Not Modified');
			die();
		}
		header("Cache-Control: max-age=$cache_seconds");
		header('Expires: ' . gmdate(DATE_RFC1123, time() + $cache_seconds));
		header('Last-Modified: ' . gmdate(DATE_RFC1123, $last_modified));
	}

	function http_cache_none() {
		header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0, s-maxage=0');
		header('Cache-Control: pre-check=0, post-check=0', false);
		header('Expires: 0');
		header('Pragma: no-cache, private');
	}

	function http_cache_permanent() {
		http_cache_custom(time(), 315360000); # cache for 10 years
	}

	function http_download($filename, $data) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header("Content-Disposition: attachment; filename=\"$filename\"");
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($data));
		die($data);
	}

	function input($name) {
		return http_api_func('input', func_get_args());
	}

	function input_exists($name) {
		return http_api_func('input_exists', func_get_args());
	}

	function input_payload() {
		return http_api_func('input_payload', func_get_args());
	}

	function logged_in() {
		return http_api_func('logged_in', func_get_args());
	}

	function login($session_id = null) {
		return http_api_func('login', func_get_args());
	}

	function logout($session_id = null) {
		return http_api_func('logout', func_get_args());
	}

	function model($model_class_name, $fail_if_input_invalid = true) {
		$definition = $model_class_name::definition();
		if (is_array($definition)) {
			$model = collect(...array_keys($definition));
		} else {
			$model = input_payload();
		}
		$valid = validate_object($model, $definition, true);
		if ($valid !== true && $fail_if_input_invalid) {
			log_error('model failed');
			fail($valid[0], ['For' => $valid[1]]);
		}
		if (is_array($model) && !count($model)) {
			log_error('model empty');
			return null;
		}
		return is_array($model) ? (object) $model : $model;
	}

	function output($str) {
		return http_api_func('output', func_get_args());
	}

	function permission($permissions) {
		return http_api_func('permission', func_get_args());
	}

	function raw_input($name) {
		#static $input = null;
		#if ($input === null)
		#	$input = json_decode(raw_post_input());
		#return $input->$name;
		return $_POST[$name] ?? null;
	}

	function raw_input_exists($name) {
		return isset($_POST[$name]);
	}

	function raw_post_input() {
		static $input = null;
		if ($input === null)
			$input = file_get_contents('php://input');
		return $input;
	}

	function rsa_decrypt($rsaPublicKeyEncryptedAndBase64Encoded_Payload) {
		return openssl_private_decrypt(base64_decode($rsaPublicKeyEncryptedAndBase64Encoded_Payload), $temp, file_get_contents(AppSettings['RsaPrivateKey'])) ? $temp : null;
	}

	function save_user_session($session, $session_id = null) {
		return http_api_func('save_user_session', func_get_args());
	}

	function server() {
		foreach (func_get_args() as $arg)
			if ($arg && ($_SERVER[$arg] ?? false))
				return $_SERVER[$arg];
	}

	# true if user session exists locally
	# if false, then it might exist remotely, or it might not, it is impossible to say without fetching it from e.g. Azure in that case
	function session_exists($session_id) {
		return http_api_func('session_exists', func_get_args());
	}

	function success($obj = []) {
		return http_api_func('success', func_get_args());
	}

	# user_id() should be session_id(), but of course PHP already uses session_id() for itself
	function user_id($newValue = null, $force = false) {
		return http_api_func('user_id', func_get_args());
	}

	# user_key() should be session_key(), but since session_id() is user_id(), we keep the name symmetric as user_key()
	function user_key($newValue = null, $force = false) {
		return http_api_func('user_key', func_get_args());
	}

	function validate_user_id($userID) {
		return preg_match(sprintf('/[a-zA-Z0-9]{%s}/', AppSettings['SessionIDLength']), $userID);
	}

	function verify_request() {
		return http_api_func('verify_request', func_get_args());
	}
?>
