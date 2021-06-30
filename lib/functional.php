<?php
	/**
	 * @package PHPCentauri
	 */

	function __phpcentauri_functional_convert_and_swap__(&$func, &$array) {
		# Convert
		if (is_string($func))
			$func = make_func($func);
		elseif (is_string($array))
			$array = make_func($array);

		# Swap
		if (is_array($func) && is_callable($array)) {
			$temp = $func;
			$func = $array;
			$array = $temp;
		}
	}

	function all($func, $array) {
		__phpcentauri_functional_convert_and_swap__($func, $array);
		if (is_array($array))
			foreach ($array as $val)
				if (!$func($val))
					return false;
		return true;
	}

	function any($func, $array) {
		__phpcentauri_functional_convert_and_swap__($func, $array);
		if (is_array($array))
			foreach ($array as $val)
				if ($func($val))
					return true;
		return false;
	}

	function curry_back($func, ...$curry) {
		if (is_string($func)) $func = make_func($func);

		$f = function(...$args) use ($func, $curry) {
			foreach ($curry as $arg)
				$args[] = $arg;
			return $func(...$args);
		};
		return $f;
	}

	function curry_front($func, ...$curry) {
		if (is_string($func)) $func = make_func($func);

		$f = function(...$args) use ($func, $curry) {
			foreach ($curry as $arg)
				array_unshift($args, $arg);
			return $func(...$args);
		};
		return $f;
	}

	function filter($func, $array) {
		__phpcentauri_functional_convert_and_swap__($func, $array);
		$ret = [];
		if (is_array($array))
			foreach ($array as $val)
				if ($func($val))
					$ret[] = $val;
		return $ret;
	}

	# Return the first item in $array where true === $func($array[$i]), $i :== 0 to count($array) - 1
	function first($func, $array = null) {
		if (is_array($func) && count($func) && $array === null)
			return $func[0];
		__phpcentauri_functional_convert_and_swap__($func, $array);
		if (is_array($array))
			foreach ($array as $val)
				if ($func($val))
					return $val;
		return null;
	}

	# Return the last item in $array where true === $func($array[$i]), $i :== count($array) - 1 to 0
	function last($func, $array = null) {
		if (is_array($func) && ($count = count($func)) && $array === null)
			return $func[$count - 1];
		__phpcentauri_functional_convert_and_swap__($func, $array);
		if (is_array($array))
			for ($i = count($array) - 1; $i >= 0; --$i)
				if ($func($array[$i])) return $array[$i];
		return null;
	}

	function make_func($expression) {
		return function(...$args) use ($expression) {
			for ($i = 0, $len = count($args); $i < $len; ++$i) {
				$expression = preg_replace('/:' . ($i + 1) . '/', "\$args[$i]", $expression);
				$expression = preg_replace('/%s/', "\$args[$i]", $expression, 1);
			}
			ob_start();
			$value = eval("return $expression;");
			$error = ob_get_clean();
			return $value;
		};
	}

	function map($func, $array) {
		__phpcentauri_functional_convert_and_swap__($func, $array);
		$ret = [];
		if (is_array($array))
			foreach ($array as $val)
				$ret[] = $func($val);
		return $ret;
	}

	function reduce($func, $array) {
		__phpcentauri_functional_convert_and_swap__($func, $array);
		$reduced = null;
		if (is_array($array))
			foreach ($array as $val)
				$reduced = $func($reduced, $val);
		return $reduced;
	}

	function where($array, $schema) {
		$ret = [];
		if (is_array($array))
			foreach ($array as $item)
				if (validate_object($array, $schema, true) === true)
					$ret[] = $item;
		return $ret;
	}
?>
