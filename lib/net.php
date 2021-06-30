<?php
	/**
	 * @package PHPCentauri
	 */

	function ping($hostname) {
		if ($ip = explode(' ', `ping -W 1 -c 1 $hostname`)[2])
			if ($ip = substr($ip, 1, strlen($ip) - 2))
				if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip)) # only ever return IP in a.b.c.d format
					return $ip;
		return '127.0.0.1';
	}
?>
