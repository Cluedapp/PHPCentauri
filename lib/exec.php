<?php
	/**
	 * @package PHPCentauri
	 */

	function atomic($lockfile_path, $critical_section) {
		do {
			# Get lock
			$f = fopen($lockfile_path, 'w');
			$file_is_valid = $f !== null && $f !== false && file_exists($lockfile_path);
			if ($file_is_valid && !flock($f, LOCK_EX)) continue; # prevent infinite loop, by only trying again if the lockfile exists, but could not be locked. do not retry if the lockfile doesn't exist, since it means the previous operation could not create the lockfile anyway
			register_shutdown_function(function() use (&$f) { if ($f !== null && $f !== false) fclose($f); });

			# Begin critical section
			$result = $critical_section();
			# End critical section

			# Release lock
			if ($file_is_valid) {
				flock($f, LOCK_UN);
				fclose($f);
				$f = null;
			}

			# Exit infinite loop
			return $result;
		} while (true);
	}

	function is_cli() {
		return defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0);
	}

	function option($option) {
		global $argv;

		if ((isset($_GET[$option]) && $_GET[$option] == 1) || (is_array($argv) && (in_array($option, array_slice($argv, 1)) || in_array("-$option", array_slice($argv, 1)) || in_array("--$option", array_slice($argv, 1)))))
			return true;
		if (isset($_GET[$option]))
			return $_GET[$option];
		if (isset($_POST[$option]))
			return $_POST[$option];
		if (is_array($argv)) {
			$len = strlen($option);
			foreach ($argv as $arg) {
				$offset = substr($arg, 0, $len + 1) == "$option=" ? $len + 1 : (substr($arg, 0, $len + 2) == "-$option=" ? $len + 2 : (substr($arg, 0, $len + 3) == "--$option=" ? $len + 3 : -1));
				if ($offset >= 0)
					return substr($arg, $offset);
			}
		}
		$data = json_decode(raw_post_input());
		if (is_object($data) && isset($data->$option))
			return $data->$option;
		return false;
	}
?>
