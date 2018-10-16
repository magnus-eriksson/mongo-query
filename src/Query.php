<?php namespace Maer\MongoQuery;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Collection;
use Traversable;
use Exception;

class Query
{
    /**
     * @var \MongoDB\Collection
     */
    protected $collection;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $orderBy = [];

    /**
     * @var array
     */
    protected $where = [];

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $skip;

    /**
     * @var array
     */
    protected $or = [];

    /**
     * @var array
     */
    protected $inList = [];

    /**
     * @var array
     */
    protected $select = [];

    /**
     * @param Collection $collection
     */
    public function __construct(Collection $collection, array $options)
    {
        $this->collection = $collection;
        $this->options    = $options;
    }


    /**
     * Get the collection instance
     *
     * @return \MongoDB\Collection
     */
    public function getInstance()
    {
        return $this->collection;
    }


    /**
     * Return ids as strings instead of mongo objects
     *
     * @param  boolean $state
     * @return $this
     */
    public function stringifyIds($state = true)
    {
        $this->options['stringifyIds'] = (bool)$state;

        return $this;
    }


    /**
     * Set order by
     *
     * @param  array  $order
     * @return $this
     */
    public function orderBy($order)
    {
        if (is_string($order)) {
            $order = [$order];
        }

        if (!is_array($order)) {
            return $this;
        }

        $result = [];
        foreach ($order as $col => $dir) {
            if (is_int($col)) {
                $this->orderBy[$dir] = 1;
                continue;
            }

            $dir = strtolower($dir);
            $dir = $dir == -1 || $dir == 'desc' ? -1 : 1;
            $this->orderBy[$col] = $dir;
        }

        return $this;
    }


    public function count()
    {
        list($query, $options) = $this->buildQuery();

        return $this->collection->count($query, $options);
    }


    /**
     * Select fields for the result
     *
     * @param  array  $fields
     * @return $this
     */
    public function select(array $fields)
    {
        $this->select = array_merge($this->select, $fields);
        $this->select = array_unique($this->select);
        $this->select = array_filter($this->select, function ($el) {
            return ($el && $el != '*');
        });

        return $this;
    }


    /**
     * Add a where clause
     *
     * @param  string $col
     * @param  string $type
     * @param  string $val
     * @return $this
     */
    public function where($col, $type, $val = null)
    {
        list($col, $val) = func_num_args() == 2
            ? $this->parseWhere($col, $type)
            : $this->parseWhere($col, $type, $val);

        $this->where[$col] = $val;

        return $this;
    }


    /**
     * Add a or where clause
     *
     * @param  string $col
     * @param  string $type
     * @param  string $val
     * @return $this
     */
    public function orWhere($col, $type, $val = null)
    {
        list($col, $val) = func_num_args() == 2
            ? $this->parseWhere($col, $type)
            : $this->parseWhere($col, $type, $val);

        $this->or[] = [$col => $val];

        return $this;
    }


    /**
     * Exists in list
     *
     * @param  string $col
     * @param  string $value
     * @return $this
     */
    public function inList($col, $value)
    {
        $query = [];
        $col  .= '.$elemMatch';
        $this->setArray($col, ['$eq' => $value], $query);

        $this->inList = array_replace($this->inList, $query);

        return $this;
    }


    /**
     * Does not exist in list
     *
     * @param  array $col
     * @param  array $value
     * @return $this
     */
    public function notInList($col, $value)
    {
        $query = [];
        $col  .= '.$nin';
        $this->setArray($col, [$value], $query);

        $this->inList = array_replace($this->inList, $query);

        return $this;
    }


    /**
     * Add a param to an array
     *
     * @param string $key
     * @param string $value
     * @param string &$arr
     */
    protected function setArray($key, $value, &$arr)
    {
        $arr = [];
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($arr[$k]) || !is_array($arr[$k])) {
                $arr[$k] = [];
            }

            $arr =& $arr[$k];
        }

        $arr[array_shift($keys)] = $value;
    }


    /**
     * Insert document(s)
     *
     * @param  array  $data
     * @return mixed
     */
    public function insert(array $data)
    {
        if (!$data) {
            return false;
        }

        $isSingle = $this->isSingle($data);

        if (!$isSingle) {
            $inserted = $this->collection->insertMany($data);
            return $inserted->getInsertedCount();
        }

        $inserted = $this->collection->insertOne($data);

        if ($inserted->getInsertedCount() == 1) {
            return $this->options['stringifyIds']
                ? (string)$inserted->getInsertedId()
                : $inserted->getInsertedId();
        }

        return false;
    }


    /**
     * Update document(s)
     *
     * @param  array $data
     * @return int
     */
    public function update(array $data)
    {
        if (!$data) {
            return 0;
        }

        list($query, $options) = $this->buildQuery();

        $isSingle = $this->isSingle($data);
        $data     = ['$set' => $data];

        if ($isSingle) {
            $updated = $this->collection->updateOne($query, $data);
        } else {
            $updated = $this->collection->updateMany($query, $data);
        }

        return $updated->getModifiedCount();
    }


    /**
     * Replace document(s)
     *
     * @param  array $data
     * @return int
     */
    public function replace(array $data)
    {
        if (!$data) {
            return 0;
        }

        list($query, $options) = $this->buildQuery();

        $isSingle = $this->isSingle($data);

        if ($isSingle) {
            $updated = $this->collection->replaceOne($query, $data);
        } else {
            $updated = $this->collection->replaceMany($query, $data);
        }

        return $updated->getModifiedCount();
    }


    /**
     * Limit the result
     *
     * @param  int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }


    /**
     * Skip number of documents
     *
     * @param  int $skip
     * @return $this
     */
    public function skip($skip)
    {
        $this->skip = $skip;

        return $this;
    }


    /**
     * Find a document based on one column
     *
     * @return array
     */
    public function find($value, $column = '_id')
    {
        if ($column == 'id') {
            $column = '_id';
        }

        if ($column == '_id' && is_string($value)) {
            try {
                $value = new ObjectId($value);
            } catch (\Exception $e) {
                $value = 'invalid-id';
            }
        }

        $query = [
            $column => $value,
        ];

        $result = $this->collection->findOne($query);
        $result = $this->processResult($result);

        return $result;
    }


    /**
     * Get the first matched document
     *
     * @return array
     */
    public function first()
    {
        $this->limit(1);
        $result = $this->get();

        return !empty($result[0]) ? $result[0] : null;
    }


    /**
     * Get results
     *
     * @return array
     */
    public function get()
    {
        list($query, $options) = $this->buildQuery();

        $result = $this->collection->find($query, $options);

        return $this->processResult($result, []);
    }


    /**
     * Get filter
     *
     * @return array
     */
    public function getFilter()
    {
        list($query, $options) = $this->buildQuery();

        return $query;
    }


    /**
     * Parse a where clause
     *
     * @param  string $col
     * @param  string $type
     * @param  string $val
     * @return $this
     */
    protected function parseWhere($col, $type, $val = null)
    {
        if (func_num_args() == 2) {
            $val  = $type;
            $type = '=';
        }

        if ($col == 'id') {
            $col = '_id';
        }

        if ($col == '_id') {
            try {
                $val = new ObjectId($val);
            } catch (Exception $e) {
                $val = 'invalid-id';
            }
        }

        if ($type == '!=') {
            $val = ['$ne' => $val];
        }

        if ($type == '>') {
            $val = ['$gt' => $val];
        }

        if ($type == '<') {
            $val = ['$lt' => $val];
        }

        if ($type == '!>') {
            $val = ['$not' => ['$gt' => $val]];
        }

        if ($type == '!<') {
            $val = ['$not' => ['$lt' => $val]];
        }

        if ($type == '*.') {
            $val = preg_quote($val, '/');
            $val = new Regex("^{$val}", 'i');
        }

        if ($type == '.*') {
            $val = preg_quote($val, '/');
            $val = new Regex("{$val}$", 'i');
        }

        if ($type == '*') {
            $val = preg_quote($val, '/');
            $val = new Regex("{$val}", 'i');
        }

        return [$col, $val];
    }


    /**
     * Process the result
     *
     * @param  array $result
     * @param  array $fallback
     * @return array
     */
    protected function processResult($result, $fallback = [])
    {
        if ($result instanceof Traversable) {
            $result = iterator_to_array($result);
        }

        if (empty($result)) {
            return $fallback;
        }

        if ($this->options['stringifyIds']) {
            $result = array_map(function ($value) {
                if (is_array($value) && !empty($value['_id'])) {
                    $value['_id'] = (string)$value['_id'];
                    return $value;
                }

                if ($value instanceof ObjectId) {
                    $value = (string)$value;
                }

                return $value;
            }, $result);
        }

        return $result;
    }


    /**
     * Build the query
     *
     * @return array
     */
    protected function buildQuery()
    {
        $query = [
            'query'   => [],
            'options' => [],
        ];

        if ($this->orderBy) {
            $query['options']['sort'] = $this->orderBy;
        }

        if ($this->where) {
            $query['query'] = array_merge(
                $query['query'],
                $this->where
            );
        }

        if (is_int($this->limit) && $this->limit > 0) {
            $query['options']['limit'] = $this->limit;
        }

        if (is_int($this->skip) && $this->skip > 0) {
            $query['options']['skip'] = $this->skip;
        }

        if ($this->inList) {
            $query['query'] = array_merge(
                $query['query'],
                $this->inList
            );
        }

        if ($this->select) {
            if (empty($query['options']['projection'])) {
                $query['options']['projection'] = [];
            }

            foreach ($this->select as $field) {
                $query['options']['projection'][$field] = 1;
            }
        }

        if ($this->or) {
            $query['query']['$or'] = $this->or;
        }

        return array_values($query);
    }


    /**
     * Check if the data contains a single document or many
     *
     * @param  array $data
     * @return boolean
     */
    protected function isSingle(array &$data)
    {
        reset($data);
        return !is_int(key($data));
    }
}
