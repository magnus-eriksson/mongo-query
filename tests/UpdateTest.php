<?php
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Maer\MongoQuery\Query
 */
class UpdateTest extends Base
{
    public function testUpdate()
    {
        $query = $this->db->test;

        $id = $query->insert([
            'name' => 'foo',
            'test' => 'not-modified',
        ]);

        $query->where('id', $id)->update([
            'name' => 'bar',
        ]);

        $query  = $this->db->test;
        $result = $query->find($id);

        $this->assertEquals('bar', $result['name']);
        $this->assertEquals('not-modified', $result['test']);
    }

    public function testReplace()
    {
        $query = $this->db->test;

        $id = $query->insert([
            'name' => 'foo',
            'test' => 'not-modified',
        ]);

        $query->where('id', $id)->replace([
            'name' => 'bar',
        ]);

        $query  = $this->db->test;
        $result = $query->find($id);

        $this->assertEquals('bar', $result['name']);
        $this->assertFalse(array_key_exists('test', $result));
    }
}
