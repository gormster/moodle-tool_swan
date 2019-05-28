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
 * CLI script for tool_swan.
 *
 * @package     tool_swan
 * @subpackage  cli
 * @copyright   2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
// Ignore the cache of classes so we can definitely see new ones
define('IGNORE_COMPONENT_CACHE', 1);

$cwd = getcwd();

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/ddllib.php');
require_once('clilib.php');

// Get the cli options.
list($options, $unrecognized) = cli_get_params(array(
    'help' => false,
    'component' => null,
    'namespace' => null
),
array(
    'h' => 'help',
    'c' => 'component'
));

$help =
"
Updates the install.xml and update.php files of an installed plugin to reflect current definitions of persistents.

Usage:

php admin/tool/swan/cli/migrate.php --component=<frankenstyle_name> [--namespace=<model namespace>]
";

if ($options['help']) {
    cli_writeln($help);
    die();
}

//
// STEP 0: DETERMINE THE COMPONENT AND CLASSES TO GENERATE FOR
//

// Try and guess the component from the current working directory
$component = null;
$dir = null;
if (empty($options['component'])) {

    $areas = core_component::get_component_list();

    $candidates = [];
    foreach ($areas as $key => $components) {
        if ($key == 'core') {
            continue;
        }
        foreach ($components as $plugin => $path) {
            if (strlen($path) == 0) {
                continue;
            }
            if (strpos($cwd, $path) === 0) {
                $candidates[$path] = $plugin;
            }
        }
    }

    uksort($candidates, strlen);
    $component = end($candidates);
    $dir = key($candidates);
} else {
    $component = $options['component'];
    $dir = core_component::get_component_directory($component);
}

if (empty($component) || empty($dir)) {
    cli_error('Unknown component, please specify with --component=<frankenstyle_name>');
}

$classes = null;
if ($unrecognized) {
    // These could be persistent class names
    foreach ($unrecognized as $classname) {
        if (class_exists($component . '\\' . $classname) && is_subclass_of($classname, core\persistent::class)) {
            $classes[] = $classname;
        } else {
            cli_error(get_string('cliunknownoption', 'admin', $classname));
        }
    }
} else {
    $allclasses = core_component::get_component_classes_in_namespace($component, $options['namespace']);
    foreach ($allclasses as $classname => $classpath) {
        if (is_subclass_of($classname, core\persistent::class)) {
            $classes[] = $classname;
        }
    }
}

if (empty($classes)) {
    cli_error('No persistent classes found in component '. $component);
}

error_reporting(E_ALL);

//
// STEP 1: READ CURRENT VERSION
//

$plugininfo = core_plugin_manager::instance()->get_plugin_info($component);

$originalversion = $plugininfo->versiondisk;

//
// STEP 2: READ CURRENT INSTALL.XML
//

$installxml = $plugininfo->full_path('db/install.xml');
$installfile = new xmldb_file($installxml);
if(!$installfile->loadXMLStructure()) {
    cli_error("Couldn't load XML structure for install file");
};
$installstructure = $installfile->getStructure();

//
// STEP 3: READ CURRENT MODEL STRUCTURE
//

$dir = substr($plugininfo->get_dir(), 1);
$newversion = make_version_greater_than($originalversion);
$currentstructure = make_structure($component, $dir, $classes, $newversion, $options['namespace']);
$currenttables = [];
foreach ($classes as $class) {
    $currenttables[$class::TABLE] = $currentstructure->getTable($class::TABLE);
}

//
// STEP 4: COMPARE INSTALL STRUCTURE TO MODEL STRUCTURE AND CREATE UPGRADE SNIPPET
//

$phpgenerator = new tool_swan\php_generator($component, $newversion);

foreach ($currenttables as $tablename => $currenttable) {
    $installtable = $installstructure->getTable($tablename);
    if (!$installtable) {
        $phpgenerator->create_table_php($currenttable);
        continue;
    }

    // TODO: Compare tables..?

    // Otherwise, compare fields, keys and indexes
    $installfields = [];
    $currentfields = [];

    foreach ($installtable->getFields() as $field) {
        $installfields[$field->getName()] = $field;
    }

    foreach ($currenttable->getFields() as $field) {
        $currentfields[$field->getName()] = $field;
    }

    $fieldnames = array_keys($installfields + $currentfields);

    foreach ($fieldnames as $fieldname) {
        if(!array_key_exists($fieldname, $installfields)) {
            // New field
            $currentfield = $currentfields[$fieldname];
            $phpgenerator->add_field_php($currenttable, $currentfield);
        } else if (!array_key_exists($fieldname, $currentfields)) {
            // Dropped field
            $installfield = $installfields[$fieldname];
            $phpgenerator->drop_field_php($installtable, $installfield);
        } else {
            $installfield = $installfields[$fieldname];
            $currentfield = $currentfields[$fieldname];
            if ($installfield->getType() != $currentfield->getType()) {
                $phpgenerator->change_field_type_php($currenttable, $currentfield);
            }
            if (($installfield->getLength() != $currentfield->getLength()) || ($installfield->getDecimals() != $currentfield->getDecimals())) {
                $phpgenerator->change_field_precision_php($currenttable, $currentfield);
            }
            if ($installfield->getNotNull() != $currentfield->getNotNull()) {
                $phpgenerator->change_field_notnull_php($currenttable, $currentfield);
            }
            if ($installfield->getDefault() != $currentfield->getDefault()) {
                $phpgenerator->change_field_default_php($currenttable, $currentfield);
            }
        }
    }
}

//
// STEP 5: INSERT SNIPPET INTO UPGRADE.PHP AFTER LAST SAVEPOINT
//

$php = $phpgenerator->get_php();

// TODO: Check git status

$file = $plugininfo->full_path('db/upgrade.php');
if(is_writable($file)) {
    include($file);
    $mirror = new ReflectionFunction('xmldb_'.$component.'_upgrade');
    $endl = $mirror->getEndLine();

    $fd = new SplFileObject($file, 'r+');
    $fd->seek($endl - 2);
    if($fd->current() != "    return true;\n") {
        throw new coding_exception('Upgrade.php be in the predicted format');
    }
    $fd->seek($endl - 3);
    $remaining = $fd->fread($fd->getSize());

    $fd->seek($endl - 3);
    $fd->fwrite($php);
    $fd->fwrite($remaining);

    cli_writeln('Updated ' . $file);
}

//
// STEP 6: INCREMENT VERSION NUMBER
//

$file = $plugininfo->full_path('version.php');
if(is_writable($file)) {
    $fd = new SplFileObject($file, 'r+');
    $match = '|(\\$plugin\\-\\>version\\s*=\\s*)(' . $originalversion . ')|';
    while (!$fd->eof()) {
        $line = $fd->current();
        if (preg_match($match, $line, $matches)) {
            $line = preg_replace($match, '${1}'.$newversion, $line);
            $fd->seek($fd->key()-1);
            $fd->fwrite($line);
        }
        $fd->next();
    }

    cli_writeln('Updated ' . $file);
}

//
// STEP 7: WRITE OUT NEW INSTALL.XML FILE
//

$file = $plugininfo->full_path('db/install.xml');
if(is_writable($file)) {
    $fd = new SplFileObject($file, 'w');
    $fd->fwrite($currentstructure->xmlOutput());

    cli_writeln('Updated ' . $file);
}