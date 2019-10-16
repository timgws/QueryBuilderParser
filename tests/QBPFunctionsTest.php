<?php
namespace timgws\test;

use Carbon\Carbon;
use timgws\QBParseException;

/**
 * Class QBPFunctionsTests
 *
 * Uses reflection to get to one particularly
 *
 * @package timgws\test
 */
class QBPFunctionsTests extends CommonQueryBuilderTests
{
    protected static function getMethod($name) {
        $class = new \ReflectionClass('\timgws\QueryBuilderParser');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    public function testOperatorNotValid()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("makeQueryWhenArray could not return a value");

        $method = self::getMethod('makeQueryWhenArray');

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $rule = json_decode($this->makeJSONForInNotInTest('contains'));

        $method->invokeArgs($qb, [
            $builder, $rule->rules[1], array('operator' => 'CONTAINS'), array('AND'), 'AND'
        ]);
    }

    public function testOperatorNotValidForNull()
    {
        $this->expectException('\timgws\QBParseException');
        $this->expectExceptionMessage("makeQueryWhenNull was called on an SQL operator that is not null");

        $method = self::getMethod('makeQueryWhenNull');

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $rule = json_decode($this->makeJSONForInNotInTest('contains'));

        $method->invokeArgs($qb, [
            $builder, $rule->rules[1], array('operator' => 'CONTAINS'), array('AND'), 'AND'
        ]);
    }

    public function testDate()
    {
        $method = self::getMethod('convertDatetimeToCarbon');

        $qb = $this->getParserUnderTest();

        /** @var Carbon $carbonDate */
        $carbonDate = $method->invokeArgs($qb, ['2010-12-11']);

        $this->assertEquals('2010', $carbonDate->year);
        $this->assertEquals('12', $carbonDate->month);
    }

    public function testDateArray()
    {
        $method = self::getMethod('convertDatetimeToCarbon');

        $qb = $this->getParserUnderTest();

        /** @var Carbon[] $carbonDate */
        $carbonDates = $method->invokeArgs($qb, [['2010-12-11', '2001-01-02']]);

        $this->assertCount(2, $carbonDates);
        $this->assertEquals('2010', $carbonDates[0]->year);
        $this->assertEquals('2001', $carbonDates[1]->year);
    }
}
