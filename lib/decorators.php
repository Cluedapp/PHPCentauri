<?php
	/**
	 * @package PHPCentauri
	 *
	 * https://en.wikipedia.org/wiki/Marker_interface_pattern
	 *
	 * This file defines simple attribute classes that act as markers/tags/decorators/wrappers,
	 * in order to provide runtime type information hints to other code
	 */

	# --- Enums ---
	
	abstract class BoolOperator {
		const Default = 'default';
		const And = 'and';
		const Or = 'or';

		private function __construct() { }
	}

	# --- Classes ---

	class ArrayAttribute {
		private $schema;

		public function __construct($schema) {
			$this->schema = $schema;
		}

		public function getSchema() {
			return $this->schema;
		}
	}

	abstract class LogicAttribute {
		public $conditions;

		public function __construct(...$conditions) {
			$this->conditions = $conditions;
		}

		public function getConditions() {
			return $this->conditions;
		}
	}

	class BoolAndAttribute extends LogicAttribute {
		public function __construct(...$conditions) {
			parent::__construct(...$conditions);
		}
	}

	class BoolOrAttribute extends LogicAttribute {
		public function __construct(...$conditions) {
			parent::__construct(...$conditions);
		}
	}

	class BoolishAttribute extends BoolOrAttribute {
		public function __construct() {
			parent::__construct(new TypeAttribute('bool'), new TypeAttribute('int'));
		}
	}

	class EmailAttribute extends PredicateAttribute {
		public function test($value) {
			return filter_var($value, FILTER_VALIDATE_EMAIL);
		}
	}

	class FunctionAttribute {
		private $func;

		public function __construct($func, ...$curryLeftArgs) {
			$this->func = function(...$args) use (&$func, &$curryLeftArgs) {
				log_error('executing FunctionAttribute', $func, $curryLeftArgs, $args);
				$value = $func(...array_merge($curryLeftArgs, $args));
				log_error('FunctionAttribute return value', $value);
				return $value;
			};
			log_error('FunctionAttribute constructor', $func, $curryLeftArgs);
		}

		public function getFunction() {
			return $this->func;
		}
	}

	class Ignore { }

	class InAttribute {
		private $inValues;

		public function __construct($inValues) {
			$this->inValues = $inValues;
		}

		public function getInValues() {
			return $this->inValues;
		}
	}

	class NullAttribute { }

	class NotNullAttribute { }

	class PickArrayAttribute {
		private $pick_values;

		public function __construct(...$pick_values) {
			$this->pick_values = $pick_values;
		}

		public function get() {
			return $this->pick_values;
		}
	}

	abstract class PredicateAttribute {
		public abstract function test($value);
	}

	class RangeAttribute {
		private $min;
		private $max;

		public function __construct($min, $max) {
			$this->min = $min;
			$this->max = $max;
		}

		public function getMin() {
			return $this->min;
		}

		public function getMax() {
			return $this->max;
		}
	}

	class RegexAttribute extends PredicateAttribute {
		public $regex;
		public $caseInsensitive;

		public function __construct(string $regex, $caseInsensitive = false) {
			$this->setRegex($regex);
			$this->setCaseInsensitive($caseInsensitive);
		}

		public function getRegex() {
			return $this->regex;
		}

		public function getRegexWithoutDelimiters() {
			return substr($this->regex, 1, strlen($this->regex) - 2);
		}

		public function setRegex($value) {
			$len = strlen($value);
			if ($len <= 1 || substr($value, 0, 1) != '/' || !preg_match('/[^\\\]\/[a-z]*$/', $value)) {
				$value = preg_replace('/\//i', '\/', $value);
				if (substr($value, 0, 1) != '/')
					$value = "/$value";
				if ($len <= 1 || !preg_match('/[^\\\]\/[a-z]*$/', $value))
					$value .= '/';
			}
			if ($this->caseInsensitive && !preg_match('/[^\\\]\/[^\/]*i[^\/]*$/', $value))
				$value .= 'i';
			$this->regex = $value;
		}

		public function getCaseInsensitive() {
			return $this->caseInsensitive;
		}

		public function setCaseInsensitive($value) {
			$this->caseInsensitive = $value;
		}

		public function test($value) {
			return preg_match($this->regex, $value);
		}
	}

	class RequiredAttribute { }

	class StringIDAttribute extends RegexAttribute {
		public function __construct() {
			parent::__construct('/(?=^((?!--).)+$)(^[a-z]{1}[a-z0-9\-]{1,28}[a-z0-9]{1}$)/i');
		}
	}

	class TextStringAttribute extends RegexAttribute {
		private $minLength;
		private $maxLength;

		public function __construct($minLength, ...$maxLength) {
			parent::__construct('/(?=^((?![<>"’&]).)*$)/');
			$this->minLength = $minLength;
			$this->maxLength = count($maxLength) ? $maxLength[0] : $minLength;
		}

		public function test($value) {
			$len = strlen($value);
			return (!$this->minLength || $len >= $this->minLength)
				&& (!$this->maxLength || $len <= $this->maxLength)
				&& parent::test($value);
		}
	}

	class TypeAttribute {
		private $type;

		public function __construct($type) {
			$this->type = $type;
		}

		public function getType() {
			return $this->type;
		}

		public function setType($value) {
			$this->type = $value;
		}
	}

	# --- Functions ---

	function in($array) {
		return new InAttribute($array);
	}
?>
