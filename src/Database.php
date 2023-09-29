<?php

namespace Maer\MongoQuery;

use MongoDB\Database as MongoDatabase;

class Database
{
    /**
     * @var MongoDatabase
     */
    protected MongoDatabase $db;

    /**
     * @var string
     */
    protected string $dbName;

    /**
     * @var array
     */
    protected array $options;


    /**
     * @param MongoDatabase $db
     * @param string $dbName
     * @param array $options
     */
    public function __construct(MongoDatabase $db, string $dbName, array $options)
    {
        $this->db = $db->withOptions([
            'typeMap' => [
                'root'     => 'array',
                'document' => 'array',
                'array'    => 'array',
            ]
        ]);

        $this->options = $options;
        $this->dbName  = $dbName;
    }


    /**
     * Get the database instance
     *
     * @return MongoDatabase
     */
    public function getDatabase(): MongoDatabase
    {
        return $this->db;
    }


    /**
     * Get a collection
     *
     * @param  string $collection
     * @return Collection
     */
    public function collection(string $collectionName): Collection
    {
        return new Collection(
            $this->db->{$collectionName},
            $this->options
        );
    }


    /**
     * Get a collection
     *
     * @param  string $collection
     * @return Collection
     */
    public function __get(string $collectionName): Collection
    {
        return $this->collection($collectionName);
    }
}
