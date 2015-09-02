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

    protected $fields;

    /**
     * @param array $fields a list of all the fields that are allowed to be filtered by the QueryBuilder
     */
    public function __construct(array $fields = null)
    {
        $this->fields = $fields;
    }

    /**
     * QueryBuilderParser's parse function!
     *
     * Build a query based on JSON that has been passed into the function, onto the builder passed into the function.
     *
     * @param $json
     * @param Builder $querybuilder
     * @return Builder
     * @throws QBParseException
     */
    public function parse($json, \Illuminate\Database\Query\Builder $querybuilder)
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
            return $querybuilder;
        }

        // This shouldn't ever cause an issue, but may as well not go through the rules.
        if (count($query->rules) < 1) {
            return $querybuilder;
        }

        return $this->loopThroughRules($query->rules, $querybuilder);
    }

    /**
     * Called by parse, loops through all the rules to find out if nested or not.
     *
     * @param array $rules
     * @param Builder $querybuilder
     * @return Builder
     * @throws QBParseException
     */
    protected function loopThroughRules(array $rules, Builder $querybuilder)
    {
        foreach ($rules as $rule) {
            /**
             * If makeQuery does not see the correct fields, it will return the QueryBuilder without modifications
             */
            $querybuilder = $this->makeQuery($querybuilder, $rule);

            if ($this->isNested($rule)) {
                $querybuilder = $this->createNestedQuery($querybuilder, $rule);
            }
        }

        return $querybuilder;
    }

    /**
     * Determine if a particular rule is actually a group of other rules
     *
     * @param $rule
     * @return bool
     */
    protected function isNested($rule)
    {
        if (isset($rule->rules)) {
            if (is_array($rule->rules) && count($rule->rules) > 0) {
                return true;
            }
        }
    }

    /**
     * Create nested queries
     *
     * When a rule is actually a group of rules, we want to build a nested query with the specified condition (AND/OR)
     *
     * @param Builder $querybuilder
     * @param stdClass $rule
     * @param null $condition
     * @return mixed
     */
    protected function createNestedQuery(Builder $querybuilder, stdClass $rule, $condition = null)
    {
        if ($condition === null)
            $condition = $rule->condition;

        $condition = $this->validateCondition($condition);

        return $querybuilder->whereNested(function($query) use (&$rule, &$querybuilder, &$condition) {
            foreach($rule->rules as $_rule) {
                if ($this->isNested($_rule)) {
                    $querybuilder = $this->createNestedQuery($query, $_rule, $rule->condition);
                } else {
                    $querybuilder = $this->makeQuery($query, $_rule);
                }
            }

        }, $rule->condition);
    }

    /**
     * Check if a given rule is correct
     *
     * Just before making a query for a rule, we want to make sure that the field, operator and value are set
     *
     * @param stdClass $rule
     * @return bool true if values are correct.
     */
    protected function checkRuleCorrect(stdClass $rule)
    {
        if (!isset($rule->operator) || !isset($rule->id) || !isset($rule->field))
            return false;

        if (!isset($rule->input) || !isset($rule->type))
            return false;

        if (!isset($this->operators[$rule->operator]))
            return false;

        return true;
    }

    /**
     * Give back the correct value when we don't accept one
     *
     * @param $rule
     * @return null|string
     */
    protected function operatorValueWhenNotAcceptingOne(stdClass $rule)
    {
        if ($this->operators[$rule->operator]['accept_values'] === false) {
            if ($rule->operator == 'is_empty' || $rule->operator == 'is_not_empty')
                $value = '';
            else
                $value = null;
        }

        return $value;
    }

    protected function operatorRequiresArray($operator)
    {
        return in_array($operator, $this->needs_array);
    }

    /**
     * Ensure that the value for a field is correct.
     *
     * Append/Prepend values for SQL statements, etc.
     *
     * @param $operator
     * @param stdClass $rule
     * @param $value
     * @return string
     * @throws QBParseException
     */
    protected function getCorrectValue($operator, stdClass $rule, $value)
    {
        $field = $rule->field;
        $_sql_op = $this->operator_sql[$rule->operator];
        $require_array = $this->operatorRequiresArray($operator);

        if ($require_array && !is_array($value)) {
            throw new QBParseException("Field (${field}) should be an array, but it isn't.");
        } elseif (!$require_array && is_array($value)) {
            if (count($value) !== 1)
                throw new QBParseException("Field (${field}) should not be an array, but it is.");
            $value = $value[0];
        }

        if (!$require_array) {
            if (isset($_sql_op['append']))
                $value = $_sql_op['append'] . $value;

            if (isset($_sql_op['prepend']))
                $value = $value . $_sql_op['prepend'];
        }

        return $value;
    }

    /**
     * makeQuery: The money maker!
     *
     * Take a particular rule and make build something that the QueryBuilder would be proud of.
     *
     * Make sure that all the correct fields are in the rule object then add the expression to
     * the query that was given by the user to the QueryBuilder.
     *
     * @param Builder $query
     * @param stdClass $rule
     * @return Builder
     * @throws QBParseException
     */
    protected function makeQuery(Builder $query, stdClass $rule)
    {
        /**
         * Make sure most of the common fields from the QueryBuilder have been added.
         */
        if (!$this->checkRuleCorrect($rule))
            return $query;

        $value = $rule->value;
        $_sql_op = $this->operator_sql[$rule->operator];
        $operator = $_sql_op['operator'];

        $require_array = $this->operatorRequiresArray($operator);

        /**
         * If the SQL Operator is set not to have a value, make sure that we set the value to null.
         */
        if ($this->operators[$rule->operator]['accept_values'] === false) {
            $value = $this->operatorValueWhenNotAcceptingOne($rule);
        }

        if (is_array($this->fields) && !in_array($rule->field, $this->fields)) {
            throw new QBParseException("Field ({$rule->field}) does not exist in fields list");
        }

        /**
         * \o/ Ensure that the value is an array only if it should be.
         */
        $value = $this->getCorrectValue($operator, $rule, $value);

        if ($require_array) {
            $query = $this->makeQueryWhenArray($query, $rule, $_sql_op, $value);
        } else {
            $query = $query->where($rule->field, $_sql_op['operator'], $value);
        }

        return $query;
    }

    /**
     * makeQuery, for arrays.
     *
     * Some types of SQL Operators (ie, those that deal with lists/arrays) have specific requirements.
     * This function enforces those requirements.
     *
     * @param Builder $query
     * @param stdClass $rule
     * @param array $_sql_op
     * @param $value
     * @return Builder
     * @throws QBParseException
     */
    protected function makeQueryWhenArray(Builder $query, stdClass $rule, array $_sql_op, $value)
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

    /**
     * Make sure that a condition is either 'or' or 'and'.
     *
     * @param $condition
     * @return string
     * @throws QBParseException
     */
    protected function validateCondition($condition) {
        $condition = trim(strtolower($condition));
        if ($condition !== 'and' && $condition !== 'or')
            throw new QBParseException("Condition can only be one of: 'and', 'or'.");

        return $condition;
    }
}
