<?php
use Maer\MongoQuery\MongoQuery;
use PHPUnit\Framework\TestCase;

class Base extends TestCase
{
    protected $db;

    public function setUp()
    {
        $this->db = (new MongoQuery(Mongo::client()))->mq_test;
    }

    public function tearDown()
    {
        $this->db->getInstance()->drop();
        $this->db = null;
    }
}
