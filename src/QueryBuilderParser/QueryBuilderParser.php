<?php

namespace timgws;

use \Carbon\Carbon;
use \stdClass;
use \Illuminate\Database\Query\Builder;
use \Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use \timgws\QBParseException;

class QueryBuilderParser
{
    use QBPFunctions;

    /**
     * The fields (if any) that we allow to filter on using QBP
     * @var array|null
     */
    protected $fields;

    protected $useHaving = false;

    /**
     * A list of all the callbacks that can be called to cleanse provided values from QBP
     * @var array
     */
    private $cleanFieldCallbacks = [];

    /**
     * @param array $fields a list of all the fields that are allowed to be filtered by the QueryBuilder
     */
    public function __construct(array $fields = null)
    {
        $this->fields = $fields;
    }

    /**
     * Use "having" instead of "where" in the queries.
     *
     * @return QueryBuilderParser
     */
    public function setUseHaving()
    {
        $this->useHaving = true;

        return $this;
    }

    /**
     * QueryBuilderParser's parse function!
     *
     * Build a query based on JSON that has been passed into the function, onto the builder passed into the function.
     *
     * @param $json
     * @param EloquentBuilder|Builder $querybuilder
     *
     * @throws QBParseException
     *
     * @return Builder
     */
    public function parse($json, EloquentBuilder|Builder $querybuilder)
    {
        // do a JSON decode (throws exceptions if there is a JSON error...)
        $query = $this->decodeJSON($json);

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
     * @param array $rules
     * @param EloquentBuilder|Builder $querybuilder
     * @param string $queryCondition
     *
     * @throws QBParseException
     *
     * @return Builder
     */
    protected function loopThroughRules(array $rules, EloquentBuilder|Builder $querybuilder, $queryCondition = 'AND')
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
     * @param EloquentBuilder|Builder $querybuilder
     * @param stdClass $rule
     * @param string|null $condition
     * @return Builder
     */
    protected function createNestedQuery(EloquentBuilder|Builder $querybuilder, stdClass $rule, $condition = null)
    {
        if ($condition === null) {
            $condition = $rule->condition;
        }

        $condition = $this->validateCondition($condition);

        return $querybuilder->whereNested(function ($query) use (&$rule, &$querybuilder, &$condition) {
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
        if (!isset($rule->operator, $rule->id, $rule->field, $rule->type)) {
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
        if ($rule->operator == 'is_empty' || $rule->operator == 'is_not_empty') {
            return '';
        }

        return null;
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

        /*
        *  Turn datetime into Carbon object so that it works with "between" operators etc.
        */
        if ($rule->type == 'date') {
            $value = $this->convertDatetimeToCarbon($value);
        }

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
     * @param EloquentBuilder|Builder $query
     * @param stdClass $rule
     * @param string $queryCondition and/or...
     *
     * @throws QBParseException
     *
     * @return Builder
     */
    protected function makeQuery(EloquentBuilder|Builder $query, stdClass $rule, $queryCondition = 'AND')
    {
        /*
         * Ensure that the value is correct for the rule, return query on exception
         */
        $this->validateCondition($queryCondition);
        try {
            $value = $this->getValueForQueryFromRule($rule);
        } catch (QBRuleException $e) {
            return $query;
        }

        return $this->convertIncomingQBtoQuery($query, $rule, $value, $queryCondition);
    }

    /**
     * Convert an incoming rule from jQuery QueryBuilder to the Eloquent Querybuilder
     *
     * (This used to be part of makeQuery, where the name made sense, but I pulled it
     * out to reduce some duplicated code inside JoinSupportingQueryBuilder)
     *
     * @param Builder $query
     * @param stdClass $rule
     * @param mixed $value the value that needs to be queried in the database.
     * @param string $queryCondition and/or...
     * @return Builder
     */
    protected function convertIncomingQBtoQuery(EloquentBuilder|Builder $query, stdClass $rule, $value, $queryCondition = 'AND')
    {
        /*
         * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
         * into on one that we can use inside the SQL query
         */
        $sqlOperator = $this->operator_sql[$rule->operator];
        $operator = $sqlOperator['operator'];
        $condition = strtolower($queryCondition);

        if ($this->operatorRequiresArray($operator)) {
            return $this->makeQueryWhenArray($query, $rule, $sqlOperator, $value, $condition);
        } elseif ($this->operatorIsNull($operator)) {
            return $this->makeQueryWhenNull($query, $rule, $sqlOperator, $condition);
        }

        if ($this->useHaving) {
            return $query->having($rule->field, $sqlOperator['operator'], $value, $condition);
        }

        return $query->where($rule->field, $sqlOperator['operator'], $value, $condition);
    }

    /**
     * Add a filter for cleaning values that are inputted from a QueryBuilder (eg, for ACL)
     * @param $field
     * @param callable|null $callback
     * @return $this
     * @throws \timgws\QBParseException
     */
    public function clean($field, Callable $callback = null)
    {
        if (isset($this->cleanFieldCallbacks[$field])) {
            throw new QBParseException("Field $field already has a clean callback set.");
        }

        if ($callback == null) {
            return $this;
        }

        $this->cleanFieldCallbacks[$field] = $callback;

        return $this;
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
    protected function getValueForQueryFromRule(stdClass $rule)
    {
        /*
         * Make sure most of the common fields from the QueryBuilder have been added.
         */
        if (isset($this->cleanFieldCallbacks[$rule->field])) {
            $rule->value = call_user_func($this->cleanFieldCallbacks[$rule->field], $rule->value);
        }

        $value = $this->getRuleValue($rule);

        /*
         * The field must exist in our list.
         */
        $this->ensureFieldIsAllowed($this->fields, $rule->field);

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
}
