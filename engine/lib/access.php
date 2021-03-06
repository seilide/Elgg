<?php

/**
 * Functions for Elgg's access system for entities, metadata, and annotations.
 *
 * Access is generally saved in the database as access_id.  This corresponds to
 * one of the ACCESS_* constants defined in {@link elgglib.php} or the ID of an
 * access collection.
 *
 * @package Elgg.Core
 * @subpackage Access
 */

use Elgg\Project\Paths;

/**
 * Set if Elgg's access system should be ignored.
 *
 * The access system will not return entities in any getter functions if the
 * user doesn't have access. This removes this restriction.
 *
 * When the access system is being ignored, all checks for create, retrieve,
 * update, and delete should pass. This affects all the canEdit() and related
 * methods.
 *
 * @tip Use this to access entities in automated scripts
 * when no user is logged in.
 *
 * @warning This will not show disabled entities.
 * Use {@link access_show_hidden_entities()} to access disabled entities.
 *
 * @note Internal: The access override is checked in elgg_override_permissions(). It is
 * registered for the 'permissions_check' hooks to override the access system for
 * the canEdit() and canWriteToContainer() methods.
 *
 * @note Internal: This clears the access cache.
 *
 * @note Internal: For performance reasons this is done at the database access clause level.
 *
 * @param bool $ignore If true, disables all access checks.
 *
 * @return bool Previous ignore_access setting.
 * @since 1.7.0
 * @see elgg_get_ignore_access()
 */
function elgg_set_ignore_access($ignore = true) {
	return _elgg_services()->session->setIgnoreAccess($ignore);
}

/**
 * Get current ignore access setting.
 *
 * @return bool
 * @since 1.7.0
 * @see elgg_set_ignore_access()
 */
function elgg_get_ignore_access() {
	return _elgg_services()->session->getIgnoreAccess();
}

/**
 * Return a string of access_ids for $user_guid appropriate for inserting into an SQL IN clause.
 *
 * @uses get_access_array
 *
 * @see get_access_array()
 *
 * @param int  $user_guid User ID; defaults to currently logged in user
 * @param int  $ignored   Ignored parameter
 * @param bool $flush     If set to true, will refresh the access list from the
 *                        database rather than using this function's cache.
 *
 * @return string A list of access collections suitable for using in an SQL call
 * @access private
 */
function get_access_list($user_guid = 0, $ignored = 0, $flush = false) {
	return _elgg_services()->accessCollections->getAccessList($user_guid, $flush);
}

/**
 * Returns an array of access IDs a user is permitted to see.
 *
 * Can be overridden with the 'access:collections:read', 'user' plugin hook.
 * @warning A callback for that plugin hook needs to either not retrieve data
 * from the database that would use the access system (triggering the plugin again)
 * or ignore the second call. Otherwise, an infinite loop will be created.
 *
 * This returns a list of all the collection ids a user owns or belongs
 * to plus public and logged in access levels. If the user is an admin, it includes
 * the private access level.
 *
 * @note Internal: this is only used in core for creating the SQL where clause when
 * retrieving content from the database. The friends access level is handled by
 * _elgg_get_access_where_sql().
 *
 * @see get_write_access_array() for the access levels that a user can write to.
 *
 * @param int  $user_guid User ID; defaults to currently logged in user
 * @param int  $ignored   Ignored parameter
 * @param bool $flush     If set to true, will refresh the access ids from the
 *                        database rather than using this function's cache.
 *
 * @return array An array of access collections ids
 */
function get_access_array($user_guid = 0, $ignored = 0, $flush = false) {
	return _elgg_services()->accessCollections->getAccessArray($user_guid, $flush);
}

/**
 * Gets the default access permission.
 *
 * This returns the default access level for the site or optionally of the user.
 * If want you to change the default access based on group of other information,
 * use the 'default', 'access' plugin hook.
 *
 * @param ElggUser $user         The user for whom we're getting default access. Defaults to logged in user.
 * @param array    $input_params Parameters passed into an input/access view
 *
 * @return int default access id (see ACCESS defines in elgglib.php)
 */
function get_default_access(ElggUser $user = null, array $input_params = []) {
	// site default access
	$default_access = _elgg_config()->default_access;

	// user default access if enabled
	if (_elgg_config()->allow_user_default_access) {
		$user = $user ? $user : _elgg_services()->session->getLoggedInUser();
		if ($user) {
			$user_access = $user->getPrivateSetting('elgg_default_access');
			if ($user_access !== null) {
				$default_access = $user_access;
			}
		}
	}

	$params = [
		'user' => $user,
		'default_access' => $default_access,
		'input_params' => $input_params,
	];
	return _elgg_services()->hooks->trigger('default', 'access', $params, $default_access);
}

/**
 * Allow disabled entities and metadata to be returned by getter functions
 *
 * @todo Replace this with query object!
 * @global bool $ENTITY_SHOW_HIDDEN_OVERRIDE
 * @access private
 */
$ENTITY_SHOW_HIDDEN_OVERRIDE = false;

/**
 * Show or hide disabled entities.
 *
 * @param bool $show_hidden Show disabled entities.
 * @return bool
 */
function access_show_hidden_entities($show_hidden) {
	global $ENTITY_SHOW_HIDDEN_OVERRIDE;
	$current_value = $ENTITY_SHOW_HIDDEN_OVERRIDE;
	$ENTITY_SHOW_HIDDEN_OVERRIDE = $show_hidden;
	return $current_value;
}

/**
 * Return current status of showing disabled entities.
 *
 * @return bool
 */
function access_get_show_hidden_status() {
	global $ENTITY_SHOW_HIDDEN_OVERRIDE;
	return $ENTITY_SHOW_HIDDEN_OVERRIDE;
}

/**
 * Returns the SQL where clause for enforcing read access to data.
 *
 * Note that if this code is executed in privileged mode it will return (1=1).
 *
 * Otherwise it returns a where clause to retrieve the data that a user has
 * permission to read.
 *
 * Plugin authors can hook into the 'get_sql', 'access' plugin hook to modify,
 * remove, or add to the where clauses. The plugin hook will pass an array with the current
 * ors and ands to the function in the form:
 *  array(
 *      'ors' => array(),
 *      'ands' => array()
 *  )
 *
 * The results will be combined into an SQL where clause in the form:
 *  ((or1 OR or2 OR orN) AND (and1 AND and2 AND andN))
 *
 * @param array $options Array in format:
 *
 * 	table_alias => STR Optional table alias. This is based on the select and join clauses.
 *                     Default is 'e'.
 *
 *  user_guid => INT Optional GUID for the user that we are retrieving data for.
 *                   Defaults to the logged in user.
 *
 *  use_enabled_clause => BOOL Optional. Should we append the enabled clause? The default
 *                             is set by access_show_hidden_entities().
 *
 *  access_column => STR Optional access column name. Default is 'access_id'.
 *
 *  owner_guid_column => STR Optional owner_guid column. Default is 'owner_guid'.
 *
 *  guid_column => STR Optional guid_column. Default is 'guid'.
 *
 * @return string
 * @access private
 */
function _elgg_get_access_where_sql(array $options = []) {
	return _elgg_services()->accessCollections->getWhereSql($options);
}

/**
 * Can a user access an entity.
 *
 * @warning If a logged in user doesn't have access to an entity, the
 * core engine will not load that entity.
 *
 * @tip This is mostly useful for checking if a user other than the logged in
 * user has access to an entity that is currently loaded.
 *
 * @todo This function would be much more useful if we could pass the guid of the
 * entity to test access for. We need to be able to tell whether the entity exists
 * and whether the user has access to the entity.
 *
 * @param \ElggEntity $entity The entity to check access for.
 * @param \ElggUser   $user   Optionally user to check access for. Defaults to
 *                           logged in user (which is a useless default).
 *
 * @return bool
 */
function has_access_to_entity($entity, $user = null) {
	return _elgg_services()->accessCollections->hasAccessToEntity($entity, $user);
}

/**
 * Returns an array of access permissions that the user is allowed to save content with.
 * Permissions returned are of the form (id => 'name').
 *
 * Example return value in English:
 * array(
 *     0 => 'Private',
 *    -2 => 'Friends',
 *     1 => 'Logged in users',
 *     2 => 'Public',
 *    34 => 'My favorite friends',
 * );
 *
 * Plugin hook of 'access:collections:write', 'user'
 *
 * @warning this only returns access collections that the user owns plus the
 * standard access levels. It does not return access collections that the user
 * belongs to such as the access collection for a group.
 *
 * @param int   $user_guid    The user's GUID.
 * @param int   $ignored      Ignored parameter
 * @param bool  $flush        If this is set to true, this will ignore a cached access array
 * @param array $input_params Some parameters passed into an input/access view
 *
 * @return array List of access permissions
 */
function get_write_access_array($user_guid = 0, $ignored = 0, $flush = false, array $input_params = []) {
	return _elgg_services()->accessCollections->getWriteAccessArray($user_guid, $flush, $input_params);
}

/**
 * Can the user change this access collection?
 *
 * Use the plugin hook of 'access:collections:write', 'user' to change this.
 * @see get_write_access_array() for details on the hook.
 *
 * Respects access control disabling for admin users and {@link elgg_set_ignore_access()}
 *
 * @see get_write_access_array()
 *
 * @param int   $collection_id The collection id
 * @param mixed $user_guid     The user GUID to check for. Defaults to logged in user.
 * @return bool
 */
function can_edit_access_collection($collection_id, $user_guid = null) {
	return _elgg_services()->accessCollections->canEdit($collection_id, $user_guid);
}

/**
 * Creates a new access collection.
 *
 * Access colletions allow plugins and users to create granular access
 * for entities.
 *
 * Triggers plugin hook 'access:collections:addcollection', 'collection'
 *
 * @note Internal: Access collections are stored in the access_collections table.
 * Memberships to collections are in access_collections_membership.
 *
 * @param string $name       The name of the collection.
 * @param int    $owner_guid The GUID of the owner (default: currently logged in user).
 *
 * @return int|false The collection ID if successful and false on failure.
 * @see update_access_collection()
 * @see delete_access_collection()
 */
function create_access_collection($name, $owner_guid = 0) {
	return _elgg_services()->accessCollections->create($name, $owner_guid);
}

/**
 * Updates the membership in an access collection.
 *
 * @warning Expects a full list of all members that should
 * be part of the access collection
 *
 * @note This will run all hooks associated with adding or removing
 * members to access collections.
 *
 * @param int   $collection_id The ID of the collection.
 * @param array $members       Array of member GUIDs
 *
 * @return bool
 * @see add_user_to_access_collection()
 * @see remove_user_from_access_collection()
 */
function update_access_collection($collection_id, $members) {
	return _elgg_services()->accessCollections->update($collection_id, $members);
}

/**
 * Deletes a specified access collection and its membership.
 *
 * @param int $collection_id The collection ID
 *
 * @return bool
 * @see create_access_collection()
 * @see update_access_collection()
 */
function delete_access_collection($collection_id) {
	return _elgg_services()->accessCollections->delete($collection_id);
}

/**
 * Get a specified access collection
 *
 * @note This doesn't return the members of an access collection,
 * just the database row of the actual collection.
 *
 * @see get_members_of_access_collection()
 *
 * @param int $collection_id The collection ID
 *
 * @return ElggAccessCollection|false
 */
function get_access_collection($collection_id) {
	return _elgg_services()->accessCollections->get($collection_id);
}

/**
 * Adds a user to an access collection.
 *
 * Triggers the 'access:collections:add_user', 'collection' plugin hook.
 *
 * @param int $user_guid     The GUID of the user to add
 * @param int $collection_id The ID of the collection to add them to
 *
 * @return bool
 * @see update_access_collection()
 * @see remove_user_from_access_collection()
 */
function add_user_to_access_collection($user_guid, $collection_id) {
	return _elgg_services()->accessCollections->addUser($user_guid, $collection_id);
}

/**
 * Removes a user from an access collection.
 *
 * Triggers the 'access:collections:remove_user', 'collection' plugin hook.
 *
 * @param int $user_guid     The user GUID
 * @param int $collection_id The access collection ID
 *
 * @return bool
 * @see update_access_collection()
 * @see remove_user_from_access_collection()
 */
function remove_user_from_access_collection($user_guid, $collection_id) {
	return _elgg_services()->accessCollections->removeUser($user_guid, $collection_id);
}

/**
 * Returns access collections owned by the entity
 *
 * @see add_access_collection()
 * @see get_members_of_access_collection()
 *
 * @param int $owner_guid GUID of the owner
 * @return \ElggAccessCollection[]|false
 */
function get_user_access_collections($owner_guid) {
	return _elgg_services()->accessCollections->getEntityCollections($owner_guid);
}

/**
 * Get all of members of an access collection
 *
 * @param int   $collection_id The collection's ID
 * @param bool  $guids_only    If set to true, will only return the members' GUIDs (default: false)
 * @param array $options       ege* options
 *
 * @return ElggUser[]|int[]|false guids or entities if successful, false if not
 * @see add_user_to_access_collection()
 */
function get_members_of_access_collection($collection_id, $guids_only = false, array $options = []) {
	if (!isset($options['limit'])) {
		$options['limit'] = 0;
	}

	if (!$guids_only) {
		return _elgg_services()->accessCollections->getMembers($collection_id, $options);
	}

	$guids = [];
	$options['callback'] = false;
	$rows = _elgg_services()->accessCollections->getMembers($collection_id, $options);
	foreach ($rows as $row) {
		$guids[] = $row->guid;
	}
	return $guids;
}

/**
 * Return entities based upon access id.
 *
 * TODO(ewinslow): Move this logic into elgg_get_entities
 *
 * @param array $options Any options accepted by {@link elgg_get_entities()} and
 * 	access_id => int The access ID of the entity.
 *
 * @see elgg_get_entities()
 * @return mixed If count, int. If not count, array. false on errors.
 * @since 1.7.0
 */
function elgg_get_entities_from_access_id(array $options = []) {
	// restrict the resultset to access collection provided
	if (!isset($options['access_id'])) {
		return false;
	}

	// @todo add support for an array of collection_ids
	$where = "e.access_id = '{$options['access_id']}'";
	if (isset($options['wheres'])) {
		if (is_array($options['wheres'])) {
			$options['wheres'][] = $where;
		} else {
			$options['wheres'] = [$options['wheres'], $where];
		}
	} else {
		$options['wheres'] = [$where];
	}

	// return entities with the desired options
	return _elgg_services()->entityTable->getEntities($options);
}

/**
 * Lists entities from an access collection
 *
 * @param array $options See elgg_list_entities() and elgg_get_entities_from_access_id()
 *
 * @see elgg_list_entities()
 * @see elgg_get_entities_from_access_id()
 *
 * @return string
 */
function elgg_list_entities_from_access_id(array $options = []) {
	return elgg_list_entities($options, 'elgg_get_entities_from_access_id');
}

/**
 * Return the name of an ACCESS_* constant or an access collection,
 * but only if the logged in user has write access to it.
 * Write access requirement prevents us from exposing names of access collections
 * that current user has been added to by other members and may contain
 * sensitive classification of the current user (e.g. close friends vs acquaintances).
 *
 * Returns a string in the language of the user for global access levels, e.g.'Public, 'Friends', 'Logged in', 'Public';
 * or a name of the owned access collection, e.g. 'My work colleagues';
 * or a name of the group or other access collection, e.g. 'Group: Elgg technical support';
 * or 'Limited' if the user access is restricted to read-only, e.g. a friends collection the user was added to
 *
 * @param int $entity_access_id The entity's access id
 * @return string
 * @since 1.7.0
 */
function get_readable_access_level($entity_access_id) {
	return _elgg_services()->accessCollections->getReadableAccessLevel($entity_access_id);
}

/**
 * A quick and dirty way to make sure the access permissions have been correctly set up
 *
 * @elgg_event_handler init system
 *
 * @return void
 */
function access_init() {
	_elgg_services()->accessCollections->markInitComplete();
}

/**
 * Runs unit tests for the access library
 *
 * @param string $hook
 * @param string $type
 * @param array $value
 * @param array $params
 * @return array
 *
 * @access private
 */
function access_test($hook, $type, $value, $params) {
	$value[] = Paths::elgg() . 'engine/tests/simpletest/ElggCoreAccessCollectionsTest.php';
	$value[] = Paths::elgg() . 'engine/tests/simpletest/ElggCoreAccessSQLTest.php';
	return $value;
}

/**
 * @see \Elgg\Application::loadCore Do not do work here. Just register for events.
 */
return function(\Elgg\EventsService $events, \Elgg\HooksRegistrationService $hooks) {
	// Tell the access functions the system has booted, plugins are loaded,
	// and the user is logged in so it can start caching
	$events->registerHandler('ready', 'system', 'access_init');

	$hooks->registerHandler('unit_test', 'system', 'access_test');
};
