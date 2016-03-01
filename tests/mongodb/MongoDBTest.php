<?php
namespace timgws\test\mongodb;

use Illuminate\Database\Capsule\Manager;
use Jenssegers\Mongodb\Query\Builder;
use Jenssegers\Mongodb\Query\Processor as MongoDBProcessor;
use timgws\test\Mocks\Connection as MongoDBConnection;
use timgws\test\CommonQueryBuilderTests;
use MongoDB\Database;

class MongoDBTest extends CommonQueryBuilderTests
{
    private $mockConnection;

    protected function createQueryBuilder()
    {
        $this->mockConnection = new MongoDBConnection([
            'name' => 'mongodb',
            'driver' => 'mongodb',
            'host' => '127.0.0.1',
            'database' => 'unittest',
        ]);
        $builder = new Builder($this->mockConnection, new MongoDBProcessor());

        return $builder;
    }

    private function getOptions()
    {
        return [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array']
        ];
    }

    public function testSimpleQuery()
    {
        $builder = $this->createQueryBuilder()->from('tim');
        $qb = $this->getParserUnderTest();

        $test = $qb->parse($this->simpleQuery, $builder);

        $wheres = [
            'price' => [
                '$lt' => 10.25
            ]
        ];

        $mock = $this->mockConnection->getCollection('');
        $mock->shouldReceive('find')
            ->once()
            ->with($wheres, $this->getOptions())
            ->andReturn(new \ArrayIterator([]));

        $builder->get();

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

        $builder = $this->createQueryBuilder()->from('tim');
        $qb = $this->getParserUnderTest();

        $test = $qb->parse($json, $builder);

        $wheres = json_decode('{"$and":[{"price":{"$lt":"10.25"}},{"$and":[{"category":{"$in":["1","2"]}},{"$or":[{"name":"dgfssdfg"},{"name":{"$ne":"dgfssdfg"}},{"$and":[{"name":"sadf"},{"name":"sadf"}]}]}]}]}', true);

        $mock = $this->mockConnection->getCollection('');
        $mock->shouldReceive('find')
            ->once()
            ->with($wheres, $this->getOptions())
            ->andReturn(new \ArrayIterator([]));

        $builder->get();
    }
}
