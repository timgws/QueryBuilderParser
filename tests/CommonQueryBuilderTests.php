<?php

namespace timgws\test;

use \PHPUnit\Framework\TestCase;
use Illuminate\Database\Connection as Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar as MySQLGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor as MySQLProcessor;
use timgws\QueryBuilderParser;

class CommonQueryBuilderTests extends TestCase
{
    protected $simpleQuery = '{"condition":"AND","rules":[{"id":"price","field":"price","type":"double","operator":"less","value":"10.25"}]}';
    protected $simpleQueryInjection = '{"condition":"ALSO","rules":[{"id":"price","field":"price","type":"double","operator":"less","value":"10.25"},{"id":"price","field":"price","type":"double","operator":"greater","value":"9.25"}]}';
    protected $json1 = '{
       "condition":"AND",
       "rules":[
          {
             "id":"price",
             "field":"price",
             "type":"double",
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
                   "operator":"begins_with",
                   "value":"Thommas"
                },
                {
                   "id":"name",
                   "field":"name",
                   "type":"string",
                   "operator":"equal",
                   "value":"John Doe"
                }
             ]
          }
       ]
    }';

    protected function getParserUnderTest($fields = null)
    {
        return new QueryBuilderParser($fields);
    }

    /**
     * @return Builder
     */
    protected function createQueryBuilder()
    {
        $pdo = new \PDO('sqlite::memory:');
        $builder = new Builder(new Connection($pdo), new MySQLGrammar(), new MySQLProcessor());

        return $builder;
    }

    protected function makeJSONForInNotInTest($operator = 'in')
    {
        return '{
           "condition":"AND",
           "rules":[
              {
                 "id":"price",
                 "field":"price",
                 "type":"double",
                 "operator":"less",
                 "value":"10.25"
              },
              {
                 "condition":"OR",
                 "rules":[{
                   "id":"category",
                   "field":"category",
                   "type":"integer",
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
