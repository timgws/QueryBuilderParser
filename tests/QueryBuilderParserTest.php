<?php

namespace timgws\test;

use Illuminate\Database\Query\Builder;

class QueryBuilderParserTest extends CommonQueryBuilderTests
{
    public function testSimpleEmptyQuery()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $test = $qb->parse("{}", $builder);
        $this->assertEquals('select *', $builder->toSql());
    }

    public function testSimpleQuery()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $test = $qb->parse($this->simpleQuery, $builder);

        $this->assertEquals('select * where `price` < ?', $builder->toSql());
    }

    public function testSimpleQueryNoInjection()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->expectException('timgws\QBParseException');
        $this->expectExceptionMessage("Condition can only be one of");

        $test = $qb->parse($this->simpleQueryInjection, $builder);

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

        $qb->parse($this->makeJSONForInNotInTest('not_in'), $builder);

        $this->assertEquals('select * where `price` < ? and (`category` not in (?, ?))', $builder->toSql());
    }

    public function testCategoryInvalidArray()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("should not be an array, but it is");

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->makeJSONForInNotInTest('contains'), $builder);

        $this->assertEquals('select * where `price` < ?', $builder->toSql());
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

    public function testJSONParseException()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("JSON parsing threw an error");

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse('{}]JSON', $builder);
    }

    private function getBetweenJSON($hasTwoValues = true, $isnot = false)
    {
        $v = '"2","3"'.((!$hasTwoValues ? ',"3"' : ''));
        $o = ( $isnot ? "not_" : "" ) . 'between';

        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"' . $o . '","value":['.$v.']}]}';

        return $json;
    }

    public function testBetweenOperator()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(), $builder);
        $this->assertEquals('select * where `price` between ? and ?', $builder->toSql());
    }

    public function testNotBetweenOperator()
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(true, true), $builder);
        $this->assertEquals('select * where `price` not between ? and ?', $builder->toSql());
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
        $this->assertCount(0, $sqlBindings);
        $this->assertEquals('select * where `price` is null', $builder->toSql());
    }

    public function testBothValuesBecomesNull()
    {
        $v = '1.23';
        $json = '{"condition":"OR","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_null","value":['.$v.']},{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_not_null","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(0, $sqlBindings);
        $this->assertEquals('select * where `price` is null or `price` is not null', $builder->toSql());
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

    private function beginsOrEndsWithTest($begins = 'begins', $not = false)
    {
        $operator = (!$not ? '' : 'not_') . $begins . '_with';
        $like = $not ? 'NOT LIKE' : 'LIKE';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $json = '{"condition":"AND","rules":[{"id":"anchor_text","field":"anchor_text","type":"string","input":"text","operator":"' . $operator . '","value":"www"}]}';
        $test = $qb->parse($json, $builder);

        $bindings_are = [];
        if ($begins == 'begins') {
            $bindings_are = ['www%'];
        } else {
            $bindings_are = ['%www'];
        }

        $this->assertEquals('select * where `anchor_text` ' . $like . ' ?', $builder->toSql());
        $this->assertEquals($bindings_are, $builder->getBindings());
    }

    public function testBeginsWith()
    {
        $this->beginsOrEndsWithTest('begins', false);
    }

    public function testBeginsNotWith()
    {
        $this->beginsOrEndsWithTest('begins', true);
    }

    public function testEndsWith()
    {
        $this->beginsOrEndsWithTest('ends', false);
    }

    public function testEndsNotWith()
    {
        $this->beginsOrEndsWithTest('ends', true);
    }

    public function testInputIsNotArray()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("should not be an array, but it is");

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

    public function testFieldNotInittedNotAllowed()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("does not exist in fields list");

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest(array('this_field_is_allowed_but_is_not_present_in_the_json_string'));
        $test = $qb->parse($this->json1, $builder);
    }

    public function testBetweenMustBeArray()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("should be an array, but it isn't");

        $json = $this->_buildJsonTestForBetween(true);

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);
    }

    public function testThrowExceptionInvalidJSON()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("JSON parsing threw an error");

        $json = $this->_buildJsonTestForBetween(false /*invalid json */);

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $test = $qb->parse($json, $builder);
    }

    /**
     * Build a JSON string
     *
     * @see testBetweenMustBeArray
     * @see testThrowExceptionInvalidJSON
     * @param $validJSON
     * @return string
     */
    private function _buildJsonTestForBetween($validJSON) {
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"between","value":"1"}]}';

        if (!$validJSON) {
            $json .= '[';
        }

        return $json;
    }

    /**
     * This is a similar test to testBetweenOperator, however, this will throw an exception if
     * there is more then two values for the 'BETWEEN' operator.
     */
    public function testBetweenOperatorThrowsException()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("should be an array with only two items.");

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(false), $builder);
    }

    /**
     * @see testBetweenOperatorThrowsException
     */
    public function testNotBetweenOperatorThrowsException()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("should be an array with only two items.");

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(false, true), $builder);
    }

    /**
     * QBP can only accept objects, not arrays.
     *
     * Make sure an exception is thrown if the JSON is valid, but after parsing,
     * we don't get back an object
     */
    public function testArrayDoesNotParse()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("The query is not valid JSON");

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
        $this->assertEquals('select *', $builder->toSql());
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
     * Null check is not using isnull function instead checking = 'NULL'
     *
     * Tests for #10
     */
    public function testIsNullBecomesNullInQuery()
    {
        $json = '{
            "condition": "OR",
                "rules": [
                {
                    "id": "t_o",
                    "field": "t_o",
                    "type": "integer",
                    "input": "text",
                    "operator": "equal",
                    "value": "0"
                },
                {
                    "id": "t_o",
                    "field": "t_o",
                    "type": "integer",
                    "input": "text",
                    "operator": "is_null",
                    "value": null
                }
                ]
        }';
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);
        $this->assertEquals('select * where `t_o` = ? or `t_o` is null',
            $builder->toSql());
        $bindings_are = ['0'];
        $this->assertEquals($bindings_are, $builder->getBindings());
    }

    /**
     * @throws \timgws\QBParseException
     */
    public function testIncorrectCondition()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("Condition can only be one of: 'and', 'or'");

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
