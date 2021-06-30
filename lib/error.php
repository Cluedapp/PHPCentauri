<?php
	/**
	 * @package PHPCentauri
	 */

	function script_start_time() {
		static $time = null;
		if ($time === null)
			$time = time();
		return $time;
	}
	script_start_time();

	function make_path($path_format) {
		$dynamic_path = str_replace('{year}', date('Y'), str_replace('{month}', date('m'), str_replace('{day}', date('d'), trim($path_format))));
		$components = preg_split('#/#', $dynamic_path, -1, PREG_SPLIT_NO_EMPTY);
		$root = substr($dynamic_path, 0, 1) == '/' ? '/' : '';
		for ($i = 0; $i < count($components) - 1; ++$i) {
			$dir = $root . implode('/', array_slice($components, 0, $i + 1));
			if (!file_exists($dir)) {
				mkdir($dir);
			}
		}
		return $dynamic_path;
	}

	function __log_it($type, $msg, $is_recursive_call = false) {
		$log_count = log_count(true);
		try {
			if (defined('AppSettings') && (log_force() || (isset($GLOBALS['argv']) && is_array($GLOBALS['argv']) && in_array('debug', $GLOBALS['argv'])) || ($_REQUEST['debug'] ?? count(array_filter(AppSettings['Debug'] ?? [], function($item) { return preg_match("/$item/", strtolower(function_exists('server') ? server('PHP_SELF') : '')) || strpos(strtolower(function_exists('server') ? server('PHP_SELF') : ''), strtolower($item)) !== false; }))))) {
				if (!$is_recursive_call && (AppSettings['LogStackTrace'] ?? false)) __log_it('Trace', 'Function stack trace: ' . json_encode(array_reduce(debug_backtrace(), function($carry, $item) { $carry[] = [$item['file'] ?? 'No file', $item['line'] ?? 'No line', $item['function'] ?? 'No function']; return $carry; }, []), JSON_PRETTY_PRINT), true);
				$original_msg = $msg;
				$msg = json_encode($msg, JSON_PRETTY_PRINT);
				if ($msg === false) $msg = print_r($original_msg, true);

				# Current time string: strftime('%c');
				$msg = date('Y-m-d H:i:s ') . 'PID: ' . getmypid() . ' Script start time:' . date(' Y-m-d H:i:s ', script_start_time()) . (function_exists('server') ? server('PHP_SELF') : '') .  " $log_count $msg" . PHP_EOL;
				if (AppSettings[$type . 'LogFile'] ?? false)
					file_put_contents(make_path(AppSettings[$type . 'LogFile']), $msg, FILE_APPEND);
				elseif (AppSettings['LogFile'] ?? false)
					file_put_contents(make_path(AppSettings['LogFile']), $msg, FILE_APPEND);
				if (is_cli()) {
					static $continue_count = 0;
					static $skip_count = 0;
					$offset = 0;
					if ($skip_count <= 0 || $skip_count-- <= 0) {
						for ($offset = 0, $len = strlen(trim($msg)); $offset < $len; $offset += 1024) {
							echo trim(substr($msg, $offset, 1024)), "\n";
							if (($continue_count <= 0 || --$continue_count <= 0) && function_exists('option') && option('step')) {
								echo 'Press ENTER to continue, Sn to skip n times, SUn to skip until n, Cn to continue n times: ';
								log_readline_waiting(true);
								$readline = strtolower(trim(readline()));
								log_readline_waiting(false);
								if (strlen($readline)) {
									if ($readline[0] == 's') {
										if (strlen($readline) >= 2 && $readline[1] == 'u') {
											$skip_count = max(0, (int) substr($readline, 2) - $log_count - 1);
										} else {
											$skip_count = max(0, (int) substr($readline, 1));
										}
										if ($skip_count > 0) echo "Skipping $skip_count times", "\n";
										break;
									} elseif ($readline[0] == 'c') {
										$continue_count = (int) substr($readline, 1);
									}
								}
								echo 'Continuing', "\n";
							}
						}
					}
				}
			}
		} catch (Exception $e) {
			if (is_cli()) {
				echo $e->getMessage();
			}
		}
	}

	function log_count($increment = false) {
		static $log_count = 0;
		if ($increment)
			++$log_count;
		return $log_count;
	}

	function log_readline_waiting($waiting = null) {
		static $log_readline_waiting = false;
		if ($waiting !== null)
			$log_readline_waiting = $waiting;
		return $log_readline_waiting;
	}

	function log_debug(...$msg) {
		__log_it('Debug', $msg);
	}

	function log_error(...$msg) {
		__log_it('Error', $msg);
	}

	function log_exception(...$msg) {
		__log_it('Exception', $msg);
	}

	function log_force($force = null) {
		static $value = false;
		if ($force !== null) $value = (bool)$force;
		return $value;
	}

	function log_info(...$msg) {
		__log_it('Info', $msg);
	}

	function log_trace(...$msg) {
		__log_it('Trace', $msg);
	}

	function log_warn(...$msg) {
		__log_it('Warn', $msg);
	}

	function udp_error_log($msg) {
		try {
			if (defined('AppSettings') && (AppSettings['UDPErrorLog'] ?? false) && (AppSettings['UDPErrorLogHost'] ?? false) && (AppSettings['UDPErrorLogPort'] ?? false) && (AppSettings['UDPErrorLogMessageFormat'] ?? false) && (AppSettings['TimeDisplayOffset'] ?? false)) {
				# $ip = explode(' ', array_reverse(explode("\n", trim(`nslookup $hostname`)))[0])[1];
				$ip = gethostbyname(AppSettings['UDPErrorLogHost']);
				if ($ip) {
					$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
					$msg = preg_replace('/%s/', $msg, preg_replace('/%h/', trim(`hostname`), preg_replace('/%d/', date('Y-m-d H:i:s', time() + AppSettings['TimeDisplayOffset']), AppSettings['UDPErrorLogMessageFormat'])));
					$len = strlen($msg);
					if ($len <= 1024) {
						socket_sendto($socket, $msg, $len, 0, $ip, AppSettings['UDPErrorLogPort']);
					} else {
						$id = uniqid();
						$id_len = strlen("[$id] ");
						$chunk_len = 1024 + $id_len;
						for ($i = 0; $i < $len; $i += 1024) {
							socket_sendto($socket, "[$id] " . substr($msg, $i, 1024), $chunk_len, 0, $ip, AppSettings['UDPErrorLogPort']);
						}
					}
					socket_close($socket);
				}
			}
		}
		catch (Exception $e) {
			log_exception('udp_error_log error', print_r($e, true));
		}
	}

	set_error_handler(function($errno = 0, $errstr = '', $errfile = '', $errline = '', array $errcontext = []) {
		try {
			# Get script filename
			$script = server('PHP_SELF');
			if (!$script) {
				$script = !function_exists('is_cli') || is_cli() ? ($GLOBALS['argv'][0] ?? 'Unknown script filename') : 'Unknown script filename';
			}

			# Generate log
			$log = array_merge([
				"$script - System failure - PHPCentauri global error handler",
				"Error file: $errfile",
				"Error line: $errline",
				"Error number: $errno",
				"Error string: $errstr",
				'Current directory: ' . getcwd()
			],
				(defined('AppSettings') && (AppSettings['LogUnhandledExceptions'] ?? false))
				? [print_r($errcontext, true), 'Stack trace', debug_backtrace(0, 3)]
				: ['Enable LogUnhandledExceptions to show full stack trace']
			);

			# Submit log
			log_exception(...$log);
			udp_error_log(json_encode($log));
		}
		catch (Exception $e) {
			# The global error handler threw an exception
			if (is_cli()) {
				print_r($e);
			}
		}
		return false;
	});

	set_exception_handler(function($exception) {
		try {
			# Get script filename
			$script = server('PHP_SELF');
			if (!$script) {
				$script = !function_exists('is_cli') || is_cli() ? ($GLOBALS['argv'][0] ?? 'Unknown script filename') : 'Unknown script filename';
			}

			# Generate log
			$log = array_merge([
				"$script - System failure - PHPCentauri global exception handler",
				"Error file: {$exception->getFile()}",
				"Error line: {$exception->getLine()}",
				"Error code: {$exception->getCode()}",
				"Error message: {$exception->getMessage()}",
				'Current directory: ' . getcwd()
			],
				(defined('AppSettings') && (AppSettings['LogUnhandledExceptions'] ?? false))
				? [print_r($exception, true), 'Stack trace', debug_backtrace(0, 3)]
				: ['Enable LogUnhandledExceptions to show full stack trace']
			);

			# Submit log
			log_exception(...$log);
			udp_error_log(json_encode($log));
		}
		catch (Exception $e) {
			# The global exception handler threw an exception
			if (is_cli()) {
				print_r($e);
			}
		}

		try {
			if (is_object($exception) && get_class($exception) === 'Google_Service_Exception') {
				get_system_cache(defined('AppSettings') && AppSettings['SystemCacheTTL'])->destroy('google_access_token');
				$error = 'Google Error' . (is_cli() ? (' (Log count = ' . log_count() . ')') : '');
			}
			else
				$error = 'Server Error' . (is_cli() ? (' (Log count = ' . log_count() . ')') : '');
			if (function_exists('fail'))
				fail($error);
			else
				die('System failure' . (is_cli() ? (' (Log count = ' . log_count() . ')') : '') . ":\r\n" . print_r($exception, true));
		}
		catch (Exception $e) {
			die('System failure' . (is_cli() ? (' (Log count = ' . log_count() . ')') : ''));
		}
	});
?>
