<?php

namespace timgws;
use \stdClass;

class QueryBuilderParser {

    private $currentRule;

    protected $operators = array (
        'equal'            => array ('accept_values' => true,  'apply_to' => ['string', 'number', 'datetime']),
        'not_equal'        => array ('accept_values' => true,  'apply_to' => ['string', 'number', 'datetime']),
        'in'               => array ('accept_values' => true,  'apply_to' => ['string', 'number', 'datetime']),
        'not_in'           => array ('accept_values' => true,  'apply_to' => ['string', 'number', 'datetime']),
        'less'             => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
        'less_or_equal'    => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
        'greater'          => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
        'greater_or_equal' => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
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
        'IN', 'NOT IN'
    );

    private $fields;

    public function QueryBuilderParser(array $fields = null)
    {
        $this->fields = $fields;
    }

    public function parse($json, \Illuminate\Database\Query\Builder $qb)
    {
        $query = @json_decode($json);

        if ($error_number = json_last_error()) {
            throw new QBParseException('JSON parsing threw an error: ' . json_last_error_msg());
        }

        if (!is_object($query)) {
            throw new QBParseException('The query is not valid JSON');
        }

        // This can happen if the querybuilder had no rules...
        if (!isset($query->rules) or !is_array($query->rules)) {
            //throw new QBParseException('The query has no rules.');
            return $qb;
        }

        // This shouldn't ever cause an issue, but may as well not go through the rules.
        if (count($query->rules) < 1) {
            return $qb;
        }

        return $this->loopThroughRules($query->rules, $qb);
    }

    private function loopThroughRules($rules, $qb)
    private function loopThroughRules(array $rules, \Illuminate\Database\Query\Builder $qb)
    {
        foreach ($rules as $rule) {
            $this->currentRule = $rule;

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

    private function createNestedQuery($qb, $rule, $condition = null)
    private function createNestedQuery(\Illuminate\Database\Query\Builder $qb, stdClass $rule, $condition = null)
    {
        if ($condition == null)
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

    private function makeQuery(\Illuminate\Database\Query\Builder $query, stdClass $rule)
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
         * Force the value to be null of the operator is null/not null...
         */
        if ($rule->operator == "is_null" || $rule->operator == "is_not_null")
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
            if ($_sql_op['operator'] == 'IN') {
                $query = $query->whereIn($rule->field, $value);
            } elseif ($_sql_op['operator'] == 'NOT IN') {
                $query = $query->whereNotIn($rule->field, $value);
            }
        } else {
            $query = $query->where($rule->field, $_sql_op['operator'], $value);
        }

        return $query;
    }
}

class QBParseException extends \Exception
{
    
}
