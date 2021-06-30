<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file wraps the Http3 API protocol
	 * Used by the Cluedapp Node API (OpenSSL encrypt/decrypt)
	 * A node that implements this protocol for inter-communication with other nodes, shall be called an http3 node
	 */

	function collect3() {
		return array_reduce(func_get_args(), function($carry, $item) {
			if (input_exists($item))
				$carry[$item] = input($item);
			return $carry;
		}, []);
	}

	function fail3($error, $data = []) {
		if (is_string($error))
			$obj = array_merge($data, ['Error' => $error]);
		else
			$obj = $error;
		$str = encrypt(json_encode($obj), get_http3_key());
		die($str);
	}

	function get_http3_key($for_url = null, $return_path = false) {
		# Get the correct key for a known site (URL), or else, use the incoming key configured for this system
		$path = null;
		if ($for_url)
			if (AppSettings['Http3Keys'] ?? false)
				foreach (AppSettings['Http3Keys'] as $url => $_path) {
					$min = min(strlen($url), strlen($for_url));
					if (substr($for_url, 0, $min) == substr($url, 0, $min)) {
						# This is a known site, so use the key for outgoing encryption for that site
						$path = $_path;
						break;
					}
				}

		# Use the key for incoming encryption, configured for this system
		if ($path == null)
			$path = AppSettings['Http3Key'];

		return $return_path ? $path : file_get_contents($path);
	}

	function input3($name) {
		return input_exists($name) ? input_payload()->$name : null;
	}

	function input_exists3($name) {
		$json = input_payload();
		return isset($json->$name);
	}

	function input_payload3() {
		static $json = null;
		if ($json === null) {
			$temp = json_decode(decrypt(raw_post_input(), get_http3_key()));
			if (is_object($temp))
				$json = $temp;
		}
		return $json;
	}

	# Do an encrypted request to an "http3" node (over HTTP, not HTTPS) and return decrypted response object/array
	function request3($url, $data, $key_path = null) {
		log_error('request3', 'url', $url, 'data', $data, 'input key path', $key_path);
		$key = $key_path ? file_get_contents($key_path) : get_http3_key($url);
        $result = curl_post_data_sync($url, encrypt(json_encode($data), $key));
		log_error('request3 - got curl result', $result);
        return $result->status == 200 ? (array)json_decode(decrypt(trim($result->data), $key)) : null;
	}

	function success3($data = []) {
		$str = encrypt(json_encode($data), get_http3_key());
		die($str);
	}
?>
