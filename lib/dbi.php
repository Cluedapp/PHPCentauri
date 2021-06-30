<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file declares the get_dbi() function, which is a factory pattern function that
	 * essentially allows the developer to write database code against the DBI interface
	 * (abstraction layer), which is itself abstracted away by writing code against entity
	 * classes (i.e. classes that inherit from BaseEntity), which then under the hood, calls
	 * the get_dbi() function to create and return an instance of a class that implements the
	 * required DBI interface driver for the API's database/DBMS that is configured in the
	 * AppSettings' DBProvider setting. The BaseEntity class makes use of this get_dbi()
	 * function, thereby giving access to entity classes that inherit from BaseEntity to be
	 * written in a declarative fashion, and delegate the actual database calls to the
	 * correct concrete class, so that entity classes can theoretically be used on any database
	 * that is supported by PHPCentauri.
	 *
	 * Currently, the Azure Table Storage and Amazon AWS DynamoDB databases are supported (i.e.
	 * they can be configured as the PHPCentauri API's database/DBMS in AppSettings).
	 *
	 * Required AppSettings:
	 * DBProvider
	 */

	function get_dbi() {
		switch (AppSettings['DBProvider']) {
			case 'azure':
				return new AzureDBProvider();
			case 'dynamodb':
				return new DynamoDBProvider();
		}
	}

	interface DBI {
		# CRUD functions
		function insert($obj);
		function select($obj, $columns);
		function select_all($class_name, $where, $columns, $limit, $offset_id);
		function update($obj);
		function delete($obj);
		function is_unique(string $class_name, array $criteria);
	}

	class AzureDBProvider implements DBI {
		function insert($obj) {
			return azure_insert($obj);
		}

		function select($obj, $columns = []) {
			return azure_select($obj, $columns);
		}

		function select_all($class_name, $where = [], $columns = [], $limit = -1, $offset_id = null) {
			return azure_select_all($class_name, $where, $columns, $limit, $offset_id);
		}

		function update($obj) {
			return azure_update($obj);
		}

		function delete($obj) {
			return azure_delete($obj);
		}

		function is_unique(string $class_name, array $criteria) {
			return !count($this->select_all($class_name, [new BoolOrAttribute($criteria)], [], 1, null));
		}
	}

	class DynamoDBProvider implements DBI {
		function get_table_name($item) {
			return $item->entityProperty('entity');			
		}

		function get_table_id_key_name($item) {
			return $item->entityProperty('id')[0];
		}

		function get_table_id_key_value($item) {
			return $item->{$item->entityProperty('id')[0]};
		}

		function get_first_available_key_name($item) {
			$primary_key_name = $this->get_table_id_key_name($item);
			if (!!$item->$primary_key_name)
				return $primary_key_name;
			$array = $item->entityProperty('unique');
			return first(function($unique_key_name) use ($item) { return !!$item->$unique_key_name ? $unique_key_name : null; }, $array);
		}

		function insert($item) {
			$table = $this->get_table_name($item);
			return dynamodb_insert($table, $item);
		}

		function select($item, $columns = []) {
			$table = $this->get_table_name($item);
			$primary_key_name = $this->get_table_id_key_name($item);
			$index_key_name = $this->get_first_available_key_name($item);
			$key_value = $item->$index_key_name; # either the primary key's or index key's value
			$by_index = $index_key_name && $primary_key_name != $index_key_name; # true if $key_name is a field name for an index ("unique field"). false if it then "must" be a field name for a primary key ("ID field"), since it isn't a "unique" field, by virtue of the fact that either a primary key or an index key needs to have been passed to this function
			$class_name = get_class($item);
			return new $class_name(dynamodb_get($table, $index_key_name, $key_value, $by_index));
		}

		function select_all($class_name, $where, $columns, $limit, $offset_id) {
			$item = new $class_name($where);
			$table = $this->get_table_name($item);
			$primary_key_name = $this->get_table_id_key_name($item);
			$index_key_name = $this->get_first_available_key_name($item);
			$key_value = $item->$index_key_name ?? null; # either the primary key's or index key's value
			$by_index = $index_key_name && $primary_key_name != $index_key_name; # true if $key_name is a field name for an index ("unique field"). false if it then "must" be a field name for a primary key ("ID field"), since it isn't a "unique" field, by virtue of the fact that either a primary key or an index key needs to have been passed to this function

			if ($by_index)
				$rows = dynamodb_get_all_by_index($table, $index_key_name, $key_value, $limit, $offset_id);
			elseif ($item->$primary_key_name ?? null)
				$rows = dynamodb_get_all($table, $primary_key_name, $limit, $offset_id);
			else
				$rows = dynamodb_get_all_by_criteria($table, $where, $limit, $offset_id);

			return array_map(function($row) use ($class_name) { return new $class_name($row); }, $rows);
		}

		function update($item) {
			$table = $this->get_table_name($item);
			$id_key_name = $this->get_table_id_key_name($item);
			$id_key_value = $this->get_table_id_key_value($item);
			return dynamodb_update($table, $id_key_name, $id_key_value, $item);
		}

		function delete($item) {
			$table = $this->get_table_name($item);
			$id_key_name = $this->get_table_id_key_name($item);
			$id_key_value = $this->get_table_id_key_value($item);
			return dynamodb_delete($table, $id_key_name, $id_key_value);
		}

		function is_unique(string $class_name, array $criteria) {
			return !count($this->select_all($class_name, [new BoolOrAttribute($criteria)], [], 1, null));
		}
	}
?>
