<?php
	/**
	 * @package PHPCentauri
	 */

	function generator($f) {
		return make_func($f);
	}

	function stringid($prefix = '') {
		$len = 30;
		do {
			$chars = 'abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789-';
			$stringid = strtolower($prefix);

			for ($i = 0, $len_ = $len - strlen($prefix); $i < $len_; ++$i)
				$stringid .= substr($chars, random_int(0, strlen($chars) - 1), 1);

			log_error('stringid', $stringid, 'is string id?', (new StringIDAttribute)->test($stringid));

		} while (!(new StringIDAttribute)->test($stringid));

		return $stringid;
	}

	function random_number_string($len) {
		$str = '';
		for ($i = 0; $i < $len; ++$i)
			$str .= random_int($i ? 0 : 1, 9);
		return $str;
	}

	function random_string_from($allowed_chars, $len, $regex) {
		$max = strlen($allowed_chars) - 1;
		do {
			$str = '';
			for ($i = 0; $i < $len; ++$i)
				$str .= $allowed_chars[random_int(0, $max)];
		} while (!preg_match($regex, $str));
		return $str;
	}

	function unique_id() {
		return bin2hex(random_bytes(16));
	}
?>
