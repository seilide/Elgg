<?php
/**
 * Access SQL tests
 *
 * @package Elgg
 * @subpackage Test
 */
class ElggCoreAccessSQLTest extends \ElggCoreUnitTest {

	/** @var \ElggUser */
	protected $user;

	protected $backup_logged_in_user;

	/**
	 * Called before each test method.
	 */
	public function setUp() {
		// Replace current hook service with new instance for each test
		_elgg_services()->hooks->backup();

		$this->user = new \ElggUser();
		$this->user->username = 'fake_user_' . rand();
		$this->user->email = 'fake_email@fake.com' . rand();
		$this->user->name = 'fake user ' . rand();
		$this->user->access_id = ACCESS_PUBLIC;
		$this->user->setPassword((string)rand());
		$this->user->owner_guid = 0;
		$this->user->container_guid = 0;
		$this->user->save();

		$this->backup_logged_in_user = _elgg_services()->session->getLoggedInUser();
		_elgg_services()->session->setLoggedInUser($this->user);
	}

	/**
	 * Called after each test method.
	 */
	public function tearDown() {
		_elgg_services()->hooks->restore();

		_elgg_services()->session->setLoggedInUser($this->backup_logged_in_user);

		$this->user->delete();
	}

	public function testCanBuildAccessSqlClausesWithIgnoredAccess() {
		$sql = _elgg_get_access_where_sql([
			'ignore_access' => true,
		]);
		$ans = "((1 = 1) AND (e.enabled = 'yes'))";
		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");
	}

	public function testCanBuildAccessSqlClausesWithIgnoredAccessWithoutDisabledEntities() {
		$sql = _elgg_get_access_where_sql([
			'use_enabled_clause' => false,
			'ignore_access' => true,
		]);
		$ans = "((1 = 1))";
		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");		
	}

	public function testCanBuildAccessSqlForLoggedInUser() {
		$sql = _elgg_get_access_where_sql();
		$friends_clause = $this->getFriendsClause($this->user->guid, 'e');
		$owner_clause = $this->getOwnerClause($this->user->guid, 'e');
		$access_clause = $this->getLoggedInAccessListClause('e');
		$ans = "(($friends_clause OR $owner_clause OR $access_clause) AND (e.enabled = 'yes'))";

		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");
	}

	public function testCanBuildAccessSqlWithCustomTableAlias() {
		$sql = _elgg_get_access_where_sql(array(
			'table_alias' => 'foo',
		));

		$friends_clause = $this->getFriendsClause($this->user->guid, 'foo');
		$owner_clause = $this->getOwnerClause($this->user->guid, 'foo');
		$access_clause = $this->getLoggedInAccessListClause('foo');
		$ans = "(($friends_clause OR $owner_clause OR $access_clause) AND (foo.enabled = 'yes'))";

		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");

		// test with no alias
		$sql = _elgg_get_access_where_sql(array(
			'user_guid' => $this->user->guid,
			'table_alias' => '',
		));

		$friends_clause = $this->getFriendsClause($this->user->guid, '');
		$owner_clause = $this->getOwnerClause($this->user->guid, '');
		$access_clause = $this->getLoggedInAccessListClause('');
		$ans = "(($friends_clause OR $owner_clause OR $access_clause) AND (enabled = 'yes'))";

		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");
	}

	public function testCanBuildAccessSqlWithCustomGuidColumn() {
		$sql = _elgg_get_access_where_sql(array(
			'owner_guid_column' => 'unit_test',
		));

		$friends_clause = $this->getFriendsClause($this->user->guid, 'e', 'unit_test');
		$owner_clause = $this->getOwnerClause($this->user->guid, 'e', 'unit_test');
		$access_clause = $this->getLoggedInAccessListClause('e');
		$ans = "(($friends_clause OR $owner_clause OR $access_clause) AND (e.enabled = 'yes'))";

		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");
	}

	public function testCanBuildAccessSqlForLoggedOutUser() {

		$user = _elgg_services()->session->getLoggedInUser();
		_elgg_services()->session->removeLoggedInUser();

		$sql = _elgg_get_access_where_sql();
		$access_clause = $this->getLoggedOutAccessListClause('e');
		$ans = "(($access_clause) AND (e.enabled = 'yes'))";

		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");

		_elgg_services()->session->setLoggedInUser($user);
	}

	public function testAccessPluginHookRemoveEnabled() {
		elgg_register_plugin_hook_handler('get_sql', 'access', array($this, 'removeEnabledCallback'));
		$sql = _elgg_get_access_where_sql([
			'ignore_access' => true,
		]);
		$ans = "((1 = 1))";
		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");
	}

	public function removeEnabledCallback($hook, $type, $clauses, $params) {
		$clauses['ands'] = array();
		return $clauses;
	}

	public function testAccessPluginHookRemoveOrs() {
		elgg_register_plugin_hook_handler('get_sql', 'access', array($this, 'removeOrsCallback'));
		$sql = _elgg_get_access_where_sql([
			'ignore_access' => true,
		]);
		$ans = "((e.enabled = 'yes'))";
		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");
	}

	public function removeOrsCallback($hook, $type, $clauses, $params) {
		$clauses['ors'] = array();
		return $clauses;
	}
	
	public function testAccessPluginHookAddOr() {
		elgg_register_plugin_hook_handler('get_sql', 'access', array($this, 'addOrCallback'));
		$sql = _elgg_get_access_where_sql([
			'ignore_access' => true,
		]);
		$ans = "((1 = 1 OR 57 > 32) AND (e.enabled = 'yes'))";
		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");
	}

	public function addOrCallback($hook, $type, $clauses, $params) {
		$clauses['ors'][] = '57 > 32';
		return $clauses;
	}

	public function testAccessPluginHookAddAnd() {
		elgg_register_plugin_hook_handler('get_sql', 'access', array($this, 'addAndCallback'));
		$sql = _elgg_get_access_where_sql([
			'ignore_access' => true,
		]);
		$ans = "((1 = 1) AND (e.enabled = 'yes' AND 57 > 32))";
		$this->assertTrue($this->assertSqlEqual($ans, $sql), "$sql does not match $ans");
	}

	public function testHasAccessToEntity() {

		$session = elgg_get_session();

		$viewer = $session->getLoggedInUser();

		$ia = elgg_set_ignore_access(true);

		$owner = new ElggUser();
		$owner->access_id = ACCESS_PUBLIC;
		$owner->save();

		$object = new ElggObject();
		$object->owner_guid = $owner->guid;
		$object->access_id = ACCESS_PRIVATE;
		$object->save();

		elgg_set_ignore_access($ia);

		$session->removeLoggedInUser();

		$this->assertFalse(has_access_to_entity($object));
		$this->assertFalse(has_access_to_entity($object, $viewer));
		$this->assertTrue(has_access_to_entity($object, $owner));

		$ia = elgg_set_ignore_access(true);
		$object->access_id = ACCESS_PUBLIC;
		$object->save();
		elgg_set_ignore_access($ia);

		$this->assertTrue(has_access_to_entity($object));
		$this->assertTrue(has_access_to_entity($object, $viewer));
		$this->assertTrue(has_access_to_entity($object, $owner));

		$ia = elgg_set_ignore_access(true);
		$object->access_id = ACCESS_LOGGED_IN;
		$object->save();
		elgg_set_ignore_access($ia);

		$this->assertFalse(has_access_to_entity($object));
		// even though user is logged out, existing users are presumed to have access to an entity
		$this->assertTrue(has_access_to_entity($object, $viewer));
		$this->assertTrue(has_access_to_entity($object, $owner));

		$session->setLoggedInUser($viewer);
		$this->assertTrue(has_access_to_entity($object));
		$this->assertTrue(has_access_to_entity($object, $viewer));
		$this->assertTrue(has_access_to_entity($object, $owner));
		$session->removeLoggedInUser();

		$ia = elgg_set_ignore_access(true);
		$owner->addFriend($viewer->guid);
		$object->access_id = ACCESS_FRIENDS;
		$object->save();
		elgg_set_ignore_access($ia);

		$this->assertFalse(has_access_to_entity($object));
		$this->assertTrue(has_access_to_entity($object, $viewer));
		$this->assertTrue(has_access_to_entity($object, $owner));

		$session->setLoggedInUser($viewer);
		$this->assertTrue(has_access_to_entity($object));
		$this->assertTrue(has_access_to_entity($object, $viewer));
		$this->assertTrue(has_access_to_entity($object, $owner));

		$ia = elgg_set_ignore_access(true);
		$owner->delete();
		$object->delete();
		elgg_set_ignore_access($ia);
	}

	public function addAndCallback($hook, $type, $clauses, $params) {
		$clauses['ands'][] = '57 > 32';
		return $clauses;
	}

	protected function assertSqlEqual($sql1, $sql2) {
		$sql1 = preg_replace('/\s+/', '', $sql1);
		$sql2 = preg_replace('/\s+/', '', $sql2);
		return $sql1 === $sql2;
	}

	protected function getFriendsClause($user_guid, $table_alias, $owner_guid = 'owner_guid') {
		$CONFIG = _elgg_config();
		$table_alias = $table_alias ? $table_alias . '.' : '';
	
		return "{$table_alias}access_id = " . ACCESS_FRIENDS . "
			AND {$table_alias}{$owner_guid} IN (
				SELECT guid_one FROM {$CONFIG->dbprefix}entity_relationships
				WHERE relationship = 'friend' AND guid_two = $user_guid
			)";
	}

	protected function getOwnerClause($user_guid, $table_alias, $owner_guid = 'owner_guid') {
		$table_alias = $table_alias ? $table_alias . '.' : '';
		return "{$table_alias}{$owner_guid} = $user_guid";
	}

	protected function getLoggedInAccessListClause($table_alias) {
		$table_alias = $table_alias ? $table_alias . '.' : '';
		return "{$table_alias}access_id IN (2,1)";
	}

	protected function getLoggedOutAccessListClause($table_alias) {
		$table_alias = $table_alias ? $table_alias . '.' : '';
		return "{$table_alias}access_id IN (2)";
	}
}
