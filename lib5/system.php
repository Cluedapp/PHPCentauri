<?php
	/**
	 * @package PHPCentauri for PHP 5
	 */

	function get_system_cache() {
		switch (SystemCacheStore) {
			case 'file':
			default:
				return new cache_file_store(SystemCacheTTL);
		}
	}
?>
