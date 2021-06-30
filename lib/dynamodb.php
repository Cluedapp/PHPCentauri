<?php
	/**
	 * @package PHPCentauri
	 *
	 * This library module is a wrapper for the Amazon AWS DynamoDB database
	 * It can be used with a locally-running DynamoDB server instance, or on a server running a DynamoDB instance on Amazon's infrastructure
	 *
	 * This file is a core part of the PHPCentauri DBI (DataBase Interface) framework.
	 * It contains functions that are used by the DBI framework's DBProvider class, to provide
	 * low-level database access to store and retrieve entities in AWS DynamoDB.
	 *
	 * Required Composer packages:
	 *    aws/aws-sdk-php
	 *
	 * Required app settings:
	 * AWSAccessKeyID
	 * AWSSecretAccessKey
	 * AWSRegion
	 * AWSEndpoint (optional)
	 */

	function dynamodb_client() {
		$config = ['version' => 'latest', 'region' => AppSettings['AWSRegion']];
		if (AppSettings['AWSEndpoint'] ?? false)
			$config['endpoint'] = AppSettings['AWSEndpoint'];
		static $client = null;
		if ($client === null)
			$client = new Aws\DynamoDb\DynamoDbClient($config);
		return $client;
	}

	function dynamodb_delete($table, $key_name, $key_value) {
		$client = dynamodb_client();
		$result = $client->deleteItem([
			'TableName' => $table,
			'Key' => [$key_name => dynamodb_wrap($key_value, false)]
		]);
		return $result;
	}

	function dynamodb_get($table, $key_name, $key_value, $by_index = false) {
		$client = dynamodb_client();
		if ($by_index) {
			return dynamodb_get_all_by_index($table, $key_name, $key_value)[0] ?? null;
		} else {
			$result = $client->getItem([
				'ConsistentRead' => true,
				'TableName' => $table,
				'Key' => [$key_name => dynamodb_wrap($key_value, true)]
			]);
			return dynamodb_unwrap($result['Item']);
		}
	}

	# The following function is potentially very expensive and slow
	function dynamodb_get_all($table, $key_name, $limit = -1, $offset_id = null) {
		$client = dynamodb_client();
		$items = [];
		if ($offset_id) {
			$params['LastEvaluatedKey'] = [$key_name => dynamodb_wrap($offset_id, false)];
		}
		do {
			$previous_offset_id = $offset_id;
			$params = [
				'TableName' => $table
			];
			if ($limit > 0) {
				$params['Limit'] = $limit;
			}
			if ($offset_id) {
				$params['LastEvaluatedKey'] = $offset_id;
			}
			log_error('dynamodb_get_all executing DynamoDB scan, ensure that AWS settings are correct in settings.php');
			$results = $client->scan($params);
			$items = array_merge($items, $results['Items']);
			$offset_id = $results['LastEvaluatedKey'] ?? '';
		} while ($offset_id && $offset_id != $previous_offset_id);

		return array_map(function($result) { return dynamodb_unwrap($result); }, $items);
	}

	function dynamodb_generate_where_expression($where, $bool_connector = 'AND') {
		$ret = '';
		foreach ($where as $key => $value) {
			if ($value instanceof BoolOrAttribute) {
				$ret .= ($ret && $bool_connector ? " $bool_connector " : '') . '(' . dynamodb_generate_where_expression($value->getConditions()) . ')';
			} elseif (is_array($value)) {
				$ret .= ($ret && $bool_connector ? " $bool_connector " : '') . '(' . dynamodb_generate_where_expression($value) . ')';
			} else if (!in_array($key, ['PartitionKey', 'RowKey', 'Timestamp'])) { # ignore Azure Table Storage properties
				$ret .= ($ret && $bool_connector ? " $bool_connector " : '') . "$key = :$key";
			}
		}

		# Remove redundant parantheses
		while (substr($ret, 0, 1) == '(' && substr($ret, strlen($ret) - 1, 1) == ')')
			$ret = substr($ret, 1, strlen($ret) - 2);

		return $ret;
	}

	function dynamodb_flatten_where_conditions($where) {
		$results = [];
		foreach ($where as $key => $value) {
			$results[$key] = $value;
			if ($value instanceof BoolOrAttribute) {
				$results = array_merge($results, dynamodb_flatten_where_conditions($value->getConditions()));
			} elseif (is_array($value)) {
				$results = array_merge($results, dynamodb_flatten_where_conditions($value));
			}
		}
		return $results;
	}

	function dynamodb_is_valid_key($key) {
		return
			is_string($key) &&
			!in_array($key, ['PartitionKey', 'RowKey', 'Timestamp']); # ignore Azure Table Storage properties
	}

	function dynamodb_generate_where_expression_values($conditions, $include_null_values) {
		return array_reduce(array_keys($conditions), function($carry, $key) use ($conditions, $include_null_values) {
			if (
					dynamodb_is_valid_key($key) &&
					($include_null_values || $conditions[$key]))
				$carry[":$key"] = dynamodb_wrap($conditions[$key], false);
			return $carry;
		}, []);
	}

	function dynamodb_get_all_by_criteria($table, $where, $limit = -1, $offset_id = null) {
		$client = dynamodb_client();
		$items = [];
		if ($offset_id) {
			$params['LastEvaluatedKey'] = [$key_name => dynamodb_wrap($offset_id, false)];
		}

		# Generate DynamoDB where-filter expression query ("FilterExpression")
		if ($where instanceof BoolOrAttribute) {
			$filter_expression_query = dynamodb_generate_where_expression($where->getConditions(), 'OR');
		} else {
			$filter_expression_query = dynamodb_generate_where_expression($where, 'AND');
		}
		log_error('dynamodb dynamodb_get_all_by_criteria filter_expression_query', $filter_expression_query);

		# Generate DynamoDB where-filter expression values ("ExpressionAttributeValues")
		$conditions = dynamodb_flatten_where_conditions($where);
		$filter_expression_values = dynamodb_generate_where_expression_values($conditions, true);

		do {
			$previous_offset_id = $offset_id;
			$params = [
				'TableName' => $table,
				'FilterExpression' => $filter_expression_query,
				'ExpressionAttributeValues' => $filter_expression_values
			];
			if ($limit > 0) {
				$params['Limit'] = $limit;
			}
			if ($offset_id) {
				$params['LastEvaluatedKey'] = $offset_id;
			}
			log_error('dynamodb_get_all_by_criteria executing DynamoDB scan, ensure that AWS settings are correct in settings.php');
			$results = $client->scan($params);
			$items = array_merge($items, $results['Items']);
			$offset_id = $results['LastEvaluatedKey'] ?? '';
		} while ($offset_id && $offset_id != $previous_offset_id);

		return array_map(function($result) { return dynamodb_unwrap($result); }, $items);
	}

	function dynamodb_get_all_by_index($table, $key_name, $key_value, $limit = -1, $offset_id = '') {
		$client = dynamodb_client();
		$items = [];
		if ($offset_id) {
			$params['LastEvaluatedKey'] = [$key_name => dynamodb_wrap($offset_id, false)];
		}
		do {
			$params = [
				'TableName' => $table,
				'IndexName' => "$key_name-index", # this is a PHPCentauri convention, the AWS DynamoDB global secondary index's (GSI's) name must be keyName-index, where keyName is the name of the synthesized hash attribute "primary key" in the GSI
				'ConsistentRead' => false, # cannot be true for a GSI
				'Select' => 'ALL_ATTRIBUTES',
				'KeyConditionExpression' => "$key_name = :index_key_val",
				'ExpressionAttributeValues' => [':index_key_val' => dynamodb_wrap($key_value, false)],
			];
			if ($limit > 0) {
				$params['Limit'] = $limit;
			}
			if ($offset_id) {
				$params['LastEvaluatedKey'] = $offset_id;
			}
			log_error('Executing DynamoDB query, ensure that AWS settings are correct in settings.php');
			$results = $client->query($params);
			$items = array_merge($items, $results['Items']);
			$offset_id = $results['LastEvaluatedKey'] ?? '';
		} while ($offset_id);
		
		return array_map(function($result) { return dynamodb_unwrap($result); }, $items);
	}

	function dynamodb_insert($table, $item) {
		log_error('dynamodb_insert - pre-wrap', $item);
		$item = dynamodb_wrap($item, true);
		log_error('dynamodb_insert - post-wrap', $item);
		$client = dynamodb_client();
		$result = $client->putItem([
			'TableName' => $table,
			'Item' => $item
		]);
		return $result;
	}

	function dynamodb_update($table, $key_name, $key_value, &$item) {
		$client = dynamodb_client();

		# Generate DynamoDB where-filter expression values ("ExpressionAttributeValues")
		$conditions = dynamodb_flatten_where_conditions($item);
		$filter_expression_values = dynamodb_generate_where_expression_values($conditions, false);
		if (isset($filter_expression_values[":$key_name"]))
			unset($filter_expression_values[":$key_name"]); # because the DynamoDB row's key's value cannot be updated

		$update_expression = 'SET ' . array_reduce(
			array_keys((array) $item),
			function($carry, $key) use ($item, $key_name) {
				if (
						dynamodb_is_valid_key($key) &&
						$key != $key_name && $item->$key) { # because the DynamoDB row's key's value cannot be updated, and updating a field's value to null is not supported
					if ($carry)
						$carry .= ', ';
					$carry .= "$key = :$key";
				}
				return $carry;
			},
			'');

		$params = [
			'TableName' => $table,
			'Key' => [$key_name => dynamodb_wrap($key_value, false)],
			'UpdateExpression' => $update_expression,
			'ExpressionAttributeValues' => $filter_expression_values
		];
		$result = $client->updateItem($params);
		return $result;
	}

	function dynamodb_wrap($value, bool $initial) {
		$ret = [];

		# Do not wrap the outermost entity itself, because it is the "root" entity that contains the wrapped values, as flat key-value properties
		if ($initial && (is_array($value) || is_object($value))) {
			foreach ((array)$value as $key => $_value)
				if (dynamodb_is_valid_key($key))
					$ret[$key] = dynamodb_wrap($_value, false);
		}
		
		# null
		elseif (is_null($value) || $value instanceof NullAttribute || (is_string($value) && $value === ''))
			$ret = ['NULL' => true];

		# boolean string
		elseif ($value === 'true' || $value === 'false')
			$ret = ['B' => $value];

		# boolean
		elseif (is_bool($value))
			$ret = ['BOOL' => !!$value];

		# number or number string
		elseif (is_numeric($value))
			$ret = ['N' => (string) $value];

		# plain string
		elseif (is_string($value))
			$ret = ['S' => $value];

		# list
		elseif (is_indexed_array($value)) {
			$bools = $strings = $nums = 0;
			foreach ($value as $value1) {
				if ($value === 'true' || $value === 'false')
					++$bools;
				elseif (is_string($value1))
					++$strings;
				elseif (is_numeric($value1))
					++$nums;
			}

			$count = count($value);

			# array of any
			if ($count == 0 || ($bools != $count && $nums != $count && $strings != $count)) {
				$type = 'L';

				$entity = [];
				for ($i = 0, $len = count($value); $i < $len; ++$i) {
					$inner_type = get_dynamodb_wrapped_field_type($value[$i]);
					$wrapped = dynamodb_wrap($value[$i], false);
					if ($inner_type === 'M')
						$entity[$i] = ['M' => $wrapped];
					else
						$entity[$i] = $wrapped;
				}
				$value = $entity;
			}

			# array of boolean strings
			elseif ($bools == $count)
				$type = 'BS';

			# array of number strings
			elseif ($nums == $count)
				$type = 'NS';

			# array of plain strings
			elseif ($strings == $count)
				$type = 'SS';

			$ret = [$type => $value];
		}

		# hash/object of any
		elseif (is_array($value) || is_object($value)) {
			$ret = ['M' => []];
			foreach ((array)$value as $key1 => $value1)
				$ret['M'][$key1] = dynamodb_wrap($value1, false);
		}

		return $ret;
	}

	function dynamodb_unwrap($item) {
		$ret = [];
		foreach ((array)$item as $key => $value)
			# null
			if (isset($value['NULL']))
				$ret[$key] = null;

			# boolean string
			elseif (isset($value['B']))
				$ret[$key] = in_array(strtolower(trim($value['B'])), ['1', 'true']);

			# boolean
			elseif (isset($value['BOOL']))
				$ret[$key] = !!$value['BOOL'];

			# number or number string
			elseif (isset($value['N']))
				$ret[$key] = (double)$value['N'];

			# plain string
			elseif (isset($value['S']))
				$ret[$key] = (string)$value['S'];

			# array of boolean strings
			elseif (isset($value['BS'])) {
				$ret[$key] = [];
				foreach ($value['BS'] as $value1)
					$ret[$key][] = !!$value1;
			}

			# array of number strings
			elseif (isset($value['NS'])) {
				$ret[$key] = [];
				foreach ($value['NS'] as $value1)
					$ret[$key][] = (double)$value1;
			}

			# array of plain strings
			elseif (isset($value['SS'])) {
				$ret[$key] = [];
				foreach ($value['SS'] as $value1)
					$ret[$key][] = (string)$value1;
			}

			# array of any
			elseif (isset($value['L'])) {
				$ret[$key] = [];
				foreach ($value['L'] as $value1)
					$ret[$key][] = dynamodb_unwrap(['temp' => $value1])['temp'];
			}

			# hash/object of any
			elseif (isset($value['M'])) {
				$ret[$key] = dynamodb_unwrap($value['M']);
			}
		return $ret;
	}
	
	function get_dynamodb_wrapped_field_type($value) {
		# null
		if (is_null($value))
			return 'NULL';

		# boolean string
		elseif ($value === 'true' || $value === 'false')
			return 'B';

		# boolean
		elseif (is_bool($value))
			return 'BOOL';

		# number or number string
		elseif (is_numeric($value))
			return 'N';

		# plain string
		elseif (is_string($value))
			return 'S';

		# list
		elseif (is_indexed_array($value)) {
			$bools = $strings = $nums = 0;
			foreach ($value as $value1) {
				if ($value === 'true' || $value === 'false')
					++$bools;
				elseif (is_string($value1))
					++$strings;
				elseif (is_numeric($value1))
					++$nums;
			}

			# array of boolean strings
			if ($bools == count($value))
				return 'BS';

			# array of number strings
			elseif ($nums == count($value))
				return 'NS';

			# array of plain strings
			elseif ($strings == count($value))
				return 'SS';

			# array of any
			else
				return 'L';
		}

		# hash/object of any
		elseif (is_array($value) || is_object($value))
			return 'M';
	}
?>
