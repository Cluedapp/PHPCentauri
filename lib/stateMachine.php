<?php
	/**
	 * @package PHPCentauri
	 *
	 *  StateMachine parses the given $input, into an array of possible
	 *  states, and loops over the resulting possible state inputs, from
	 *  start to finish.
	 *
	 *  States $states is a hash of objects (automata instances)
	 *  that inherit from class State.
	 */

	abstract class StateMachine {
		public $state;
		public $states;
		public $executing;
		private $inputParsedYet;

		public function __construct() {
			$this->states = [];
			$this->executing = false;
		}

		# Parse an input object into an array of possible automata inputs
		public abstract function parseInput($input);

		# Abstract factory pattern, to create a new instance of an automata state
		public abstract function factory($input);

		# Execute the state machine engine (linearly process all inputs)
		# to arrive at a final value
		public function execute($input) {
			# Initialize the state machine
			$this->executing = true;
			$input = $this->parseInput($input);

			# Move through the inputs, changing the state machine's
			# state along the way during each loop-iteration
			for ($i = 0, $len = count($input); $i < $len; ++$i) {
				$next = $input[$i];
				if (!strlen(trim($next))) continue;

				if ($this->state && $this->state->isKnownInput($next))
					$this->state = $this->state->move($next);
				else {
					if (!isset($this->states[$next])) $this->states[$next] = $this->factory($next);
					$this->state = $this->states[$next]->set($this->state);
				}
			}
			
			$this->executing = false;

			# Chain the state machine
			return $this;
		}

		# Set default/custom states, and let unknown states be created by the factory method
		# This method should return $this to achieve chaining
		public function setStates($states) {
			$this->states = $states;
			return $this;
		}

		# Get the current (final) value represented by the state machine, after processing all inputs
		public function get() {
			if ($this->state instanceof State) return $this->state->get();
			else return $this->state;
		}
	}

	# TokenStateMachine assumes that $input is a string,
	# and $states is a hash of objects that inherit from interface State.
	abstract class TokenStateMachine extends StateMachine {
		public function parseInput($input) {
			return preg_split('/(?:\s*([(),\s])\s*)(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string)$input, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		}
	}

	/** Declare a state machine that can operate in the context of a PHP environment */
	class PHPStateMachine extends TokenStateMachine {
		# Generate a new state based on some input
		public function factory($input) {
			$className = strtoupper(substr($input, 0, 1)) . substr($input, 1) . 'State';
			if (class_exists($className)) {
				log_error('PHPStateMachine factory', '$input =', $input, 'class exists', $className);
				return new $className;
			} elseif (function_exists($input)) {
				log_error('PHPStateMachine factory', '$input =', $input, 'function exists');
				return new FunctionState($input);
			} elseif (defined($input)) {
				log_error('PHPStateMachine factory', '$input =', $input, 'constant defined');
				return new ValueState(constant($input));
			} elseif (isset($$input)) {
				log_error('PHPStateMachine factory', '$input =', $input, 'variable defined');
				return new ValueState($$input);
			} else {
				log_error('PHPStateMachine factory', '$input =', $input, 'returning new ValueState');
				return new ValueState($input);
			}
		}
	}

	/** An automata */
	abstract class State {
		# Self-explanatory
		protected $parent;

		# The actual "read-value", or "output value", of this automata
		public $value;

		# Get the read-value of the automata
		public function get() {
			return $this->value;
		}

		# Used to set an executing state machine's state, to an instance of this automata
		public function set($parent) {
			$class = get_class($this);
			$state = new $class;
			$state->parent = $parent;
			return $state;
		}

		# Return true if the given input is known to (interpretable by) this automata
		public function isKnownInput($input) {
			return false;
		}

		# Move the state machine's state back to this automata's parent state
		public function end($value, $parent = null) {
			$this->value = $value;
			if ($parent) $this->parent = $parent;

			if ($this->parent) {
				$this->parent->move($value);
				return $this->parent;
			}

			# Move the state machine to this automata itself, since this automata has no parent state
			return $this;
		}

		/** Act upon an input (i.e. do a behaviour, depending on the input given, to
		 *  alter the automata's internal state and/or read-value based on its current
		 *  internal state, etc.)
		 */
		public function move($input) {
			return $this;
		}
	}

	/** A type of automata that collects arguments and passes all the collected values to a new collector class's constructor */
	class CollectorState extends State {
		protected $collectorClassName;

		public function __construct($collectorClassName) {
			$this->collectorClassName = $collectorClassName;
			$this->value = [];
		}

		public function get() {
			return $this->collectorClassName ? (new $this->collectorClassName(...$this->value)) : $this->value;
		}

		public function isKnownInput($input) {
			return $input instanceof State || is_numeric($input) || in_array($input, ['(', ',', ')', 'true', 'false', 'and', 'or']) || !in_array(json_decode($input), [false, null]);
		}

		public function move($input) {
			switch ($input) {
				case 'and':
				case 'or':
				case ',':
					return $this;
				case '(':
					if ($this->parent == null) {
						$state = new CollectorState(null);
						$state->parent = $this;
						return $state;
					}
					return $this;
				case ')':
					# Calculate the final value of the automata's state
					return $this->end($this->get());
				default:
					# Add the input to the list of values
					if (is_array($input)) {
						$this->value = array_merge($this->value, $input);
					} else {
						$this->value[] = is_string($input) ? json_decode($input) : $input;
					}
					$ret = $this->handleDefaultInput($input);
					return $ret ? $ret : $this;
			}
		}

		public function set($parent) {
			$state = clone $this;
			if ($parent !== null && $parent instanceof State && (!$parent instanceof CollectorState)) {
				$state->move($parent->value);
			} else {
				$state->parent = $parent;
			}
			return $state;
		}

		public function handleDefaultInput($input) {
			return null;
		}
	}

	class AndState extends CollectorState {
		public function __construct() {
			parent::__construct('BoolAndAttribute');
		}

		public function handleDefaultInput($input) {
			# Short-circuit: If the input value is false, and this state has a parent state, move this and-automata's parent state to false, and return the state machine's state, back to the parent automata
			if (!$input && $this->parent) {
				log_error('AndState short-circuit');
				$this->parent->move(false);
				return $this->parent;
			}

			# If the input was true, or this and-automata has no parent, return this instance back as the state machine's state
			return $this;
		}
	}

	class ArrayState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('array'), $parent);
		}
	}

	class BoolState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('bool'), $parent);
		}
	}

	class BoolishState extends State {
		public function set($parent) {
			return $this->end(new BoolishAttribute, $parent);
		}
	}

	class DateIntState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('date_int'), $parent);
		}
	}

	class DateStringState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('date_string'), $parent);
		}
	}

	class EmailState extends State {
		public function set($parent) {
			return $this->end(new EmailAttribute, $parent);
		}
	}

	class FunctionState extends State {
		private $function_name;

		public function __construct($function_name) {
			// $this->value = [$function_name];
			$this->function_name = $function_name;
		}

		public function set($parent) {
			return $this->end(new FunctionAttribute($this->function_name), $parent);
		}
	}

	class IntState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('int'), $parent);
		}
	}

	class IntsState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('ints'), $parent);
		}
	}

	class JSONObjectState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('json_object'), $parent);
		}
	}

	class JSONStringState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('json_string'), $parent);
		}
	}

	class NotNullState extends State {
		public function set($parent) {
			return $this->end(new NotNullAttribute, $parent);
		}
	}

	class NumericState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('numeric'), $parent);
		}
	}

	class NumericsState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('numerics'), $parent);
		}
	}

	class OrState extends CollectorState {
		public function __construct() {
			parent::__construct('BoolOrAttribute');
		}

		public function handleDefaultInput($input) {
			# Short-circuit: If the input value is true, and this state has a parent state, move this or-automata's parent state to true, and return the state machine's state, back to the parent automata
			if ($input && $this->parent) {
				log_error('OrState short-circuit');
				$this->parent->move(true);
				return $this->parent;
			}

			# If the input was false, or this or-automata has no parent, return this instance back as the state machine's state
			return $this;
		}
	}

	class RangeState extends CollectorState {
		public function __construct() {
			parent::__construct('RangeAttribute');
		}
	}

	# RegexState can be created like the following:
	# i('regex("/.*/")') === i('regex(".*")')
	class RegexState extends CollectorState {
		public function __construct() {
			parent::__construct('RegexAttribute');
		}
	}

	class RequireState extends State {
		public function set($parent) {
			return $this->end(new RequiredAttribute, $parent);
		}
	}

	class StringIDState extends State {
		public function set($parent) {
			return $this->end(new StringIDAttribute, $parent);
		}
	}

	class StringIDsState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('stringids'), $parent);
		}
	}

	class StringState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('string'), $parent);
		}
	}

	class StringsState extends State {
		public function set($parent) {
			return $this->end(new TypeAttribute('strings'), $parent);
		}
	}

	class TextStringState extends CollectorState {
		public function __construct() {
			parent::__construct('TextStringAttribute');
		}
	}

	class ValueState extends State {
		public function __construct($value) {
			$this->value = $value;
		}

		public function set($parent) {
			return $this->end($this->value, $parent);
		}
	}

	/**
	 *  Interpret a script string
	 */
	function i($script) {
		log_error("i($script)");
		$states = [
			'!null' => new NotNullState,
			'date_int' => new DateIntState,
			'date_string' => new DateStringState,
			'json_string' => new JSONStringState,
			'json_object' => new JSONObjectState,
			'stringid' => new StringIDState,
			'stringids' => new StringIDsState,
			'text' => new TextStringState
		];
		return (new PHPStateMachine)->setStates($states)->execute($script)->get();
	}

	/*
	class BoolAndAttribute {
		public function __construct(...$value) {
			$this->value = $value;
		}
	}

	class BoolOrAttribute {
		public function __construct(...$value) {
			$this->value = $value;
		}
	}

	abstract class PredicateAttribute {
		public abstract function test($value);
	}

	class RegexAttribute extends PredicateAttribute {
		public $regex;

		public function __construct(string $regex) {
			$this->regex = $regex;
		}

		public function getRegex() {
			return $this->regex;
		}

		public function setRegex($value) {
			$this->regex = $value;
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

	print_r(i('and(1, 2)'));
	print_r(i('and(1 and 2 and 3 and 4, 1 and 2)'));
	print_r(i('and(require, stringid, regex("/[a-z][a-z0-9]+/"))'));
	print_r(i('1 or 2 and 3'));
	*/
?>
