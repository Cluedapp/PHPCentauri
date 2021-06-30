<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file is the autoloader for PHPCentauri,
	 * include it in a PHP file to pull in the entire
	 * PHPCentauri library in one go
	 */

	if (!function_exists('phpcentauri_autoload')) {
		function get_absolute_path($path) {
			$start_slash = !!preg_match('/^(\/|\\\\)/', $path) ? substr($path, 0, 1) : '';
			$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
			$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
			$absolutes = array();
			foreach ($parts as $part) {
				if ('.' == $part) continue;
				if ('..' == $part) {
					array_pop($absolutes);
				} else {
					$absolutes[] = $part;
				}
			}
			return $start_slash . implode(DIRECTORY_SEPARATOR, $absolutes);
		}

		function phpcentauri_require($file) {
			static $files = [];
			$file = realpath($file);
			if (!in_array($file, $files) && file_exists($file) && is_file($file)) {
				$files[] = $file;
				require_once $file;
			}
		}

		function require_dir($dir) {
			foreach ($dir as $d)
				foreach (scandir($d) as $file)
					if (!in_array($file, ['.', '..']))
						if (is_dir("$d/$file"))
							require_dir(["$d/$file"]);
						elseif (preg_match('/\.php$/', $file) && realpath("$d/$file") != realpath(__FILE__))
							phpcentauri_require("$d/$file");
		}

		function search_for_file($file) {
			# Search for "$file" (and if it exists, return its contents), within the current directory, and all ancestor directories, up to and including the root directory

			$dir = getcwd();
			do {
				if (file_exists($file)) {
					return file_get_contents($file);
				}
				$prev_dir = getcwd();
				chdir('..');
			} while ($prev_dir != getcwd());
			chdir($dir);
			return null;
		}

		function search_for_require($file) {
			# Search for "$file" (and if it exists, require it), within the current directory, and all ancestor directories, up to and including the root directory

			$dir = getcwd();
			do {
				if (file_exists($file)) {
					phpcentauri_require($file);
					break;
				}
				$prev_dir = getcwd();
				chdir('..');
			} while ($prev_dir != getcwd());
			chdir($dir);
		}

		function search_for_require_dir($dir_to_require) {
			# Search for "$dir_to_require" (and if it exists, require it), within the current directory, and all ancestor directories, up to and including the root directory

			$dir = getcwd();
			do {
				if (is_dir($dir_to_require)) {
					require_dir([$dir_to_require]);
					break;
				}
				$prev_dir = getcwd();
				chdir('..');
			} while ($prev_dir != getcwd());
			chdir($dir);
		}

		function search_for_class($search_dir, $class) {
			# This function tries to locate the class given by "$class"

			$dir = getcwd();
			$prev_dir = $dir;
			chdir($search_dir);
			do {
				$a = array_merge(["../entities/$class.php", "../models/$class.php", sprintf("../entities/%s.php", string_to_variable_name(variable_name_to_readable_string($class))), sprintf("../models/%s.php", strtolower(str_replace(' ', '_', variable_name_to_readable_string($class)))), sprintf("../models/%s.php", strtolower(str_replace(' ', '_', variable_name_to_readable_string($class))))], glob('../entities/*'), glob('../models/*'));
				foreach ($a as $file) {
					$file = trim($file);
					search_for_require(realpath($file));
					if (class_exists($class))
						break;
				}
				$prev_dir = getcwd();
				chdir('..');
			} while ($prev_dir != getcwd());
			chdir($dir);
		}

		function phpcentauri_autoload_class($class) {
			# This is a helper function that is intended to run when PHP needs to autoload the class given by "$class", and this function, when it is then called, tries its best to locate the class
			# One can also run this function manually, to load a class just-in-time or on demand, instead of waiting for PHP to go through its motions to do so and eventually call this function

			log_error('PHPCentauri class autoloader', $class);
			# Search for the class in the currently-running script file's directory, and if it's not found, then search in the current diretory, because the currently-running script file's directory is not necessarily the same as the current directory
			search_for_class(dirname(__FILE__), $class);
			if (!class_exists($class))
				search_for_class(getcwd(), $class);

			if (!class_exists($class))
				log_error('PHPCentauri class autoloader', 'Could not find class', $class);
		}

		function phpcentauri_core_loaded_yet($value = null) {
			static $local = null;
			if ($local == null) $local = $value;
			return $local;
		}

		function phpcentauri_autoload() {
			# Autodetect which PHPCentauri library version to use
			$lib = substr(phpversion(), 0, 1) == '5' ? 'lib5' : 'lib';

			# Get autoload.php's (i.e. this file's) directory
			$lib_dir = dirname(__FILE__);

			# (Optional) Load settings.php (using current directory as starting point)
			$environment = search_for_file('environment.json'); # this file, environment.json, should generally not be added to a Git repo, as it contains a JSON string with environment-specific "meta settings" (i.e. settings that describe settings) configuration values, for example it is used to configure which PHPCentauri settings.php file to load, which is useful/applicable when settings files and filenames themselves are fixed, but the specific settings file to load, should be selectable at runtime, e.g. for development or production
			$environment = $environment ? json_decode($environment) : null;
			search_for_require($environment ? $environment->settings_file : 'settings.php');

			# (Optional) Load settings.php (using autoload.php's, i.e. this file's, directory as starting point)
			if (!defined('AppSettings')) {
				$dir = getcwd();
				chdir($lib_dir);
				$environment = search_for_file('environment.json'); # see comment above about environment.json
				$environment = $environment ? json_decode($environment) : null;
				search_for_require($environment ? $environment->settings_file : 'settings.php');
				chdir($dir);
			}

			# (Required) Load PHPCentauri core library
			if (!phpcentauri_core_loaded_yet()) {
				phpcentauri_core_loaded_yet(true);

				# Load config.php (part of PHPCentauri and should be in the same directory as autoload.php, i.e. this file)
				search_for_require(get_absolute_path("$lib_dir/config.php"));

				require_dir([$lib_dir]);

				if (!class_exists('APIError') && interface_exists('IAPIError')) {
					class APIError implements IAPIError { }
				}

				# Register PHPCentauri's class autoloader function, to search for unknown entity or model classes when they are referenced by a PHPCentauri API
				spl_autoload_register(function($class) {
					# This function is called automatically by PHP each time an unknown class is referenced
					phpcentauri_autoload_class($class);
				});
			}

			# (Optional) Load "../lib_api" directory (using current directory as starting point)
			if (is_dir("../{$lib}_api"))
				require_dir(glob("../{$lib}_api"));

			# (Optional) Load "lib_api" directory (using current directory as starting point)
			if (is_dir("{$lib}_api"))
				require_dir(glob("{$lib}_api"));

			# (Optional) Load "lib_api" directory (using autoload.php's, i.e. this file's, directory as starting point)
			if (!is_dir("../{$lib}_api") && !is_dir("{$lib}_api")) {
				$dir = getcwd();
				chdir($lib_dir);
				search_for_require_dir("{$lib}_api");
				chdir($dir);
			}
		}
	}

	phpcentauri_autoload();
?>
