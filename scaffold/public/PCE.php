<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file implements the main router entry point (i.e. handler)
	 * for PHPCentauri Controller-MVC Engine (PCE), which is Cluedapp's
	 * generic PHP REST Model-View-Controller (MVC) API middleware.
	 *
	 * PCE is a lightweight engine, does not support very advanced
	 * routing capabilities, and relies on a few conventions:
	 * - Controller class files must be located in ../controllers,
	 *   relative to the location of this file.
	 * - Controller classes should end with the word "Controller",
	 *   e.g. AccountController.
	 * - Action methods should be instance methods, and can have any
	 *   name.
	 * - Routes can be specified, and a default router is attempted
	 *   if no explicit action method route matches the input route
	 * - Routes are case-insensitive.
	 * - The PHPCentauri View Engine (PVE) can integrate with PCE to
	 * 	 return an HTML view as a response from a controller method.
	 * - PVE is response-agnostic, so you can return anything from
	 *   an action method. Typically you would terminate the script
	 *   in the action method with an API response by using either
	 *   the success() or fail() method, or by calling
	 *   view('ViewName') to return arbitrary HTML, JSON or text
	 *   from a view file. View files can have a template, see PVE.php.
	 * - Action methods can have phpDoc decoration comments directly
	 *   above the method, to describe how the action should be called
	 *   by PCE:
	 *   @route /controller/action					The input route should match this explicit route, for this action to be called. The first matching route is used, even if other routes could also match. If no matching explicit route is found, the default route is tried, whereby the route /ctrl/action maps to CtrlController::action. If the default router is also unsuccessful, an error is returned
	 *   @login										User needs to be logged in to use this action
	 *   @input {parameterName} {interpreted type}	Ignore the {}'s. The $parameterName action argument is injected with the value of the corresponding Http API input, and the input must have the corresponding interpreted type
	 *   @model {parameterName} {ModelClassName}	Ignore the {}'s. The $parameterName action argument is injected with a model instance with the type of the ModelClassName model class. Input values to construct the model instance is collected from Http API input values
	 *
	 * A rewrite should be added to IIS, Nginx or Apache, so that
	 * URLs are routed correctly to PCE:
	 *
	 * # Route to PCE handler (PHPCentauri Controller-MVC Engine)
	 * rewrite (?i)/api/Ctrl/(([^./]+)/)+([^./]+)/?$ /api/pce.php?controller=$1&action=$3 last;
	 *
	 * # Allow for matching of full route path
	 * rewrite (?i)/api/Ctrl/(.+)$ /api/pce.php?route=%2F$1 last;
	 */

	require_once '../vendor/autoload.php';

	function sanitize_route_component($route) {
		return preg_replace('/^[.\/]+|[.\/]+$/', '', $route);
	}

	function try_call_route_action($controller_class, $action) {
		$method_info = new ReflectionMethod("$controller_class::$action");

		$php_doc = $method_info->getDocComment();

		# Loop through each of the method's phpDoc decoration comment lines
		foreach (explode("\n", $php_doc) as $comment_line) {
			$comment_line = trim($comment_line);

			# phpDoc: @login - specifies that user must be logged in to call this action
			if (preg_match('/@login/i', $comment_line)) {
				verify_request(true);
				continue;
			}

			# phpDoc: @verb {HttpVerb} - specifies the HTTP verb(s) that this action is compatible with
			if (preg_match('/@verb\s+(.+)/i', $comment_line, $matches)) {
				$verbs = preg_split('/[\s,]+/', strtolower($matches[1]), -1, PREG_SPLIT_NO_EMPTY);
				$method = strtolower(server('REQUEST_METHOD'));
				foreach ($verbs as $verb)
					if ($method === $verb)
						continue 2;
				return [APIError::Method, []];
			}
		}

		# Inject values into action method's parameters
		$defined_params = $method_info->getParameters();
		$params = [];

		# Loop through each method parameter
		foreach ($defined_params as $param) {
			# Loop through each of the method's phpDoc decoration comment lines
			foreach (explode("\n", $php_doc) as $comment_line) {
				$name = ucfirst($param->getName());

				# phpDoc: @input {InputParameterName} {validation string used by i() function}
				if (preg_match("/@input\s+$name\s+(.+)/i", $comment_line, $matches)) {
					$input = input($name);
					$valid = validate_object([$name => $input], [$name => i('require' . ($matches[1] ? " and {$matches[1]}" : ''))], true);
					if ($valid !== true)
						return [$valid[0], ['For' => $valid[1]]];
					$params[] = $input;
					continue 2;
				}

				# phpDoc: @input-object-property {FunctionAndInputParameterName} {ClassName}
				if (preg_match("/@input-object-property\s+$name\s+(\S+)/i", $comment_line, $matches)) {
					$input_parameter_value = (array) input($name);
					$class_name = $matches[1];
					$obj = new $class_name($input_parameter_value);
					$params[] = $obj;
					continue 2;
				}

				# phpDoc: @input-object-fields {FunctionParameterName} {ClassName}
				if (preg_match("/@input-object-fields\s+$name\s+(\S+)/i", $comment_line, $matches)) {
					$class_name = $matches[1];
					$array = [];
					foreach (get_class_vars($class_name) as $key => $val)
						if (input_exists($key))
							$array[$key] = input($key);
					$params[] = new $class_name($array);
					continue 2;
				}

				# phpDoc: @raw-input {InputParameterName} {PHP code to evaluate, to create the validation value for the input value. The PHP code's evaluated result is passed to validate_object() as the validation value against which to validate the input value}
				if (preg_match("/@raw-input\s+$name\s+(.+)/i", $comment_line, $matches)) {
					$input = input($name);
					$valid = validate_object([$name => $input], [$name => eval("return {$matches[1]};")], true);
					if ($valid !== true)
						return [$valid[0], ['For' => $valid[1]]];
					$params[] = $input;
					continue 2;
				}

				# phpDoc: @model {FunctionParameterName} {ModelClassName}
				if (preg_match("/@model\s+$name\s+(\S+)/i", $comment_line, $matches)) {
					$model = $matches[1]::model(false);
					if ($model === null) {
						log_error('try_call_route_action model null');
						return [APIError::Input, []];
					}
					$params[] = $model;
					continue 2;
				}
			}

			log_error('try_call_route_action invalid parameter input');
			return ['Parameter', ['For' => $name]];
		}

		# Call the controller's action method
		$return_value = call_user_func_array([new $controller_class, $action], $params) ?? [];

		# Assume controller action call was successful if we have reached this point. If fail() was called, the script would have die()'d by now
		# This also allows controller methods to just return a value upon success (instead of having to must call success($return_value), although you can also do that if you want), which might make the controller method more intuitive to read and understand
		# Another benefit is that you don't have to return a value, nor call success(), if the action is successful, it can just naturally end, which is semantically significant
		success($return_value ?? []);
	}

	function handle_route($controller_route, $action, $full_route, $controller_path) {
		# Look for controller file
		if (file_exists($controller_path)) {

			# So a controller file was found

			# Load the controller class. The controller class's name must end with the word Controller, e.g. AccountController
			require_once $controller_path;
			$controller_prefix = ucfirst(pathinfo($controller_route ? $controller_route : $controller_path, PATHINFO_FILENAME));
			log_error('Controller prefix', $controller_prefix);
			$controller_class =  $controller_prefix . 'Controller';

			# Remember the error messages provided by try_call_route_action()
			$fails = [];

			log_error('available methods', get_class_methods($controller_class));

			# Loop through each action method, and see if the action method's route matches the input route
			foreach (get_class_methods($controller_class) as $method) {
				log_error('handle_route() inspecting', $method);

				$found = false; # found a route to a suitable action method yet?

				# Get meta information about the action method
				$method_info = new ReflectionMethod("$controller_class::$method");

				# Get the action method's PCE action specifiers, provided in the method's phpDoc-style decoration comment block
				$php_doc = $method_info->getDocComment();

				# Check if the route to action $method matches the input route (i.e. if the input route is "suitable" to the route defined for the action $method)
				$route = preg_match('/@route ([^\r\n]+)/i', $php_doc, $matches);
				if (isset($matches[1])) {
					# Check if full route path (passed in by web server) matches the route decorator on the action method
					log_error('Comparing full route', $full_route, $matches[1]);
					if ($full_route === trim(rtrim(trim($matches[1]), '/'))) {
						log_error('Full route matched');

						# So there is a match
						$found = true;
					}

					# So there is a route decorator on the action $method
					$found_controller_route = explode('/', sanitize_route_component($matches[1]));
					$found_action = array_pop($found_controller_route); # remove and get action component from route string
					$found_controller_route = sanitize_route_component(implode('/', $found_controller_route));

					# Check if there is a suitable route to this action (i.e. if the input route matches the route to this action method)
					if (strtolower($found_controller_route) == strtolower($controller_route) && strtolower($found_action) === strtolower($action)) {
						# So there is a match
						$found = true;
					} # else, the route on the action does not match the input route, try match with another action method
				}

				log_error('found a method?', $found);

				# A controller action must be a routable class instance method. If a suitable routable action method was found to exist on the controller class, then we assume that it's one of the potential actions which the caller wants to route to and invoke, and so we try to call the action method (since the route matches, but there might be other constraints that fail)
				if ($found) {
					# So a suitably routable action method was found, so try to execute the action method. The action method might fail because there could be further constraints given by other phpDoc decorator comments
					log_error('calling try_call_route_action for', $method);
					$result = try_call_route_action($controller_class, $method);
					log_error('try_call_route_action result', $result);
					$fails[] = $result;
				}
			}

			# Try default route
			$found = is_callable("$controller_class::$action");
			if ($found) {
				# So a default route exists
				log_error('try_call_route_action calling default action (i.e. the method with the same name as the action)', $action);
				$method_info = new ReflectionMethod("$controller_class::$action");
				$result = try_call_route_action($controller_class, $action);
				log_error('result', $result);
				$fails[] = $result;
			} # else, a default route doesn't exist either

			if (count($fails)) {
				log_error('calling fail with', $fails[0], $fails[0][0], $fails[0][1]);
				fail($fails[0][0], $fails[0][1]);
			}

			fail(APIError::Invalid);
		}
	}

	verify_request(false);
	$controller_route = strtolower(sanitize_route_component($_GET['controller'] ?? '')); # passed by web server rewrite
	$action = $_GET['action'] ?? ''; # the action method name, case-insensitive, passed by web server rewrite
	$full_route = trim(rtrim(trim($_GET['route'] ?? ''), '/')); # the optional full route, passed by web server rewrite
	$controller_path = sprintf('../controllers/%s.php', $controller_route); # this now contains the path to the controller file, relative to the controllers directory
	log_error('Controller-route', $controller_route, 'Action', $action, 'Full route', $full_route, 'Controller path', $controller_path);
	if ($controller_route) {
		log_error('Handling controller route directly');
		handle_route($controller_route, $action, $full_route, $controller_path);
	} else {
		foreach (glob('../controllers/*.php') as $controller_path) {
			log_error('Handling controller path', $controller_path);
			handle_route($controller_route, $action, $full_route, $controller_path);
		}
	}

	# Respond with an error if the given controller or action doesn't exist
	log_error('PCE controller or action doesn\'t exist', 'controller-route', $_GET['controller'] ?? '', 'action', $_GET['action'] ?? '');
	fail(APIError::Invalid);
?>
