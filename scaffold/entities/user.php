<?php
	class User extends BaseEntity {

		public $UserID;
		public $UserCellphone;
		public $UserEmail;
		public $UserFirstName;
		public $UserLastName;
		public $Username;
		public $UserPassword;
		public $UserStatus;

		function __construct(array $init = []) {
			parent::__construct($init);

			# Azure Table Storage
			$this->RowKey = $this->UserID;

			# Identity
			$this->entityProperty('entity', 'User');
			$this->entityProperty('collection_name', 'Users');
			$this->entityProperty('id', ['UserID']);
			$this->entityProperty('unique', ['UserEmail', 'UserCellphone']);

			# Permission
			$this->entityProperty('permission', ['Account' => ['Admin' => ['Value' => 1]]]);
			$this->entityProperty('permission_func', function($input) {
				# Call fail(APIError::Permission) if user doesn't have the necessary access rights for this entity

				# The return value of this function is ignored
				# return; # or 1) return true; for semantic indication that the user has access rights, or 2. don't do anything, just implicitly return from the function
			});
			
			# Validation
			$this->entityProperty('add_validate', [
				'UserCellphone' => i('require'),
				'UserEmail' => i('require'),
				'UserFirstName' => i('require'),
				'UserID' => i('require'),
				'UserLastName' => i('require'),
				'Username' => i('require'),
				'UserPassword' => i('require'),
			]);
			$this->entityProperty('edit_validate', [
				'UserID' => i('require'),
			]);
			$this->entityProperty('any_validate', [
				'UserCellphone' => i('regex("^\d{10}$")'),
				'UserEmail' => i('email'),
				'UserFirstName' => i('string'),
				'UserID' => i('string'),
				'UserLastName' => i('string'),
				'Username' => i('string'),
				'UserPassword' => i('string'),
			]);

			# Defaults
			$this->entityProperty('add_defaults', [
				'UserStatus' => true
			]);

			# Transform
			$this->entityProperty('transform', [
				['UserEmail', function($email) { return strtolower($email); }],
				['UserFirstName', function($first_name) { return trim($first_name); }],
				['UserLastName', function($last_name) { return trim($last_name); }],
				['Username', function($username) { return trim($username); }],
				['UserPassword', function($password) { return password_hash($password, PASSWORD_DEFAULT); }],
				['UserStatus', i('bool')]
			]);

			# Triggers
			$this->entityProperty('add_before', function($new_entity) {
				# Return a string to prevent the entity from being added
				# Returning either true or false, both have the same effect, which is that a boolean is not a string, so no error is raised
				return;
			});
			$this->entityProperty('add_after', function($new_entity) {
				# The entity is already added now, so the return value cannot affect the entity anymore
				return;
			});
			$this->entityProperty('edit_before', function($new_entity) {
				# Return false to prevent the entity from being edited
				# Returning either true or false, both have the same effect, which is that a boolean is not a string, so no error is raised
				return;
			});
			$this->entityProperty('edit_after', function($new_entity) {
				# The entity is already edited now, so the return value cannot affect the entity anymore
				return;
			});
			$this->entityProperty('any_before', function($new_entity) {
				# Return false to prevent the entity from being added or edited
				# Returning either true or false, both have the same effect, which is that a boolean is not a string, so no error is raised
				# This is only called for add or edit, not for delete
				return;
			});
			$this->entityProperty('any_after', function($new_entity) {
				# The entity is already added or edited now, so the return value cannot affect the entity anymore
				# This is only called for add or edit, not for delete
				return;
			});
			$this->entityProperty('delete_before', function($old_entity) {
				# Return false to prevent the entity from being deleted
				# Returning either true or false, both have the same effect, which is that a boolean is not a string, so no error is raised
				return;
			});
			$this->entityProperty('delete_after', function($old_entity) {
				# The entity is already deleted now, so the return value cannot affect the entity anymore
				return;
			});

			# List
			$this->entityProperty('list_summary', [
				'UserEmail',
				'UserFirstName',
				'UserID',
				'UserLastName',
				'UserStatus', 
			]);
			$this->entityProperty('list_rest', [
				'UserCellphone',
				'Username',
			]);
			$this->entityProperty('list_transform', [
				['AccountStatus', i('int')]
			]);
			$this->entityProperty('list_sort', [
				['UserLastName', 'strtolower(:1)'],
				['UserFirstName', 'strtolower(:1)'],
				['UserEmail', 'strtolower(:1)'],
			]);

			# Relations
			$this->entityProperty('relation', []);
		}
	}
?>
