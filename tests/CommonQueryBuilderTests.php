<?php

namespace timgws\test;

use Illuminate\Database\Connection as Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar as MySQLGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor as MySQLProcessor;
use timgws\QueryBuilderParser;

class CommonQueryBuilderTests extends \PHPUnit_Framework_TestCase
{
    protected $simpleQuery = '{"condition":"AND","rules":[{"id":"price","field":"price","type":"double","input":"text","operator":"less","value":"10.25"}]}';
    protected $json1 = '{
       "condition":"AND",
       "rules":[
          {
             "id":"price",
             "field":"price",
             "type":"double",
             "input":"text",
             "operator":"less",
             "value":"10.25"
          },
          {
             "condition":"OR",
             "rules":[
                {
                   "id":"name",
                   "field":"name",
                   "type":"string",
                   "input":"text",
                   "operator":"begins_with",
                   "value":"Thommas"
                },
                {
                   "id":"name",
                   "field":"name",
                   "type":"string",
                   "input":"text",
                   "operator":"equal",
                   "value":"John Doe"
                }
             ]
          }
       ]
    }';

    protected function setUp()
    {
    }

    protected function getParserUnderTest($fields = null)
    {
        return new QueryBuilderParser($fields);
    }

    protected function createQueryBuilder()
    {
        $pdo = new \PDO('sqlite::memory:');
        $builder = new Builder(new Connection($pdo), new MySQLGrammar(), new MySQLProcessor());

        return $builder;
    }

    protected function makeJSONForInNotInTest($is_in = true)
    {
        $operator = 'not_in';
        if ($is_in) {
            $operator = 'in';
        }

        return '{
           "condition":"AND",
           "rules":[
              {
                 "id":"price",
                 "field":"price",
                 "type":"double",
                 "input":"text",
                 "operator":"less",
                 "value":"10.25"
              },
              {
                 "condition":"OR",
                 "rules":[{
                   "id":"category",
                   "field":"category",
                   "type":"integer",
                   "input":"select",
                   "operator":"'.$operator.'",
                   "value":[
                      "1", "2"
                   ]}
                 ]
              }
           ]
        }
        ';
    }
}
