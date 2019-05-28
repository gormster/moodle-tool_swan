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
 * Test persistent objects
 *
 * @package     tool_swan
 * @category    test
 * @copyright   2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Basic semi-realworld example.
 */
class basic_persistent extends core\persistent {

    const TABLE = 'test_basic';

    protected static function define_properties() {
        return array(
            'userid' => array(
                'type' => PARAM_INT,
                'foreignkey' => 'user.id'
            ),
            'message' => array(
                'type' => PARAM_RAW,
                'dbtype' => XMLDB_TYPE_TEXT
            ),
            'read' => array(
                'type' => PARAM_BOOL,
                'default' => false
            )
        );
    }

}

/**
 * For testing foreign keys.
 */
class fk_persistent extends core\persistent {
    const TABLE = 'test_fk';

    protected static function define_properties() {
        return array(
            'fromuser' => array(
                'type' => PARAM_INT,
                'uniqueforeignkey' => ['fromuser-touser' => 'user.id'],
                'foreignkey' => ['usersource' => 'user_source.userid']
            ),
            'touser' => array(
                'type' => PARAM_INT,
                'uniqueforeignkey' => ['fromuser-touser' => 'user.id']
            ),
            'sourcetype' => array(
                'type' => PARAM_INT,
                'foreignkey' => ['source' => 'source.type']
            ),
            'sourceid' => array(
                'type' => PARAM_INT,
                'foreignkey' => ['source' => 'source.object', 'usersource' => 'user_source.id']
            )
        );
    }
}

/**
 * For testing unique keys
 */
class unique_persistent extends core\persistent {
    const TABLE = 'test_unique';

    protected static function define_properties() {
        return array(
            'nonce' => array(
                'type' => PARAM_RAW,
                'dbtype' => XMLDB_TYPE_CHAR,
                'precision' => 64,
                'uniquekey' => true
            ),
            'fromuser' => array(
                'type' => PARAM_INT,
                'uniquekey' => 'fromuser-touser-itemid'
            ),
            'touser' => array(
                'type' => PARAM_INT,
                'uniquekey' => 'fromuser-touser-itemid'
            ),
            'itemid' => array(
                'type' => PARAM_INT,
                'uniquekey' => 'fromuser-touser-itemid'
            )
        );
    }
}

/**
 * For testing indexes
 */
class index_persistent extends core\persistent {
    const TABLE = 'test_index';

    protected static function define_properties() {
        return array(
            'kind' => array(
                'type' => PARAM_PLUGIN,
                'dbtype' => XMLDB_TYPE_CHAR,
                'precision' => 64,
                'index' => true
            ),
            'fromuser' => array(
                'type' => PARAM_INT,
                'uniqueindex' => 'fromuser-touser-itemid'
            ),
            'touser' => array(
                'type' => PARAM_INT,
                'uniqueindex' => 'fromuser-touser-itemid'
            ),
            'itemid' => array(
                'type' => PARAM_INT,
                'uniqueindex' => 'fromuser-touser-itemid'
            ),
        );
    }
}