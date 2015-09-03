<?php

namespace timgws\test;

use timgws\QueryBuilderParser;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Connection as Connection;
use Illuminate\Database\Connectors\MySqlConnector as MySQL;
use Illuminate\Database\Query\Grammars\MySqlGrammar as MySQLGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor as MySQLProcessor;

class QueryBuilderParserTest extends \PHPUnit_Framework_TestCase
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

    public function testSimpleQuery()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $test = $qb->parse($this->simpleQuery, $builder);

        $this->assertEquals('select * where `price` < ?', $builder->toSql());
    }

    public function testMoreComplexQuery()
    {
        $builder = $this->createQueryBuilder();
        $qb =  $this->getParserUnderTest();

        $test = $qb->parse($this->json1, $builder);

        $this->assertEquals('select * where `price` < ? OR (`name` LIKE ? and `name` = ?)', $builder->toSql());
    }

    public function testBetterThenTheLastTime()
    {
        $builder = $this->createQueryBuilder();
        $qb =  $this->getParserUnderTest();

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
        $qb = $this->getParserUnderTest();

        $qb->parse($this->makeJSONForInNotInTest(), $builder);

        $this->assertEquals('select * where `price` < ? OR (`category` in (?, ?))', $builder->toSql());
    }

    public function testCategoryNotIn()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

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
        $qb = $this->getParserUnderTest();

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
        $qb = $this->getParserUnderTest();

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
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(), $builder);
        $this->assertEquals('select * where `price` between ? and ?', $builder->toSql());
    }

    private function noRulesOrEmptyRules($hasRules = false)
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $rules = '{"condition":"AND"}';
        if ($hasRules)
            $rules = '{"condition":"AND","rules":[]}';

        $test = $qb->parse($rules, $builder);

        $this->assertEquals('select *', $builder->toSql());
    }

    public function testNoRulesNoQuery() {
        $this->noRulesOrEmptyRules(false);
        $this->noRulesOrEmptyRules(true);
    }

    public function testValueBecomesNull() {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            . '{"id":"price","field":"price","type":"double","input":"text",'
            . '"operator":"is_null","value":[' . $v . ']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(1, $sqlBindings);
        $this->assertEquals($sqlBindings[0], 'NULL');
    }

    /**
     * @expectedException \timgws\QBParseException
     * @expectedExceptionMessage Field (price) does not exist in fields list
     */
    public function testFieldNotInittedNotAllowed()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest(array('this_field_is_allowed_but_is_not_present_in_the_json_string'));
        $test = $qb->parse($this->json1, $builder);
    }

    /**
     * @expectedException \timgws\QBParseException
     * @expectedExceptionMessage Field (price) should be an array, but it isn't.
     */
    public function testBetweenMustBeArray($validJSON = true)
    {
        $json = '{"condition":"AND","rules":['
            . '{"id":"price","field":"price","type":"double","input":"text",'
            . '"operator":"between","value":"1"}]}';

        if (!$validJSON)
            $json .= '[';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);
    }

    /**
     * @expectedException \timgws\QBParseException
     * @expectedExceptionMessage JSON parsing threw an error
     */
    public function testThrowExceptionInvalidJSON()
    {
        $this->testBetweenMustBeArray(false /*invalid json*/);
    }

    /**
     * This is a similar test to testBetweenOperator, however, this will throw an exception if
     * there is more then two values for the 'BETWEEN' operator.
     * @expectedException \timgws\QBParseException
     */
    public function testBetweenOperatorThrowsException()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(false), $builder);
    }
}
