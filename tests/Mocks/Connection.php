<?php
namespace timgws\test\Mocks;


/**
 * Class Connection
 *
 * Connection, with mocked collection.
 *
 * @package timgws\test\Mocks
 */
class Connection extends \Jenssegers\Mongodb\Connection implements \Jenssegers\Mongodb\Contracts\ConnectionContract
{

    private $mockedCollection;

    /**
     * Get a MongoDB collection.
     *
     * @param  string   $name
     * @return Collection
     */
    public function setCollection($name)
    {
        $this->mockedCollection = \Mockery::mock('MongoCollection');

        return $this->mockedCollection;
    }

    public function getCollection($name)
    {
        if (is_null($this->mockedCollection)) {
            return $this->setCollection($name);
        }

        return $this->mockedCollection;
    }
}
