<?php
	/**
	 * @package PHPCentauri
	 */

	function get_system_cache($timeout = null) {
		if ($timeout === null) $timeout = AppSettings['SystemCacheTTL'];
		$prefix = AppSettings['SystemCacheSessionKeyPrefix'] ?? 'system';
		switch (AppSettings['SystemCacheStore'] ?? '') {
			case 'file':
				return new cache_file_store($timeout, $prefix);
			case 'redis':
			default:
				return new Predis\Session\Handler(new Predis\Client(AppSettings['RedisConnectionString'] ?? 'tcp://127.0.0.1:6379', ['prefix' => $prefix]), ['gc_maxlifetime' => $timeout]);
		}
	}
?>
