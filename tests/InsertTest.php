<?php
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Maer\MongoQuery\Query
 */
class InsertTest extends Base
{
    public function testInsert()
    {
        $query = $this->db->test;

        $id = $query->insert(['name' => 'foo']);

        $this->assertInternalType('string', $id);
        $this->assertGreaterThanOrEqual(24, strlen($id));
        $this->assertEquals(1, $query->count());
    }

    public function testInsertMany()
    {
        $this->db->test->getInstance()->drop();
        $query = $this->db->test;

        $ids = $query->insert([
            [
                'name' => 'foo'
            ],
            [
                'name' => 'bar',
            ]
        ]);

        $this->assertEquals(2, count($ids));
    }
}
