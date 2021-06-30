<?php
	/**
	 * @package PHPCentauri for PHP 5
	 */

	class cache_file_store {
		public function __construct($ttl) {
			foreach (array_filter(glob('../cache/$prefix*'), 'is_file') as $filename)
				if (filemtime($filename) < time() - $ttl)
					unlink($filename);
		}

		public function exists($session_id) {
			return file_exists("../cache/$session_id.txt");
		}

		public function read($session_id) {
			return $this->exists($session_id) ? file_get_contents("../cache/$session_id.txt") : '';
		}

		public function write($session_id, $data) {
			file_put_contents("../cache/$session_id.txt", $data);
		}

		public function destroy($session_id) {
			unlink("../cache/$session_id.txt");
		}
	}
?>
