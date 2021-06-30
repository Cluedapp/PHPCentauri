<?php
	/**
	 * @package PHPCentauri
	 */

	function escape($str, ...$args) {
		for ($i = 1, $len = count($args); $i <= $len; ++$i) {
			$str = preg_replace("/:$i/", is_string($args[$i - 1]) ? '"' . str_replace('\\', '\\\\', $args[$i - 1]) . '"' : json_encode($args[$i - 1]), $str);
		}
		return $str;
	}

	function hexstr_endian_reverse($hexstr) {
		$result = '';
		for ($i = strlen($hexstr) - 2; $i >= 0; $i -= 2) {
			$result .= substr($hexstr, $i, 2);
		}
		return $result;
	}

	function regex($subject, $pattern, $pattern_delimiter = '/') {
		$pattern = trim($pattern, $pattern_delimiter);
		preg_match("$pattern_delimiter$pattern$pattern_delimiter", $subject, $matches);
		return $matches;
	}

	function replace($subject, $pattern, $replacement, $pattern_delimiter = '/') {
		$pattern = trim($pattern, $pattern_delimiter);
		return preg_replace("$pattern_delimiter$pattern$pattern_delimiter", $replacement, $subject);
	}

	function string_to_range($string) {
		$ret = [];

		foreach (explode(',', $string) as $section) {
			$string = trim($section);

			# single number
			if (is_numeric($string))
				$ret[] = (int)$string;

			# range in format x-y
			elseif (preg_match('/^(\d+)\s*-\s*(\d+)$/', $string, $matches)) {
				$start = (int)$matches[1];
				$end = (int)$matches[2];
				$ret1 = [];
				for ($i = $start; $i <= $end; ++$i)
					$ret1[] = $i;
				$ret = array_merge($ret, $ret1);
			}
		}

		return $ret;
	}

	function string_to_variable_name($string) {
		return strtolower(str_replace(' ', '_', $string));
	}

	function utf8_to_unicode($utf8) {
		# Decode UTF-8 to Unicode (https://en.wikipedia.org/wiki/UTF-8)
		$unicode = '';
		$len = strlen($utf8);
		$i = 0;
		while ($i < $len) {
			$b1 = ord($utf8[$i]);
			$b2 = $i + 1 < $len ? ord($utf8[$i + 1]) : 0;
			$b3 = $i + 2 < $len ? ord($utf8[$i + 2]) : 0;
			$b4 = $i + 3 < $len ? ord($utf8[$i + 3]) : 0;
			if ((($b1 & 0b11111000) === 0b11110000) && (($b2 & 0b11000000) === 0b10000000) && (($b3 & 0b11000000) === 0b10000000) && (($b4 & 0b11000000) === 0b10000000)) {
				$unicode .= chr((($b1 << 5) & 0b11100000) + (($b2 >> 1) & 0b00011111)) . chr((($b2 << 7) & 0b10000000) + (($b3 << 1) & 0b01111110) + (($b4 >> 5) & 0b00000001)) . chr(($b4 << 3) & 0b11111000);
				$i += 4;
			} elseif ((($b1 & 0b11110000) === 0b11100000) && (($b2 & 0b11000000) === 0b10000000) && (($b3 & 0b11000000) === 0b10000000)) {
				$unicode .= chr((($b1 << 4) & 0b11110000) + (($b2 >> 4) & 0b00001111)) . chr((($b2 << 6) & 0b11000000) + ($b & 0b00111111));
				$i += 3;
			} elseif ((($b1 & 0b11100000) === 0b11000000) && (($b2 & 0b11000000) === 0b10000000)) {
				$unicode .= chr((($b1 << 3) & 0b11111000) + (($b2 >> 3) & 0b00000111)) . chr(($b2 << 5) & 0b11100000);
				$i += 2;
			} elseif (($b1 & 0b10000000) === 0b00000000) {
				$unicode .= chr($b1 & 0b01111111);
				++$i;
			} else {
				$unicode = '';
				break;
			}
		}
		return $unicode;
	}

	function size_to_readable_string($size) {
		return ((int) ($size / pow(1000, (int) log($size, 1000)))) . ' ' . ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'][(int) log($size, 1000)];
	}

	function variable_name_to_readable_string($str) {
		$matches = [];
		preg_match_all('/([A-Z]?[a-z]*)/', $str, $matches);
		$matches = preg_grep('/.+/', $matches[0]);
		if (count($matches))
			return array_reduce($matches, function($sum, $val) { return $sum . (strlen($sum) && (strlen($val) > 1 || (strlen($val) && preg_match('/[a-z]$/', $sum))) ? ' ' : '') . (strtolower($val) == 'id' ? 'ID' : ucfirst($val)); }, '');
		return '';
	}

	// XSV = "X" separated values, splits a string of lines into an array of 1D arrays
	function xsv($string, $separator) {
		return array_map(function($line) use ($separator) { return explode($separator, trim($line)); }, explode("\n", trim($string)));
	}
?>
