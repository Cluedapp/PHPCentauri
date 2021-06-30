<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file implements the PHPCentauri Item Engine (PIE)
	 * controller actions.
	 *
	 * See PHPCentauri controllers/PIE.php for more information.
	 */

	function add_entity($dbEntityNameOrItem, $item = null) {
		# $dbEntityNameOrItem is double-purposed as a string (containing the entity class name) or an object (containing the "item")
		$item = (is_object($dbEntityNameOrItem) || is_array($dbEntityNameOrItem)) && !$item ? (array)$dbEntityNameOrItem : $item;
		$dbEntityNameOrItem = is_string($dbEntityNameOrItem) ? $dbEntityNameOrItem : (is_object($dbEntityNameOrItem) ? get_class($dbEntityNameOrItem) : $dbEntityNameOrItem);

		return entity_action($dbEntityNameOrItem, 'add', false, $item, false)['Success'] == 1;
	}

	function delete_entity($dbEntityNameOrItem, $item = null) {
		# $dbEntityNameOrItem is double-purposed as a string (containing the entity class name) or an object (containing the "item")
		$item = (is_object($dbEntityNameOrItem) || is_array($dbEntityNameOrItem)) && !$item ? (array)$dbEntityNameOrItem : $item;
		$dbEntityNameOrItem = is_string($dbEntityNameOrItem) ? $dbEntityNameOrItem : (is_object($dbEntityNameOrItem) ? get_class($dbEntityNameOrItem) : $dbEntityNameOrItem);

		return entity_action($dbEntityNameOrItem, 'delete', false, $item, false)['Success'] == 1;
	}

	function edit_entity($dbEntityNameOrItem, $item = null) {
		# $dbEntityNameOrItem is double-purposed as a string (containing the entity class name) or an object (containing the "item")
		$item = (is_object($dbEntityNameOrItem) || is_array($dbEntityNameOrItem)) && !$item ? (array)$dbEntityNameOrItem : $item;
		$dbEntityNameOrItem = is_string($dbEntityNameOrItem) ? $dbEntityNameOrItem : (is_object($dbEntityNameOrItem) ? get_class($dbEntityNameOrItem) : $dbEntityNameOrItem);

		return entity_action($dbEntityNameOrItem, 'edit', false, $item, false)['Success'] == 1;
	}

	function list_entity($dbEntityNameOrItem, $item = null) {
		# $dbEntityNameOrItem is double-purposed as a string (containing the entity class name) or an object (containing the "item")
		$item = (is_object($dbEntityNameOrItem) || is_array($dbEntityNameOrItem)) && !$item ? (array)$dbEntityNameOrItem : $item;
		$dbEntityNameOrItem = is_string($dbEntityNameOrItem) ? $dbEntityNameOrItem : (is_object($dbEntityNameOrItem) ? get_class($dbEntityNameOrItem) : $dbEntityNameOrItem);

		return entity_action($dbEntityNameOrItem, 'list', false, $item, false)['Success'] == 1;
	}

	# The below functions should ideally not be used outside of this file

	function entity_action($item, $action, $collect_input, $input_data, $with_success) {
		$file = item_entity_php_filename($item);
		if (!file_exists($file))
			fail(APIError::Nonexist);
		require_once $file;

		$item = ucfirst($item);
		$item = new $item;
		$entity = $item->entityProperty('entity');
		$add_after = $item->entityProperty('add_after');
		$add_before = $item->entityProperty('add_before');
		$add_defaults = $item->entityProperty('add_defaults');
		$add_validate = $item->entityProperty('add_validate');
		$any_after = $item->entityProperty('any_after'); # "any" means either "add" or "edit", but not "delete"
		$any_before = $item->entityProperty('any_before');  # "any" means either "add" or "edit", but not "delete"
		$any_validate = $item->entityProperty('any_validate');
		$collection_name = $item->entityProperty('collection_name');
		$delete_before = $item->entityProperty('delete_before');
		$delete_after = $item->entityProperty('delete_after');
		$edit_after = $item->entityProperty('edit_after');
		$edit_before = $item->entityProperty('edit_before');
		$edit_validate = $item->entityProperty('edit_validate');
		$google_drive_item_folder_name_key = $item->entityProperty('google_drive_item_folder_name_key');
		$google_drive_parent_folder_id = $item->entityProperty('google_drive_parent_folder_id');
		$id = $item->entityProperty('id');
		$list_regex_alternative = $item->entityProperty('list_regex_alternative');
		$list_rest = $item->entityProperty('list_rest');
		$list_sort = $item->entityProperty('list_sort');
		$list_summary = $item->entityProperty('list_summary');
		$list_transform = $item->entityProperty('list_transform');
		$permission = $item->entityProperty('permission');
		$permission_func = $item->entityProperty('permission_func');
		$relation = $item->entityProperty('relation');
		$require_login = $item->entityProperty('require_login');
		$transform = $item->entityProperty('transform');
		$unique = $item->entityProperty('unique');

		switch ($action) {
		case 'add':
			$add_before_func = function(&$item) use ($add_before, $any_before) { if ($add_before && is_string($err = $add_before($item))) return $err; if ($any_before && is_string($err = $any_before($item))) return $err; };
			$add_after_func = function(&$item) use ($add_after, $any_after) { $add_after && $add_after($item); $any_after && $any_after($item); };
			return item_add($entity, $id, $unique, $require_login, $add_validate, $any_validate, $transform, $add_before_func, $add_after_func, $add_defaults, $permission, $permission_func, $collect_input, $input_data, $with_success);

		case 'delete':
			$delete_before_func = function(&$item) use ($delete_before) { if ($delete_before && is_string($err = $delete_before($item))) return $err; };
			return item_delete($entity, $id, $require_login, $edit_validate, $any_validate, $delete_before_func, $delete_after, $permission, $permission_func, $collect_input, $input_data, $with_success);

		case 'edit':
			$edit_before_func = function(&$item) use ($edit_before, $any_before) { if ($edit_before && is_string($err = $edit_before($item))) return $err; if ($any_before && is_string($err = $any_before($item))) return $err; };
			$edit_after_func = function(&$item) use ($edit_after, $any_after) { $edit_after && $edit_after($item); $any_after && $any_after($item); };
			return item_edit($entity, $id, $unique, $require_login, $edit_validate, $any_validate, $transform, $edit_before_func, $edit_after_func, $permission, $permission_func, $collect_input, $input_data, $with_success);

		case 'list':
			return item_list($entity, $collection_name, $id, $require_login, $any_validate, $relation, $list_summary, $list_rest, $list_regex_alternative, $list_transform, $list_sort, $permission, $permission_func, $collect_input, $input_data, $with_success);

		case 'pic_add':
			return item_pic_add($entity, $id, $require_login, $edit_validate, $google_drive_parent_folder_id, $google_drive_item_folder_name_key, $permission, $permission_func, $collect_input, $input_data, $with_success);

		case 'pic_delete':
			return item_pic_delete($entity, $id, $require_login, $edit_validate, $google_drive_parent_folder_id, $google_drive_item_folder_name_key, $permission, $permission_func, $collect_input, $input_data, $with_success);

		case 'pic_list':
			return item_pic_list($entity, $id, $require_login, $edit_validate, $google_drive_parent_folder_id, $google_drive_item_folder_name_key, $permission, $permission_func, $collect_input, $input_data, $with_success);

		case 'pic_list_count':
			return item_pic_list_count($entity, $id, $require_login, $edit_validate, $google_drive_parent_folder_id, $google_drive_item_folder_name_key, $permission, $permission_func, $collect_input, $input_data, $with_success);

		default:
			fail(APIError::Invalid);
		}
	}

	function item_add($dbEntityName, $id, $unique, $require_login, $add_validate, $any_validate, $transform, $before_func, $after_func, $defaults, $permission = null, $permission_func = null, $collect_input = true, $input_data = null, $with_success = true) {
		require_once item_entity_php_filename($dbEntityName);

		# Verify request
		verify_request($require_login);
		$permission && permission($permission);

		# Collect input
		$validate = array_merge_values(function(&$cur, &$new) { return new BoolAndAttribute($cur, $new); }, $add_validate, $any_validate);
		$input = $collect_input ? collect(...array_keys($validate)) : $input_data;
		log_error("got $dbEntityName input", $input);

		# Validate input data
		$valid = validate_object($input, $validate);

		# Validation error
		if ($valid !== true)
			fail($valid[0], ['For' => $valid[1]]);

		# Permission
		$permission_func && $permission_func($input);

		# Insert/update database
		$array = [];
		foreach (array_keys($validate) as $item)
			$array[] = $item;
		foreach ($transform as $key => $value)
			$array[] = [$key, $value];
		array_unshift($array, $input);
		log_error('item_add pick', $array);
		$input = pick(...$array);
		log_error('picked', $input);
		$item = new $dbEntityName(array_merge_if(':1 !== null', $defaults, $input));

		# Before trigger
		if ($before_func && (strlen((string)($err = $before_func($item))) > 0)) fail($err);
		$item = new $dbEntityName((array) $item); # $before_func($item) erroneously transforms $item's class from $dbEntityName to stdClass

		# Duplicate
		$array = $id;
		if (!empty($unique))
			$array = array_merge($array, $unique);
		array_unshift($array, $item);
		$provider = get_dbi();
		if (count($provider->select_all($dbEntityName, [new BoolOrAttribute(pick(...$array))], [], -1, null)))
			fail(APIError::Duplicate, ['For' => $unique]);

		log_error("now adding $dbEntityName", $item);
		if ($item->insert()) {
			# After trigger
			$after_func && $after_func($item);

			# Update cache
			item_cache_add($dbEntityName, $item);

			# Return result
			$return_value = ['Success' => 1, $dbEntityName => $item];
			return $with_success ? success($return_value) : $return_value;
		} else {
			fail(APIError::Database);
		}
	}

	function item_cache($dbEntityName, $saveEntities = null) { # $dbEntityName is the name of an entity. if is_array($saveEntities) then manually cache the entities in $saveEntities, for $dbEntityName. else, return all entities from cache for $dbEntityName
		require_once item_entity_php_filename($dbEntityName);

		if (!AppSettings['DataCacheEnabled'])
			return $dbEntityName::all();

		static $cache = []; # variable to hold cached entities, e.g. ['EntityA' => [entityObj1, entityObj2], 'EntityB' => [entityObj3, entityObj4], ...]
		$dbEntityName = ucfirst($dbEntityName);
		$dbEntityLower = lcfirst($dbEntityName);

		if (is_array($saveEntities)) { # manually cache entities
			$cache[$dbEntityName] = $saveEntities;
			log_error('item_cache', 'deleting all from', $dbEntityName);
			execute("DELETE FROM `$dbEntityName`");
			foreach ($saveEntities as $entity) {
				log_error('item_cache', 'adding entity', $dbEntityName, $newEntity);
				insert_db($dbEntityName, $entity);
			}
		} else {
			# Get items from cache
			if (!isset($cache[$dbEntityName]))
				$cache[$dbEntityName] = item_cache_get_entities($dbEntityName);
			return $cache[$dbEntityName];
		}
	}

	function item_cache_add($dbEntityName, $newEntity) {
		if (AppSettings['DataCacheEnabled']) {
			log_error('item_cache_add', 'adding entity', $dbEntityName, $newEntity);
			insert_db($dbEntityName, $newEntity);
		}
	}

	function item_cache_delete($dbEntityName, $oldEntity) {
		if (AppSettings['DataCacheEnabled'])
			delete_db($dbEntityName, $oldEntity);
	}

	// $id ::= ['Key1' => 'Value1', 'Key2' => 'Value2', ...]
	function item_cache_get($dbEntityName, $id) {
		if (!AppSettings['DataCacheEnabled'])
			return $dbEntityName::get($id);
		else
			return item_cache_get_entities($dbEntityName, $id)[0] ?? null;
	}

	// $id ::= ['Key1' => 'Value1', 'Key2' => 'Value2', ...]
	function item_cache_get_entities($dbEntityName, $id = []) {
		$data = select_db($dbEntityName, ['*'], $id);
		require_once item_entity_php_filename($dbEntityName);
		return is_array($data) ? array_map(function($row) use ($dbEntityName) { return new $dbEntityName((array)$row); }, $data) : [];
	}

	// $where ::= ['Key1' => 'Value1', 'Key2' => 'Value2', ...]
	function item_cache_search($dbEntityName, $where) {
		if (!AppSettings['DataCacheEnabled']) {
			log_error('calling', 'db entity name', $dbEntityName, 'all', 'where', $where);
			return $dbEntityName::all($where);
		} else
			return item_cache_get_entities($dbEntityName, array_map_assoc(function($k, $v) { return is_array($v) ? $v[1] : $v; }, $where));
	}

	// $id ::= ['Key1', 'Key2', ...]
	function item_cache_set($dbEntityName, $id, &$newEntity) {
		if (AppSettings['DataCacheEnabled'])
			update_db($dbEntityName, $newEntity, pick($newEntity, ...$id));
	}

	function item_delete($dbEntityName, $id, $require_login, $edit_validate, $any_validate, $before_func, $after_func, $permission = null, $permission_func = null, $collect_input = true, $input_data = null, $with_success = true) {
		require_once item_entity_php_filename($dbEntityName);

		# Verify request
		verify_request($require_login);
		$permission && permission($permission);

		# Collect input
		$input = $collect_input ? collect(...$id) : $input_data;

		# Validate input data
		$validate = array_merge_values(function(&$cur, &$new) { return new BoolAndAttribute($cur, $new); }, $edit_validate, $any_validate);
		$array = $id;
		array_unshift($array, $validate);
		$valid = validate_object($input, pick(...$array));

		# Validation error
		if ($valid !== true)
			fail($valid[0], ['For' => $valid[1]]);

		# Permission
		$permission_func && $permission_func($input);

		# Read from database
		$item = $dbEntityName::get($input);

		# "Data doesn't exist" error
		if (!$item) {
			fail(APIError::Nonexist);
		}

		# Before trigger
		if ($before_func && (strlen((string)($err = $before_func($item))) > 0)) fail($err);

		# Delete from database
		$result = $item->delete();

		# Return result
		if ($result) {
			# After trigger
			$after_func && $after_func($item);

			# Update cache
			item_cache_delete($dbEntityName, $item);

			$return_value = ['Success' => 1];
			return $with_success ? success($return_value) : $return_value;
		} else
			fail(APIError::Database);
	}

	function item_edit($dbEntityName, $id, $unique, $require_login, $edit_validate, $any_validate, $transform, $before_func, $after_func, $permission = null, $permission_func = null, $collect_input = true, $input_data = null, $with_success = true) {
		require_once item_entity_php_filename($dbEntityName);

		# Verify request
		verify_request($require_login);
		$permission && permission($permission);

		# Collect input
		$validate = array_merge_values(function(&$cur, &$new) { return new BoolAndAttribute($cur, $new); }, $edit_validate, $any_validate);
		$input = $collect_input ? collect(...array_keys($validate)) : $input_data;
		log_error("got $dbEntityName input", $input);

		# Validate input data
		$valid = validate_object($input, $validate);

		# Validation error
		if ($valid !== true)
			fail($valid[0], ['For' => $valid[1]]);

		# Permission
		$permission_func && $permission_func($input);

		# Read from database
		$array = $id;
		array_unshift($array, $input);
		$item = $dbEntityName::get(pick(...$array));
		log_error("$dbEntityName from db", $item);

		# "Data doesn't exist" error
		if (!$item)
			fail(APIError::Nonexist);

		# Insert/update database
		$array = [];
		foreach (array_keys($validate) as $item)
			$array[] = $item;
		foreach ($transform as $key => $value)
			$array[] = [$key, $value];
		array_unshift($array, $input);
		$input = pick(...$array);
		log_error('picked', $input);
		$item = new $dbEntityName(array_merge_if(':1 !== null', (array)$item, $input));

		# Before trigger
		if ($before_func && (strlen((string)($err = $before_func($item))) > 0)) fail($err);
		$item = new $dbEntityName((array) $item); # $before_func($item) erroneously transforms $item's class from $dbEntityName to stdClass

		# Duplicate
		$array = $id;
		if (!empty($unique))
			$array = array_merge($array, $unique);
		array_unshift($array, $item);
		$provider = get_dbi();
		$found = $provider->select_all($dbEntityName, [new BoolOrAttribute(pick(...$array))], [], -1, null);
		$item_id = pick($item, ...$id);
		foreach ($found as $found_) {
			$found_id = pick($found_, ...$id);
			if ($item_id != $found_id) # if two rows' IDs are equal, then it's one and the same row, and in this case an update is allowed, since the "duplicate" row is indeed actually the very row itself we want to update, so it's not a duplicate, and so it's allowed
				foreach ($found_id as $k => $v)
					if ($item_id[$k] == $v) # if two rows' ID's were not the same, but one of the ID fields are the same, then it's a duplicate. all ID fields must be unique in a table
						fail(APIError::Duplicate, ['For' => $unique]);
		}

		log_error("now updating $dbEntityName", $item);
		if ($item->update()) {
			# After trigger
			$after_func && $after_func($item);

			# Update cache
			item_cache_set($dbEntityName, $id, $item);

			# Return result
			$return_value = ['Success' => 1, $dbEntityName => $item];
			return $with_success ? success($return_value) : $return_value;
		} else {
			fail(APIError::Database);
		}
	}

	function item_entity_php_filename($dbEntityName) {
		log_error('item_entity_php_filename', $dbEntityName);
		$a = [lcfirst($dbEntityName), string_to_variable_name(variable_name_to_readable_string($dbEntityName))];
		foreach ($a as $file)
			if (file_exists("../entities/$file.php"))
				return "../entities/$file.php";
		return $a[0];
	}

	function item_list($dbEntityName, $returnKey, $id, $require_login, $validate, $relation, $summaryItems, $nonSummaryItems, $list_regex_alternative, $transform, $sort, $permission = null, $permission_func = null, $collect_input = true, $input_data = null, $with_success = true) {
		require_once item_entity_php_filename($dbEntityName);

		# Verify request
		verify_request($require_login);
		$permission && permission($permission);

		# Collect input
		$input = $collect_input ? collect(...$id) : $input_data;
		log_error('collected input', $input);

		# Validate input data
		$array = $id;
		array_unshift($array, $validate);
		$valid = validate_object($input, array_merge(pick(...$array), ['Start' => i('stringid or json_object'), 'Quantity' => i('numeric')]));

		# Validation error
		if ($valid !== true)
			fail($valid[0], ['For' => $valid[1]]);

		# Sanitize input
		$start = input('Start');
		if (is_object($start)) $start = (array)$start;
		if (is_string($start)) $start = strtolower($start);
		$quantity = input('Quantity');
		if (!$quantity) $quantity = 10;
		$where = (array)input('Where');
		$regex = (array)input('Regex');
		$join_where = array_values(preg_grep('/[.]/', $where)); # $join_where conditions need to be checked in tables joined to the current entity's table, in order to check that joined equals condition(s) are met in an entity
		$self_where = array_diff($where, $join_where); # $self_where contains [column => v] conditions that can be checked in the current entity's table row directly, it doesn't have to be joined with another table to perform a check if the specified equals condition is met in an entity
		$join_regex = array_values(preg_grep('/[.]/', $regex)); # $join_regex conditions need to be checked in tables joined to the current entity's table, in order to check that joined regex condition(s) are met in an entity
		$self_regex = array_diff($regex, $join_regex); # $self_regex contains [column => regex] conditions that can be checked in the current entity's table row directly, it doesn't have to be joined with another table to perform a check if the specified regex condition is met in an entity

		# Permission
		$permission_func && $permission_func($input);

		# Read from database
		# $summary = array_keys($input) !== $id || !all('!!:1', array_values($input));
		$summary = !count(array_keys($input)) || !all('!!:1', array_values($input));

		# Keep in mind that we double-purpose the $summaryItems and $nonSummaryItems array variables as (a) database columns, and as (b) pick specifiers.
		# Here, we are only interested in their purpose as database columns. It is possible for $summaryItems and $nonSummaryItems to contain arrays (pick specifiers). If it does, we need to transform that array into a scalar by replacing the array with its first element.
		$si = array_values(preg_grep('/[^.]/', array_map(function($col) { return is_array($col) ? $col[0] : $col; }, $summaryItems)));
		$nsi = array_values(preg_grep('/[^.]/', array_map(function($col) { return is_array($col) ? $col[0] : $col; }, $nonSummaryItems)));

		# Join items
		$jsi = array_values(preg_grep('/[.]/', $si)); # summary join items
		$jnsi = array_values(preg_grep('/[.]/', $nsi)); # non-summary join items
		$si = array_diff($si, $jsi);
		$nsi = array_diff($si, $jnsi);
		$join_columns = array_merge($jsi, $summary ? [] : $jnsi);

		log_error('item_list items', 'join_where', $join_where, 'self_where', $self_where, 'join_regex', $join_regex, 'self_regex', $self_regex, 'si', $si, 'nsi', $nsi, 'jsi', $jsi, 'jnsi', $jnsi, 'join_columns', $join_columns);

		if (!$summary)
			# Return all item details when getting a specific item
			#$items = $dbEntityName::get($input, array_merge($id, $si, $nsi));
			$items = [item_cache_get($dbEntityName, $input)];
		else {
			# Return only a summary of all items
			# $args = [array_merge_if(':1 !== null', $input, $self_where), array_merge($id, $si), $quantity];
			# if ($start) $args[] = is_array($start) ? ($start[0] ?? null) : $start;
			# $items = $dbEntityName::all(...$args);
			$items = item_cache_search($dbEntityName, array_merge_if(':1 !== null', $self_where + array_map_assoc(function($k, $v) { return [$k, new RegexAttribute(strtolower(preg_replace('/^\/*|\/*$/', '', $v)), true)]; }, $self_regex)));
		}

		# Define join value function, which gets a value from an ancestor entity, based on the referential links from an initial entity to the ancestor entity
		# #chain is an array of entity names, terminated with the field who's value should be returned, e.g. ['Location', 'Branch', 'Organization', 'OrganizationStatus']
		$get_join_value = function($initialEntity, $chain) {
			static $join_entities = [];
			$len = count($chain);

			# Get all required data from database, in order to join tables, for checking the specified join condition (e.g. Person.Employer.ContractType = 1), below
			for ($i = 0; $i < $len - 1; ++$i) {
				$item = $chain[$i] = ucfirst($chain[$i]);
				if (!isset($join_entities[$item]))
					$join_entities[$item] = item_cache($item);
			}

			$current_join_from_entity = $initialEntity;
			$current_join_from_entity_name = get_class($initialEntity);
			$found = true;
			for ($i = 0; $i < $len - 1; ++$i) {
				$current_join_to_entity_name = ucfirst($chain[$i]);
				$found = false;
				log_error('current join from entity', $current_join_from_entity);
				$relation = $current_join_from_entity->entityProperty('relation');
				for ($k = 0, $len2 = count($relation); $k < $len2; ++$k) {
					log_error('item_list evaluating relation', $current_join_from_entity_name, $relation[$k]);
					list($current_possible_join_to_entity_name, $current_possible_join_property_name) = explode('.', $relation[$k]);
					log_error('item_list relation', $current_possible_join_to_entity_name, $current_possible_join_property_name);

					log_error('item_list comparing right entity names', $current_possible_join_to_entity_name, $current_join_to_entity_name);
					if ($current_possible_join_to_entity_name == $current_join_to_entity_name) {
						$current_join_from_entity_name = $current_join_to_entity_name;
						$current_join_property_name = $current_possible_join_property_name;
						$entities = $join_entities[$current_join_to_entity_name];
						for ($l = 0, $len3 = count($entities); !$found && $l < $len3; ++$l) {
							$current_possible_join_to_entity = $entities[$l];
							log_error('item_list intermediate relation join condition comparison: comparing entity values to see if the corresponding property in each entity matches. if it does, then the entities can be joined, and the right-side entity is taken as the linking entity (e.g. it becomes the new left entity)', $current_join_from_entity->$current_join_property_name, $current_possible_join_to_entity->$current_join_property_name);
							if (isset($current_join_from_entity->$current_join_property_name, $current_possible_join_to_entity->$current_join_property_name) && $current_join_from_entity->$current_join_property_name == $current_possible_join_to_entity->$current_join_property_name) {
								log_error('item_list intermediate linking entity found', $current_join_from_entity_name, $relation[$k]);
								$found = true;
								$current_join_from_entity = $current_possible_join_to_entity;
							}
						}
						if (!$found) {
							log_error('item_list intermediate relation found, but the intermediate linking entity was not found, so the join condition cannot be further evaluated. The result of this join condition will evaluate to true', $initialEntity, $chain, $chain[$i], $relation[$k]);
							break 2;
						} else {
							log_error('item_list intermediate relation found, and the intermediate linking entity was also found, continuing', $initialEntity, $chain, $chain[$i], $relation[$k]);
							break;
						}
					}
				}
				if (!$found) {
					log_error('item_list intermediate relation or linking entity not found, so the result of this join condition evaluates to true', $initialEntity, $chain);
					break;
				}
			}

			return ['Found' => $found, 'Value' => $found ? $current_join_from_entity->{$chain[$len - 1]} : ''];
		};

		# Apply joins
		log_error('item_list', 'summary', $summary, 'where', $where, 'join_where', $join_where, 'self_where', $self_where, 'join_regex', $join_regex, 'self_regex', $self_regex, 'join_columns', $join_columns);
		if (is_array($items))
			for ($i = 0; $i < count($items);) {
				$child = $items[$i];

				# Do the joined wheres (join tables and check that specified join property has the required value (e.g. join Person and Employer, and check that Person.Employer.ContractType = 1))
				foreach ($join_where as $column => $where_value) { # $where ::= [Entity1.Entity2.Column => condition], e.g. [Person.Employer.ContractType => 1]
					log_error('item_list evaluating join where condition', $column, $where_value);
					$chain = explode('.', $column);
					$len = count($chain);

					$join = $get_join_value($child, $chain);

					if (($len == 1 || $join['Found']) && $join['Value'] != $where_value) {
						log_error('item_list final join where condition evaluated to false', $child, $column, $where_value);
						array_splice($items, $i, 1);
						continue 2;
					} else {
						log_error('item_list final join where condition evaluated to true', $child, $column, $where_value);
					}
				}

				# Do the joined regexes (join tables and check that specified join property matches the required regex (e.g. join Person and Employer, and check that Person.Employer.ContractType matches [1-9]))
				foreach ($join_regex as $column => $regex_value) { # $where ::= [Entity1.Entity2.Column => condition], e.g. [Person.Employer.ContractType => '[1-9]']
					log_error('item_list evaluating join regex condition', $column, $regex_value);
					$chain = explode('.', $column);
					$len = count($chain);

					$join = $get_join_value($child, $chain);

					$regex_tester = new RegexAttribute(strtolower($regex_value), true);
					$found = $regex_tester->test(strtolower($join['Value']));
					if (!$found && $list_regex_alternative && isset($list_regex_alternative[$column]))
						for ($j = 0, $len2 = count($list_regex_alternative[$column]); !$found && $j < $len2; ++$j) {
							$subject = strtolower(call_user_func(make_func($list_regex_alternative[$column][$j]), $join['Value']));
							$found = $regex_tester->test($subject);
							log_error('pattern', strtolower($regex_value), 'regex alternative function', $list_regex_alternative[$column], 'subject', $subject, 'result', $found);
						}

					if (($len == 1 || $join['Found']) && !$found) {
						log_error('item_list final join regex condition evaluated to false', $child, $column, $regex_value);
						array_splice($items, $i, 1);
						continue 2;
					} else {
						log_error('item_list final join regex condition evaluated to true', $child, $column, $regex_value);
					}
				}

				# Add "joined columns", from ancestor (linked/associated/primary) rows, to child rows retrieved from database
				if (count($join_columns))
					foreach ($join_columns as $join_string) { # ['Person.Employer.ContractType', 'Organization.OrganizationID', 'Linked_Relation.Field', etc.]
						$chain = explode('.', $join_string);
						$len = count($chain);
						$join = $get_join_value($child, $chain);
						if ($len == 1 || $join['Found'])
							$items[$i]->{$chain[$len - 1]} = $join['Value'];
					}

				log_error('item_list final item', $items[$i]);

				++$i;
			}

		# Return result
		if ($items) {
			# log_error('item_list got items', $items);
			# Sort result
			if ($summary) {
				$array = $sort;
				array_unshift($array, $items);
				$items = multisort(...$array);
			} else
				$items = $items[0];

			# Pick result
			$array = $transform;
			foreach ($id as $item)
				array_push($array, $item);
			foreach ($summaryItems as $item) {
				# A column definition can either be a string or an array with 2 elements, e.g. 'OrganizationStatus' or ['OrganizationStatus', i('bool')]
				$item_name = is_array($item) ? $item[0] : $item;

				# Extract only the property name from column definition, e.g. "Branch.Organization.OrganizationStatus", extract only "OrganizationStatus"
				$item_name = ($a = explode('.', $item_name))[count($a) - 1];
				if (is_array($item))
					$item[0] = $item_name;
				else
					$item = $item_name;
				array_push($array, $item);
			}
			if (!$summary)
				foreach ($nonSummaryItems as $item) {
					# A column definition can either be a string or an array with 2 elements, e.g. 'OrganizationStatus' or ['OrganizationStatus', i('bool')]
					$item_name = is_array($item) ? $item[0] : $item;

					# Extract only the property name from column definition, e.g. "Branch.Organization.OrganizationStatus", extract only "OrganizationStatus"
					$item_name = ($a = explode('.', $item_name))[count($a) - 1];
					if (is_array($item))
						$item[0] = $item_name;
					else
						$item = $item_name;
					array_push($array, $item);
				}
			array_unshift($array, $items);
			# log_error('item_list ready to pick array', $array);
			$items = pick(...$array);
			# log_error('item_list picked', $items);

			if ($summary) {
				# Paginate data
				if (empty($start))
					$startFunc = 'true';
				else {
					$startFunc = '';

					if (is_string($start)) {
						if (count($id) > 1)
							fail(APIError::Invalid);
						$startFunc = escape("strtolower(%s['{$id[0]}']) == strtolower(:1)", $start);
					} else {
						log_error('got list arguments', $start);
						foreach ($id as $item)
							if (isset($start[$item])) {
								$startFunc .= ($startFunc ? ' && ' : '') . escape("strtolower(%s['$item']) == strtolower(:1)", $start[$item]);
								log_error('item_list start func (loop)', $startFunc);
							}
					}

					if (empty($startFunc)) $startFunc = 'true';
					log_error('item_list final start function', $startFunc);
				}
				$ret = paginate($items, $quantity, $startFunc, AppSettings['DataCacheEnabled']);

				# Return result
				$return_value = [$returnKey => $ret->data, 'More' => $ret->more];
				return $with_success ? success($return_value) : $return_value;
			} else
				$return_value = [$returnKey => [$items]];
				return $with_success ? success($return_value) : $return_value;
		} else {
			if (!$summary)
				fail(APIError::Nonexist);

			$return_value = [$returnKey => []];
			return $with_success ? success($return_value) : $return_value;
		}
	}

	function item_pic_add($dbEntityName, $id, $require_login, $validate, $pictureBaseParentFolderID, $pictureFolderNameKey, $permission = null, $permission_func = null, $collect_input = true, $input_data = null, $with_success = true) {
		require_once item_entity_php_filename($dbEntityName);
		require_once '../google/drive.php';

		# Verify request
		verify_request($require_login);
		$permission && permission($permission);

		# Collect input
		$input = $collect_input ? collect(...array_keys($validate)) : $input_data;
		$input['Pic'] = $_FILES['Pic'];
		log_error("got $dbEntityName input", $input);

		# Permission
		$permission_func && $permission_func($input);

		# Validate input data
		$valid = validate_object($input, array_merge($validate, ['Pic' => i('require and array')]));

		# Validation error
		if ($valid !== true)
			fail($valid[0], ['For' => $valid[1]]);

		# Error
		$file = $_FILES['Pic'];
		if (($file['error'] ?? '') !== UPLOAD_ERR_OK)
			fail(APIError::Invalid);

		# Read from database
		$item = $dbEntityName::get(pick(...$id));
		log_error("$dbEntityName from db", $item);

		# "Data doesn't exist" error
		if (!$item) 
			fail(APIError::Nonexist);

		# Resize uploaded file
		$im = imagecreatefromstring(file_get_contents($file['tmp_name']));
		$im_resized = imagecreatetruecolor(610, 610);
		list($width, $height) = getimagesizefromstring($im);
		imagecopyresampled($im_resized, $im, 0, 0, 0, 0, 610, 610, $width, $height);
		ob_start();
		imagejpeg($im);
		$data = ob_get_clean();

		# Create filename
		$pictureNumberFilenames = array_map(function($file) { return $file->name; }, list_files($pictureBaseParentFolderID, $input[$pictureFolderNameKey])->files);
		$newPictureNumber = 1;
		while (in_array("$newPictureNumber.jpg", $pictureNumberFilenames))
			++$newPictureNumber;
		$newPictureNumberFilename = "$newPictureNumber.jpg";
		++$item->{"{$dbEntityName}PictureCount"};

		# Upload file to Google Drive
		$fileID = upload_file($pictureBaseParentFolderID, $input[$pictureFolderNameKey], $newPictureNumberFilename, $data, 'image/jpeg');

		# Update database
		$item->update();

		# Update cache
		item_cache_set($dbEntityName, $id, $item);

		# Return result
		$return_value = ['PicNum' => $newPictureNumber, 'PicID' => $fileID];
		return $with_success ? success($return_value) : $return_value;
	}

	function item_pic_delete($dbEntityName, $id, $require_login, $validate, $pictureBaseParentFolderID, $pictureFolderNameKey, $permission = null, $permission_func = null, $collect_input = true, $input_data = null, $with_success = true) {
		require_once item_entity_php_filename($dbEntityName);
		require_once '../google/drive.php';

		# Verify request
		verify_request($require_login);
		$permission && permission($permission);

		# Collect input
		$input = $collect_input ? collect(...$id) : $input_data;
		log_error("got $dbEntityName input", $input);

		# Validate input data
		$array = $id;
		array_unshift($array, $validate);
		$valid = validate_object($input, array_merge(pick(...$array), ['PicID' => i('require')]));

		# Validation error
		if ($valid !== true)
			fail($valid[0], ['For' => $valid[1]]);

		# Permission
		$permission_func && $permission_func($input);

		# Read from database
		$array = $id;
		array_unshift($array, $input);
		$item = $dbEntityName::get(pick(...$array));
		log_error("$dbEntityName from db", $item);

		# "Data doesn't exist" error
		if (!$item)
			fail(APIError::Nonexist);

		# "Data doesn't exist" error
		if (!in_array($input['PicID'], array_map(function($file) { return $file->id; }, list_files($pictureBaseParentFolderID, $input[$pictureFolderNameKey])->files)))
			fail(APIError::Nonexist);

		# Delete file from Google Drive
		delete_file($pictureBaseParentFolderID, $input[$pictureFolderNameKey], $input['PicID']);

		# Update database
		--$item->{"{$dbEntityName}PictureCount"};
		$result = $item->update();

		# Return result
		if ($result) {
			# Update cache
			item_cache_set($dbEntityName, $id, $item);

			$return_value = ['Success' => 1];
			return $with_success ? success($return_value) : $return_value;
		} else
			fail(APIError::Database);
	}

	function item_pic_list($dbEntityName, $id, $require_login, $validate, $pictureBaseParentFolderID, $pictureFolderNameKey, $permission = null, $permission_func = null, $collect_input = true, $input_data = null, $with_success = true) {
		require_once item_entity_php_filename($dbEntityName);
		require_once '../google/drive.php';

		# Verify request
		verify_request($require_login);
		$permission && permission($permission);

		# Collect input
		$array = $id;
		array_push($array, 'Offset');
		array_push($array, 'Count');
		$input = $collect_input ? collect(...$id) : $input_data;
		log_error("got $dbEntityName input", $input);

		# Validate input data
		$array = $id;
		array_unshift($array, $validate);
		array_push($array, ['Offset' => i('numeric')]);
		array_push($array, ['Count' => i('numeric')]);
		$valid = validate_object($input, pick(...$array));

		# Validation error
		if ($valid !== true)
			fail($valid[0], ['For' => $valid[1]]);

		# Permission
		$permission_func && $permission_func($input);

		# Read from database
		$array = $id;
		array_unshift($array, $input);
		$item = $dbEntityName::get(pick(...$array));
		log_error("$dbEntityName from db", $item);

		# "Data doesn't exist" error
		if (!$item)
			fail(APIError::Nonexist);

		# List files on Google Drive
		$result = multisort(array_map(function($file) { return ['PicID' => $file->id, 'PicNum' => (int)$file->name]; }, list_files($pictureBaseParentFolderID, $input[$pictureFolderNameKey])->files), 'PicNum');
		$offset = @$input['Offset'];
		$count = @$input['Count'];
		if (!$count)
			$count = 10;
		if ($offset || $count) {
			$offset = max($offset, 0);
			$count = max($count, 0);
			$result = array_slice($result, $offset, $count);
		}

		# Return result
		if ($result) {
			return $with_success ? success($result) : $result;
		}
		else
			fail(APIError::Database);
	}

	function item_pic_list_count($dbEntityName, $id, $require_login, $validate, $pictureBaseParentFolderID, $pictureFolderNameKey, $permission = null, $permission_func = null, $collect_input = true, $input_data = null, $with_success = true) {
		require_once item_entity_php_filename($dbEntityName);
		require_once '../google/drive.php';

		# Verify request
		verify_request($require_login);
		$permission && permission($permission);

		# Collect input
		$input = $collect_input ? collect(...$id) : $input_data;
		log_error("got $dbEntityName input", $input);

		# Validate input data
		$array = $id;
		array_unshift($array, $validate);
		$valid = validate_object($input, pick(...$array));

		# Validation error
		if ($valid !== true)
			fail($valid[0], ['For' => $valid[1]]);

		# Permission
		$permission_func && $permission_func($input);

		# Read from database
		$array = $id;
		array_unshift($array, $input);
		$item = $dbEntityName::get(pick(...$array));
		log_error("$dbEntityName from db", $item);

		# "Data doesn't exist" error
		if (!$item)
			fail(APIError::Nonexist);

		# Get file count on Google Drive
		$result = ['Count' => count(list_files($pictureBaseParentFolderID, $input[$pictureFolderNameKey])->files)];

		# Return result
		if ($result) {
			return $with_success ? success($result) : $result;
		} else
			fail(APIError::Database);
	}
?>
