<?php
	/**
	 * @package PHPCentauri
	 */

	function redis_session() {
		static $session = null;
		if ($session === null) {
			// Set 'gc_maxlifetime' to specify a time-to-live of x seconds for session keys
			log_error('creating redis session handler with TTL =', AppSettings['SessionTTLInSeconds']);
			$session = new Predis\Session\Handler(redis_session_client(), ['gc_maxlifetime' => AppSettings['SessionTTLInSeconds']]);
		}
		return $session;
	}

	function redis_session_client() {
		static $client = null;
		if ($client === null) {
			log_error('connecting to redis session server', AppSettings['RedisConnectionString']);
			$client = new Predis\Client(AppSettings['RedisConnectionString'], ['prefix' => AppSettings['SessionKeyPrefix']]);
		}
		return $client;
	}

	# Get a session store configured for this specific system, based on the SessionStore setting
	function get_session_store() {
		$timeout = AppSettings['SessionTTLInSeconds'] ?? 1200; # default session TTL = 20 minutes
		switch (AppSettings['SessionStore'] ?? '') {
			case 'file':
				return new cache_file_store($timeout, AppSettings['SessionKeyPrefix'] ?? 'session-');
			case 'redis':
			default:
				return redis_session();
		}
	}

	# Get a session store client configured for this specific system, based on the SessionStore setting
	function get_session_store_client() {
		$timeout = AppSettings['SessionTTLInSeconds'] ?? 1200; # default session TTL = 20 minutes
		switch (AppSettings['SessionStore'] ?? '') {
			case 'file':
				return new cache_file_store($timeout, AppSettings['SessionKeyPrefix'] ?? 'session-');
			case 'redis':
			default:
				return redis_session_client();
		}
	}
?>
