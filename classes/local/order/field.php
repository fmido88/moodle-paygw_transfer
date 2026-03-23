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

namespace paygw_transfer\local\order;

use core\exception\coding_exception;
use core\lang_string;
use core_reportbuilder\local\report\column;
use ReflectionProperty;

/**
 * Class field.
 *
 * @package    paygw_transfer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field {
    /**
     * The current component.
     * @var string
     */
    protected string $component;

    /**
     * The fieldname.
     * @var string
     */
    public readonly string $fieldname;

    /**
     * Order database field object.
     * @param string  $name            database field name
     * @param string  $type            database field type
     * @param string  $getter          the getter method in order class
     * @param bool    $null            could be null or not?
     * @param mixed   $default         the default value
     * @param ?string $cleantype       PARAM_ constant
     * @param ?string $titleidentifier string identifier
     * @param bool    $report          should be included in the report or not
     * @param mixed   $setter
     */
    public function __construct(
        /** @var string The field name */
        public readonly string $name,
        /** @var string the database field type */
        public readonly string $type,
        /** @var string the getter method in the order class */
        protected readonly string  $getter,
        /** @var bool null allowed or not */
        public readonly bool $null = false,
        /** @var string|int|float|null|bool the default value */
        public readonly string|int|float|null|bool $default = null,
        /** @var string|lang_string|null string identifier for the title of this field */
        protected readonly string|lang_string|null $titleidentifier = null,
        /** @var bool include in the report or not */
        public readonly bool $report = true,
        /** @var ?string the cleaning type PARAM_ */
        protected ?string $cleantype = null,
        /** @var ?string the setter method if existed. */
        protected ?string $setter = null,
    ) {
        $this->component = 'paygw_' . order::gateway();

        if ($this->cleantype === null) {
            $this->cleantype = match($this->type) {
                'int'             => PARAM_INT,
                'number', 'float' => PARAM_FLOAT,
                'char', 'text'    => PARAM_TEXT,
                default           => PARAM_RAW_TRIMMED
            };
        } else {
            // Throws coding exception for invalid type.
            \core\param::from_type($this->cleantype);
        }
        $this->check_field();
    }

    /**
     * Get the title of this field.
     * @return lang_string|null
     */
    public function get_title(): ?lang_string {
        if (!isset($this->titleidentifier)) {
            return null;
        }

        if ($this->titleidentifier instanceof lang_string) {
            return $this->titleidentifier;
        }

        return new lang_string($this->titleidentifier, $this->component);
    }

    /**
     * Return the column types to be used in report builder.
     * @return int
     */
    public function get_column_type() {
        if (\in_array($this->name, ['timecreated', 'timemodified'])) {
            return column::TYPE_TIMESTAMP;
        }

        return match($this->type) {
            'char' => column::TYPE_TEXT,
            'number', 'float' => column::TYPE_FLOAT,
            'text'  => column::TYPE_LONGTEXT,
            'int'   => column::TYPE_INTEGER,
            default => column::TYPE_TEXT,
        };
    }

    /**
     * Get a clean value.
     * @param  mixed $value
     * @return mixed
     */
    public function clean_value($value) {
        return clean_param($value, $this->cleantype);
    }

    /**
     * Get the formatted value.
     * @param  mixed              $value
     * @return lang_string|string
     */
    public function format_value($value) {
        if (\in_array($this->name, ['timecreated', 'timemodified'])) {
            return userdate($value);
        }

        if ($this->name === 'component') {
            return new lang_string('pluginname', $value);
        }

        if ($this->name === 'status') {
            $statuses = order::get_statuses_list();

            if (isset($statuses[$value])) {
                return $statuses[$value];
            }
        }

        if ($this->type === 'text') {
            return format_text($value);
        }

        if ($this->type === 'char') {
            return format_string($value);
        }

        return $value;
    }

    /**
     * Call the getter method for this field.
     * @param order $order
     */
    public function get(order $order) {
        if ($this->getter === '') {
            return $order->{$this->name};
        }

        return \call_user_func([$order, $this->getter]);
    }

    /**
     * Get the default value of the field.
     * @throws coding_exception
     * @return bool|float|int|string|null
     */
    public function get_default_value() {
        if ($this->default !== null) {
            return $this->default;
        }

        if ($this->null) {
            return null;
        }

        throw new coding_exception("The field {$this->name} has no default value and cannot be null");
    }

    /**
     * Set a value of this field.
     * @param order $order
     * @param mixed $value
     */
    public function set(order $order, $value) {
        if (!isset($this->setter)) {
            return;
        }
        $value = $this->clean_value($value);

        return \call_user_func([$order, $this->setter], $value);
    }

    /**
     * Get the table fields.
     * @return \database_column_info[]
     */
    protected static function get_table_fields(): array {
        global $DB;
        static $fields;

        if (!isset($fields)) {
            $tablename = order::get_orders_table_name();

            if (!$DB->get_manager()->table_exists($tablename)) {
                throw new coding_exception("The table $tablename not exist");
            }
            $fields = $DB->get_columns($tablename);
        }

        return $fields;
    }

    /**
     * Validate the field structure.
     * @throws coding_exception
     * @return void
     */
    protected function check_field() {
        global $DB;

        if (!\array_key_exists($this->name, self::get_table_fields())) {
            throw new coding_exception("The field {$this->name} not defined in get_extra_field method");
        }

        $class = order::class;

        if (!property_exists($class, $this->name)) {
            throw new coding_exception("The property {$this->name} not declared in the class $class");
        }

        if ($this->getter === '' || !method_exists($class, $this->getter)) {
            $property = new ReflectionProperty($class, $this->name);

            if (!$property->isPublic()) {
                $errors[] = "The getter method {$this->getter} for the nonpublic "
                          . "property {$this->name} not exist in the class $class";
            }
        }

        if ($this->setter !== null && !method_exists($class, $this->setter)) {
            $errors[] = "The setter method {$this->setter} for the field {$this->name} is not exist for the class $class";
        }

        $validtypes = ['int', 'number', 'float', 'char', 'text', 'binary', 'datetime'];

        if (!\in_array($this->type, $validtypes)) {
            $errors[] = "The field type {$this->type} is not a valid type, it should be one of '"
                      . implode("','", $validtypes) . "'.";
        }

        $column = self::get_table_fields()[$this->name];

        if (self::moodletype2string($column->meta_type) != $this->type) {
            $errors[] = "The database table field type {$column->type} not "
                        . "matching the defined type {$this->type}";
        }

        $boolstring = fn (bool $input) => $input ? 'true' : 'false';

        if ((bool)$column->not_null === $this->null) {
            $errors[] = 'The database table field notnull set to '
                        . $boolstring((bool)$column->not_null)
                        . ' while it should be ' . $boolstring(!$this->null);
        }

        if ($this->report && empty($this->titleidentifier)) {
            $errors[] = "The field {$this->name} marked to be placed at the report but not has a string identifier for it";
        }

        if ($this->titleidentifier !== null) {
            $strman = get_string_manager();

            if ($this->titleidentifier instanceof lang_string) {
                $identifier = $this->titleidentifier->get_identifier();
                $component  = $this->titleidentifier->get_component();
            } else {
                $identifier = $this->titleidentifier;
                $component  = $this->component;
            }

            if (!$strman->string_exists($identifier, $component)) {
                $msg = "The string identifier {$identifier} for the field {$this->name} is not exist in the component {$component}";
                debugging($msg, DEBUG_DEVELOPER);
            }
        }

        if (!empty($errors)) {
            $tablename = order::get_orders_table_name();

            throw new coding_exception("The database table $tablename not match the structure of the class $class: "
                                        . implode("<br>\n", $errors));
        }
    }

    /**
     * Convert the moodle database meta_type to simple string type.
     *
     * @param string $metatype
     * @return string
     */
    protected static function moodletype2string(string $metatype) {
        return match($metatype) {
            'I' => 'int',
            'R' => 'int',
            'N' => 'number',
            'F' => 'float', // Nobody should be using floats!
            'C' => 'char',
            'X' => 'text',
            'B' => 'binary',
            'T' => 'timestamp',
            'D' => 'datetime',
        };
    }
}
