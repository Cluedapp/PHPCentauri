<?php
	/**
	 * @package PHPCentauri
	 */

	function array_map_assoc(callable $f, array $a) {
		$ret = [];
		foreach ($a as $k => &$v)
			$ret[$k] = $f($k, $v);
		return $ret;
	}

	function array_merge_if($func, ...$args) {
		if (is_string($func)) $func = make_func($func);

		$ret = [];
		foreach ($args as &$array) {
			foreach ($array as $k => &$v) {
				$v = $v instanceof Closure ? $v() : $v;
				if ($func($v)) $ret[$k] = $v;
			}
		}

		return $ret;
	}

	function array_merge_values($merge_func, ...$args) {
		$ret = [];
		foreach ($args as &$array)
			foreach ($array as $k => &$v)
				$ret[$k] = isset($ret[$k]) ? $merge_func($ret[$k], $v) : $v;
		return $ret;
	}

	function flatten_array($a, $prefix = '', $cascade = false, $cascade_separator = '.') {
		$ret = [];
		$prefix = $prefix . ($prefix && $cascade ? $cascade_separator : '');
		foreach ($a as $k => &$v) {
			if (is_array($v) || is_object($v)) $ret = array_merge($ret, flatten_array((array)$v, $cascade ? "$prefix$k" : $prefix, $cascade, $cascade_separator));
			elseif (is_numeric($k)) $ret[] = $v;
			else $ret["$prefix$k"] = $v;
		}
		return $ret;
	}

	function is_indexed_array(&$array) {
		if (!is_array($array))
			return false;
		$len = count($array);
		$keys = array_keys($array);
		if (!$len)
			return true;
		if (min($keys) != 0 || max($keys) != $len - 1)
			return false;
		foreach ($keys as $index)
			if (!is_numeric($index))
				return false;
		return true;
	}

	function is_subarray($array, $subarray) {
		$ret = true;
		$keys = array_keys($subarray);
		for ($i = 0, $len = count($keys); $i < $len && $ret; ++$i) {
			$k = $keys[$i];
			$item = &$array[$k];
			$subitem = &$subarray[$k];
			$ret = isset($array[$k]) && ((is_array($subitem) && is_subarray($item, $subitem)) || (
					   ($subitem instanceof RegexAttribute && $subitem->test($item))
					|| ($subitem instanceof RangeAttribute && between($item, $subitem->getMin(), $subitem->getMax()))
					|| ($subitem instanceof FunctionAttribute && !!call_user_func($subitem->getFunction(), $item))
					|| (is_callable($subitem) && !!$subitem($item))
					|| ($item == $subitem)));
		}
		return $ret;
	}

	/**
	  * @function multisort
	  *
	  * @summary Sort array of subarrays based on provided arguments. A copy of the array is returned, it is not sorted in place
	  *
	  * @param 1 An input array (of subarrays) to sort
	  *
	  * @param 2..N Zero or more sort-specifiers to instruct the multisort function,
	  *        by which keys in the subarrays, to sort the provided subarrays
	  *
	  *        A sort-specifier can be one of the following:
	  *        a) A case-sensitive string key name, e.g. 'name', 0, etc.
	  *        b) A case-sensitive inverse string key name, e.g. '~name', '~0', etc. to sort
	  *           by, but in reverse order
	  *        c) An array with 2 elements. Element 1 is the key name, and
	  *           element 2 is a selector/transform function, to generate the
	  *           left and right values to be compared with each other. Occurrences
	  *           of the string "%s" (without quotes) are substituted with the
	  *           corresponding left and right value. Quotes are not needed around %s
	  *           in the selector/transform function, as %s is replaced by a variable
	  *           name, not with the contents of the actual value
	  *
	  * @return Sorted copy of original sorted indexed array of subarrays
	  *
	  * @example multisort([['a' => 2], ['a' => 1]], 'a')
	  *          => [['a' => 1], ['a' => 2]]
	  *
	  * @example multisort([['a' => 3, 'b' => 2], ['a' => 2, 'b' => 1], ['a' => 1, 'b' => 3]], '~b', 'a')
	  *          => [['a' => 1, 'b' => 3], ['a' => 3, 'b' => 2], ['a' => 2, 'b' => 1]]
	  *
	  * @example multisort([['name' => 'JOHN'], ['name' => 'joe']], ['name', 'strtolower(%s)'])
	  *          => [['name' => 'joe'], ['name' => 'JOHN']]
	  */
	function multisort($array, ...$args) {
		usort(
			$array,
			function($a, $b) use ($args) {
				$a = (array)$a;
				$b = (array)$b;
				foreach ($args as $arg) {
					if (is_array($arg)) {
						$argname = $arg[0];
						$func = make_func($arg[1]);
						$cmp = $func($a[$argname]) <=> $func($b[$argname]);
					} else {
						$mul = 1;
						if (substr($arg, 0, 1) == '~') {
							$arg = substr($arg, 1);
							$mul = -1;
						} else {
							$mul = 1;
						}
						$cmp = ($a[$arg] <=> $b[$arg]) * $mul;
					}
					if ($cmp) return $cmp;
				}
				return 0;
			});
		return $array;
	}

	function paginate($array, $limit, $startFunction, $is_all_data_in_array) {
		if (is_string($startFunction)) $startFunction = make_func($startFunction);
		$ret = [];
		$start = false;
		$start_index = -1;
		for ($i = 0, $len = count($array); $i < $len && count($ret) < $limit; ++$i) {
			$item = $array[$i];
			if (!$start && $startFunction($item)) {
				$start = true;
				$start_index = $i;
			}
			if ($start) $ret[] = $item;
		}
		if ($is_all_data_in_array) {
			return (object)['data' => $ret, 'more' => $start && $start_index + count($ret) < $len]; # used if it is known as a business rule that all possible data is contained in $array
		} else
			return (object)['data' => $ret, 'more' => count($ret) >= $limit]; # used if it is known that $array is only a subset of all the possible data
	}

	/**
	  * @function phpcentauri_array_diff
	  *
	  * Recursively compare two arrays/objects, and return an array of the differences between the two.
	  * Similar to built-in array_diff() and array_diff_assoc(), but it handles the scenario
	  * when either argument is either an array or object, i.e. it can compare an array to an object
	  * and vice versa
	  */
	function phpcentauri_array_diff(&$array1, &$array2) {
		if ((!is_array($array1) && !is_object($array1)) || (!is_array($array2) && !is_object($array2))) {
			return [];
		}
		$is = [is_array($array1), is_array($array2)]; # is argument N an array or object?
		$keys1 = array_keys($is[0] ? $array1 : get_object_vars($array1));
		$keys2 = array_keys($is[1] ? $array2 : get_object_vars($array2));
		$fields = array_merge($keys1, $keys2);
		$diff = [];
		foreach ($fields as $field) {
			$has = [ # does argument N (either an array or object) have this field?
				in_array($field, $keys1),
				in_array($field, $keys2),
			];
			if ($has[0] && $has[1]) {
				# They both have the field, so compare the field in each array/object
				$val = [
					$is[0] ? $array1[$field] : $array1->$field,
					$is[1] ? $array2[$field] : $array2->$field,
				];
				$is_val = [ # is each corresponding field value, either an array or an object?
					is_array($val[0]) || is_object($val[0]),
					is_array($val[1]) || is_object($val[1])
				];
				if ($is_val[0] && $is_val[1]) {
					$val_diff = phpcentauri_array_diff($val[0], $val[1]);
					if (!empty($val_diff)) {
						$diff[$field] = $val_diff;
					}
				}
				elseif ($val[0] != $val[1]) {
					# There is a difference
					$diff[$field] = $val[1];
				}
			} else {
				# Only one of them have the field (or neither, which cannot logically happen)
				if ($has[0]) {
					if (($is[0] && isset($array1[$field])) || (!$is[0] && isset($array1->$field)))
						$diff[$field] = $is[0] ? $array1[$field] : $array1->$field;
				} else {
					if (($is[1] && isset($array2[$field])) || (!$is[1] && isset($array2->$field)))
						$diff[$field] = $is[1] ? $array2[$field] : $array2->$field;
				}
			}
		}
		return $diff;
	}
?>
