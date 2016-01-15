<?php

namespace timgws;

use \stdClass;
use \timgws\QBParseException;
use \Illuminate\Database\Query\Builder;

class QueryBuilderParser
{
    use QBPFunctions;

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
     *
     * @throws QBParseException
     *
     * @return Builder
     */
    public function parse($json, Builder $querybuilder)
    {
        $query = json_decode($json);

        if (json_last_error()) {
            throw new QBParseException('JSON parsing threw an error: '.json_last_error_msg());
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

        return $this->loopThroughRules($query->rules, $querybuilder, $query->condition);
    }

    /**
     * Called by parse, loops through all the rules to find out if nested or not.
     *
     * @param array   $rules
     * @param Builder $querybuilder
     * @param string  $queryCondition
     *
     * @throws QBParseException
     *
     * @return Builder
     */
    protected function loopThroughRules(array $rules, Builder $querybuilder, $queryCondition = 'AND')
    {
        foreach ($rules as $rule) {
            /*
             * If makeQuery does not see the correct fields, it will return the QueryBuilder without modifications
             */
            $querybuilder = $this->makeQuery($querybuilder, $rule, $queryCondition);

            if ($this->isNested($rule)) {
                $querybuilder = $this->createNestedQuery($querybuilder, $rule, $queryCondition);
            }
        }

        return $querybuilder;
    }

    /**
     * Determine if a particular rule is actually a group of other rules.
     *
     * @param $rule
     *
     * @return bool
     */
    protected function isNested($rule)
    {
        if (isset($rule->rules) && is_array($rule->rules) && count($rule->rules) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Create nested queries
     *
     * When a rule is actually a group of rules, we want to build a nested query with the specified condition (AND/OR)
     *
     * @param Builder $querybuilder
     * @param stdClass $rule
     * @param string|null $condition
     * @return Builder
     */
    protected function createNestedQuery(Builder $querybuilder, stdClass $rule, $condition = null)
    {
        if ($condition === null) {
            $condition = $rule->condition;
        }

        $condition = $this->validateCondition($condition);

        return $querybuilder->whereNested(function($query) use (&$rule, &$querybuilder, &$condition) {
            foreach ($rule->rules as $loopRule) {
                $function = 'makeQuery';

                if ($this->isNested($loopRule)) {
                    $function = 'createNestedQuery';
                }

                $querybuilder = $this->{$function}($query, $loopRule, $rule->condition);
            }

        }, $condition);
    }

    /**
     * Check if a given rule is correct.
     *
     * Just before making a query for a rule, we want to make sure that the field, operator and value are set
     *
     * @param stdClass $rule
     *
     * @return bool true if values are correct.
     */
    protected function checkRuleCorrect(stdClass $rule)
    {
        if (!isset($rule->operator) || !isset($rule->id) || !isset($rule->field)) {
            return false;
        }

        if (!isset($rule->input) || !isset($rule->type)) {
            return false;
        }

        if (!isset($this->operators[$rule->operator])) {
            return false;
        }

        return true;
    }

    /**
     * Give back the correct value when we don't accept one.
     *
     * @param $rule
     *
     * @return null|string
     */
    protected function operatorValueWhenNotAcceptingOne(stdClass $rule)
    {
        $value = null;

        if ($this->operators[$rule->operator]['accept_values'] === false) {
            if ($rule->operator == 'is_empty' || $rule->operator == 'is_not_empty') {
                $value = '';
            }
        }

        return $value;
    }

    /**
     * Ensure that the value for a field is correct.
     *
     * Append/Prepend values for SQL statements, etc.
     *
     * @param $operator
     * @param stdClass $rule
     * @param $value
     *
     * @throws QBParseException
     *
     * @return string
     */
    protected function getCorrectValue($operator, stdClass $rule, $value)
    {
        $field = $rule->field;
        $sqlOperator = $this->operator_sql[$rule->operator];
        $requireArray = $this->operatorRequiresArray($operator);

        $value = $this->enforceArrayOrString($requireArray, $value, $field);

        return $this->appendOperatorIfRequired($requireArray, $value, $sqlOperator);
    }

    /**
     * makeQuery: The money maker!
     *
     * Take a particular rule and make build something that the QueryBuilder would be proud of.
     *
     * Make sure that all the correct fields are in the rule object then add the expression to
     * the query that was given by the user to the QueryBuilder.
     *
     * @param Builder  $query
     * @param stdClass $rule
     * @param string   $queryCondition and/or...
     *
     * @throws QBParseException
     *
     * @return Builder
     */
    protected function makeQuery(Builder $query, stdClass $rule, $queryCondition = 'AND')
    {
        /*
         * Ensure that the value is correct for the rule, return query on exception
         */
        try {
            $value = $this->getValueForQueryFromRule($rule);
        } catch (QBRuleException $e) {
            return $query;
        }

        /*
         * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
         * into on one that we can use inside the SQL query
         */
        $sqlOperator = $this->operator_sql[$rule->operator];
        $operator = $sqlOperator['operator'];
        $condition = strtolower($queryCondition);

        if ($this->operatorRequiresArray($operator)) {
            return $this->makeQueryWhenArray($query, $rule, $sqlOperator, $value, $condition);
        }

        return $query->where($rule->field, $sqlOperator['operator'], $value, $condition);
    }

    /**
     * Ensure that the value is correct for the rule, try and set it if it's not.
     *
     * @param stdClass $rule
     *
     * @throws QBRuleException
     * @throws \timgws\QBParseException
     *
     * @return mixed
     */
    protected function getValueForQueryFromRule($rule)
    {
        /*
         * Make sure most of the common fields from the QueryBuilder have been added.
         */
        if (!$this->checkRuleCorrect($rule)) {
            throw new QBRuleException();
        }

        $value = $rule->value;

        /*
         * The field must exist in our list.
         */
        if (is_array($this->fields) && !in_array($rule->field, $this->fields)) {
            throw new QBParseException("Field ({$rule->field}) does not exist in fields list");
        }

        /*
         * If the SQL Operator is set not to have a value, make sure that we set the value to null.
         */
        if ($this->operators[$rule->operator]['accept_values'] === false) {
            return $this->operatorValueWhenNotAcceptingOne($rule);
        }

        /*
         * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
         * into on one that we can use inside the SQL query
         */
        $sqlOperator = $this->operator_sql[$rule->operator];
        $operator = $sqlOperator['operator'];

        /*
         * \o/ Ensure that the value is an array only if it should be.
         */
        $value = $this->getCorrectValue($operator, $rule, $value);

        return $value;
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
        if ($sqlOperator['operator'] == 'IN') {
            $query = $query->whereIn($rule->field, $value, $condition);
        } elseif ($sqlOperator['operator'] == 'NOT IN') {
            $query = $query->whereNotIn($rule->field, $value, $condition);
        } elseif ($sqlOperator['operator'] == 'BETWEEN') {
            if (count($value) !== 2) {
                throw new QBParseException("{$rule->field} should be an array with only two items.");
            }

            $query = $query->whereBetween($rule->field, $value);
        }

        return $query;
    }
}
