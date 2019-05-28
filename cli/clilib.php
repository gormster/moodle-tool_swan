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
 * Common functions for cli scripts
 *
 * @package     tool_swan
 * @subpackage  cli
 * @copyright   2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


function make_structure($component, $dir, $classes, $version, $namespace = null) {

    list($type, $name) = core_component::normalize_component($component);

    $structure = new tool_swan\xmldb_structure($dir . '/db');
    $structure->setPath($dir . '/db');

    foreach($classes as $class) {
        $mirror = new ReflectionClass($class);
        if ($namespace && ($mirror->getNamespaceName() != $namespace)) {
            continue;
        }

        if ($mirror->isAbstract()) {
            continue;
        }

        if (!$mirror->isSubclassOf(core\persistent::class)) {
            continue;
        }

        $parser = new tool_swan\parser($class);

        $table = $parser->get_table();

        $structure->addTable($table);

        $structure->setComment("XMLDB file for $type/$name");
    }

    $structure->setVersion($version);

    return $structure;
}

function make_version_greater_than($original) {
    $currentversion = (int) (userdate(time(), '%Y%m%d', 99, false) . '00');
    if($currentversion <= $original) {
        return $original + 1;
    } else {
        return $currentversion;
    }

}
