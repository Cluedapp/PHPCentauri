<?php
	/**
	 * @package PHPCentauri for PHP 5
	 */

	function log_error() {
		if (Debug)
			error_log(json_encode(func_get_args()));
	}
?>
