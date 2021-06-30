<?php
	/**
	 * @package PHPCentauri for PHP 5
	 */

	function json2html($something, $scope_chars = null, $indent_count = 1) {
		$format_value = function($value, $indent_count) {
			if (is_object($value))
				return json2html($value, '{}', $indent_count + 1);
			elseif (is_array($value))
				return json2html($value, '[]', $indent_count + 1);
			elseif (is_null($value))
				return '<span style="color: #800000">' . 'null</span>';
			elseif (is_bool($value))
				return '<span style="color: #000080">' . ($value ? 'true' : 'false') . '</span>';
			elseif (is_numeric($value))
				return '<span style="color: #000080">' . $value . '</span>';
			elseif (is_string($value) && preg_match('/^https?:\/\/.+/', $value))
				return '<span style="color: #800080">' . htmlentities($value) . '</span>';
			elseif (is_string($value))
				return '<span style="color: #008000">' . json_encode(htmlentities($value)) . '</span>';
		};

		if ($scope_chars === null)
			$scope_chars = is_array($something) ? '[]' : is_object($something) ? '{}' : '';

		if (!is_array($something) && !is_object($something))
			return $format_value($something, $indent_count);

		$str = '<b>' . $scope_chars[0] . '</b><br />';
		$array = (array)$something;
		$keys = array_keys($array);
		for ($i = 0, $len = count($keys); $i < $len; ++$i) {
			$key = $keys[$i];
			$value = $array[$key];
			$str .= str_repeat('&nbsp;&nbsp;', $indent_count);
			if (is_object($something))
			{
				$show_dash = is_object($value) || is_array($value);
				if (!$show_dash)
					$str .= '<span style="color: #ffffff">';
				$str .= '- ';
				if (!$show_dash)
					$str .= '</span>';
				$str .= '<b>' . $key . ':</b> ';
			}
			$str .= $format_value($value, $indent_count) . ($i == $len - 1 ? '' : ',') . '<br />';
		}
		$str .= str_repeat('&nbsp;&nbsp;', $indent_count - 1) . '<b>' . $scope_chars[1] . '</b>';
		return $str;
	}
?>
