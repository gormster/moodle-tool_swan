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

namespace tool_swan;

/**
 * Generates PHP code for migrations. Largely copied from tool_xmldb.
 *
 * @package     tool_swan
 * @subpackage  cli
 * @copyright   2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use xmldb_field;
use xmldb_table;
use core_component;

class php_generator {

    protected $component;

    protected $newversion;

    protected $tableactions;

    protected $fieldactions;

    function __construct($component, $newversion) {
        $this->component = $component;
        $this->newversion = $newversion;
        $this->tableactions = [];
        $this->fieldactions = [];
    }

    function add_field_php(xmldb_table $table, xmldb_field $field) {

        $result = '';
        if ($table->getAllErrors()) {
            throw new coding_exception($table->getAllErrors());
        }

        // Add contents
        $result .= XMLDB_LINEFEED;
        $result .= '        $field = new xmldb_field(' . "'" . $field->getName() . "', " . $field->getPHP(true) . ');' . XMLDB_LINEFEED;

        // Launch the proper DDL
        $result .= XMLDB_LINEFEED;
        $result .= '        if (!$dbman->field_exists($table, $field)) {'. XMLDB_LINEFEED;
        $result .= '            $dbman->add_field($table, $field);' . XMLDB_LINEFEED;
        $result .= '        }'. XMLDB_LINEFEED;

        $this->fieldactions[$table->getName()][] = $result;
    }

    function drop_field_php(xmldb_table $table, xmldb_field $field) {

        $result = '';
        if ($table->getAllErrors()) {
            throw new coding_exception($table->getAllErrors());
        }

        // Add contents
        $result .= XMLDB_LINEFEED;
        $result .= '        $field = new xmldb_field(' . "'" . $field->getName() . "'" . ');' . XMLDB_LINEFEED;

        // Launch the proper DDL
        $result .= XMLDB_LINEFEED;
        $result .= '        if ($dbman->field_exists($table, $field)) {' . XMLDB_LINEFEED;
        $result .= '            $dbman->drop_field($table, $field);' . XMLDB_LINEFEED;
        $result .= '        }' . XMLDB_LINEFEED;

        $this->fieldactions[$table->getName()][] = $result;
    }

    function change_field_type_php(xmldb_table $table, xmldb_field $field) {

        $result = '';
        if ($table->getAllErrors()) {
            throw new coding_exception($table->getAllErrors());
        }

        // Calculate the type tip text
        $type = $field->getXMLDBTypeName($field->getType());

        // Add contents
        $result .= XMLDB_LINEFEED;
        $result .= '        // Changing type of field ' . $field->getName() . ' on table ' . $table->getName() . ' to ' . $type . '.' . XMLDB_LINEFEED;
        $result .= '        $field = new xmldb_field(' . "'" . $field->getName() . "', " . $field->getPHP(true) . ');' . XMLDB_LINEFEED;
        $result .= '        $dbman->change_field_type($table, $field);' . XMLDB_LINEFEED;

        $this->fieldactions[$table->getName()][] = $result;
    }

    function change_field_precision_php(xmldb_table $table, xmldb_field $field) {

        $result = '';
        if ($table->getAllErrors()) {
            throw new coding_exception($table->getAllErrors());
        }

        // Calculate the precision tip text
        $precision = '(' . $field->getLength();
        if ($field->getDecimals()) {
            $precision .= ', ' . $field->getDecimals();
        }
        $precision .= ')';

        // Add contents
        $result .= XMLDB_LINEFEED;
        $result .= '        // Changing precision of field ' . $field->getName() . ' on table ' . $table->getName() . ' to ' . $precision . '.' . XMLDB_LINEFEED;
        $result .= '        $field = new xmldb_field(' . "'" . $field->getName() . "', " . $field->getPHP(true) . ');' . XMLDB_LINEFEED;
        $result .= '        $dbman->change_field_precision($table, $field);' . XMLDB_LINEFEED;

        $this->fieldactions[$table->getName()][] = $result;
    }

    function change_field_notnull_php(xmldb_table $table, xmldb_field $field) {

        $result = '';
        if ($table->getAllErrors()) {
            throw new coding_exception($table->getAllErrors());
        }

        $notnull = $field->getNotnull() ? 'not null' : 'null';

        // Add contents
        $result .= XMLDB_LINEFEED;
        $result .= '        // Changing nullability of field ' . $field->getName() . ' on table ' . $table->getName() . ' to ' . $notnull . '.' . XMLDB_LINEFEED;
        $result .= '        $field = new xmldb_field(' . "'" . $field->getName() . "', " . $field->getPHP(true) . ');' . XMLDB_LINEFEED;
        $result .= '        $dbman->change_field_notnull($table, $field);' . XMLDB_LINEFEED;

        $this->fieldactions[$table->getName()][] = $result;
    }

    function change_field_default_php(xmldb_table $table, xmldb_field $field) {

        $result = '';
        if ($table->getAllErrors()) {
            throw new coding_exception($table->getAllErrors());
        }

        $default = $field->getDefault() === null ? 'drop it' : $field->getDefault();

        // Add contents
        $result .= XMLDB_LINEFEED;
        $result .= '        // Changing the default of field ' . $field->getName() . ' on table ' . $table->getName() . ' to ' . $default . '.' . XMLDB_LINEFEED;
        $result .= '        $field = new xmldb_field(' . "'" . $field->getName() . "', " . $field->getPHP(true) . ');' . XMLDB_LINEFEED;
        $result .= '        $dbman->change_field_default($table, $field);' . XMLDB_LINEFEED;

        $this->fieldactions[$table->getName()][] = $result;
    }

    function create_table_php(xmldb_table $table) {

        $result = '';
        if ($table->getAllErrors()) {
            throw new coding_exception($table->getAllErrors());
        }

        $result .= '        // Define table ' . $table->getName() . ' to be created.' . XMLDB_LINEFEED;
        $result .= '        $table = new xmldb_table(' . "'" . $table->getName() . "'" . ');' . XMLDB_LINEFEED;
        $result .= XMLDB_LINEFEED;
        $result .= '        // Adding fields to table ' . $table->getName() . '.' . XMLDB_LINEFEED;
        // Iterate over each field
        foreach ($table->getFields() as $field) {
            // The field header, with name
            $result .= '        $table->add_field(' . "'" . $field->getName() . "', ";
            // The field PHP specs
            $result .= $field->getPHP(false);
            // The end of the line
            $result .= ');' . XMLDB_LINEFEED;
        }
        // Iterate over each key
        if ($keys = $table->getKeys()) {
            $result .= XMLDB_LINEFEED;
            $result .= '        // Adding keys to table ' . $table->getName() . '.' . XMLDB_LINEFEED;
            foreach ($keys as $key) {
                // The key header, with name
                $result .= '        $table->add_key(' . "'" . $key->getName() . "', ";
                // The key PHP specs
                $result .= $key->getPHP();
                // The end of the line
                $result .= ');' . XMLDB_LINEFEED;
            }
        }
        // Iterate over each index
        if ($indexes = $table->getIndexes()) {
            $result .= XMLDB_LINEFEED;
            $result .= '        // Adding indexes to table ' . $table->getName() . '.' . XMLDB_LINEFEED;
            foreach ($indexes as $index) {
                // The index header, with name
                $result .= '        $table->add_index(' . "'" . $index->getName() . "', ";
                // The index PHP specs
                $result .= $index->getPHP();
                // The end of the line
                $result .= ');' . XMLDB_LINEFEED;
            }
        }

        // Launch the proper DDL
        $result .= XMLDB_LINEFEED;
        $result .= '        // Conditionally launch create table for ' . $table->getName() . '.' . XMLDB_LINEFEED;
        $result .= '        if (!$dbman->table_exists($table)) {' . XMLDB_LINEFEED;
        $result .= '            $dbman->create_table($table);' . XMLDB_LINEFEED;
        $result .= '        }' . XMLDB_LINEFEED;

        $this->tableactions[] = $result;
    }

    function drop_table_php(xmldb_table $table) {

        $result = '';
        // Validate if we can do it
        if ($table->getAllErrors()) {
            return false;
        }

        // Add contents
        $result .= XMLDB_LINEFEED;
        $result .= '        // Define table ' . $table->getName() . ' to be dropped.' . XMLDB_LINEFEED;
        $result .= '        $table = new xmldb_table(' . "'" . $table->getName() . "'" . ');' . XMLDB_LINEFEED;

        // Launch the proper DDL
        $result .= XMLDB_LINEFEED;
        $result .= '        // Conditionally launch drop table for ' . $table->getName() . '.' . XMLDB_LINEFEED;
        $result .= '        if ($dbman->table_exists($table)) {' . XMLDB_LINEFEED;
        $result .= '            $dbman->drop_table($table);' . XMLDB_LINEFEED;
        $result .= '        }' . XMLDB_LINEFEED;

        $this->tableactions[] = $result;
    }

    function get_php() {
        $result = '';
        $result .= '    if ($oldversion < ' . $this->newversion . ') {' . XMLDB_LINEFEED;

        foreach ($this->tableactions as $step) {
            $result .= $step;
        }

        foreach ($this->fieldactions as $table => $steps) {
            $result .= XMLDB_LINEFEED;
            $result .= '        $table = new xmldb_table(' . "'" . $table . "'" . ');' . XMLDB_LINEFEED;

            foreach ($steps as $step) {
                $result .= $step;
            }
        }

        $result .= $this->upgrade_savepoint_php();

        $result .= '    }' . XMLDB_LINEFEED . XMLDB_LINEFEED;

        return $result;
    }

    /**
     * Completely copied from XMLDBAction, but uses $this->newversion
     * @param  [type] $structure [description]
     * @return [type]            [description]
     */
    function upgrade_savepoint_php() {
        global $CFG;

        // NOTE: $CFG->admin !== 'admin' is not supported in XMLDB editor, sorry.

        list($plugintype, $pluginname) = core_component::normalize_component($this->component);

        $result = '';

        switch ($plugintype ) {
            case 'core': // has own savepoint function
                $result = XMLDB_LINEFEED .
                          '        // Main savepoint reached.' . XMLDB_LINEFEED .
                          '        upgrade_main_savepoint(true, ' . $this->newversion . ');' . XMLDB_LINEFEED;
                break;
            case 'mod': // has own savepoint function
                $result = XMLDB_LINEFEED .
                          '        // ' . ucfirst($pluginname) . ' savepoint reached.' . XMLDB_LINEFEED .
                          '        upgrade_mod_savepoint(true, ' . $this->newversion . ', ' . "'$pluginname'" . ');' . XMLDB_LINEFEED;
                break;
            case 'block': // has own savepoint function
                $result = XMLDB_LINEFEED .
                          '        // ' . ucfirst($pluginname) . ' savepoint reached.' . XMLDB_LINEFEED .
                          '        upgrade_block_savepoint(true, ' . $this->newversion . ', ' . "'$pluginname'" . ');' . XMLDB_LINEFEED;
                break;
            default: // rest of plugins
                $result = XMLDB_LINEFEED .
                          '        // ' . ucfirst($pluginname) . ' savepoint reached.' . XMLDB_LINEFEED .
                          '        upgrade_plugin_savepoint(true, ' . $this->newversion . ', ' . "'$plugintype'" . ', ' . "'$pluginname'" . ');' . XMLDB_LINEFEED;
        }
        return $result;
    }

}