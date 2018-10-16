<?php
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Maer\MongoQuery\MongoQuery
 */
class GetTest extends Base
{
    public function setUp()
    {
        parent::setUp();

        $inserted = $this->db->test->insert([
            [
                '_id'   => 123,
                'name'  => 'custom-id',
                'test'  => 'three',
                'test2' => 'test3',
                'num'   => 1,
                'list'  => [
                    'hello',
                    'world',
                ],
            ],
            [
                'name'  => 'foo',
                'test'  => 'one',
                'test2' => 'test1',
                'num'   => 2,
                'list'  => [
                    'hello',
                    'world',
                ],
            ],
            [
                'name'  => 'bar',
                'test'  => 'one',
                'test2' => 'different',
                'num'   => 3,
                'list'  => [
                    'test',
                    'world',
                ],
            ],
            [
                'name'  => 'example',
                'test'  => 'two',
                'test2' => 'test2',
                'num'   => 4,
                'list'  => [
                    'unique',
                    'values',
                ],
            ],
        ]);

        if (!$inserted) {
            throw new Exception('Insert for GetTest failed');
        }
    }

    public function testGet()
    {
        $query  = $this->db->test;
        $result = $query->get();

        $this->assertEquals(4, count($result));
    }

    public function testFind()
    {
        $query  = $this->db->test;
        $result = $query->find(123);

        $this->assertEquals('custom-id', $result['name'] ?? null);

        $query  = $this->db->test;
        $result = $query->find('foo', 'name');

        $this->assertEquals(2, $result['num']);
    }

    public function testFirst()
    {
        $query  = $this->db->test;
        $result = $query->orderBy(['name' => 'asc'])->first();

        $this->assertEquals('bar', $result['name'] ?? null);
    }

    public function testSelect()
    {
        $query  = $this->db->test;
        $result = $query->select(['list'])->first();

        // This should only contain the "list" and "_id" fields
        $this->assertEquals(2, count($result));
    }
}
