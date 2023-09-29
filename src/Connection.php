<?php

namespace Maer\MongoQuery;

use MongoDB\Client;

class Connection
{
    /**
     * @var Client
     */
    protected Client $client;

    /**
     * List of databases
     * @var array
     */
    protected array $databases = [];

    /**
     * @var array
     */
    protected array $options = [
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
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }


    /**
     * Get database
     *
     * @param  string $dbName
     * @return Database
     */
    public function database(string $dbName = null): Database
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
    public function __get(string $dbName): Database
    {
        return $this->database($dbName);
    }
}
