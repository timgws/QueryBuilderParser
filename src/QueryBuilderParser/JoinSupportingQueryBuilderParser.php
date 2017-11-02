<?php

namespace timgws;

use Illuminate\Database\Query\Builder;
use stdClass;
use timgws\QBParseException;

class JoinSupportingQueryBuilderParser extends QueryBuilderParser
{
    /**
     * @var null|array an associative array of the join fields keyed by fields name, with the following keys
     */
    protected $joinFields;

    /**
     * @param array $fields     a list of all the fields that are allowed to be filtered by the QueryBuilder
     * @param array $joinFields an associative array of the join fields keyed by fields name, with the following keys
     *                          - from_table       The name of the master table
     *                          - from_col         The column of the master table to use in the join
     *                          - to_table         The name of the join table
     *                          - to_col           The column of the join table to use
     *                          - to_value_column  The column of the join table containing the value to use as a
     *                                             where clause
     *                          - to_clause*       An additional clause to add to the join condition, compatible
     *                                             with $query->where($clause)
     *                          - not_exists*      Only return rows which do not exist in the subclause
     *
     * * optional field
     */
    public function __construct(array $fields = null, array $joinFields = null)
    {
        parent::__construct($fields);
        $this->joinFields = $joinFields;
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
     * @param string   $queryCondition the condition that will be used in the query
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

        $condition = strtolower($queryCondition);

        if (is_array($this->joinFields) && array_key_exists($rule->field, $this->joinFields)) {
            return $this->buildSubclauseQuery($query, $rule, $value, $condition);
        }

        return $this->convertIncomingQBtoQuery($query, $rule, $value, $condition);
    }

    /**
     * Build a subquery clause if there are join fields that have been specified.
     *
     * @param Builder $query
     * @param stdClass $rule
     * @param string|null $value
     * @return Builder the query builder object
     */
    private function buildSubclauseQuery($query, $rule, $value, $condition)
    {
        /*
         * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
         * into on one that we can use inside the SQL query
         */
        $_sql_op = $this->operator_sql[$rule->operator];
        $operator = $_sql_op['operator'];
        $require_array = $this->operatorRequiresArray($operator);

        $subclause = $this->joinFields[$rule->field];
        $subclause['operator'] = $operator;
        $subclause['value'] = $value;
        $subclause['require_array'] = $require_array;

        $not = array_key_exists('not_exists', $subclause) && $subclause['not_exists'];

        // Create a where exists clause to join to the other table, and find results matching the criteria
        $query = $query->whereExists(
            /**
             * @param Builder $query
             */
            function(Builder $query) use ($subclause) {

                $q = $query->selectRaw(1)
                    ->from($subclause['to_table'])
                    ->whereRaw($subclause['to_table'].'.'.$subclause['to_col']
                        .' = '
                        .$subclause['from_table'].'.'.$subclause['from_col']);

                if (array_key_exists('to_clause', $subclause)) {
                    $q->where($subclause['to_clause']);
                }

                $this->buildSubclauseInnerQuery($subclause, $q);
            },
            $condition,
            $not
        );

        return $query;
    }

    /**
     * The inner query for a subclause
     *
     * @see buildSubclauseQuery
     * @param array $subclause
     * @param Builder $query
     * @return Builder the query builder object
     */
    private function buildSubclauseInnerQuery($subclause, Builder $query)
    {
        if ($subclause['require_array']) {
            return $this->buildRequireArrayQuery($subclause, $query);
        }

        if ($subclause['operator'] == 'NULL' || $subclause['operator'] == 'NOT NULL') {
            return $this->buildSubclauseWithNull($subclause, $query, ($subclause['operator'] == 'NOT NULL' ? true : false));
        }

        return $this->buildRequireNotArrayQuery($subclause, $query);
    }

    /**
     * The inner query for a subclause when an array is required
     *
     * @see buildSubclauseInnerQuery
     * @throws QBParseException when an invalid array is passed.
     * @param array $subclause
     * @param Builder $query
     * @return Builder the query builder object
     */
    private function buildRequireArrayQuery($subclause, Builder $query)
    {
        if ($subclause['operator'] == 'IN') {
            $query->whereIn($subclause['to_value_column'], $subclause['value']);
        } elseif ($subclause['operator'] == 'NOT IN') {
            $query->whereNotIn($subclause['to_value_column'], $subclause['value']);
        } elseif ($subclause['operator'] == 'BETWEEN') {
            if (count($subclause['value']) !== 2) {
                throw new QBParseException($subclause['to_value_column'].
                    ' should be an array with only two items.');
            }

            $query->whereBetween($subclause['to_value_column'], $subclause['value']);
        } elseif ($subclause['operator'] == 'NOT BETWEEN') {
            if (count($subclause['value']) !== 2) {
                throw new QBParseException($subclause['to_value_column'].
                    ' should be an array with only two items.');
            }

            $query->whereNotBetween($subclause['to_value_column'], $subclause['value']);
        }

        return $query;
    }

    /**
     * The inner query for a subclause when an array is not requeired
     *
     * @see buildSubclauseInnerQuery
     * @param array $subclause
     * @param Builder $query
     * @return Builder the query builder object
     */
    private function buildRequireNotArrayQuery($subclause, Builder $query)
    {
        return $query->where($subclause['to_value_column'], $subclause['operator'], $subclause['value']);
    }

    /**
     * The inner query for a subclause when the operator is NULL.
     *
     * @see buildSubclauseInnerQuery
     * @param array $subclause
     * @param Builder $query
     * @return Builder the query builder object
     */
    private function buildSubclauseWithNull($subclause, Builder $query, $isNotNull = false)
    {
        if ($isNotNull === true) {
            return $query->whereNotNull($subclause['to_value_column']);
        }

        return $query->whereNull($subclause['to_value_column']);
    }

}
