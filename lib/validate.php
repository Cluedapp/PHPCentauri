<?php
	/**
	 * @package PHPCentauri
	 */

	function cellphone($str) {
		return preg_match('/^(\+?27|0)[6-8]\d{8}$/', $str);
	}

	function is_email($str) {
		return (new EmailAttribute())->test($str);
	}

	function is_guid($str) {
		return preg_match('/^\{?[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}\}?$/i', $str);
	}

	function is_md5($str) {
		return preg_match('/^[a-zA-Z0-9]{32}$/', $str);
	}

	function luhn($str) {
		$array = str_split($str);
		for ($i = count($array) - 1; $i >= 0; $i -= 2) {
			$array[$i] *= 2;
			if ($array[$i] > 9)
				$array[$i] -= 9;
		}
		$checksum = (array_sum($array) * 9) % 10;
		return $checksum;
	}

	function luhn_validate($str) {
		$luhn = luhn(substr($str, 0, strlen($str) - 1));
		return substr($str, strlen($str) - 1, 1) == substr($luhn, strlen($luhn) - 1, 1);
	}

	/**
	  * Perform an array/object schema validation, similar to XML schema
	  * validation, but for a PHP array/object.
	  *
	  * Each key in the schema indicates a field that must be valid in the object.
	  * If the object contains a key that is not in the schema, then that key is ignored,
	  * and the object is considered valid with that key.
	  *
	  * @param $obj An array or object to validate
	  * @param $schema An array or object, defining the schema that $obj should
	  *        conform to
	  * @param $keysMustBePresentInObj True if the array or object in $obj is required
	  *        to contain every value given in $schema, in order to conform to the schema
	  */
	function validate_object($obj, $schema, $keysMustBePresentInObj = false) {
		$valid = null;

		if ((is_array($obj) || is_object($obj)) && (is_array($schema) || is_object($schema))) {
			$obj = (array)$obj;
			$schema = (array)$schema;

			foreach ($schema as $k => &$type) {
				if ($keysMustBePresentInObj && ($obj === null || !array_key_exists($k, $obj))) return ['Null', trim($k)];
				log_error('calling __validate_value', '$k', $k, '$type', $type, '$obj[$k]', array_key_exists($k, $obj) ? $obj[$k] : null);
				$valid = __validate_value($type, array_key_exists($k, $obj) ? $obj[$k] : null, $obj, $k);
				log_error('__validate_value returned', $valid);
				if ($valid !== true) return $valid;
			}
			return true;
		} else {
			return __validate_value($schema, $obj, null, null, $keysMustBePresentInObj);
		}
	}

	/**
	  * @function __validate_value
	  * @internal
	  *
	  * @summary Helper function for validate_object
	  *
	  * @example __validate_value('int', 123, <optional_container_object>, <optional_key>, <optional_bool>) === true;
	  */
	function __validate_value($type, $val, $obj = null, $key = null, $keysMustBePresentInObj = false) {
		log_error('__validate_value called', '$type', $type, '$val', $val);

		$valid = true;
		$errorCode = 'Format';

		if ($type instanceof BoolAndAttribute) {
			log_error('__validate_value bool and', (array)$type->getConditions());
			foreach ($type->getConditions() as $condition) {
				log_error('__validate_value bool and', '$condition', is_object($condition) ? get_class($condition) : $condition);
				$valid = __validate_value($condition, $val, $obj, $key, $keysMustBePresentInObj) === true;
				if ($valid !== true) break;
			}
		} elseif ($type instanceof BoolOrAttribute) {
			log_error('__validate_value bool or', (array)$type->getConditions());
			foreach ($type->getConditions() as $condition) {
				log_error('__validate_value bool or', '$condition', is_object($condition) ? get_class($condition) : $condition);
				$valid = __validate_value($condition, $val, $obj, $key, $keysMustBePresentInObj) === true;
				if ($valid === true) break;
			}
		} elseif ($type instanceof FunctionAttribute) {
			log_error('__validate_value function');
			$valid = $val === null || !!call_user_func($type->getFunction(), $val);
		} elseif ($type instanceof InAttribute) {
			log_error('__validate_value in');
			$valid = $val === null || !!in_array($val, $type->getInValues());
		} elseif ($type instanceof NotNullAttribute) {
			log_error('__validate_value not null');
			$valid = $val !== null;
		} elseif ($type instanceof PredicateAttribute) {
			log_error('__validate_value predicate', 'test value', $val, 'test result', $type->test($val));
			$valid = $val === null || !!$type->test($val);
		} elseif ($type instanceof RangeAttribute) {
			log_error('__validate_value range');
			$valid = $val === null || between($val, $type->getMin(), $type->getMax());
		} elseif ($type instanceof RequiredAttribute) {
			log_error('__validate_value required');
			$valid = $val !== null && $val != '' && array_key_exists($key, $obj);
			$errorCode = 'Null';
		} elseif ($type instanceof ArrayAttribute) {
			log_error('__validate_value array');
			if ($val !== null) {
				$val = (array)$val;
				foreach ($val as $i => $array) {
					$array_or_object_to_validate = (array)$array;
					$valid = validate_object($array_or_object_to_validate, $type->getSchema(), $val, $i, $keysMustBePresentInObj);
					if ($valid !== true) break;
				}
			}
		} elseif ($val !== null && $type instanceof TypeAttribute) {
			log_error('__validate_value type', $type->getType());
			switch ($type->getType()) {
				case 'array':
					log_error('__validate_value array');
					$valid = is_array($val);
					break;
				case 'bool':
					log_error('__validate_value bool');
					$valid = is_bool($val);
					break;
				case 'json_object':
					log_error('__validate_value json_object');
					$valid = !in_array(json_encode($val), [false, null]);
					break;
				case 'json_string':
					log_error('__validate_value json_string');
					$valid = !in_array(json_decode($val), [false, null]);
					break;
				case 'int':
					log_error('__validate_value int');
					$valid = is_int($val);
					break;
				case 'ints':
					log_error('__validate_value ints');
					$valid = is_array($val) && count(array_filter($val, 'is_int')) === count($val);
					break;
				case 'numeric':
					log_error('__validate_value numeric');
					$valid = is_numeric($val);
					break;
				case 'numerics':
					log_error('__validate_value numerics');
					$valid = is_array($val) && count(array_filter($val, 'is_numeric')) === count($val);
					break;
				case 'string':
					log_error('__validate_value string');
					$valid = is_string($val);
					break;
				case 'strings':
					log_error('__validate_value strings');
					$valid = is_array($val) && count(array_filter($val, 'is_string')) === count($val);
					break;
				case 'stringids':
					log_error('__validate_value stringids');
					$valid = is_array($val) && count(array_filter($val, [new StringIDAttribute, 'test'])) === count($val);
					break;
				default:
					log_error('__validate_value unknown type', $type, 'provided, so value is hereby invalid');
					$valid = false;
			}
		} elseif (is_callable($type)) {
			log_error('__validate_value callable');
			$valid = $val === null || !!$type($val);
		} elseif (is_object($type) || (is_array($type) && !is_indexed_array($type))) {
			log_error('__validate_value non-indexed array/object', '$type = ', (array)$type, '$val =', $val);
			if ($val !== null)
				$valid = validate_object($val, $type, $keysMustBePresentInObj);
			log_error('__validate_value non-indexed array/object result', $valid);
		} elseif (is_array($type)) {
			log_error('__validate_value indexed array');
			#for ($i = 0; $i < count($type) && $valid; ++$i) {
			#	$valid = __validate_value($type[$i], $val, $obj, $key);
			#}
			$valid = $val === null || ((array)$type == (array)$val);
		} else {
			log_error('__validate_value default comparison');
			$valid = $val === null || ($type == $val);
		}

		return $valid === true ? true : [$errorCode, trim($key)];
	}
?>
