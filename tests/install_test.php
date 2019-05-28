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
 * File containing tests for install.
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

global $CFG;

require_once('util/persistent.php');
require_once($CFG->dirroot . '/' . $CFG->admin .'/tool/swan/cli/clilib.php');

/**
 * The install test class.
 *
 * @package    tool_swan
 * @copyright  2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_swan_install_testcase extends advanced_testcase {

    public function setUp() {

    }

    private function check_object($actual, $expected, $test = []) {
        $this->assertNotEmpty($actual);
        foreach ($test as $t) {
            $a = $actual->$t();
            $e = $expected->$t();
            $this->assertEquals($e, $a, "Failed on $t");
        }
    }

    private function check_field($actual, $expected, $test = ['getType','getLength','getDecimals','getNotNull','getUnsigned','getSequence','getDefault']) {
        $this->check_object($actual, $expected, $test);
    }

    private function check_key($actual, $expected, $test = ['getType', 'getFields', 'getRefTable', 'getRefFields']) {
        $this->check_object($actual, $expected, $test);
    }

    private function check_index($actual, $expected, $test = ['getUnique', 'getFields']) {
        $this->check_object($actual, $expected, $test);
    }

    public function test_basic_structure() {
        $result = make_structure('local_example', 'local/example', [basic_persistent::class], 2019010100);

        // Since XML and PHP outputs aren't strictly defined, we'll look at the actual structure
        $this->assertEquals('local/example/db', $result->getPath());
        $this->assertEquals('2019010100', $result->getVersion());

        $table = $result->getTable('test_basic');

        $this->assertNotEmpty($table);

        // Check the default keys
        $idfield = $table->getField('id');
        $this->check_field($idfield, new xmldb_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE));
        $usermodifiedfield = $table->getField('usermodified');
        $this->check_field($usermodifiedfield, new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL));
        $timecreatedfield = $table->getField('timecreated');
        $this->check_field($timecreatedfield, new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL));
        $timemodifiedfield = $table->getField('timemodified');
        $this->check_field($timemodifiedfield, new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL));

        // Check the keys defined on this table
        $useridfield = $table->getField('userid');
        $this->check_field($useridfield, new xmldb_field('userid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL));

        $messagefield = $table->getField('message');
        $this->check_field($messagefield, new xmldb_field('userid', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL));

        $readfield = $table->getField('read');
        $this->check_field($readfield, new xmldb_field('userid', XMLDB_TYPE_INTEGER, 2, null, XMLDB_NOTNULL, 0));

        // Check the foreign key
        $useridkey = $table->getKey('userid');
        $this->check_key($useridkey, new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']));
    }

}
