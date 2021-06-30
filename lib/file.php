<?php
	/**
	 * @package PHPCentauri
	 */

	function file_put_contents2($filename, $data) {
		# Ensure that the directory for a file exists when writing the file
		$dir = dirname($filename);
		`mkdir -p $dir`;
		file_put_contents($filename, $data);
	}

	function json_decode_file($filename) {
		return json_decode(file_get_contents($filename));
	}

	function mime_type($data) {
		return strtolower(trim(explode(';', (new finfo(FILEINFO_MIME))->buffer($data))[0]));
	}
?>
