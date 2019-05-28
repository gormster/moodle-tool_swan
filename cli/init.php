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
 * Create an install.xml file for a new plugin.
 *
 * @package     tool_swan
 * @subpackage  cli
 * @copyright   2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$cwd = getcwd();

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('clilib.php');

error_reporting(E_ALL);

// Get the cli options.
list($options, $files) = cli_get_params(array(
    'help' => false,
    'component' => null,
    'namespace' => null,
    'output' => 'xml'
),
array(
    'h' => 'help',
));

$help =
"
Create an install.xml file for a new plugin. Pass a list of PHP files containing your persistent classes.

Usage: php admin/tool/swan/cli/init.php --component=<frankenstyle_name> [--namespace=<model namespace>] [file] [file] [file]...
";

if ($options['help'] || empty($files) || empty($options['component'])) {
    cli_writeln($help);
    die();
}

$component = $options['component'];
if (!empty($options['namespace'])) {
    $namespace = $component . '\\' . $options['namespace'];
} else {
    $namespace = $component;
}
$oldclasses = get_declared_classes();

// This is kind of dangerous. Need to make absolutely sure that we're CLI only here.
// Possibly increase safety, though this plugin shouldn't even be installed on a production system.
foreach ($files as $file) {
    require_once($CFG->dirroot . '/' . $file);
}

$classes = array_diff(get_declared_classes(), $oldclasses);

list($type, $name) = core_component::normalize_component($component);

$dir = core_component::get_plugin_types()[$type] . '/' . $name;
if (strpos($dir,$CFG->dirroot) !== 0) {
    cli_error('$CFG->dirroot is not properly set');
}

$dir = substr($dir, strlen($CFG->dirroot) + 1);
$plugin = new stdClass;
include($CFG->dirroot . '/' . $dir . '/version.php');

$structure = make_structure($component, $dir, $classes, $plugin->version, $namespace);

switch ($options['output']) {
    case 'xml':
        echo $structure->xmlOutput();
        break;
    case 'sql':
        $dbman = $DB->get_manager();
        $sqlarr = $dbman->generator->getCreateStructureSQL($structure);
        foreach($sqlarr as $sql) {
            echo $sql . XMLDB_LINEFEED;
        }
        break;
    default:
        cli_error('Unknown output type ' . $options['output']);
        # code...
        break;
}
