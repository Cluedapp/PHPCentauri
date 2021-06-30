<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file is a core part of the PHPCentauri DBI (DataBase Interface) framework.
	 * It is the base entity ORM class, from which all model classes must inherit, for the
	 * model's backing table to be interacted with through the DBI framework's DBProvider class.
	 */

	class BaseEntity {
		protected static $provider;

		public $PartitionKey;
		public $RowKey;
		public $Timestamp;

		function __construct(array $init = []) {
			static::$provider = get_dbi();

			foreach ($init as $k => &$v) {
				$this->$k = $v;
			}
			if (!$this->PartitionKey) {
				$this->PartitionKey = AppSettings['DefaultPartitionKey'];
			}
		}

		public function getPartitionKey() {
			return $this->PartitionKey;
		}

		public function setPartitionKey($value) {
			$this->PartitionKey = $value;
		}

		public function getRowKey() {
			return $this->RowKey;
		}

		public function setRowKey($value) {
			$this->RowKey = $value;
		}

		public function entityProperty() {
			static $properties = [];
			$args = func_get_args();
			if (count($args) == 1)
				return $properties[$args[0]] ?? null;
			else
				$properties[$args[0]] = $args[1];
		}

		public function __call($name, $arguments) {
			switch ($name) {
				case 'get':
					# Called like $entity->get([$where...], [$columns...]);
					return call_user_func_array([&$this, '__getInstance'], $arguments);
				case 'delete':
					# Called like $entity->delete([$where...]);
					return call_user_func_array([&$this, '__deleteInstance'], $arguments);
				case 'isUnique':
					# Called like $entity->isUnique([$where...]);
					return call_user_func_array([&$this, '__isUniqueInstance'], $arguments);
			}
		}

		public static function __callStatic($name, $arguments) {
			switch ($name) {
				case 'get':
					# Called like Entity::get([$where...], [$columns...]);
					return call_user_func_array([static::class, '__getStatic'], $arguments);
				case 'delete':
					# Called like Entity::delete([$where...]);
					return call_user_func_array([static::class, '__deleteStatic'], $arguments);
				case 'isUnique':
					# Called like Entity::isUnique([$where...]);
					return call_user_func_array([static::class, '__isUniqueStatic'], $arguments);
			}
		}

		# Not called directly
		# Called like Entity::get([$where...]);
		public static function __getStatic($where = [], $columns = []) {
			$className = static::class;
			$where = (array)new $className($where);
			static::__normalizeCriteria($where);
			return static::$provider->select(new $className($where), $columns);
		}

		# Not called directly
		# Called like $entity->get([$where...], [$columns...]);
		public function __getInstance($where = [], $columns = []) {
			$className = get_class($this);
			$where = array_merge((array)$this, (array)new $className($where));
			static::__normalizeCriteria($where);
			return static::$provider->select(new $className($where), $columns);
		}

		# Called like Entity::all([$where...], [$columns...]);
		public static function all($where = [], $columns = []) {
			$className = static::class;
			$where = (array)new $className($where);
			static::__normalizeCriteria($where);
			return static::$provider->select_all($className, $where, $columns, -1, null);
		}

		# Called like $entity->insert();
		public function insert() {
			return static::$provider->insert($this);
		}

		# Called like Entity::delete([$where...]);
		public static function __deleteStatic($where = []) {
			static::__normalizeCriteria($where);
			return static::$provider->delete(static::$provider->select_all(static::class, $where));
		}

		# Called like $entity->delete();
		public function __deleteInstance() {
			return static::$provider->delete($this);
		}

		# Called like $entity->update();
		public function update() {
			return static::$provider->update($this);
		}

		# Check if the row is unique, based on its entity properties
		# Called like Entity::isUnique([$where...]);
		public static function __isUniqueStatic($where = []) {
			$className = static::class;
			static::__normalizeCriteria($where);
			# $obj = new $className();
			$unique = $obj->entityProperty('unique');
			return !$unique || !count($unique) || static::$provider->is_unique($className, $where);
		}

		# Check if the entity is unique or duplicate, based on its entity properties
		# Called like $entity->isUnique();
		public function __isUniqueInstance() {
			$className = get_class($this);
			$unique = $this->entityProperty('unique');
			$criteria = pick($this, ...$unique);
			static::__normalizeCriteria($criteria);
			# $obj = new $className($criteria);
			return !$unique || !count($unique) || static::$provider->is_unique($className, $criteria);
		}

		private static function __normalizeCriteria(&$where) {
			foreach ($where as $k => $v)
				if ($v === null) unset($where[$k]);
			# if (!isset($where['PartitionKey'])) {
			# 	$where['PartitionKey'] = AppSettings['DefaultPartitionKey'];
			# }
		}
	}
?>
