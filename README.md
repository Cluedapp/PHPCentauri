# PHPCentauri

This is PHPCentauri, a PHP web API framework, by Cluedapp.

PHPCentauri provides an MVC structure and a programming library, on which to build a PHP API. In other words, it is a framework on top of which an MVC API can be written.

PHPCentauri includes a lightweight MVC engine, ORM mapper, a REST entity engine, an RDBMS database wrapper, a state machine, a generic HTTP API, a unit test framework, functional programming constructs, Google and Google Drive integration, and easy-to-use functions for encryption, email and JSON manipulation.

The main MVC API engine components are:

- PCE: PHPCentauri Controller-MVC Engine. It allows you to implement MVC controllers and actions in your API.
- PIE: PHPCentauri Item Engine. It allows you to implement REST endpoints for accessing database entities in your API. Currently, Azure Table Storage entities are supported.
- PVE: PHPCentauri View Engine. It allows you to implement views in your API. Views can be returned from a controller action, or they can be accessed directly. A basic template engine, called PCHTML, which is implemented by PVE, is built in to PHPCentauri. PVE is _view-format agnostic_, so you can use normal HTML, plain text, PHP, etc. The only requirement is that the content returned from a view should ultimately be wrapped in a top-level <content> tag in your view file or in one of its master template files.

[PHPCentauri official website on GitHub](https://github.com/Cluedapp/PHPCentauri)

# Installation

- Create a new API project directory on your filesystem. Your API project directory can have any path and name.
- Install [Composer](https://getcomposer.org).
- Create a composer.json file.
```
{
  "name": "My API's name",
  "type": "api",
  "description": "My API's description",
  "require": {
	"cluedapp/phpcentauri": "dev-master"
  }
}
```
- Install PHPCentauri into your API project directory:

  > composer install

- You're done.

# Scaffolding

It is assumed that PHPCentauri has been installed as per the above steps.

- Automatic scaffolding:
  - Run _vendor/cluedapp/phpcentauri/scaffold.bat_ and follow the instructions.
  - Change the settings in _settings.php_ in your API project directory as appropriate.

- Manual scaffolding:
  - For PHP >= 7.0: create a _lib_api_ subdirectory inside your API project directory.
  - For PHP <= 5.6: create a _lib5_api_ subdirectory inside your API project directory.
  - Inspect the files, subdirectories, and files in the subdirectories, of the _vendor/cluedapp/phpcentauri/scaffold_ directory, under your API project directory.
  - Create _cache_, _controllers_, _entities_, _models_ and _views_ subdirectories in your API project directory, similar to subdirectories under the _vendor/cluedapp/phpcentauri/scaffold_ directory.
    - The _cache_ subdirectory should be empty. It is used by PHPCentauri as a temporary directory.
    - The _controllers_ subdirectory contains one PHPCentauri Controller Engine (PCE) controller class per file. The public methods in a controller class represent the MVC routable actions of that controller.
    - The _entities_ subdirectory contains one PHPCentauri Item Engine (PIE) class per file. Each class defines the rules for one Azure Table Storage database table. There is one table definition per file. As a whole, all the files together represent the Azure Table Storage database as used by your API. Please see the _phpcentauri_ directory for more information.
    - The _models_ subdirectory contains one MVC model class per file. Models can be used as a controller input field. Please see the scaffold example directory for an example model class file, and the User.php controller for an example on how to use a model class as a controller action input parameter.
    - The _views_ subdirectory contains PCHTML view files. The .pchtml extension is the default, conventional extension used by PHPCentauri View Engine (PVE).
    - The _lib_api_ (or _lib5_api_) subdirectory can contain any custom PHP files. So, put any PHP files here, which you want to have included automatically in your API, for example startup code, or your own classes and functions for use in your API.
  - Copy _vendor/cluedapp/phpcentauri/scaffold/settings.php_ to your API project directory, and change the settings as appropriate for your API.
  - Include the relevant required Composer packages, as suggested in the _vendor/cluedapp/phpcentauri/composer.json_ Composer file, into your own _composer.json_ file, inside your API project directory.
  - Run _composer install_ inside your API project directory.

# Server setup

This procedure explains how to make the scaffolded API that was created above, accessible from your website.

You can add your API to an existing website, or create a new website specifically for your API. The choice is up to you.

For the below steps, it is assumed that a website has already been created in Nginx, Apache or IIS, and that the website is accessible with a HTTP/HTTPS URL.

- Create or locate the physical filesystem path that is mapped to the document root directory for your API's HTTP/HTTPS website endpoint. We'll call this directory, your API website directory.
  - Nginx: _root_ directive
  - Apache: _DocumentRoot_ directive
  - IIS: _Physical Path_ setting in IIS Manager (%windir%\system32\inetsrv\InetMgr.exe)
- Is is crucially important to now take the following point to heart: your API website directory is not (and should not be) the same directory as your API project directory.
- Locate your API project directory on your filesystem. You created the API project directory during the initial installation step in this readme document.
  - Create a _public_ subdirectory in your API project directory. If you used automatic scaffolding to generate your API's directory structure, then the _public_ directory will already be there.
  - Now, you can do one of two things, depending on your own preference.
    - Either make a _symbolic link_ from this _public_ directory (the target), to an _api_ subdirectory (the link), in your API website directory.
	  - Your API endpoint URL will thus have the form http://api.example.com/api/...
	- Or, configure your web server application, to make your API website endpoint's root directory, this  _public_ directory.
	  - Your API endpoint URL will thus have the form: http://api.example.com/...
	- Both of the above options will yield a similar result, the only difference being the _api/_ part at the end of the first endpoint's URL.
- Make _hard links_ from PCE.php, PIE.php and PVE.php in _vendor/cluedapp/phpcentauri/scaffold/public_ to the _public_ subdirectory in your API project directory (the API website's _api_ directory points to the _public_ directory, if you configured it this way, _api_ = _public_). These files are the "engine" of your PHPCentauri API. The reason for making _hard links_ instead of copying them, is so that they will be updated when PHPCentauri is updated through Composer. However, it is also fine to copy them, instead of creating _hard links_.
- Add the following routes to your web server configuration. You can add none, some, or all of them, depending on the PHPCentauri functionality you need.

```
# Route to PCE controller (PHPCentauri Controller-MVC Engine)
rewrite (?i)/api/Ctrl/(([^.?/]+)/)+([^.?/]+)/?$ /api/pce.php?controller=$1&action=$3 last;

# Allow for matching of full route path
rewrite (?i)/api/Ctrl/(.+)$ /api/pce.php?route=%2F$1 last;
```

```
# Route to PIE controller (PHPCentauri Item Engine)
rewrite (?i)/api/Item/([^.?/]+)/(add|delete|edit|list|pic_add|pic_delete|pic_list|pic_list_count)/?$ /api/pie.php?item=$1&action=$2 last;
```

```
# Route to PVE controller (PHPCentauri View Engine)
rewrite (?i)/api/View/(.+)/?$ /api/pve.php?view=$1.pchtml&view_dir=/path/to/views/subdirectory/under/api/project/directory last;
```

- You can create your own custom PCE controller routes, depending on the paths into which you organize your API project's PCE controller classes. The above PCE controller route was just given as a typical default example.

- You can place .pchtml views in any directory, they do not have to follow the _/api/View_ route, as shown above. The fact that the _/api/View_ route above is prepended with _/api/View_ was just for the sake of an example. However, to keep things clear, you should place views into one directory, and the path to that directory should be in a location that is relative to the _public_ directory, as configured in your _settings.php_ file's _ViewRelativePath_ setting.

- Typically, .pchtml files would be scattered throughout your website, and your PHPCentauri API would not be directly accessible. Only PCE.php, PIE.php and PVE.php are accessible over HTTP. All other components of your API are _hidden_, since only the _public_ directory is exposed, and none of the other PHPCentauri directories in your API project's directory.

# Basic usage

- PHPCentauri uses the convention (the assumption) that your API's code has been organized into specific subdirectories in your API project directory.

- A typical PHPCentauri API project's root directory structure is:

  - cache
  - controllers
  - entities
  - lib_api (or lib5_api)
  - models
  - public
  - vendor
  - views
  - settings.php

- This directory structure is similar to the _vendor/cluedapp/phpcentauri/scaffold_ directory, for your reference.
  
- You will generally put your API's PHP code files inside the _controllers_, _entities_, _lib_api_ (or _lib5_api_), _public_, _models_ and _views_ subdirectories. The type of file you are adding, will dictate into which subdirectory the file will go, e.g. if you want to add a new controller class, you create a new PHP file in the _controllers_ subdirectory. If you want to add a new entity class, model class or view, you create a new PHP file in the _entities_, _models_ or _views_ subdirectory, respectively. For an _always-included_ PHP file, add a new PHP code file to the _lib_api_ subdirectory.

- Since it is dangerous to directly expose the contents of your API project's root directory over HTTP/HTTPS, you should only expose the _public_ subdirectory, as the root of your API's HTTP/HTTPS endpoint. This is to avoid your API's controllers, entities, models, views and other content from being directly exposed over HTTP/HTTPS. By only exposing the _public_ subdirectory as your API endpoint's root directory on your web server, you will protect yourself from the theft of your API's code.

- The _public_ subdirectory will usually contain PCE.php, PIE.php and PVE.php, along with any other files that you want to have _exposed_ as the public part of your API. Files outside of _public_ can be considered as the unexposed, protected part of your API.

- To pull in the PHPCentauri library into one of your own custom API files in the _public_ subdirectory, using Composer, then the following line should be somewhere at the top of your code:

```php
require_once '../vendor/autoload.php';
```

- An example PHPCentauri API code file to return a JSON HTTP response, containing the current time (e.g. located in _{your API project directory}/public/time.php_):

```php
<?php
	require_once '../vendor/autoload.php';
	$time = time();
	success(['Time' => $time]);
?>
```

- To call the time.php API, you would use an HTTP URL such as *http://api.example.com/api/time*, depending on your web server configuration. To hide the fact that you are using PHP on your web server, you should use your web server's URL rewrite functions, to rewrite URLs in order to add a .php extension, where appropriate.

# Controllers

This section explains how to use PCE, the PHPCentauri Controller-MVC Engine, to implement MVC controllers and actions in your API.

- Typically, MVC controller action routes use the following form: http://example.com/Controller/Action

- The MVC Controller referenced in the route, is a class, and the MVC Action referenced in the route, is an instance method in the MVC Controller class.

- To reiterate: In PCE, the MVC Controller class is simply a regular PHP class, and MVC Actions are regular instance methods that you put in the controller class.

- However, all that being said, PCE routes can have any format, on condition that the web server passes the route to PCE.php correctly.

An example of creating your own MVC controller and action in PCE is now given.

- Create a new file called <X>Controller.php in the _controllers_ subdirectory, where <X> is the desired controller name, e.g. DocumentController.php. In this example, we will use DocumentController.php, but you can use any controller name. The actions are a bit contrived, because the focus is on illustrating how controller actions work in PCE, and not what the actions in this specific example are doing.

```php
class DocumentController {
	private $data = [['ID' => 1, 'Name' => 'Document1'], ['ID' => 2, 'Name' => 'Document2']];

	/**
	 * This action can be reached with the default route handler, using the route URL "Document/GetDocuments"
	 */
	public function getDocuments() {
		success($this->data);
	}

	/**
	 * This action contains a phpDoc-style comment with a PCE action specifier, specifying that the action must be called with a specific route URL, namely "Document/FirstDocument"
	 * It also shows that a value can be returned from an action, which will be used as the action's result in the response that the API makes to the API request
	 *
	 * @route Document/FirstDocument
	 */
	public function returnTheFirstDocument() {
		return $this->data[0];
	}

	/**
	 * @input ID int
	 * @input Name string
	 */
	public function getDocumentByIDOrName($id, $name) {
		foreach ($this->data as $item)
			if ($item->ID == $id || $item->Name == $name)
				return $item; # or success($item);
		fail('Nonexist');
	}

	/**
	 * @login
	 */
	public function loggedIn($id, $name) {
		success(['LoggedIn' => 1]); # or return ['LoggedIn' => 1];
	}

	/**
	 * @raw-input Docs new ArrayAttribute(['ID' => i('int'), 'Name' => i('string')])
	 */
	public function getMultipleDocuments($docs) {
		$ids = [];
		$names = [];
		$output = [];

		foreach ($data as $item)
			foreach ($docs as $doc)
				if (!in_array($id, $ids) && !in_array($name, $names) && ($item->ID == $doc->ID || $item->Name == $doc->Name)) {
					$ids[] = $id;
					$names[] = $name;
					$output[] = $doc;
				}

		success(['Documents' => $output]); # or return ['Documents' => $output];
	}
}
```

- Please note that a new controller class instance is created for each request, and that class instances are not cached, so data in any instance fields are lost when the request for the controller has ended.

- An action method's PCE action specifiers must be provided in a phpDoc-style comment at the top of the action method. An action method can have zero or more action specifiers. There can be only one action specifier per line in the phpDoc-style comment block. Lines in the phpDoc-style comment do not need to contain an action specifier, the comment block can contain a mixture of action specifiers and regular text. The currently supported action specifiers are:
  - @login
    - The action can only be called if the user is logged in. A login error is given if the action is called and the user is not logged in
  - @input
    - Usage: @input {input JSON parameter name} {parameter type, e.g. json, string, int}
	- The input JSON parameter name is case-insensitive. @input parameters can be given in any order, i.e. the order does not have to be the same as the order in which parameters are defined in the action method's signature.
	- The {} curly braces used above should not be placed in your PCE action specifier.
	- The {} curly braces used above should not be placed in your PCE action specifier.
  - @input-object-fields 
    - Usage: @input-object-fields {FunctionParameterName} {ClassName}
	- This action specifier collects the input arguments that correspond to the field names for class "ClassName" name created a new object of class "ClassName", assigns the collected input arguments to the properties of the new object instance, an assigns the object as the argument for the controller action's "FunctionParameterName"  parameter
  - @input-object-property 
    - Usage: @input-object-property {FunctionAndInputParameterName} {ClassName}
	- This action specifier collects the input argument whose name is "FunctionAndInputParameterName", converts it to an array, passes it as the first and only argument for the class "ClassName", and assigns it as the argument for the controller action's "FunctionAndInputParameterName" parameter
	- The {} curly braces used above should not be placed in your PCE action specifier.
	- The {} curly braces used above should not be placed in your PCE action specifier.
  - @model
    - Usage: @model {input JSON parameter name} {model class name}
	- The input JSON parameter name is case-insensitive. @model parameters can be given in any order, i.e. the order does not have to be the same as the order in which parameters are defined in the action method's signature.
  - @raw-input
    - Usage: @raw-input {input JSON parameter name} {raw PHP code to evaluate to create the validation value against which the parameter's input value is validated}
	- The input JSON parameter name is case-insensitive. @model parameters can be given in any order, i.e. the order does not have to be the same as the order in which parameters are defined in the action method's signature.
	- The {} curly braces used above should not be placed in your PCE action specifier.
  - @returns
    - Usage: @returns {description of what the action returns}
	- The {} curly braces used above should not be placed in your PCE action specifier.
  - @route
    - Usage: @route {route}
	- The route can start with a / (forward slash). Starting a route with or without a / will both have the same effect. The route should be relative to your API's base URL, e.g. if your base URL is http://www.example.com, and the full URL to the action is http://www.example.com/Controller/Action, then the route must be "/Controller/Action" (without the quotes).
	- The @route PCE action specifier makes it possible to specify a different controller name than the controller class in which the action is defined. To use this feature (i.e. using a different controller name for the class and the route) currently requires an additional route also be added to your web server.
	- The {} curly braces used above should not be placed in your PCE action specifier.
	- To allow PCE to match on the full route string (instead of on the controller and action separately), optionally if that is a use case of yours, then your web server should pass the full route path string to PCE.php, so that it can do this for you.
  - @verb
    - Usage: @verb {comma or space-separated HTTP verbs}
	- Allows you to provide the allowed HTTP verbs for this controller action
	- Example: @verb GET POST
	- The {} curly braces used above should not be placed in your PCE action specifier.

- An action can return a value, which will be used as the action's result in the response created by the API, for the API request.
  Alternatively, an action can call success() to return nothing, or success($return_value) to return a value.
  Finally, an action can also just do nothing when it is finished. In other words, neither return anything nor call the success function. The API will then succeed, and return an empty response.

- Model classes can be used by PCE action methods, by using the @model action specifier. Model classes must be placed in the _models_ subdirectory. An example follows:

  - Create a model class, and place it in _models/DocumentLookup.php_:

```php
class DocumentLookup {
	public $ID;
	public $Name;
}
```

  - To use the created model class as a parameter type in a PCE controller action:

```php
class DocumentController {
	private $data = [['ID' => 1, 'Name' => 'Document1'], ['ID' => 2, 'Name' => 'Document2']];

	/**
	 * This action expects a JSON input value with a value that represents a DocumentLookup object
	 * @model document DocumentLookup
	 */
	public function getDoc($document) {
		return where($this->data, $document);
	}
}
```

- To return a PCHTML view from a controller action:

  - Create a new view file in your _views_ subdirectory, e.g. my-view-file.pchtml

```
<template>null</template>
<content>This is a test</content>
```

  - Create a controller action to return the view:

```php
class MyTestController {
	function test() {
		view('my-view-file');
	}
}
```

  - Call the controller action method with an appropriate URL, e.g. http://www.example.com/pce.php?controller=MyTest&action=Test. The rendered view will be returned.

- Child views can be nested inside parent views by setting the <template> tag to the path of a parent view for the child view. Tags from a child view can be rendered in the parent view with the _render_ function, e.g.:

```php
<!-- This is a child PCHTML view -->
<template>parent.pchtml</template>
<title>This is a title</title>
<body>
	This is a body
</body>
```

```php
<!-- This is a parent PCHTML view -->
<template>null</template>
<content>
	<head>
		<title>Child title: <?php render('title'); ?></title>
	</head>
	<body>
		The child's body is: <?php render('body'); ?>

		And this is custom text in the parent view
	</body>
</content>
```

# Frontend integration

- [JSCentauri](https://github.com/Cluedapp/JSCentauri) is a Javascript web library with built-in PHPCentauri API support. JSCentauri is also maintained by Cluedapp.

- To configure PHPCentauri (on the backend) and JSCentauri (on the frontend) to interoperate correctly, ensure the following:
  - PHPCentauri API protocol version. Ensure that the following two values are corresponding. The server and the browser can only communicate correctly if they have both agreed to use the same protocol version.
    - PHPCentauri API, settings.php: 'HttpApiProtocol' setting (numeric version number)
	- JSCentauri, settings.js: JSCentauriSettings.apiProtocol: 'httpX' (X is the matching numeric version number specified in the API above)
	- As of this writing, Http4 is the latest PHPCentauri API protocol version supported by PHPCentauri and JSCentauri. Thus, to use the Http4 API Protocol:
	  - settings.php: 'HttpApiProtocol' => 4
	  - settings.js: JSCentauriSettings.apiProtocol = 'http4'

  - User sessions. Configured with the 'SessionStore' setting in your PHPCentauri API's settings.php. It can be either 'file' or 'redis'.
    - 'SessionStore' => 'file' # use the cache directory in your API project directory to store user sessions
    - 'SessionStore' => 'redis' # use Redis to store user sessions. Redis should be set up and running on your server, and the 'RedisConnectionString' setting be set correctly

# Debugging

- Use your Nginx, Apache and IIS access and error log files. These log files could indicate the error that occurred, and what the reason is that your API is not accessible from your website endpoint.
- Use PHP's error log file.
- Open settings.php and note the path specified in the LogFile setting. Open the log file. PHPCentauri writes log entries into this file.
- Use an API testing tool such as Fiddler (Windows standalone), Postman (Chrome) or HTTPRequester (Firefox) to test your API's endpoints.

# Unit tests

- Run _tests/run.bat_ to run PHPCentauri's unit tests. PHPCentauri's PHPTest is used as the unit test runner.

# Documentation

- In the PHPCentauri directory:

```
$ wget http://www.phpdoc.org/phpDocumentor.phar
$ php phpDocumentor.phar -d . -t docs
```

- Open docs/index.html to view the PHPCentauri documentation.

# License

PHPCentauri was created by Cluedapp.

PHPCentauri is licensed to everyone under the *Cluedapp source code license*:

`Source code can be redistributed and used freely.`

It means that I am not aware of any problems with my source code, and that you are welcome to use my source code as you wish.
