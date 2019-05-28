# Swan #

Swan is a database migration tool for Moodle plugins.

## The Process ##

### For a new plugin ###
1. Create your persistent models, using the extra fields detailed below.
2. Make sure your `version.php` has a valid version number in it.
3. Run `php admin/tool/swan/cli/init.php --component=COMPONENT_NAME [--namespace=MODEL_NAMESPACE] FILES...` where COMPONENT_NAME is your frankenstyle plugin name, PLUGIN_NAMEPSACE is an optional model namespace, if your models aren't in the plugin's root namespace, and FILES is a list of files containing your persistent classes.
4. Pipe the output of that command to your `install.xml` file.
5. Run a Moodle upgrade to see your new plugin.

### For existing plugins ###

1. Update your persistent models, using the extra fields detailed below.
2. Make sure your `install.xml` and `upgrade.php` are up-to-date.
3. `git commit` - migration permanently modifies your install.xml and upgrade.php files!
4. Run `php admin/tool/swan/cli/migrate.php --component=COMPONENT_NAME [--namespace=MODEL_NAMESPACE]` where COMPONENT_NAME is your frankenstyle plugin name, PLUGIN_NAMEPSACE is an optional model namespace, if your models aren't in the plugin's root namespace.
5. Run a Moodle upgrade to see your changes.

## Extending `persistent` ##

One of the great things about `persistent` is that the model info keys are just plain strings - so you can add new ones without upsetting the existing mechanisms. Swan takes advantage of this by introducing new keys to `properties_definition`, and using the existing ones to provide sensible defaults.

### New keys ###

Name       | Type           | Default           | Meaning      
-----------|----------------|-------------------|------------------------------
dbtype     | XMLDB_TYPE_*   | based on `type`   | Underlying database type, equivalent to `TYPE` attribute in `install.xml`.
precision  | string         | based on `type`   | Database precision, equivalent to `LENGTH` and `DECIMALS` attribute in `install.xml`, comma separated. **Required for `dbtype => XMLDB_TYPE_CHAR`.**
sequence   | bool           | false             | Automatically increasing sequence, like id; equivalent to `SEQUENCE` attribute in `install.xml`.
foreignkey | key            | null              | Create a foreign key, equivalent to `<KEY type="foreign">` in `install.xml`.
uniquekey  | key            | null              | Create a unique key, equivalent to `<KEY type="unique">` in `install.xml`.
uniqueforeignkey | key      | null              | Create a unique, foreign key, equivalent to `<KEY type="foreign-unique">` in `install.xml`.\*
index      | index          | null              | Create an index on this field, equivalent to `<INDEX>` in `install.xml`.
uniqueindex| index          | null              | Create a unique index on this field, equivalent to `<INDEX UNIQUE="true">` in `install.xml`.

#### key and index types

Keys and indexes can be expressed in several ways:
* `TRUE` will simply create a key or index with the same name as the field
* A string will create a named index or key, unless the key is foreign
* An array of strings will create a named index or key for each entry, unles the key is foreign

Foreign keys work a little differently:
* To create a single foreign key, use the form 'table.field'
* To create multiple foreign keys, use an associative array with 'keyname' => 'table.field'

To create an index or key across multiple keys, use the same name and key/index type for each of them. For example, this example creates a unique index named `my_unique_index` on the `name` and `kind` fields:

```php
protected static function define_properties() {
    return array(
        'name' => array(
            'type' => PARAM_TEXT,
            'dbtype' => XMLDB_TYPE_CHAR,
            'precision' => 255,
            'uniqueindex' => 'my_unique_index'
        ),
        'kind' => array(
            'type' => PARAM_INT,
            'uniqueindex' => 'my_unique_index'
        )
    );
}
```

\* The adjectives are reversed relative to the XMLDB representation because of [English adjective order](https://en.wikipedia.org/wiki/Adjective#Order).

## License ##

2018 Morgan Harris <morgan.harris@unsw.edu.au>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
