<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * File containing tests for parser.
 *
 * @package     tool_swan
 * @category    test
 * @copyright   2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// For installation and usage of PHPUnit within Moodle please read:
// https://docs.moodle.org/dev/PHPUnit
//
// Documentation for writing PHPUnit tests for Moodle can be found here:
// https://docs.moodle.org/dev/PHPUnit_integration
// https://docs.moodle.org/dev/Writing_PHPUnit_tests
//
// The official PHPUnit homepage is at:
// https://phpunit.de

require_once('util/persistent.php');

/**
 * The parser test class.
 *
 * @package    tool_swan
 * @copyright  2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_swan_parser_testcase extends advanced_testcase {

    public function setUp() {

    }

    // Write the tests here as public funcions.
    public function test_get_fields() {
        $persistent = basic_persistent::class;
        $parser = new tool_swan\parser($persistent);

        $fields = $parser->get_fields();

        $this->assertCount(7, $fields);
        $this->assertEquals(new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL), $fields['userid']);
        $this->assertEquals(new xmldb_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL), $fields['message']);
        $this->assertEquals(new xmldb_field('read', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0), $fields['read']);
    }

    public function test_get_keys_basic() {
        $persistent = basic_persistent::class;
        $parser = new tool_swan\parser($persistent);

        $keys = $parser->get_keys();

        $this->assertCount(2, $keys);
        $this->assertEquals(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']), $keys['primary']);
        $this->assertEquals(new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']), $keys['userid']);
    }

    public function test_get_keys_foreign() {
        $persistent = fk_persistent::class;
        $parser = new tool_swan\parser($persistent);

        $keys = $parser->get_keys();

        $this->assertCount(4, $keys);
        $this->assertEquals(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']), $keys['primary']);
        $this->assertEquals(new xmldb_key('fromuser-touser', XMLDB_KEY_FOREIGN_UNIQUE, ['fromuser', 'touser'], 'user', ['id', 'id']), $keys['fromuser-touser']);
        $this->assertEquals(new xmldb_key('source', XMLDB_KEY_FOREIGN, ['sourcetype', 'sourceid'], 'source', ['type', 'object']), $keys['source']);
        $this->assertEquals(new xmldb_key('usersource', XMLDB_KEY_FOREIGN, ['fromuser', 'sourceid'], 'user_source', ['userid', 'id']), $keys['usersource']);
    }

    public function test_get_keys_unique() {
        $persistent = unique_persistent::class;
        $parser = new tool_swan\parser($persistent);

        $keys = $parser->get_keys();

        $this->assertCount(3, $keys);
        $this->assertEquals(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']), $keys['primary']);
        $this->assertEquals(new xmldb_key('nonce', XMLDB_KEY_UNIQUE, ['nonce']), $keys['nonce']);
        $this->assertEquals(new xmldb_key('fromuser-touser-itemid', XMLDB_KEY_UNIQUE, ['fromuser','touser','itemid']), $keys['fromuser-touser-itemid']);
    }

    public function test_get_indexes() {
        $persistent = index_persistent::class;
        $parser = new tool_swan\parser($persistent);

        $indexes = $parser->get_indexes();

        $this->assertCount(2, $indexes);
        $this->assertEquals(new xmldb_index('kind', XMLDB_INDEX_NOTUNIQUE, ['kind']), $indexes['kind']);
        $this->assertEquals(new xmldb_index('fromuser-touser-itemid', XMLDB_INDEX_UNIQUE, ['fromuser','touser','itemid']), $indexes['fromuser-touser-itemid']);
    }
}
