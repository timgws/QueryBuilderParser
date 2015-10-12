<?php

namespace timgws;

use Illuminate\Database\Query\Builder;
use stdClass;
use timgws\QBParseException;

class JoinSupportingQueryBuilderParser extends QueryBuilderParser
{
    /**
     * @var array an associative array of the join fields keyed by fields name, with the following keys
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
            $query = $this->buildSubclauseQuery($query, $rule, $value);
        } else {
            /*
             * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
             * into on one that we can use inside the SQL query
             */
            $sqlOperator = $this->operator_sql[$rule->operator];
            $operator = $sqlOperator['operator'];
            $requireArray = $this->operatorRequiresArray($operator);

            if ($requireArray) {
                $query = $this->makeQueryWhenArray($query, $rule, $sqlOperator, $value, $condition);
            } else {
                $query = $query->where($rule->field, $sqlOperator['operator'], $value, $condition);
            }
        }

        return $query;
    }

    private function buildSubclauseQuery($query, $rule, $value)
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
            function (Builder $query) use ($subclause) {

                $q = $query->selectRaw(1)
                    ->from($subclause['to_table'])
                    ->whereRaw($subclause['to_table'].'.'.$subclause['to_col']
                        .' = '
                        .$subclause['from_table'].'.'.$subclause['from_col']);

                if (array_key_exists('to_clause', $subclause)) {
                    $q->where($subclause['to_clause']);
                }

                if ($subclause['require_array']) {
                    if ($subclause['operator'] == 'IN') {
                        $q->whereIn($subclause['to_value_column'], $subclause['value']);
                    } elseif ($subclause['operator'] == 'NOT IN') {
                        $q->whereNotIn($subclause['to_value_column'], $subclause['value']);
                    } elseif ($subclause['operator'] == 'BETWEEN') {
                        if (count($subclause['value']) !== 2) {
                            throw new QBParseException($subclause['to_value_column'].
                                ' should be an array with only two items.');
                        }

                        $q->whereBetween($subclause['to_value_column'], $subclause['value']);
                    }
                } else {
                    $q->where($subclause['to_value_column'], $subclause['operator'], $subclause['value']);
                }
            },
            'and',
            $not);

        return $query;
    }
}
