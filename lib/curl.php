<?php
	/**
	 * @package PHPCentauri
	 *
	 * Required PHP extensions:
	 *    php_curl
	 *
	 * List of all cURL options: http://www.nusphere.com/kb/phpmanual/function.curl-setopt.htm
	 *
	 * $headers format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value']
	 */

	function curl_get_async($url, $headers = []) {
		# Do "asynchronous" request (because timeout = 1ms)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_FOLLOWLOCATION => 1,				# Follow the redirects (needed for mod_rewrite)
			CURLOPT_FRESH_CONNECT => 1,					# Always ensure the connection is fresh
			CURLOPT_HEADER => 0,						# Don't return headers
			CURLOPT_HTTPHEADER => $headers,				# Add custom HTTP headers (format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value'])
			CURLOPT_NOBODY => 1,						# Don't return the body
			CURLOPT_POSTREDIR => 3,						# Follow HTTP 301 and 302 redirects
			CURLOPT_RETURNTRANSFER => 1,				# Return from curl_exec rather than echoing
			CURLOPT_TIMEOUT => 1,						# Timeout super fast once connected, so it goes into async
			CURLOPT_URL => $url							# Set URL
		]);
		curl_exec($ch);
	}

	function curl_get_sync($url, $headers = []) {
		# Do "synchronous" request (because timeout is normal)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_FOLLOWLOCATION => 1,				# Follow the redirects (needed for mod_rewrite)
			CURLOPT_FRESH_CONNECT => 1,					# Always ensure the connection is fresh
			CURLOPT_HEADER => 1,						# Do return headers
			CURLOPT_HTTPHEADER => $headers,				# Add custom HTTP headers (format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value'])
			CURLOPT_NOBODY => 0,						# Don't return the body
			CURLOPT_POSTREDIR => 3,						# Follow HTTP 301 and 302 redirects
			CURLOPT_RETURNTRANSFER => 1,				# Return from curl_exec rather than echoing
			CURLOPT_URL => $url							# Set URL
		]);
		$data = curl_exec($ch);
		return (object)[
			'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'headers' => substr($data, 0, strpos($data, "\r\n\r\n")),
			'data' => substr($data, strpos($data, "\r\n\r\n") + 4)
		];
	}

	function curl_get_fields_sync($url, $fields = [], $headers = []) {
		if (!empty($fields) && !empty(json_encode($fields))) {
			$query = http_build_query($fields);
			$url = $url . ($query ? ((strpos($url, '?') === false ? '?' : '&') . $query) : '');
		}
		return curl_get_sync($url, $headers);
	}

	function curl_get_sync_ignore_ssl($url, $headers = []) {
		# Do "synchronous" request (because timeout is normal)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_FOLLOWLOCATION => 1,				# Follow the redirects (needed for mod_rewrite)
			CURLOPT_FRESH_CONNECT => 1,					# Always ensure the connection is fresh
			CURLOPT_HEADER => 1,						# Do return headers
			CURLOPT_HTTPHEADER => $headers,				# Add custom HTTP headers (format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value'])
			CURLOPT_NOBODY => 0,						# Don't return the body
			CURLOPT_POSTREDIR => 3,						# Follow HTTP 301 and 302 redirects
			CURLOPT_RETURNTRANSFER => 1,				# Return from curl_exec rather than echoing
			CURLOPT_SSL_VERIFYHOST => 0,				# Ignore invalid SSL
			CURLOPT_SSL_VERIFYPEER => 0,				# Ignore invalid SSL
			CURLOPT_URL	 => $url						# Set URL
		]);
		$data = curl_exec($ch);
		return (object)[
			'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'headers' => substr($data, 0, strpos($data, "\r\n\r\n")),
			'data' => substr($data, strpos("\r\n\r\n") + 4)
		];
	}

	function curl_post_data_async($url, $data, $headers = []) {
		# Do "asynchronous" request (because timeout = 1ms)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_FOLLOWLOCATION => 1,				# Follow the redirects (needed for mod_rewrite)
			CURLOPT_FRESH_CONNECT => 1,					# Always ensure the connection is fresh
			CURLOPT_HEADER => 0,						# Don't return headers
			CURLOPT_HTTPHEADER => $headers,				# Add custom HTTP headers (format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value'])
			CURLOPT_NOBODY => 0,						# Return the body
			CURLOPT_POST => 1,							# Enable POST (not GET)
			CURLOPT_POSTFIELDS => $data,				# Set POST data
			CURLOPT_POSTREDIR => 3,						# Follow HTTP 301 and 302 redirects
			CURLOPT_RETURNTRANSFER => 1,				# Return from curl_exec rather than echoing
			CURLOPT_TIMEOUT => 1,						# Timeout super fast once connected, so it goes into async
			CURLOPT_URL => $url							# Set URL
		]);
		curl_exec($ch);
	}

	function curl_post_data_sync($url, $data, $headers = []) {
		# Do "synchronous" request (because timeout is normal)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_FOLLOWLOCATION => 1,				# Follow the redirects (needed for mod_rewrite)
			CURLOPT_FRESH_CONNECT => 1,					# Always ensure the connection is fresh
			CURLOPT_HEADER => 1,						# Do return headers
			CURLOPT_HTTPHEADER => $headers,				# Add custom HTTP headers (format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value'])
			CURLOPT_NOBODY => 0,						# Return the body
			CURLOPT_POST => 1,							# Enable POST (not GET)
			CURLOPT_POSTFIELDS => $data,				# Set POST data
			CURLOPT_POSTREDIR => 3,						# Follow HTTP 301 and 302 redirects
			CURLOPT_RETURNTRANSFER => 1,				# Return from curl_exec rather than echoing
			CURLOPT_URL => $url							# Set URL
		]);
		$data = curl_exec($ch);
		return (object)[
			'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'headers' => substr($data, 0, strpos($data, "\r\n\r\n")),
			'data' => substr($data, strpos($data, "\r\n\r\n") + 4)
		];
	}

	function curl_post_fields_async($url, $fields, $headers = []) {
		# Do "asynchronous" request (because timeout = 1ms)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_FOLLOWLOCATION => 1,				# Follow the redirects (needed for mod_rewrite)
			CURLOPT_FRESH_CONNECT => 1,					# Always ensure the connection is fresh
			CURLOPT_HEADER => 0,						# Don't return headers
			CURLOPT_HTTPHEADER => $headers,				# Add custom HTTP headers (format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value'])
			CURLOPT_NOBODY => 0,						# Return the body
			CURLOPT_POST => 1,							# Enable POST (not GET)
			CURLOPT_POSTFIELDS =>						# Set POST data
				http_build_query($fields),
			CURLOPT_POSTREDIR => 3,						# Follow HTTP 301 and 302 redirects
			CURLOPT_RETURNTRANSFER => 1,				# Return from curl_exec rather than echoing
			CURLOPT_TIMEOUT => 1,						# Timeout super fast once connected, so it goes into async
			CURLOPT_URL => $url							# Set URL
		]);
		curl_exec($ch);
	}

	function curl_post_fields_sync($url, $fields, $headers = []) {
		# Do "synchronous" request (because timeout is normal)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_FOLLOWLOCATION => 1,				# Follow the redirects (needed for mod_rewrite)
			CURLOPT_FRESH_CONNECT => 1,					# Always ensure the connection is fresh
			CURLOPT_HEADER => 1,						# Do return headers
			CURLOPT_HTTPHEADER => $headers,				# Add custom HTTP headers (format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value'])
			CURLOPT_NOBODY => 0,						# Return the body
			CURLOPT_POST => 1,							# Enable POST (not GET)
			CURLOPT_POSTFIELDS =>						# Set POST data
				http_build_query($fields),
			CURLOPT_POSTREDIR => 3,						# Follow HTTP 301 and 302 redirects
			CURLOPT_RETURNTRANSFER => 1,				# Return from curl_exec rather than echoing
			CURLOPT_URL => $url							# Set URL
		]);
		$data = curl_exec($ch);
		return (object)[
			'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'headers' => substr($data, 0, strpos($data, "\r\n\r\n")),
			'data' => substr($data, strpos($data, "\r\n\r\n") + 4)
		];
	}

	# Do a custom method request
	# Set $method = 'X', where X in ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD']
	# If using GET method, $data must be in ['key' => 'value] format
	# Otherwise, $data should be a JSON-encoded or URL-encoded string
	function curl_request_data_sync($method, $url, $data = '', $headers = []) {
		$method = strtoupper($method);

		if ($method === 'GET')
			return curl_get_fields_sync($url, $data, $headers);

		# Do "synchronous" request (because timeout is normal)
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => $method,			# Set request method
			CURLOPT_FOLLOWLOCATION => 1,				# Follow the redirects (needed for mod_rewrite)
			CURLOPT_FRESH_CONNECT => 1,					# Always ensure the connection is fresh
			CURLOPT_HEADER => 1,						# Do return headers
			CURLOPT_HTTPHEADER => $headers,				# Add custom HTTP headers (format is ['Header-Name: Header-Value'], not ['Header-Name' => 'Header-Value'])
			CURLOPT_NOBODY => 0,						# Return the body
			CURLOPT_POSTFIELDS => $data,				# Set POST data
			CURLOPT_POSTREDIR => 3,						# Follow HTTP 301 and 302 redirects
			CURLOPT_RETURNTRANSFER => 1,				# Return from curl_exec rather than echoing
			CURLOPT_URL => $url							# Set URL
		]);
		$data = curl_exec($ch);
		return (object)[
			'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'headers' => substr($data, 0, strpos($data, "\r\n\r\n")),
			'data' => substr($data, strpos($data, "\r\n\r\n") + 4)
		];
	}

	/*
	function get_async($url) {
		try {
			(new GuzzleHttp\Client)->request('GET', $url, ['timeout' => 1, 'connect_timeout' => 1, 'read_timeout' => 1]);
		} catch (Exception $e) {
		}
	}

	function post_async($url, $data) {
		try {
			(new GuzzleHttp\Client)->request('POST', $url, ['form_params' => $data, 'timeout' => 1, 'connect_timeout' => 1, 'read_timeout' => 1]);
		} catch (Exception $e) {
		}
	}

	function post_sync($url, $data) {
		try {
			return (new GuzzleHttp\Client)->request('POST', $url, ['form_params' => $data])->getBody()->getContents();
		} catch (Exception $e) {
		}
		return null;
	}
	*/
?>
