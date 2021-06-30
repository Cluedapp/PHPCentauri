<?php
	/**
	 * @package PHPCentauri
	 */

	function between($value, $min, $max) {
		return $value >= $min && $value <= $max;
		}

	function in_csv($search, $csv) {
		return in_array($search, preg_split('/\s*,\s*/', $csv));
	}
?>
