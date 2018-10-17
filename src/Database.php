<?php namespace Maer\MongoQuery;

use MongoDB\Database as MongoDatabase;

class Database
{
    /**
     * @var \MongoDB\Client
     */
    protected $db;

    /**
     * @var string
     */
    protected $dbName;

    /**
     * @var array
     */
    protected $options;


    /**
     * @param Client $db
     */
    public function __construct(MongoDatabase $db, $dbName, array $options)
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

        $this->index();
    }


    /**
     * Get the database instance
     *
     * @return \MongoDB\Database
     */
    public function getInstance()
    {
        return $this->db;
    }


    /**
     * Get a collection
     *
     * @param  string $collection
     * @return Query
     */
    public function collection($collectionName)
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
     * @return Query
     */
    public function __get($collectionName)
    {
        return $this->collection($collectionName);
    }


    /**
     * Create indexes
     */
    protected function index()
    {
    }
}
