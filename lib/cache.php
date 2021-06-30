<?php
	/**
	 * @package PHPCentauri
	 */

	class cache_file_store {
		private $prefix;

		public function __construct($ttl, $prefix = '') {
			foreach (array_filter(glob("../cache/$prefix*"), 'is_file') as $filename)
				if (is_numeric($ttl) && filemtime($filename) < time() - $ttl) {
					log_error('Old cache file', $filename, 'filemtime', filemtime($filename), 'time', time(), 'ttl', $ttl);
					unlink($filename);
				}
			$this->prefix = $prefix;
		}

		public function destroy($cache_id) {
			log_error('Destroying cache file', "../cache/{$this->prefix}$cache_id.txt");
			unlink("../cache/{$this->prefix}$cache_id.txt");
		}

		public function exists($cache_id) {
			return file_exists("../cache/{$this->prefix}$cache_id.txt");
		}

		function keys($search_prefix = '*') {
			return array_map('basename', array_filter(glob("../cache/{$this->prefix}$search_prefix"), 'is_file'));
		}

		public function read($cache_id) {
			log_error('Cache read', "../cache/{$this->prefix}$cache_id.txt", $this->exists($cache_id) ? file_get_contents("../cache/{$this->prefix}$cache_id.txt") : '');
			return $this->exists($cache_id) ? file_get_contents("../cache/{$this->prefix}$cache_id.txt") : '';
		}

		public function write($cache_id, $data) {
			log_error('Cache write', "../cache/{$this->prefix}$cache_id.txt", $data);
			file_put_contents("../cache/{$this->prefix}$cache_id.txt", $data);
		}
	}
?>
