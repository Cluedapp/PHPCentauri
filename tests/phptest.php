<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file defines PHPTest, which is PHPCentauri's unit test framework.
	 * It works by looking for files ending in .test.txt in the current directory.
	 * Each of these files is a test suite, and the test cases in the test suite file
	 * are each run one by one. A pass or fail is reported for each test case.
	 *
	 * Test case template:
	 *
	 * <test case description>
	 * <test case type>
	 * <value to evaluate>
	 * <comparison>
	 * <compare to value>
	 *
	 * <test case description> ::= <string>
	 * <test case type> ::= [assert, assert that]
	 * <value to evaluate> ::= { php code to evaluate, should return a value }
	 * <comparison> ::=
		  [is, <empty string>]
	      AND ([!=, not equal, ne, not, isnt, isn't, does not equal]
			OR [>, greater than, gt]
			OR [>=, greater than or equal, gte]
			OR [<, less than, lt]
			OR [<=, less than or equal, lte]
			ELSE DEFAULT [=, equal, equals])
	 * <compare to value> ::= { php code to evaluate, should return a value }
	 *
	 * Test suite file example:
	 *
	 * test 1
	 * assert
	 * 1
	 * =
	 * 1
	 *
	 * test 2
	 * assert that
	 * 2
	 * >
	 * 1
	 */

	/*
	# List all console text colors
	for ($i = 0; $i < 100; ++$i)
		echo "\x1B[{$i}m", $i, "\x1B[0m", "\n";
	*/

	$dir = getcwd();
	chdir(dirname(__FILE__));
	require_once '../lib/autoload.php';
	chdir($dir);

	$count = 0;
	foreach (glob('*') as $file) {
		if ($file != basename(__FILE__) && substr($file, strlen($file) - 9, 9) == '.test.txt') {
			ob_start();

			$test_cases = explode("\n", file_get_contents($file));
			for ($i = 0; $i < count($test_cases);) {
				if (in_array(trim($test_cases[$i]), ['assert', 'assert that'])) {
					$result = eval('return ' . $test_cases[$i + 1] . ';');
					$compare_to = eval('return ' . $test_cases[$i + 3] . ';');
					$comparison = explode(' ', trim($test_cases[$i + 2]));
					$comparison = array_slice($comparison, $comparison[0] == 'is' ? 1 : 0);
					$comparison = array_slice($comparison, 0, $comparison[count($comparison) - 1] == 'to' ? count($comparison) - 1 : count($comparison));
					$comparison = trim(implode(' ', $comparison));
					switch ($comparison) {
						case '!=': case 'not equal to': case 'ne': case 'not': case 'isnt': case 'isn\'t': case 'not equal': case 'does not equal':
							$test = is_object($result) && is_object($compare_to) ? $result != $compare_to : $result !== $compare_to;
							break;
						case '>': case 'greater than': case 'gt':
							$test = $result > $compare_to;
							break;
						case '>=': case 'greater than or equal': case 'gte':
							$test = $result >= $compare_to;
							break;
						case '<': case 'less than': case 'lt':
							$test = $result < $compare_to;
							break;
						case '<=': case 'less than or equal': case 'lte':
							$test = $result <= $compare_to;
							break;
						default:
							$test = is_object($result) && is_object($compare_to) ? $result == $compare_to : $result === $compare_to;
							break;
					}
					if ($test)
						echo "\x1B[42mPASS:\x1B[0m ", $test_cases[$i - 1], "\n";
					else
						echo "\x1B[41mFAIL:\x1B[0m ", $test_cases[$i - 1], "\n", "\tValue to evaluate: ", json_encode($result), "\n", "\tCompare to value: ", json_encode($compare_to), "\n", "\tComparison: $comparison", "\n";
					$i += 4;
				} else
					++$i;
			}

			$str = explode("\n", ob_get_clean());
			echo $count++ ? "\n" : '', basename($file), "\n", "\t", trim(array_reduce($str, function($acc, $cur) { return "$acc\n\t$cur"; }, '')), "\n";
		}
	}
?>
