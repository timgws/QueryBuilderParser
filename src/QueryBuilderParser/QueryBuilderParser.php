<?php

namespace timgws;
use \stdClass;
use \timgws\QBParseException;
use \Illuminate\Database\Query\Builder;

class QueryBuilderParser {

    protected $operators = array (
        'equal'            => array ('accept_values' => true,  'apply_to' => ['string', 'number', 'datetime']),
        'not_equal'        => array ('accept_values' => true,  'apply_to' => ['string', 'number', 'datetime']),
        'in'               => array ('accept_values' => true,  'apply_to' => ['string', 'number', 'datetime']),
        'not_in'           => array ('accept_values' => true,  'apply_to' => ['string', 'number', 'datetime']),
        'less'             => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
        'less_or_equal'    => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
        'greater'          => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
        'greater_or_equal' => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
        'between'          => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
        'begins_with'      => array ('accept_values' => true,  'apply_to' => ['string']),
        'not_begins_with'  => array ('accept_values' => true,  'apply_to' => ['string']),
        'contains'         => array ('accept_values' => true,  'apply_to' => ['string']),
        'not_contains'     => array ('accept_values' => true,  'apply_to' => ['string']),
        'ends_with'        => array ('accept_values' => true,  'apply_to' => ['string']),
        'not_ends_with'    => array ('accept_values' => true,  'apply_to' => ['string']),
        'is_empty'         => array ('accept_values' => false, 'apply_to' => ['string']),
        'is_not_empty'     => array ('accept_values' => false, 'apply_to' => ['string']),
        'is_null'          => array ('accept_values' => false, 'apply_to' => ['string', 'number', 'datetime']),
        'is_not_null'      => array ('accept_values' => false, 'apply_to' => ['string', 'number', 'datetime'])
    );

    protected $operator_sql = array (
        'equal'            => array ('operator' => '='),
        'not_equal'        => array ('operator' => '!='),
        'in'               => array ('operator' => 'IN'),
        'not_in'           => array ('operator' => 'NOT IN'),
        'less'             => array ('operator' => '<'),
        'less_or_equal'    => array ('operator' => '<='),
        'greater'          => array ('operator' => '>'),
        'greater_or_equal' => array ('operator' => '>='),
        'between'          => array ('operator' => 'BETWEEN'),
        'begins_with'      => array ('operator' => 'LIKE',     'append'  => '%'),
        'not_begins_with'  => array ('operator' => 'NOT LIKE', 'append'  => '%'),
        'contains'         => array ('operator' => 'LIKE',     'append'  => '%', 'prepend' => '%'),
        'not_contains'     => array ('operator' => 'NOT LIKE', 'append'  => '%', 'prepend' => '%'),
        'ends_with'        => array ('operator' => 'LIKE',     'prepend' => '%'),
        'not_ends_with'    => array ('operator' => 'NOT LIKE', 'prepend' => '%'),
        'is_empty'         => array ('operator' => '='),
        'is_not_empty'     => array ('operator' => '!='),
        'is_null'          => array ('operator' => 'NULL'),
        'is_not_null'      => array ('operator' => 'NOT NULL')
    );

    protected $needs_array = array (
        'IN', 'NOT IN', 'BETWEEN'
    );

    private $fields;

    public function __construct(array $fields = null)
    {
        $this->fields = $fields;
    }

    public function parse($json, \Illuminate\Database\Query\Builder $qb)
    {
        $query = json_decode($json);

        if ($error = json_last_error()) {
            throw new QBParseException('JSON parsing threw an error: ' . $error);
        }

        if (!is_object($query)) {
            throw new QBParseException('The query is not valid JSON');
        }

        // This can happen if the querybuilder had no rules...
        if (!isset($query->rules) || !is_array($query->rules)) {
            return $qb;
        }

        // This shouldn't ever cause an issue, but may as well not go through the rules.
        if (count($query->rules) < 1) {
            return $qb;
        }

        return $this->loopThroughRules($query->rules, $qb);
    }

    /**
     * Called by parse, loops through all the rules to find out if nested or not.
     *
     * @param array $rules
     * @param Builder $qb
     * @return Builder
     * @throws QBParseException
     */
    private function loopThroughRules(array $rules, Builder $qb)
    {
        foreach ($rules as $rule) {
            $qb = $this->makeQuery($qb, $rule);

            if ($this->isNested($rule)) {
                $qb = $this->createNestedQuery($qb, $rule);
            }
        }

        return $qb;
    }

    private function isNested($rule)
    {
        if (isset($rule->rules)) {
            if (is_array($rule->rules) && count($rule->rules) > 0) {
                return true;
            }
        }
    }

    private function createNestedQuery(Builder $qb, stdClass $rule, $condition = null)
    {
        if ($condition === null)
            $condition = $rule->condition;

        $condition = strtolower($condition);

        return $qb->whereNested(function($query) use (&$rule, &$qb, &$condition) {
            foreach($rule->rules as $_rule) {
                if ($this->isNested($_rule)) {
                    $qb = $this->createNestedQuery($query, $_rule, $rule->condition);
                } else {
                    $qb = $this->makeQuery($query, $_rule);
                }
            }

        }, $rule->condition);
    }

    private function makeQuery(Builder $query, stdClass $rule)
    {
        /**
         * Make sure most of the common fields from the QueryBuilder have been added.
         */
        if (!isset($rule->operator) || !isset($rule->id) || !isset($rule->field))
            return $query;

        if (!isset($rule->input) || !isset($rule->type))
            return $query;

        if (!isset($this->operators[$rule->operator]))
            return $query;

        $value = $rule->value;
        $_sql_op = $this->operator_sql[$rule->operator];

        if (isset($_sql_op['append']))
            $value = $_sql_op['append'] . $value;

        if (isset($_sql_op['prepend']))
            $value = $value . $_sql_op['prepend'];

        /**
         * If the SQL Operator is set not to have a value, make sure that we set the value to null.
         */
        if ($this->operators[$rule->operator]['accept_values'] === false)
            $value = null;

        if (is_array($this->fields) && !in_array($rule->field, $this->fields)) {
            throw new QBParseException("Field ({$rule->field}) does not exist in fields list");
        }

        $operator = $_sql_op['operator'];
        $require_array = in_array($operator, $this->needs_array);

        /**
         * \o/ Ensure that the value is an array only if it should be.
         */
        if ($require_array && !is_array($value)) {
            throw new QBParseException("Field ({$rule->field}) should be an array, but it isn't.");
        } elseif (!$require_array && is_array($value)) {
            throw new QBParseException("Field ({$rule->field}) should not be an array, but it is.");
        }

        if ($require_array) {
            $query = $this->makeQueryWhenArray($query, $rule, $_sql_op, $value);
        } else {
            $query = $query->where($rule->field, $_sql_op['operator'], $value);
        }

        return $query;
    }

    private function makeQueryWhenArray(Builder $query, stdClass $rule, array $_sql_op, $value)
    {
        if ($_sql_op['operator'] == 'IN') {
            $query = $query->whereIn($rule->field, $value);
        } elseif ($_sql_op['operator'] == 'NOT IN') {
            $query = $query->whereNotIn($rule->field, $value);
        } elseif ($_sql_op['operator'] == 'BETWEEN') {
            if (count($value) !== 2)
                throw new QBParseException("{$rule->field} should be an array with only two items.");

            $query = $query->whereBetween($rule->field, $value);
        }

        return $query;
    }
}
