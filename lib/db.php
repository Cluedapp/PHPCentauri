<?php
	/**
	 * @package PHPCentauri
	 *
	 * These classes and functions are for interaction with PDO database drivers only.
	 *
	 * Required PHP extensions:
	 *    php_pdo_mysql (only if needed)
	 *    php_pdo_pgsql (only if needed)
	 *
	 * Requires PHP PDO DLL extension loaded for MySQL, PostgreSQL, etc.
	 */

	# Define a raw database expression that should be inserted as is (i.e. not be quoted or escaped) into a SQL statement
	class DBExpression {
		public function __construct($expression) {
			$this->setExpression($expression);
		}

		function getExpression() {
			return $this->expression;
		}

		function setExpression($expression) {
			$this->expression = $expression;
		}
	}

	function db_expression($expression) {
		return new DBExpression($expression);
	}

	function get_db($cached_db = true, $static_db = true, $db_type = PDO_DB_TYPE, $host = PDO_HOST, $db_name = PDO_DB_NAME, $username = PDO_USERNAME, $password = PDO_PASSWORD, $charset = PDO_CHARSET) {
		try {
			static $dbs = [];
			$key = "$db_type $host $db_name $username $password $charset";
			if ($cached_db && isset($dbs[$key]))
				return $dbs[$key];

			static $db = null;
			if ($static_db && $db)
				return $db;

			try {
				$db = new PDO("$db_type:host=$host;dbname=$db_name", $username, $password);
			} catch (PDOException $e) {
				log_error('get_db() exception: ', $e->getMessage());
				$db = null;
				db_error(func_get_args());
			}

			if (!$db && ($fallback_hosts = defined('PDO_FALLBACK_HOSTS') ? PDO_FALLBACK_HOSTS : '')) {
				foreach (is_array($fallback_hosts) ? $fallback_hosts : explode(',', $fallback_hosts) as $fallback_host) {
					try {
						$fallback_host = trim($fallback_host);
						$db = new PDO("$db_type:host=$fallback_host;dbname=$db_name", $username, $password);
						break;
					} catch (PDOException $e) {
						log_error('get_db() exception: ', $e->getMessage());
						$db = null;
						db_error(func_get_args());
					}
				}
			}

			if (!$db) {
				log_error('get_db(): !$db');
				$db = null;
				db_error(func_get_args());
				return null;
			} else {
				$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				execute("SET NAMES '$charset'");
				$dbs[$key] = $db;
				return $db;
			}
		} catch (PDOException $e) {
			log_error('get_db() exception: ', $e->getMessage());
			$db = null;
			db_error(func_get_args());
		}

		return null;
	}

	# $st ::= PDOStatement
	function db_error($args, $st = null) {
		$db = get_db();
		if ($args)
			log_error(print_r($args, true));
		log_error('$db->errorInfo():', !!$db ? print_r($db->errorInfo(), true) : '!$db');
		log_error('$st->errorInfo():', !!$st ? print_r($st->errorInfo(), true) : '!$st');
	}

	function db_quote($value) {
		$db = get_db();
		if (!$db) {
			log_error('db_quote', '!$db');
			return '';
		}
		$sql_string_escape = function($str) { return "'" . str_replace("'", "\\'", str_replace('\\', '\\\\', $str)) . "'"; };
		return
			$value instanceof DBExpression
			?
				$value->getExpression() # expression is injected as-is into SQL - use with care, as it can expose the database to a SQL injection attack
			:
				(is_null($value)
				?
					'NULL' # DB null
				:
					($value && is_bool($value)
					?
						'1' # bit 1
					:
						(!$value && is_bool($value)
						?
							'0' # bit 0
						:
							(is_numeric($value)
							?
								$value # regular number
							:
								(is_string($value) && json_decode($value)
								?
									$sql_string_escape($value) # $value is already a JSON string, don't re-stringify it to a JSON string within a JSON string, just DB-escape it
								:
									(is_array($value) || is_object($value)
									?
										$sql_string_escape(json_encode($value)) # $value must be made a JSON string, and DB-escaped
									:
										$db->quote($value) # $value is a regular string that must be DB-escaped
									)
								)
							)
						)
					)
				);
	}

	function sql($query, $params) {
		foreach ($params as $key => &$value)
			$query = preg_replace("/:$key/", db_quote($value), $query);
		return $query;
	}

	# $params ::= [param1 => val1, param2 => val2, ...]
	function query($query, $params = []) {
		$st = null;
		try {
			$db = get_db();

			if (!$db) {
				log_error('query', '!$db');
				return null;
			}

			$query = sql($query, $params);
			log_error('executing query', $query);

			$st = $db->prepare($query);
			if (!$st) {
				log_error('query(): !$st - Invalid query:', $query);
				db_error(func_get_args());
			}

			$st->execute();

			return $st;
		} catch (PDOException $e) {
			log_error('query() exception:', $e->getMessage());
			log_error('Query:', $query);
			db_error(func_get_args(), $st);
		}
	}

	# fetch a single row
	function fetch() {
		$st = null;
		try {
			$st = query(...func_get_args());
			return $st->fetch(PDO::FETCH_OBJ);
		} catch (PDOException $e) {
			log_error('fetch() exception:', $e->getMessage());
			db_error(func_get_args(), $st);
		}
	}

	# fetch a single value from the first row, first column
	function fetch_value() {
		$st = null;
		try {
			$st = query(...func_get_args());
			return $st->fetchColumn();
		} catch (PDOException $e) {
			log_error('fetch() exception:', $e->getMessage());
			db_error(func_get_args(), $st);
		}
	}

	# fetch all rows in the resultset, return array of objects
	function fetch_all() {
		$st = null;
		try {
			$st = query(...func_get_args());
			return $st->fetchAll(PDO::FETCH_OBJ);
		} catch (PDOException $e) {
			log_error('fetch_all() exception:', $e->getMessage());
			db_error(func_get_args(), $st);
		}
	}

	# fetch an array of items, the query must select one column per row
	function fetch_all_column() {
		$st = null;
		try {
			$st = query(...func_get_args());
			return $st->fetchAll(PDO::FETCH_COLUMN);
		} catch (PDOException $e) {
			log_error('fetch_all_column() exception:', $e->getMessage());
			db_error(func_get_args(), $st);
		}
	}

	# fetch all rows in the resultset, return array of [0 => val1, 1 => val2, etc]
	function fetch_all_numeric() {
		$st = null;
		try {
			$st = query(...func_get_args());
			return $st->fetchAll(PDO::FETCH_NUM);
		} catch (PDOException $e) {
			log_error('fetch_all_numeric() exception:', $e->getMessage());
			db_error(func_get_args(), $st);
		}
	}

	# execute query, discard result
	function execute() {
		$st = null;
		try {
			$st = query(...func_get_args());
		} catch (PDOException $e) {
			log_error('execute() exception:', $e->getMessage());
			db_error(func_get_args(), $st);
		}
	}

	function last_insert_id() {
		try {
			return get_db()->lastInsertId();
		} catch (PDOException $e) {
			log_error('last_insert_id() exception:', $e->getMessage());
			db_error(func_get_args());
		}
	}

	function where_clause($where) {
		return
			count(array_keys($where))
				?
					' WHERE ' .
					implode(
						' AND ',
						array_map_assoc(
							function($k, $v) {
								return
									$v instanceof DBExpression
										? $v->getExpression()
										:
											(
												"`$k` " .
												(
													$v instanceof RegexAttribute
														? ('LIKE \'%' . str_replace('*', '%', $v->getRegexWithoutDelimiters()) . '%\'')
														:
															(
																$v instanceof InAttribute
																	? ('IN (' . implode(',', array_map('db_quote', $v->getInValues())) . ')')
																	: '= ' . db_quote($v)
															)
												)
											);
							},
							(array) $where
						)
					)
				:
					'';
	}

	function db_table_name($table) {
		return implode('.', map(explode('.', $table), function ($part) { return "`$part`"; }));
	}

	function select_db($table, $columns = ['*'], $where = []) {
		log_error('select_db', $table, $columns, $where);
		$table = db_table_name($table);
		$data = fetch_all('SELECT ' . implode(',', $columns) . " FROM $table" . where_clause($where), $where);
		log_error('select_db got data', $data);
		return $data;
	}

	function insert_db($table, $obj) {
		log_error('insert_db', $table, $obj);
		$table = db_table_name($table);
		$sql = "INSERT INTO $table (";
		$sql_values = '';
		$b = '';
		foreach ($obj as $k => &$v) {
			$sql .= "$b`$k`";
			$sql_values .= "$b:$k";
			$b = ',';
		}
		$sql .= ") VALUES ($sql_values)";
		execute($sql, $obj);
	}

	function update_db($table, $obj, $where = []) {
		log_error('update_db', $obj, $where);
		$table = db_table_name($table);
		$sql = "UPDATE $table SET ";
		$b = '';
		foreach ($obj as $k => &$v) {
			$sql .= "$b`$k` = :$k";
			$b = ',';
		}
		$sql .= where_clause($where);
		execute($sql, $obj);
	}

	function delete_db($table, $obj) {
		log_error('delete_db', $obj);
		$table = db_table_name($table);
		$sql = "DELETE FROM $table" . where_clause($obj);
		execute($sql, $obj);
	}
?>
