<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file implements the main router entry point (i.e. handler)
	 * for PHPCentauri Item Engine (PIE), which is Cluedapp's generic PHP
	 * REST Entity Controller API middleware.
	 *
	 * PIE input models should be placed in the entities directory.
	 * The relevant terminology here is "controller", "entity",
	 * "entity file", "entity class", "database row", "database table",
	 * "model", "item" and "thing". This "controller" controls adding,
	 * deleting, updating, etc. an "entity" using a PHPCentauri Http API.
	 * A PIE "entity file" (essentially a file in the "entities"
	 * directory) defines a one-to-one mapping between a "database row"
	 * in a "database table" and an instance of the "entity class". Each
	 * PHP property in an "entity class" is mapped to and from a single
	 * database field in the "database row" for that particular "entity".
	 *
	 * Entities are called "entities" and not "models" to distinguish
	 * between an "entity" that represents an actual existing "database
	 * table" or an actual existing "database row" (the difference between
	 * the table and the row in this context is irrelevant), and a "model"
	 * which does not usually refer to a database table or row, but just a
	 * generic object that can be de-serialized from data POSTed to the
	 * controller. An "entity" can be in one of two states, either an "actual"
	 * state or a "lookup" state. When in an "actual" state, the PHP properties
	 * of the "entity" will contain the actual values that were read from,
	 * or will be written to, the "database row"'s fields for that particular
	 * "entity". When in a "lookup" state, the "entity" can be called an "item",
	 * and each of the "entity"'s PHP properties can contain an array, which
	 * represents a set of allowable values which the database field/column for
	 * that PHP property may contain in the "database row" in the "database
	 * table", for it to be returned from the database, when a retrieve/list
	 * operation is performed (see tableStorage.php and the __createCriteria
	 * function).
	 *
	 * The term "item" is a bit more loose. It can be seen as a "thing",
	 * an instance of an "entity", or an array/object of values to populate
	 * into an "entity", for saving to the database, for example. It is
	 * also called "item" and not "entity" since the word "entity"
	 * leans a bit more to the business domain jargon side, where a business
	 * "thing" is abstracted and modeled to exist in a database, almost like
	 * a "record". When thinking about a business object's real life physical
	 * existence, one tends to think of that "thing" as an "item", while when
	 * thinking about the "thing"'s data definition and existence in a
	 * database, one tends to conceptualize the "thing" as an "entity" or
	 * "record". When the "entity" is read from the database and spawned
	 * into the the world of PHP, it becomes an "item".
	 *
	 * To be clear, "model", "item" and "thing" don't have anything to
	 * do with PIE. They are just mentioned to contrast them with the
	 * other terms in the relevant terminology.
	 *
	 * The add_before, edit_before, any_before and delete_before entity
	 * properties are triggers that are fired before an entity is added,
	 * updated or deleted to/from its database table. If a non-zero
	 * length string is returned from the trigger function, then an error
	 * is reported by the API using the returned string, and the
	 * entity is not added/update/deleted.
	 *
	 * The add_after, edit_after, any_after and delete_after entity
	 * property trigger functions are fired after an entity is added,
	 * updated or deleted from its database table. The return value from
	 * the trigger function is ignored.
	 *
	 * The any_before and any_after triggers are only fired when an
	 * entity is (attempted to be) added or updated, not when it is
	 * (attempted to be) deleted.
	 *
	 * A rewrite should be added to IIS, Nginx or Apache, so that
	 * URLs are routed correctly to PIE:
	 * # Route to PIE handler (PHPCentauri Item Engine)
	 * rewrite (?i)/api/Item/([^./]+)/(add|delete|edit|list|pic_add|pic_delete|pic_list|pic_list_count)/?$ /api/pie.php?item=$1&action=$2 last;
	 *
	 * Supported controller actions:
	 * add, delete, edit, list, pic_add, pic_delete, pic_list, pic_list_count
	 */

	require_once '../vendor/autoload.php';

	# Input passed by web server rewrite
	$entity_name = $_GET['item'] ?? null;
	$action = strtolower($_GET['action']);

	entity_action($entity_name, $action, true, null, true);
?>
