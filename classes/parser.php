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
 * Parses a persistent into a migratable XMLDB format.
 *
 * @package     tool_swan
 * @category    util
 * @copyright   2018 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_swan;

defined('MOODLE_INTERNAL') || die();

use core\persistent;
use xmldb_field;
use xmldb_table;
use xmldb_key;
use xmldb_index;
use coding_exception;

/**
 * Parses a persistent into a migratable XMLDB format.
 */
class parser {

    protected $persistent;

    /**
     * Constructs a parser based around this persistent instance.
     * @param string $persistent Fully qualified class name (obtained from ::class)
     */
    public function __construct($persistent) {
        $this->persistent = $persistent;
    }

    protected function default_fields() {
        return [
            'id' => new xmldb_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE),
            'usermodified' => new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL),
            'timecreated' => new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL),
            'timemodified' => new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL)
        ];
    }

    public function get_fields() {
        $fields = $this->default_fields();
        $properties = $this->persistent::properties_definition();
        foreach ($properties as $name => $options) {
            if (array_key_exists($name, $fields)) {
                continue;
            }
            $dbtype = isset($options['dbtype']) ? $options['dbtype'] : $this->default_dbtype_for_type($options['type']);
            $precision = isset($options['precision']) ? $options['precision'] : $this->default_precision_for_type($options['type'], $dbtype);
            $nullable = isset($options['null']) ? $options['null'] == NULL_ALLOWED : false;
            $sequence = isset($options['sequence']) ? $options['sequence'] : false;
            $comment = isset($options['comment']) ? $options['comment'] : '';
            $default = null;
            if (isset($options['default']) && !($options['default'] instanceof Closure)) {
                $default = clean_param($options['default'], $this->clean_type_for_dbtype($dbtype));
            }

            $field = new xmldb_field(
                $name,
                $dbtype,
                $precision,
                null, // unsigned is deprecated since 2.3
                empty($nullable) ? XMLDB_NOTNULL : null,
                empty($sequence) ? null : XMLDB_SEQUENCE,
                $default
            );
            $field->setComment($comment);

            $fields[$name] = $field;
        }

        return $fields;
    }

    protected function default_keys() {
        return [
            'primary' => new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id'])
        ];
    }

    public function get_keys() {
        $keys = $this->default_keys();

        $keytypes = ['foreignkey' => XMLDB_KEY_FOREIGN,
                     'uniqueforeignkey' => XMLDB_KEY_FOREIGN_UNIQUE,
                     'uniquekey' => XMLDB_KEY_UNIQUE];

        $this->get_keys_indexes($keys, $keytypes, xmldb_key::class);

        return $keys;
    }

    public function get_indexes() {
        $indexes = [];

        $indextypes = ['index' => XMLDB_INDEX_NOTUNIQUE,
                       'uniqueindex' => XMLDB_INDEX_UNIQUE];

        $this->get_keys_indexes($indexes, $indextypes, xmldb_index::class);

        return $indexes;
    }

    private function get_keys_indexes(array &$keys, $keytypes, $keyclass) {
        $properties = $this->persistent::properties_definition();

        foreach ($properties as $fieldname => $options) {
            foreach ($keytypes as $option => $keytype) {
                if (!isset($options[$option])) {
                    continue;
                }
                $iskey = ($keyclass == xmldb_key::class);
                $isindex = ($keyclass == xmldb_index::class);
                $isforeign = $iskey && (($keytype == XMLDB_KEY_FOREIGN) || ($keytype == XMLDB_KEY_FOREIGN_UNIQUE));
                $isunique = ($iskey && (($keytype == XMLDB_KEY_FOREIGN_UNIQUE) || ($keytype == XMLDB_KEY_UNIQUE)))
                    || ($isindex && ($keytype == XMLDB_INDEX_UNIQUE));

                // Array of index name => foreign relation
                $thesekeys = $options[$option];
                if (!is_array($thesekeys)) {
                    // A non-foreign key/index can simply be true for automatic naming
                    // or assign an index name. A foreign key must reference a table, hence
                    // it must be a string if not an array.
                    if ($isforeign) {
                        $thesekeys = [$fieldname => $thesekeys];
                    } else {
                        if (is_string($thesekeys)) {
                            $thesekeys = [$thesekeys => true];
                        } else {
                            $thesekeys = [$fieldname => true];
                        }
                    }
                }

                foreach ($thesekeys as $keyname => $value) {
                    if ($isforeign) {
                        $ref = explode('.', $value, 2);
                        if (count($ref) != 2) {
                            throw new coding_exception('foreignkey must be in format table.field');
                        }
                        list($reftable, $reffield) = $ref;
                    }

                    if (isset($keys[$keyname])) {
                        // If the key already exists, merge this field into it
                        $key = $keys[$keyname];
                        if ($iskey && $key->getType() != $keytype) {
                            throw new coding_exception('Multi-field keys must all be of the same type');
                        } else if ($isindex && ($key->getUnique() != $isunique)) {
                            throw new coding_exception('Multi-field indexes must all be of the same uniqueness');
                        }
                        if ($isforeign && ($key->getRefTable() != $reftable)) {
                            throw new coding_exception('Foreign keys to multiple fields must all reference the same table');
                        }
                        $key->setFields(array_merge($key->getFields(), [$fieldname]));
                        if ($isforeign) {
                            $key->setRefFields(array_merge($key->getRefFields(), [$reffield]));
                        }
                    } else {
                        $key = new $keyclass(
                            $keyname,
                            $keytype,
                            [$fieldname]);
                        if ($isforeign) {
                            $key->setRefTable($reftable);
                            $key->setRefFields([$reffield]);
                        }
                        $keys[$keyname] = $key;
                    }
                }
            }
        }
    }

    public function get_table() {
        $table = new xmldb_table($this->persistent::TABLE);

        foreach ($this->get_fields() as $field) {
            $table->addField($field);
        }

        foreach ($this->get_keys() as $key) {
            $table->addKey($key);
        }

        foreach ($this->get_indexes() as $index) {
            $table->addIndex($index);
        }

        $mirror = new \ReflectionClass($this->persistent);
        $comment = $this->get_comment_summary($mirror->getDocComment()) ?? 'generated by tool_swan';
        $table->setComment($comment);

        return $table;
    }

    private function get_comment_summary($docblock) {
        $comments = explode("\n", $docblock);
        foreach ($comments as $comment) {
            $comment = ltrim($comment, " \t*");
            $start = substr($comment, 0, 2);
            if ($start == "/*") {
                $comment = substr($comment, 2);
                $comment = ltrim($comment, " \t*");
            } else if ($start == "*/") {
                continue;
            }

            $comment = trim($comment);
            if (strlen($comment) > 0) {
                return $comment;
            }
        }

        return null;
    }

    /**
     * Get the default DB type for this param type
     * @param  int $type One of the PARAM_ constants
     * @return int       One of the XMLDB_TYPE_ constants
     */
    public function default_dbtype_for_type($type) {
        switch ($type) {
            case PARAM_INT:
            case PARAM_BOOL:
                return XMLDB_TYPE_INTEGER;
            case PARAM_FLOAT:
                return XMLDB_TYPE_NUMBER;
            default:
                throw new coding_exception('DB type cannot be inferred');
        }
    }

    /**
     * Get the default DB precision for this param type
     * @param  int $type One of the PARAM_ constants
     * @return string|null    An appropriate database precision
     */
    public function default_precision_for_type($type, $dbtype) {
        switch ($dbtype) {
            case XMLDB_TYPE_INTEGER:
                switch($type) {
                    case PARAM_BOOL:
                        return "2";
                    default:
                        return "10";
                }
            case XMLDB_TYPE_NUMBER:
                return "10,5";
            default:
                return null;
        }
    }

    public function clean_type_for_dbtype($dbtype) {
        switch ($dbtype) {
            case XMLDB_TYPE_INTEGER:
                return PARAM_INT;
            case XMLDB_TYPE_NUMBER:
            case XMLDB_TYPE_FLOAT:
                return PARAM_FLOAT;
            default:
                return PARAM_RAW;
        }
    }

}