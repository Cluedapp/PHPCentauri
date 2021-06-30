<?php
	/**
	 * @package PHPCentauri
	 *
	 * Required Composer packages:
	 *    predis/predis
	 */

	class Redis {
		public $client;
		public $prefix;
		public $ttl;

		function __construct($ttl, $prefix) {
			$this->ttl = $ttl;
			$this->prefix = $prefix;
			$client = new Predis\Client(AppSettings['RedisConnectionString'], ['prefix' => $prefix]);
			$handler = new Predis\Session\Handler($client, ['gc_maxlifetime' => $ttl]);

			return $handler;
		}

		function read($key) {
			return $handler->read($key);
		}

		function write($key, $data) {
			$handler->write($key, $data);
		}

		function keys() {
			return $client->keys('*');
		}
	}
?>
