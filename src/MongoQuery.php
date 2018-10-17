<?php namespace Maer\MongoQuery;

use MongoDB\Client;

class MongoQuery
{
    /**
     * @var \MongoDB\Client
     */
    protected $client;

    /**
     * List of databases
     * @var array
     */
    protected $databases = [];

    /**
     * @var array
     */
    protected $options = [
        'dbName'       => null,
        'stringifyIds' => true,
        'indexes'      => [],
        'indexOptions' => [],
    ];


    /**
     * @param Client $client
     * @param array  $options
     */
    public function __construct(Client $client = null, array $options = [])
    {
        if (is_null($client)) {
            $client = new Client('mongodb://localhost:27017');
        }

        $this->client  = $client;
        $this->options = array_merge(
            $this->options,
            $options
        );
    }


    /**
     * Get the mongo instance
     *
     * @return \MongoDB\Client
     */
    public function getInstance()
    {
        return $this->client;
    }


    /**
     * Get a database
     *
     * @param  string $dbName
     * @return Database
     */
    public function database($dbName = null)
    {
        $dbName = $dbName ?: $this->options['dbName'];

        if (!$dbName) {
            throw new \Exception('You need to either configure a default database or pass the database name');
        }

        if (!empty($this->databases[$dbName])) {
            return $this->databases[$dbName];
        }

        return $this->databases[$dbName] = new Database(
            $this->client->{$dbName},
            $dbName,
            $this->options
        );
    }


    /**
     * Get the database
     *
     * @param  string $dbName
     * @return Database
     */
    public function __get($dbName)
    {
        return $this->database($dbName);
    }
}
