<?php

namespace timgws\test;

use Illuminate\Database\Query\Builder;

class QueryBuilderParserTest extends CommonQueryBuilderTests
{
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
        $qb = $this->getParserUnderTest();

        $test = $qb->parse($this->json1, $builder);

        $this->assertEquals('select * where `price` < ? and (`name` LIKE ? or `name` = ?)', $builder->toSql());
    }

    public function testBetterThenTheLastTime()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $json = '{"condition":"AND","rules":[{"id":"anchor_text","field":"anchor_text","type":"string","input":"text","operator":"contains","value":"www"},{"condition":"OR","rules":[{"id":"citation_flow","field":"citation_flow","type":"double","input":"text","operator":"greater_or_equal","value":"30"},{"id":"trust_flow","field":"trust_flow","type":"double","input":"text","operator":"greater_or_equal","value":"30"}]}]}';
        $test = $qb->parse($json, $builder);

        $this->assertEquals('select * where `anchor_text` LIKE ? and (`citation_flow` >= ? or `trust_flow` >= ?)', $builder->toSql());
    }

    public function testCategoryIn()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->makeJSONForInNotInTest(), $builder);

        $this->assertEquals('select * where `price` < ? and (`category` in (?, ?))', $builder->toSql());
    }

    public function testCategoryNotIn()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->makeJSONForInNotInTest(false), $builder);

        $this->assertEquals('select * where `price` < ? and (`category` not in (?, ?))', $builder->toSql());
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
                             "id":"name",
                             "field":"name",
                             "type":"string",
                             "input":"text",
                             "operator":"not_equal",
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
                                },
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

        //$this->assertEquals('select * where `price` < ? AND (`category` in (?, ?) OR (`name` = ? AND (`name` = ?)))', $builder->toSql());
        $this->assertEquals('select * where `price` < ? and (`category` in (?, ?) and (`name` = ? or `name` != ? or (`name` = ? and `name` = ?)))', $builder->toSql());
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
        $v = '"2","3"'.((!$hasTwoValues ? ',"3"' : ''));

        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"between","value":['.$v.']}]}';

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
        if ($hasRules) {
            $rules = '{"condition":"AND","rules":[]}';
        }

        $test = $qb->parse($rules, $builder);

        $this->assertEquals('select *', $builder->toSql());
    }

    public function testNoRulesNoQuery()
    {
        $this->noRulesOrEmptyRules(false);
        $this->noRulesOrEmptyRules(true);
    }

    public function testValueBecomesNull()
    {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_null","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(1, $sqlBindings);
        $this->assertEquals($sqlBindings[0], 'NULL');
    }

    public function testValueBecomesEmpty()
    {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_empty","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(1, $sqlBindings);
        $this->assertEquals($sqlBindings[0], '');
    }

    public function testValueIsValid()
    {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_truely_empty","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(0, $sqlBindings);
    }

    /**
     * @expectedException timgws\QBParseException
     * @expectedMessage Field (price) should not be an array, but it is.
     */
    public function testInputIsNotArray()
    {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"equal","value":["tim","simon"]}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);
    }

    public function testRuleHasInputAndType()
    {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","inputs":"text",'
            .'"operator":"is_truely_empty","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(0, $sqlBindings);
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
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"between","value":"1"}]}';

        if (!$validJSON) {
            $json .= '[';
        }

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
     *
     * @expectedException \timgws\QBParseException
     */
    public function testBetweenOperatorThrowsException()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(false), $builder);
    }

    /**
     * QBP can only accept objects, not arrays.
     *
     * Make sure an exception is thrown if the JSON is valid, but after parsing,
     * we don't get back an object
     *
     * @expectedException \timgws\QBParseException
     */
    public function testArrayDoesNotParse()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse('["test1","test2"]', $builder);
    }

    /**
     * Just a quick test to make sure that QBP::isNested returns false when
     * there is no nested rules inside the rules...
     */
    public function testIsNestedReturnsFalseWhenEmptyNestedRules()
    {
        $some_json_input = '{
       "condition":"AND",
       "rules":[{
             "condition":"OR",
             "rules":[]
          }]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($some_json_input, $builder);
    }

    public function testQueryContains()
    {
        $some_json_input = '{"condition":"AND","rules":[{"id":"name","field":"name","type":"string","input":"text","operator":"contains","value":"Johnny"},{"condition":"AND","rules":[{"id":"category","field":"category","type":"integer","input":"select","operator":"equal","value":"2"},{"id":"in_stock","field":"in_stock","type":"integer","input":"radio","operator":"equal","value":"1"},{"condition":"OR","rules":[{"id":"name","field":"name","type":"string","input":"text","operator":"begins_with","value":"tim"},{"id":"name","field":"name","type":"string","input":"text","operator":"contains","value":"timgws"}]},{"condition":"OR","rules":[{"id":"name","field":"name","type":"string","input":"text","operator":"ends_with","value":"builder"},{"id":"name","field":"name","type":"string","input":"text","operator":"contains","value":"qbp"},{"id":"name","field":"name","type":"string","input":"text","operator":"begins_with","value":"query"}]}]}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($some_json_input, $builder);

        $expected_sql = 'select * where `name` like ? and (`category` = ? and `in_stock` = ? and (`name` like ? or `name` like ?) and (`name` like ? or `name` like ? or `name` like ?))';
        $sql = $builder->toSql();

        $this->assertEquals(strtolower($expected_sql), strtolower($sql));
    }

    /**
     * QBP should successfully parse OR conditions.
     *
     * @throws \timgws\QBParseException
     */
    public function testNestedOrGroup()
    {
        $json = '{"condition":"AND",
        "rules":[
        {"id":"email_pool","field":"email_pool","type":"string","input":"select","operator":"contains","value":["Fundraising"]},
        {"condition":"OR","rules":[
            {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]},
            {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Banbury"]}]}]}';
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);
        $this->assertEquals('select * where `email_pool` LIKE ? and (`geo_constituency` in (?) or `geo_constituency` in (?))',
            $builder->toSql());
    }

    /**
     * @throws \timgws\QBParseException
     * @expectedException \timgws\QBParseException
     * @expectedExceptionMessage Condition can only be one of: 'and', 'or'.
     */
    public function testIncorrectCondition()
    {
        $json = '{"condition":null,"rules":[
            {"condition":"AXOR","rules":[
                {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]},
                {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]},
                {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"is_empty","value":["Aberdeen South"]},
                {"condition":"AXOR","rules":[
                    {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]},
                    {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]}
                ]}
            ]}
        ]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);

        print_r($builder->toSql());
    }
}
