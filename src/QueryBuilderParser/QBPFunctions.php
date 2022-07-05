<?php
namespace timgws;

use \Illuminate\Database\Query\Builder;
use \stdClass;
use \Carbon\Carbon;

trait QBPFunctions
{
    /**
     * @param stdClass $rule
     */
    abstract protected function checkRuleCorrect(stdClass $rule);

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
        'not_between'      => array ('accept_values' => true,  'apply_to' => ['number', 'datetime']),
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
        'not_between'      => array ('operator' => 'NOT BETWEEN'),
        'begins_with'      => array ('operator' => 'LIKE',     'prepend'  => '%'),
        'not_begins_with'  => array ('operator' => 'NOT LIKE', 'prepend'  => '%'),
        'contains'         => array ('operator' => 'LIKE',     'append'  => '%', 'prepend' => '%'),
        'not_contains'     => array ('operator' => 'NOT LIKE', 'append'  => '%', 'prepend' => '%'),
        'ends_with'        => array ('operator' => 'LIKE',     'append' => '%'),
        'not_ends_with'    => array ('operator' => 'NOT LIKE', 'append' => '%'),
        'is_empty'         => array ('operator' => '='),
        'is_not_empty'     => array ('operator' => '!='),
        'is_null'          => array ('operator' => 'NULL'),
        'is_not_null'      => array ('operator' => 'NOT NULL')
    );

    protected $needs_array = array(
        'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN',
    );

    /**
     * Determine if an operator (LIKE/IN) requires an array.
     *
     * @param $operator
     *
     * @return bool
     */
    protected function operatorRequiresArray($operator)
    {
        return in_array($operator, $this->needs_array);
    }

    /**
     * Determine if an operator is NULL/NOT NULL
     *
     * @param $operator
     *
     * @return bool
     */
    protected function operatorIsNull($operator)
    {
        return ($operator == 'NULL' || $operator == 'NOT NULL') ? true : false;
    }

    /**
     * Make sure that a condition is either 'or' or 'and'.
     *
     * @param $condition
     * @return string
     * @throws QBParseException
     */
    protected function validateCondition($condition)
    {
        if (is_null($condition)) {
            return $condition;
        }

        $condition = trim(strtolower($condition));

        if ($condition !== 'and' && $condition !== 'or') {
            throw new QBParseException("Condition can only be one of: 'and', 'or'.");
        }

        return $condition;
    }

    /**
     * Enforce whether the value for a given field is the correct type
     *
     * @param bool $requireArray value must be an array
     * @param mixed $value the value we are checking against
     * @param string $field the field that we are enforcing
     * @return mixed value after enforcement
     * @throws QBParseException if value is not a correct type
     */
    protected function enforceArrayOrString($requireArray, $value, $field)
    {
        $this->checkFieldIsAnArray($requireArray, $value, $field);

        if (!$requireArray && is_array($value)) {
            return $this->convertArrayToFlatValue($field, $value);
        }

        return $value;
    }

    /**
     * Ensure that a given field is an array if required.
     *
     * @see enforceArrayOrString
     * @param boolean $requireArray
     * @param $value
     * @param string $field
     * @throws QBParseException
     */
    protected function checkFieldIsAnArray($requireArray, $value, $field)
    {
        if ($requireArray && !is_array($value)) {
            throw new QBParseException("Field ($field) should be an array, but it isn't.");
        }
    }

    /**
     * Convert an array with just one item to a string.
     *
     * In some instances, and array may be given when we want a string.
     *
     * @see enforceArrayOrString
     * @param string $field
     * @param $value
     * @return mixed
     * @throws QBParseException
     */
    protected function convertArrayToFlatValue($field, $value)
    {
        if (count($value) !== 1) {
            throw new QBParseException("Field ($field) should not be an array, but it is.");
        }

        return $value[0];
    }

    /**
     * Convert a Datetime field to Carbon items to be used for comparisons.
     *
     * @param $value
     * @return \Carbon\Carbon
     * @throws QBParseException
     */
    protected function convertDatetimeToCarbon($value)
    {
        if (is_array($value)) {
            return array_map(function ($v) {
                return new Carbon($v);
            }, $value);
        }

        return new Carbon($value);
    }

    /**
     * Append or prepend a string to the query if required.
     *
     * @param bool $requireArray value must be an array
     * @param mixed $value the value we are checking against
     * @param mixed $sqlOperator
     * @return mixed $value
     */
    protected function appendOperatorIfRequired($requireArray, $value, $sqlOperator)
    {
        if (!$requireArray) {
            if (isset($sqlOperator['append'])) {
                $value = $sqlOperator['append'].$value;
            }

            if (isset($sqlOperator['prepend'])) {
                $value = $value.$sqlOperator['prepend'];
            }
        }

        return $value;
    }

    /**
     * Decode the given JSON
     *
     * @param string incoming json
     * @throws QBParseException
     * @return stdClass
     */
    private function decodeJSON($json)
    {
        $query = json_decode($json);

        if (json_last_error()) {
            throw new QBParseException('JSON parsing threw an error: '.json_last_error_msg());
        }

        if (!is_object($query)) {
            throw new QBParseException('The query is not valid JSON');
        }

        return $query;
    }

    /**
     * get a value for a given rule.
     *
     * throws an exception if the rule is not correct.
     *
     * @param stdClass $rule
     * @throws QBRuleException
     */
    private function getRuleValue(stdClass $rule)
    {
        if (!$this->checkRuleCorrect($rule)) {
            throw new QBRuleException();
        }

        return $rule->value;
    }

    /**
     * Check that a given field is in the allowed list if set.
     *
     * @param $fields
     * @param $field
     * @throws QBParseException
     */
    private function ensureFieldIsAllowed($fields, $field)
    {
        if (is_array($fields) && !in_array($field, $fields)) {
            throw new QBParseException("Field ({$field}) does not exist in fields list");
        }
    }

    /**
     * makeQuery, for arrays.
     *
     * Some types of SQL Operators (ie, those that deal with lists/arrays) have specific requirements.
     * This function enforces those requirements.
     *
     * @param Builder  $query
     * @param stdClass $rule
     * @param array    $sqlOperator
     * @param array    $value
     * @param string   $condition
     *
     * @throws QBParseException
     *
     * @return Builder
     */
    protected function makeQueryWhenArray(Builder $query, stdClass $rule, array $sqlOperator, array $value, $condition)
    {
        if ($sqlOperator['operator'] == 'IN' || $sqlOperator['operator'] == 'NOT IN') {
            return $this->makeArrayQueryIn($query, $rule, $sqlOperator['operator'], $value, $condition);
        } elseif ($sqlOperator['operator'] == 'BETWEEN' || $sqlOperator['operator'] == 'NOT BETWEEN') {
            return $this->makeArrayQueryBetween($query, $rule, $sqlOperator['operator'], $value, $condition);
        }

        throw new QBParseException('makeQueryWhenArray could not return a value');
    }

    /**
     * Create a 'null' query when required.
     *
     * @param Builder  $query
     * @param stdClass $rule
     * @param array    $sqlOperator
     * @param string   $condition
     *
     * @throws QBParseException when SQL operator is !null
     * @return Builder
     */
    protected function makeQueryWhenNull(Builder $query, stdClass $rule, array $sqlOperator, $condition)
    {
        if ($sqlOperator['operator'] == 'NULL') {
            return $query->whereNull($rule->field, $condition);
        } elseif ($sqlOperator['operator'] == 'NOT NULL') {
            return $query->whereNotNull($rule->field, $condition);
        }

        throw new QBParseException('makeQueryWhenNull was called on an SQL operator that is not null');
    }

    /**
     * makeArrayQueryIn, when the query is an IN or NOT IN...
     *
     * @see makeQueryWhenArray
     * @param Builder $query
     * @param stdClass $rule
     * @param string $operator
     * @param array $value
     * @param string $condition
     * @return Builder
     */
    private function makeArrayQueryIn(Builder $query, stdClass $rule, $operator, array $value, $condition)
    {
        if ($operator == 'NOT IN') {
            return $query->whereNotIn($rule->field, $value, $condition);
        }

        return $query->whereIn($rule->field, $value, $condition);
    }


    /**
     * makeArrayQueryBetween, when the query is a BETWEEN or NOT BETWEEN...
     *
     * @see makeQueryWhenArray
     * @param Builder $query
     * @param stdClass $rule
     * @param string operator the SQL operator used. [BETWEEN|NOT BETWEEN]
     * @param array $value
     * @param string $condition
     * @throws QBParseException when more then two items given for the between
     * @return Builder
     */
    private function makeArrayQueryBetween(Builder $query, stdClass $rule, $operator, array $value, $condition)
    {
        if (count($value) !== 2) {
            throw new QBParseException("{$rule->field} should be an array with only two items.");
        }

        if ( $operator == 'NOT BETWEEN' ) {
            return $query->whereNotBetween( $rule->field, $value, $condition );
        }

        return $query->whereBetween($rule->field, $value, $condition);
    }
}
