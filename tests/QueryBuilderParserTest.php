<?php

use timgws\QueryBuilderParser;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Connection as Connection;
use Illuminate\Database\Connectors\MySqlConnector as MySQL;
use Illuminate\Database\Query\Grammars\MySqlGrammar as MySQLGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor as MySQLProcessor;

class QueryBuilderParserTest extends PHPUnit_Framework_TestCase
{
    private $simpleQuery = '{"condition":"AND","rules":[{"id":"price","field":"price","type":"double","input":"text","operator":"less","value":"10.25"}]}';
    private $json1 = '{
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

    private function createQueryBuilder()
    {
        $pdo = new PDO('sqlite::memory:');
        $builder = new Builder(new Connection($pdo), new MySQLGrammar(), new MySQLProcessor());

        return $builder;
    }
    /**
     * @covers QBP::construct
     */

    /**
     * @covers QueryBuilderParser::parse
     */
    public function testSimpleQuery()
    {
        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $test = $qb->parse($this->simpleQuery, $builder);

        $this->assertEquals('select * where `price` < ?', $builder->toSql());
    }

    /**
     * @covers QueryBuilderParser::parse
     */
    public function testMoreComplexQuery()
    {
        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $test = $qb->parse($this->json1, $builder);

        $this->assertEquals('select * where `price` < ? OR (`name` LIKE ? and `name` = ?)', $builder->toSql());
    }

    public function testBetterThenTheLastTime()
    {
        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $json = '{"condition":"AND","rules":[{"id":"anchor_text","field":"anchor_text","type":"string","input":"text","operator":"contains","value":"www"},{"condition":"OR","rules":[{"id":"citation_flow","field":"citation_flow","type":"double","input":"text","operator":"greater_or_equal","value":"30"},{"id":"trust_flow","field":"trust_flow","type":"double","input":"text","operator":"greater_or_equal","value":"30"}]}]}';
        $test = $qb->parse($json, $builder);

        $this->assertEquals('select * where `anchor_text` LIKE ? OR (`citation_flow` >= ? and `trust_flow` >= ?)', $builder->toSql());
    }

    private function makeJSONForInNotInTest($is_in = true)
    {

        $operator = "not_in";
        if ($is_in) {
            $operator = "in";
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
                   "operator":"' . $operator . '",
                   "value":[
                      "1", "2"
                   ]}
                 ]
              }
           ]
        }
        ';
    }

    public function testCategoryIn()
    {
        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $qb->parse($this->makeJSONForInNotInTest(), $builder);

        $this->assertEquals('select * where `price` < ? OR (`category` in (?, ?))', $builder->toSql());
    }

    public function testCategoryNotIn()
    {
        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $qb->parse($this->makeJSONForInNotInTest(false), $builder);

        $this->assertEquals('select * where `price` < ? OR (`category` not in (?, ?))', $builder->toSql());
    }

    public function testManyNestedQuery()
    {
        // $('#builder-basic').queryBuilder('setRules', /** This object */);
        $json = '{
           "condition":"AND",
           "rules":[
              {
                 "id":"price",
                 "field":"price",
                 "type":"double",
                 "input":"text",
                 "operator":"less",
                 "value":"10.25"
              }, {
                 "condition":"AND",
                 "rules":[
                    {
                       "id":"category",
                       "field":"category",
                       "type":"integer",
                       "input":"select",
                       "operator":"in",
                       "value":[
                          "1", "2"
                       ]
                    }, {
                       "condition":"OR",
                       "rules":[
                          {
                             "id":"name",
                             "field":"name",
                             "type":"string",
                             "input":"text",
                             "operator":"equal",
                             "value":"dgfssdfg"
                          }, {
                             "condition":"AND",
                             "rules":[
                                {
                                   "id":"name",
                                   "field":"name",
                                   "type":"string",
                                   "input":"text",
                                   "operator":"equal",
                                   "value":"sadf"
                                }
                             ]
                          }
                       ]
                    }
                 ]
              }
           ]
        }';

        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $qb->parse($json, $builder);

        $this->assertEquals('select * where `price` < ? AND (`category` in (?, ?) OR (`name` = ? AND (`name` = ?)))', $builder->toSql());
        //$this->assertEquals('/* This test currently fails. This should be fixed. */', $builder->toSql());

    }

    /**
     * @expectedException \timgws\QBParseException
     */
    public function testJSONParseException()
    {
        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $qb->parse('{}]JSON', $builder);
    }

    private function getBetweenJSON($hasTwoValues = true)
    {
        $v = '"2","3"' . ((!$hasTwoValues ? ',"3"' : ''));

        $json = '{"condition":"AND","rules":['
            . '{"id":"price","field":"price","type":"double","input":"text",'
            . '"operator":"between","value":[' . $v . ']}]}';

        return $json;
    }

    public function testBetweenOperator()
    {
        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $qb->parse($this->getBetweenJSON(), $builder);
        $this->assertEquals('select * where `price` between ? and ?', $builder->toSql());
    }

    /**
     * This is a similar test to testBetweenOperator, however, this will throw an exception if
     * there is more then two values for the 'BETWEEN' operator.
     * @expectedException \timgws\QBParseException
     */
    public function testBetweenOperatorThrowsException()
    {
        $builder = $this->createQueryBuilder();
        $qb = new QueryBuilderParser();

        $qb->parse($this->getBetweenJSON(false), $builder);
    }
}
