<?php

namespace timgws;

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

        return $this->convertQBrules($query->rules, $qb);
    }

    private function convertQBrules($rules, $qb, $nested = false)
    {
        // nested = true will add a nested where statement to the query.
        foreach($rules as $rule) {
            $this->currentRule = $rule;

            if (isset($rule->rules)) {
                if (is_array($rule->rules) && count($rule->rules) > 0) {
                    $qb = $qb->whereNested(function($query) use (&$rule, &$qb) {
                        foreach($rule->rules as $_rule) {
                            $qb = $this->makeQuery($query, $_rule);
                        }
                    }, $rule->condition);
                }
            } else {
                $qb = $this->makeQuery($qb, $rule);
            }
        }

        return $qb;
    }

    private function makeQuery($query, $rule)
    {
        if (!isset($rule->operator) or !isset($rule->id) or !isset($rule->field))
            return $query;

        if (!isset($rule->input) or !isset($rule->type))
            return $query;

        if (!isset($this->operators[$rule->operator]))
            return $query;

        $value = $rule->value;
        $_sql_op = $this->operator_sql[$rule->operator];

        if (isset($_sql_op['append']))
            $value = $_sql_op['append'] . $value;

        if (isset($_sql_op['prepend']))
            $value =  $value . $_sql_op['prepend'];

        if ($rule->operator == "is_null" or $rule->operator == "is_not_null")
            $value = null;

        if (is_array($this->fields) && !in_array($rule->field, $this->fields)) {
            throw new QBParseException("Field ({$rule->field}) does not exist in fields list");
        }

        $operator = $_sql_op['operator'];
        $require_array = in_array($operator, $this->needs_array);

        if ($require_array && !is_array($value)) {
            throw new QBParseException("Field ({$rule->field}) should be an array, but it isn't.");
        } elseif (!$require_array && is_array($value)) {
            throw new QBParseException("Field ({$rule->field}) should not be an array, but it is.");
        }

        if ($require_array) {
            $query = $query->whereIn($rule->field, $_sql_op['operator'], $value);
        } else {
            $query = $query->where($rule->field, $_sql_op['operator'], $value);
        }

        return $query;
    }
}

class QBParseException extends \Exception
{
    
}
