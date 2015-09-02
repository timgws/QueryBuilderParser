<?php

namespace timgws\test;

use timgws\QueryBuilderParser;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Connection as Connection;
use Illuminate\Database\Connectors\MySqlConnector as MySQL;
use Illuminate\Database\Query\Grammars\MySqlGrammar as MySQLGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor as MySQLProcessor;

class JoinSupportingQueryBuilderParserTest extends QueryBuilderParserTest
{
    protected function getParserUnderTest($fields = null)
    {

        return new \timgws\JoinSupportingQueryBuilderParser($fields, $this->getJoinFields());
    }

    private function getJoinFields()
    {
        return [
            'join1' => [
                'from_table' => 'master',
                'from_col' => 'm_col',
                'to_table' => 'subtable',
                'to_col' => 's_col',
                'to_value_column' => 's_value'
            ],
            'join2' => [
              'from_table' => 'master2',
              'from_col' => 'm2_col',
              'to_table' => 'subtable2',
              'to_col' => 's2_col',
              'to_value_column' => 's2_value',
              'not_exists' => true
            ],
          'joinwithclause' => [
            'from_table' => 'master',
            'from_col' => 'm_col',
            'to_table' => 'subtable',
            'to_col' => 's_col',
            'to_value_column' => 's_value',
            'clause' => ['othercol' => 'value']
          ]
        ];
    }

    public function testJoinWhere()
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"double","input":"text","operator":"less","value":"10.25"}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select `1` from `subtable` where subtable.s_col = master.m_col and `s_value` < ?)',
          $builder->toSql());
    }

    public function testJoinNotExistsWhere()
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"double","input":"text","operator":"less","value":"10.25"}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select `1` from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` < ?)',
          $builder->toSql());
    }

    public function testJoinIn()
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"text","input":"select","operator":"in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select `1` from `subtable` where subtable.s_col = master.m_col and `s_value` in (?, ?))',
          $builder->toSql());
    }

    public function testJoinNotExistsIn()
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select `1` from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` in (?, ?))',
          $builder->toSql());
    }

    public function testJoinNotIn()
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"text","input":"select","operator":"not_in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select `1` from `subtable` where subtable.s_col = master.m_col and `s_value` not in (?, ?))',
          $builder->toSql());
    }

    // Urgh, double negative
    public function testJoinNotExistsNotIn()
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"not_in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select `1` from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` not in (?, ?))',
          $builder->toSql());
    }

    public function testJoinBetween()
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"text","input":"select","operator":"between","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select `1` from `subtable` where subtable.s_col = master.m_col and `s_value` between ? and ?)',
          $builder->toSql());
    }

    public function testJoinNotExistsBetween()
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"between","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select `1` from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` between ? and ?)',
          $builder->toSql());
    }

    public function testJoinWithClause()
    {
        $json = '{"condition":"AND","rules":[{"id":"joinwithclause","field":"joinwithclause","type":"text","input":"select","operator":"in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $test = $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select `1` from `subtable` where subtable.s_col = master.m_col and (`othercol` = ?) and `s_value` in (?, ?))',
          $builder->toSql());
    }

}
