<?php
	/**
	 * @package PHPCentauri
	 */

	function cast($value, $type) {
		switch ($type) {
			case 'date_int':
				return strtotime($value);
			case 'date_string':
				return date('Y-m-d', $value);
			case 'json_object':
				return json_decode($value);
			case 'json_string':
				return json_encode($value);
			default:
				return eval("return ($type)\$value;");
		}
	}
?>
