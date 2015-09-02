<?php

namespace timgws;
use Illuminate\Support\Facades\DB;
use \stdClass;
use \timgws\QBParseException;
use \Illuminate\Database\Query\Builder;

class JoinSupportingQueryBuilderParser extends QueryBuilderParser{

    protected $joinFields;

    /**
     * @param array $fields a list of all the fields that are allowed to be filtered by the QueryBuilder
     * @param joinFields an associative array of the join fields keyed by fields name, with the following keys
     * - from_table       The name of the master table
     * - from_col         The column of the master table to use in the join
     * - to_table         The name of the join table
     * - to_col           The column of the join table to use
     * - to_value_column  The column of the join table containing the value to use as a where clause
     * - not_exists       Only return rows which do not exist in the subclause
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

        if (is_array($this->joinFields) && array_key_exists($rule->field, $this->joinFields))
        {
            $subclause = $this->joinFields[$rule->field];
            $subclause['operator'] = $operator;
            $subclause['value'] = $value;
            $subclause['require_array'] = $require_array;

            $not = array_key_exists('not_exists',$subclause) && $subclause['not_exists'];

            // Create a where exists clause to join to the other table, and find results matching the criteria
            $query = $query->whereExists(
              function ($query) use ($subclause) {

                $q = $query->select('1')
                  ->from($subclause['to_table'])
                  ->whereRaw($subclause['to_table'].'.'.$subclause['to_col']
                    . ' = '
                    . $subclause['from_table'] . '.'.$subclause['from_col']);

                  if ($subclause['require_array']) {

                      if ($subclause['operator'] == 'IN') {
                          $q->whereIn($subclause['to_value_column'], $subclause['value']);
                      } elseif ($subclause['operator'] == 'NOT IN') {
                          $q->whereNotIn($subclause['to_value_column'], $subclause['value']);
                      } elseif ($subclause['operator'] == 'BETWEEN') {
                          if (count($subclause['value']) !== 2) {
                              throw new QBParseException($subclause['to_value_column'].
                                " should be an array with only two items.");
                          }
                          $q->whereBetween($subclause['to_value_column'], $subclause['value']);
                      }
                  } else {
                      $q->where($subclause['to_value_column'], $subclause['operator'], $subclause['value']);
                  }
            },'and',$not);

        } else {
            if ($require_array) {
                $query = $this->makeQueryWhenArray($query, $rule, $_sql_op, $value);
            } else {
                $query = $query->where($rule->field, $_sql_op['operator'], $value);
            }
        }
        return $query;
    }

}
