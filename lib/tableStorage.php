<?php
	/**
	 * @package PHPCentauri
	 *
	 * This library module is a complete ORM implementation and wrapper for the Microsoft Azure Table Storage database
	 *
	 * This file is a core part of the PHPCentauri DBI (DataBase Interface) framework.
	 * It contains functions that are used by the DBI framework's DBProvider class, to provide
	 * low-level database access to store and retrieve entities in Azure Table Storage.
	 *
	 * There are five public functions:
	 * azure_select_all
	 * azure_select
	 * azure_insert
	 * azure_update
	 * azure_delete
	 *
	 * The rules:
	 *
	 * 1) The tables (entities) are modeled by any PHP class, and the entity
	 *    class must inherit from class BaseEntity
	 *
	 * 2) Put all the entity classes in the "entities" folder
	 *
	 * 3) The PHP class name is singular, and it is automatically pluralized for
	 *    the Azure Table Storage table name. For example, PHP class name "User"
	 *    is mapped to Azure table name "Users"
	 *
	 * 4) PHP object property's name/value represents the Azure table field's name/value
	 *
	 * 5) PHP property name is ProperCase, and Azure column name is also Pascalcase.
	 *    Note that it is Pascalcase and not ProperCase, as there is no way to know
	 *    the word boundaries within a string of letters. For example PHP property
	 *    "userPassword" is mapped to Azure column "UserPassword" and "userpassword"
	 *    is mapped to "Userpassword"
	 *
	 * 6) An entity's partition key and row key cannot be changed - it is a
	 *    built-in retriction of Azure Table Storage
	 *
	 * 7) Check the helper methods in the BaseEntity class like insert, update, delete, they
	 *    give a nice OOP experience
	 *
	 * Required PHP extensions:
	 *    php_curl
	 *    php_openssl
	 *    php_mbstring
	 *
	 * Required Composer packages:
	 *    microsoft/azure-storage-table
	 */

	# use WindowsAzure\Common\ServicesBuilder;
	use MicrosoftAzure\Storage\Common\Internal;
	use MicrosoftAzure\Storage\Common\Internal\Utilities;
	use MicrosoftAzure\Storage\Common\ServiceException;
	use MicrosoftAzure\Storage\Table\Models\BatchOperations;
	use MicrosoftAzure\Storage\Table\Models\EdmType;
	use MicrosoftAzure\Storage\Table\Models\Entity;
	use MicrosoftAzure\Storage\Table\Models\Filters\Filter;
	use MicrosoftAzure\Storage\Table\Models\QueryEntitiesOptions;
	use MicrosoftAzure\Storage\Table\TableRestProxy;

	/**
	 * Select all rows from a table
	 *
	 * @param $className An instance of an entity, OR a class name of an entity,
	 *        representing the Azure table for which to fetch rows. For example
	 *        a class name of "User" fetches rows from the "Users" table
	 *
	 * $param $where Row filter criteria which are AND'ed or OR'ed together, for example:
	 *         ['userId' => 1, 'name' => 'John'] becomes "userId eq 1 and name eq 'John'"
	 *         ['name' => ['John', 'Jim']] becomes "(name eq 'John' or name eq 'Jim')"
	 *         ['userId' => 1, 'name' => ['John', 'Jim']] becomes "userId eq 1 and (name eq 'John' or name eq 'Jim')"
	 *
	 * @param $columns String array of column names to select. If empty array then all columns are selected.
	 *        Partition key and row key are always selected
	 */
	function azure_select_all($className, $where = [], $columns = [], $limit = -1, $offsetID = null) {
		if (!$className) return;

		# Get ORM object's class name
		$table = __getORMTableName($className);

		# Table storage query options
		$options = new QueryEntitiesOptions();

		# Limit to N rows
		if ($limit > -1)
			$options->setTop($limit);

		# Offset
		if ($offsetID) {
			$options->setNextPartitionKey((new $className)->getPartitionKey());
			$options->setNextRowKey($offsetID);
		}

		# Select columns
		if (count($columns))
			$options->setSelectFields(__prepareSelectColumnsArray($columns));

		# Criteria
		if (count($where))
			$options->setFilter(Filter::applyQueryString(__createCriteria($where, true)));

		# Execute query
		$entities = null;
		$result = __tryDb(function($db) use (&$entities, $table, &$options) {
			$entities = $db->queryEntities($table, $options)->getEntities();
		});

		# Error check
		if (!$result) {
			log_error('azure_select_all __tryDb return value = false so query failed, returning from function');
			return false;
		}

		# Create resultset array
		log_error('azure_select_all results', count($entities));
		return array_map(function($entity) use ($className) { return __ormReverse($className, $entity); }, $entities);
	}

	/**
	 * Select the first row from a table that matches the given criteria
	 *
	 * @param $obj An instance of an entity, OR a class name of an entity,
	 *        representing the Azure table for which to fetch rows. For example
	 *        an instance or class name of "User" fetches rows from the "Users" table
	 *
	 * @param $columns String array of column names to select. If empty array then
	 *        all columns are selected. Partition key and row key are always selected
	 */
	function azure_select($obj, $columns = []) {
		if (!$obj) return;

		# Get ORM object's class name and table name
		$className = __getORMClassName($obj);
		$table = __getORMTableName($obj);

		# Table storage query options
		$options = new QueryEntitiesOptions();

		# Limit to 1 row
		$options->setTop(1);

		# Select columns
		if (count($columns))
			$options->setSelectFields(__prepareSelectColumnsArray($columns));

		$partitionKey = ($obj instanceof BaseEntity) ? $obj->getPartitionKey() : null;
		$rowKey = ($obj instanceof BaseEntity) ? $obj->getRowKey() : null;

		if ($partitionKey && $rowKey && !($partitionKey instanceof Ignore) && !($rowKey instanceof Ignore)) {
			log_error('azure_select getting by explicit partition and row key', $partitionKey, $rowKey);

			# Execute query
			$entity = null;
			$result = __tryDb(function($db) use (&$entity, $table, $partitionKey, $rowKey) {
				$entity = $db->getEntity($table, $partitionKey, $rowKey)->getEntity();
			});

			# Error check
			if (!$result) {
				log_error('azure_select __tryDb return value = false so query failed, returning from function');
				return false;
			}

			# Create resultset array
			log_error('azure_select result', $entity);
			return $entity ? __ormReverse($className, $entity) : null;
		} else {
			# Criteria
			if ($criteria = __createCriteria($obj, false))
				$options->setFilter(Filter::applyQueryString($criteria));

			# Execute query
			$entities = null;
			$result = __tryDb(function($db) use (&$entities, $table, &$options) {
				$entities = $db->queryEntities($table, $options)->getEntities();
			});

			# Error check
			if (!$result) {
				log_error('azure_select __tryDb return value = false so query failed, returning from function');
				return false;
			}

			# Create resultset array
			$count = count($entities);
			if ($count) {
				log_error('azure_select results', $count);
				return __ormReverse($className, $entities[0]);
			} else {
				log_error('azure_select No row found');
				return null;
			}
		}
	}

	/**
	 * Insert one or more entities to a table
	 *
	 * @param $obj An instance of an entity, OR an array of entities.
	 *        If it's an array, then there can be a mix of different types of entities in the array,
	 *        each entity will be inserted into the correct Azure table
	 */
	function azure_insert($obj) {
		if (!$obj) return;

		return __tryDb(function($db) use ($obj) {
			# Handle single object or array
			if (is_array($obj)) {
				$operations = new BatchOperations();
				foreach ($obj as &$obj1) {
					$entity = __orm($obj1);
					if ($obj1) $operations->addInsertOrReplaceEntity(__getORMTableName($obj1), $entity);
				}
				$db->batch($operations);
			} else {
				$entity = __orm($obj);
				$db->insertOrReplaceEntity(__getORMTableName($obj), $entity);
			}
		});
	}

	/**
	 * Update one or more entities in a table
	 *
	 * @param $obj An instance of an entity, OR an array of entities.
	 *        If it's an array, then there can be a mix of different types of entities in the array,
	 *        each entity will be updated in the correct Azure table
	 */
	function azure_update($obj) {
		if (!$obj) return;

		return __tryDb(function($db) use ($obj) {
			# Handle single object or array
			if (is_array($obj)) {
				$operations = new BatchOperations();
				foreach ($obj as &$obj1) {
					if ($obj1) $operations->addInsertOrMergeEntity(__getORMTableName($obj1), __orm($obj1));
				}
				$db->batch($operations);
			} else {
				$db->insertOrMergeEntity(__getORMTableName($obj), __orm($obj));
			}
		});
	}

	/**
	 * Delete one or more entities from a table
	 *
	 * @param $obj An instance of an entity, OR an array of entities.
	 *        If it's an array, then there can be a mix of different types of entities in the array,
	 *        each entity will be deleted from the correct Azure table
	 */
	function azure_delete($obj) {
		if (!$obj) return;

		return __tryDb(function($db) use ($obj) {
			# Handle single object or array
			if (is_array($obj)) {
				$operations = new BatchOperations();
				foreach ($obj as &$obj1) {
					if ($obj1) $operations->addDeleteEntity(__getORMTableName($obj1), $obj1->getPartitionKey(), $obj1->getRowKey());
				}
				$db->batch($operations);
			} else {
				$db->deleteEntity(__getORMTableName($obj), $obj->getPartitionKey(), $obj->getRowKey());
			}
		});
	}

	function azure_datetime(DateTime $datetime = null) {
		return 'datetime\'' . Utilities::convertToEdmDateTime($datetime ?? new DateTime) . '\'';
	}

	function tableStorageConnectionString($value = null, $force = false) {
		static $cs = AppSettings['TableStorageConnectionString'];
		if ($value !== null || $force)
			$cs = $value;
		return $cs;
	}

	function __camelCase($name) {
		return strtolower(substr($name, 0, 1)) . substr($name, 1);
	}

	function __createCriteria($arrayOrObj, $includeNullOrEmptyCriteria = true, $booleanOperatorBetweenConditions = BoolOperator::Default, $propertyName = null) {
		# Handle an object that was given as input criteria. If an array was given, then it's straightforward
		if ($arrayOrObj instanceof BaseEntity) {
			$obj = $arrayOrObj;
			$arrayOrObj = (array)$arrayOrObj;

			# Just in case partition key and row key properties are protected/private, get them here,
			# because protected/private properties are discarded when you cast an object to array, using above $arrayOrObj = (array)$arrayOrObj;
			$arrayOrObj['PartitionKey'] = $obj->getPartitionKey();
			$arrayOrObj['RowKey'] = $obj->getRowKey();
		}

		$betweenBooleanOperator = $booleanOperatorBetweenConditions == BoolOperator::Default ? (is_indexed_array($arrayOrObj) ? 'or' : 'and') : $booleanOperatorBetweenConditions;
		$recursiveBooleanOperator = 'and';
		$filter = '';
		$accepted = []; # checks if key and value already used in criteria

		foreach ($arrayOrObj as $original_key => &$v) {
			if ($v instanceof Ignore) continue;

			# Normalize the key/property name
			$k = preg_replace('/[^a-zA-Z0-9_]/', '', empty(trim($propertyName)) || is_numeric($propertyName) ? $original_key : $propertyName);

			if (!in_array([$k, $v], $accepted) && (!empty($k) || is_numeric($k)) && (($v !== null && !empty($v)) || $includeNullOrEmptyCriteria)) {
				if ($betweenBooleanOperator == 'and' && !empty($k) && !is_numeric($k)) $accepted[] = [$k, $v];
				if (is_array($v)) {
					if (!is_indexed_array($v)) {
						$val = $v['value'];
						if (!is_array($val))
							$operator = __getOperator($v['operator']);
						$v = $val;
					} else
						$recursiveBooleanOperator = 'or';
				} elseif ($v instanceof BoolOrAttribute) {
					$recursiveBooleanOperator = 'or';
					$v = $v->conditions[0];
				} else {
					$operator = __getOperator($v);
				}
				if (is_array($v)) {
					if ($criteria = __createCriteria($v, $includeNullOrEmptyCriteria, $recursiveBooleanOperator, is_numeric($k) ? null : $k))
						$filter .= ($filter ? " $betweenBooleanOperator " : '') . "($criteria)";
				} else
					$filter .=  ($filter ? " $betweenBooleanOperator " : '') . (__pascalCase($k) . ' ' . $operator . ' ' . __quote($v));
			}
		}
		log_error('__createCriteria result', $filter);
		return $filter;
	}

	function __getDb() {
		# $tableRestProxy = ServicesBuilder::getInstance()->createTableService(tableStorageConnectionString());
		$tableClient = TableRestProxy::createTableService(tableStorageConnectionString());
		return $tableClient;
	}

	function __getOperator($v) {
		if ($v instanceof DateTime) return 'gt';
		$op = ['>' => 'gt', '<' => 'lt', '=' => 'eq', '!' => 'ne'];
		$v = substr($v, 0, 1);
		return $op[$v] ?? 'eq';
	}

	function __getORMClassName($objOrTableName) {
		# Singularize the object type or table name, to get the class name
		#$className = is_object($objOrTableName) ? get_class($objOrTableName) : strtoupper(substr($objOrTableName, 0, 1)) . preg_replace('/ies$/i', 'y', preg_replace('/s$/i', '', substr($objOrTableName, 1)));
		#log_error("__getORMClassName Generated class name $className");
		#return $className;

		# Object name = table name = class name
		return is_object($objOrTableName) ? get_class($objOrTableName) : $objOrTableName;
	}

	function __getORMTableName($objOrClassName) {
		# Pluralize the object type or class name, to get the table name
		#$className = is_object($objOrClassName) ? get_class($objOrClassName) : $objOrClassName;
		#$table = strtoupper(substr($className, 0, 1)) . preg_replace('/y$/i', 'ies', preg_replace('/([^s])$/i', '$1s', substr($className, 1)));
		#log_error("__getORMClassName Generated table name $table");
		#return $table;

		# Object name = table name = class name
		return is_object($objOrClassName) ? get_class($objOrClassName) : $objOrClassName;
	}

	function __getColumnType($val) {
		if ($val instanceof DateTime) {
			return EdmType::DATETIME;
		}

		switch (strtolower(gettype($val))) {
			case 'integer':
				return EdmType::STRING;
			case 'null':
				return null;
			case 'string':
				return EdmType::STRING;
			case 'double':
				return EdmType::DOUBLE;
			case 'boolean':
				return EdmType::BOOLEAN;
		}

		if (is_numeric($val)) {
			return EdmType::STRING;
		}

		log_error('__getColumnType Unknown column type');
	}

	# Perform ORM to convert PHP object into Azure database entity (object-relational mapping)
	function __orm($obj) {
		$entity = new Entity();

		$partitionKey = $obj->getPartitionKey();
		$rowKey = $obj->getRowKey();
		if (empty($partitionKey) || $partitionKey instanceof Ignore) $partitionKey = AppSettings['DefaultPartitionKey']; # because null is not allowed in the database
		if (empty($rowKey) || $partitionKey instanceof Ignore) $rowKey = substr(preg_replace('/[^a-z0-9]/i', '', unique_id()), 0, 10);

		$entity->setPartitionKey($partitionKey);
		$entity->setRowKey($rowKey);

		foreach ($obj as $k => &$v) {
			log_error('__orm', $k, $v);
			if (is_numeric($k))
				continue;
			$k = __pascalCase($k);
			$val = __ormValue($v);
			$entity->addProperty($k, __getColumnType($v), $val);
			$entity->setPropertyValue($k, $val);
			log_error('__orm loop finished', $k, $v);
		}

		return $entity;
	}

	function __ormValue($val) {
		if ($val instanceof Ignore) {
			return '';
		} elseif ($val instanceof DateTime) {
			return $val;
		} elseif (is_bool($val)) {
			return $val ? true : false;
		} elseif (is_object($val) || is_array($val))
			return '';

		log_error('__ormValue got value', $val);
		return strval($val);
	}

	# Perform reverse ORM to get PHP object (reverse object-relational mapping)
	function __ormReverse($className, $entity) {
		log_error("__ormReverse Got class name: $className");
		$obj = new $className;

		$obj->setPartitionKey($entity->getPartitionKey());
		$obj->setRowKey($entity->getRowKey());
		log_error('__ormReverse', $entity->getProperties());

		foreach ($entity->getProperties() as $name => $value) {
			$ormReversePropertyName = __pascalCase($name);
			if (!in_array($ormReversePropertyName, ['PartitionKey', 'RowKey']))
				$obj->$ormReversePropertyName = $value->getValue();
		}

		return $obj;
	}

	function __pascalCase($name) {
		if (!is_string($name)) {
			log_error('__pascalCase error', '!is_string($name)');
			return $name;
		}
		return strtoupper(substr($name, 0, 1)) . substr($name, 1);
	}

	function __prepareSelectColumnsArray($columns) {
		return array_unique(array_merge(array_map('\\__pascalCase', $columns), ['PartitionKey', 'RowKey']));
	}

	function __quote($val) {
		if ($val instanceof DateTime) {
			return azure_datetime($val);
		}

		switch (strtolower(gettype($val))) {
			case 'null':
				return 'null';
			case 'integer':
				return "'$val'";
			case 'string':
				$s0 = substr((string)$val, 0, 1);
				$s1 = substr((string)$val, 1);
				return is_numeric($val) ? "'$val'" : (!is_numeric($s0) && is_numeric($s1) ? "'$s1'" : "'$val'");
			case 'double':
				return $val;
			case 'boolean':
				return $val ? 'true' : 'false';
		}

		if (is_numeric($val)) {
			return "'$val'";
		}

		log_error('__quote Unknown column type');
	}

	function __tryDb($callback) {
		try {
			$callback(__getDb());
			return true;
		}
		catch (Exception $e) {
			# Handle exception based on error codes and messages.
			# Error codes and messages are here:
			# http://msdn.microsoft.com/library/azure/dd179438.aspx
			$code = $e->getCode();
			$error_message = $e->getMessage();
			log_error("__tryDb $code: $error_message.");
			return false;
		}
	}
?>
