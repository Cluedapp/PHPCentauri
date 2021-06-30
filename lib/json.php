<?php
	/**
	 * @package PHPCentauri
	 */

	# Return a scalar or array, from an object source, given by the "node lookup path" to retrieve the value or array at, in the JSON object tree
	# $source can be an object, array, JSON string, or a HTTP URL (which is fetched and contains a JSON string).
	# if $source is invalid, then an empty array is returned
	# Each individual node in the "node lookup path" is separated by a period . for example:
	# $source = {"people":{"age": [{"age":1},{"age":2},{"age":3},{"age":4}], "names":[{"name":"John","surname":"Smith"},{"name":"Mary","surname":"Smith"}]}}
	# $path = 'people.name' => return ["John", "Mary"]
	# $path = 'people.age.1.age' => return 2
	function jpath($source, $path) {
		$obj = is_object($source) || is_array($source) ? (array)$source : json_decode(substr(trim($source), 0, 1) == '{' ? $source : file_get_contents($source));
		if (!$obj)
			return [];

		$path = explode('.', $path);
		for ($i = 0, $len = count($path); $i < $len - 1; ++$i)
			$obj = ((array)$obj)[$path[$i]];

		$final_key = $path[$len - 1];
		if (is_array($obj)) {
			$ret = [];
			foreach ($obj as $item) {
				if (is_object($item)) {
					$ret[] = $item->$final_key;
				} elseif (is_array($obj))
					return $obj[$final_key];
			}
			return $ret;
		} if (is_object($obj))
			return $obj->$final_key;
		else
			return $obj;
	}

	function json2html($something, $scope_chars = null, $indent_count = 1) {
		$format_value = function($value, $indent_count) {
			if (is_object($value) || (is_array($value) && !is_indexed_array($value)))
				return json2html((object)$value, '{}', $indent_count + 1);
			elseif (is_array($value))
				return json2html($value, '[]', $indent_count + 1);
			elseif (is_null($value))
				return '<span style="color: #800000">' . 'null</span>';
			elseif (is_bool($value))
				return '<span style="color: #000080">' . ($value ? 'true' : 'false') . '</span>';
			elseif (is_numeric($value))
				return '<span style="color: #000080">' . $value . '</span>';
			elseif (is_string($value))
				return '<span style="color: #800080">' . htmlentities($value) . '</span>';
		};

		$is_array = is_array($something);
		$is_indexed_array = $is_array && is_indexed_array($something);
		$is_object = is_object($something);

		if ($scope_chars === null)
			$scope_chars = $is_indexed_array ? '[]' : '{}';

		if (!$is_array && !$is_object)
			return $format_value($something, $indent_count);

		$str = '<b>' . $scope_chars[0] . '</b><br />';
		$array = (array)$something;
		$keys = array_keys($array);
		for ($i = 0, $len = count($keys); $i < $len; ++$i) {
			$key = $keys[$i];
			$value = $array[$key];
			$str .= str_repeat('&nbsp;&nbsp;', $indent_count);
			if (($is_array && !$is_indexed_array) || $is_object)
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

	function json_merge($jsonStrings) {
		return json_encode(array_replace_recursive(...(count($jsonStrings) ? array_map(function($json) { $a = json_decode($json, true); return is_array($a) ? $a : []; }, $jsonStrings) : [[]])));
	}

	/**
	  * @function pick
	  *
	  * @summary Pick case-insensitive properties from an object or array
	  *
	  * @param 1 Input array/object from which to pick values or properties
	  *
	  * @param 2..N Key-specifiers to instruct the pick function, how to map
	  *        keys from the input array/object to the output array.
	  *
	  *        In the simplest case, the arguments are all just string names
	  *        of case-insensitive keys or properties to pick from the input
	  *        array/object in parameter 1.
	  *
	  *        If the N'th argument	is a string, then the case-insensitive
	  *        key name given by the string is mapped directly from the
	  *        main array/object, to the output array with the given key name
	  *        (with the case specified by the argument, not by the original key).
	  *
	  *        The fun part: If the N'th argument is an array, then the key
	  *        name is specified by the first string/int/RegexAttribute element
	  *        in this N'th input array/object, and the second or subsequent
	  *        element that is a string/int (not a RegexAttribute) becomes the
	  *        key name to use in the generated output array. If it is an int,
	  *        it indicates that the item from the main input array/object should
	  *        be appended to the output array, and not stored by a string key
	  *        name. An optional third array element can be provided, which
	  *        specifies the type that the picked value in the output array should
	  *        be cast/converted to, e.g. to cast an integer in the input
	  *        array/object to a bool value in the output array.
	  *
	  *        Only keys that actually exist in the input array/object,
	  *        will be present in the output array. The output array will not
	  *        contain data that was not in the input array/object.
	  *
	  *        If another input string/int/RegexAttribute results in a duplicate
	  *        key that has already been placed in the generated output array, then
	  *        it us ignored and only the first value is kept.
	  *
	  * @return Generated output hash array (not object) with mapped keys and
	  *         values as specified by the arguments
	  *
	  * @example pick(['a' => 1, 'b' => 2], 'a', ['B', 'C'])
	  *          => ['a' => 1, 'C' => 2]
	  * @example pick([['a' => 1, 'b' => 2], ['A' => 3, 'B' => 4]], 'a', ['B', 'C'])
	  *          => [['a' => 1, 'C' => 2], ['a' => 3, 'C' => 4]]
	  * @example pick((object)['a' => 1, 'b' => 2], 'a', ['B', 'C'])
	  *          => ['a' => 1, 'C' => 2]
	  * @example pick((object)['a' => 1, 'b' => 2], 'a', ['B', 'C', ''])
	  *          => ['a' => 1, 'C' => 2]
	  * @example pick((object)['a' => 1, 'b' => 2], 'a', [new RegexAttribute('/B/i'), 'C', i('bool')])
	  *          => ['a' => 1, 'C' => true]
	  * @example pick((object)['a' => 1, 'b' => 2], 'a', 'C', i('regex("/.+/")'))
	  *          => ['a' => 1, 'b' => 2]
	  * @example pick((object)['a' => 1], ['a', i('bool')], ['a', i('int')])
	  *          => ['a' => true]
	  */
	function pick($data, ...$args) {
		# Take the first argument as the input array/object and the remaining
		# arguments as key specifiers
		$ret = [];

		# First check if the input array/object is an indexed array of sub-arrays,
		# in case it is, the return array will also be an indexed array, either of
		# sub-arrays or of scalar values
		if (!is_object($data) && isset($data[0])) {
			foreach ($data as $obj) {
				# Recursively call pick on each sub-array
				$temp = pick(...array_merge([(array)$obj], $args));

				# If the newly generated, picked array has only one element, then replace
				# the generated array with that single scalar item
				if (count($args) == 1 && is_array($args[0]) && is_numeric($args[0][1]) && isset($temp[0])) {
					$temp = $temp[0];
				}

				# Place the generated item (either a sub-array or scalar value) in the output hash array
				$ret[] = $temp;
			}
		} else {
			# Data is a single flat array, so the return value will now be a single array with key/value pairs
			$data = (array)$data;

			# Loop through each value in the input array/object
			foreach ($data as $k => $v) {
				# Loop through each key-specifier argument
				foreach ($args as $key_specifier_index => $key_specifier) {
					# Ignore null keys, a null key is used when creating the args array and we are limited by PHP syntax, when there
					# must be an element, but we don't actually want an element to be in the array, so we put a null in to
					# "deactivate" the array element :)
					if ($key_specifier === null) continue;

					# Get the from-key-name (old - in the input array/object) and the to-key-name (new - in the output hash array) for the mapped keys
					$old = (is_array($key_specifier) ? first('is_string(:1) || is_int(:1) || :1 instanceof RegexAttribute', $key_specifier) : $key_specifier);
					$new = (is_array($key_specifier) ? first('is_string(:1) || is_int(:1)', array_slice($key_specifier, 1)) : $key_specifier);
					if (!is_string($new) && !is_int($new))
						$new = $k;

					# Check if a mapping is possible
					if (($old instanceof RegexAttribute && $old->test($k)) || ($key_specifier instanceof PickArrayAttribute) || ((is_string($old) || is_int($old)) && strtolower($old) == strtolower($k))) {
						if (is_array($key_specifier)) {
							# Prevent the key name from being interrogated below
							$key_specifier = array_slice($key_specifier, 1);

							# Check if picked value can be null
							if ($v === null && first(':1 instanceof NotNullAttribute', $key_specifier)) continue;

							# Check if picked value should be cast to another type, and cast the item if required
							if ($cast = first(':1 instanceof TypeAttribute', $key_specifier))
								$v = cast($v, $cast->getType());

							# Check if picked value should be transformed by a function
							if ($func = first('is_callable(:1)', $key_specifier)) {
								$v = $func($v);
							}
						} elseif ($key_specifier instanceof PickArrayAttribute) {
							# the picked value itself should be a picked array
							$ret = array_merge($ret, [pick($data, ...$key_specifier->get())]);
							unset($args[$key_specifier_index]);
							continue;
						}

						# If the output key name is a number, then append the new item to the output hash array
						if (is_numeric($new)) $ret[] = $v;
						# Else, map it to the new string key name in the output hash array
						elseif (!isset($ret[$new])) $ret[$new] = $v;
					}
				}
			}
		}
		return $ret;
	}
?>
