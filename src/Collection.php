<?php namespace Maer\MongoQuery;

use Closure;
use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Collection as MongoCollection;
use Traversable;

class Collection
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
     * The current query
     * @var array
     */
    protected $query;


    /**
     * @param Collection $collection
     */
    public function __construct(MongoCollection $collection, array $options)
    {
        $this->collection = $collection;
        $this->options    = $options;
        $this->resetQuery();
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
     * Add a where clause
     *
     * @param  string $key
     * @param  string $type
     * @param  string $value
     * @return $this
     */
    public function where($key, $type, $value = null)
    {
        if (func_num_args() == 2) {
            $value = $type;
            $type  = '=';
        }

        $this->query['filters']->where($key, $type, $value);

        return $this;
    }


    /**
     * Add an or where clause
     *
     * @param  Closure|string $key
     * @param  mixed          $type
     * @param  mixed          $value
     * @return $this
     */
    public function orWhere($key, $type = null, $value = null)
    {
        $this->query['filters']->orWhere($key, $type, $value);

        return $this;
    }


    /**
     * Exists in list
     *
     * @param  string $key
     * @param  string $value
     * @return $this
     */
    public function inList($key, $value)
    {
        $this->query['filters']->inList($key, $value);

        return $this;
    }


    /**
     * Does not exist in list
     *
     * @param  array $key
     * @param  array $value
     * @return $this
     */
    public function notInList($key, $value)
    {
        $this->query['filters']->notInList($key, $value);

        return $this;
    }


    /**
     * Select fields for the result
     *
     * @param  array  $fields
     * @return $this
     */
    public function select(array $fields)
    {
        $this->query['options']->select($fields);

        return $this;
    }


    /**
     * Limit the result
     *
     * @param  int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->query['options']->limit($limit);

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
        $this->query['options']->skip($skip);

        return $this;
    }


    /**
     * Get results
     *
     * @return array
     */
    public function get()
    {
        list($filters, $options) = $this->getFiltersAndOptions();

        $result = $this->collection->find($filters, $options);

        return $this->processResult($result, []);
    }


    /**
     * Find a document based on one column
     *
     * @return array
     */
    public function find($value, $column = '_id')
    {
        $this->resetQuery();

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
        $this->query['options']->limit(1);
        $result = $this->get();

        return !empty($result[0]) ? $result[0] : null;
    }


    /**
     * Insert document(s)
     *
     * @param  array  $data
     * @return mixed
     */
    public function insert(array $data)
    {
        $this->resetQuery();

        if (!$data) {
            return false;
        }

        $isSingle = $this->isSingle($data);

        if (!$isSingle) {
            $inserted = $this->collection->insertMany($data);

            if ($inserted->getInsertedCount() == 0) {
                return [];
            }

            $ids = [];
            foreach ($inserted->getInsertedIds() as $id) {
                $ids[] = $this->options['stringifyIds']
                    ? (string)$id
                    : $id;
            }

            return $ids;
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

        list($filters, $options) = $this->getFiltersAndOptions();

        $isSingle = $this->isSingle($data);
        $data     = ['$set' => $data];

        if ($isSingle) {
            $updated = $this->collection->updateOne($filters, $data);
        } else {
            $updated = $this->collection->updateMany($filters, $data);
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

        list($filters, $options) = $this->getFiltersAndOptions();

        $isSingle = $this->isSingle($data);

        if ($isSingle) {
            $updated = $this->collection->replaceOne($filters, $data);
        } else {
            $updated = $this->collection->replaceMany($filters, $data);
        }

        return $updated->getModifiedCount();
    }


    /**
     * Get total count of matched documents
     *
     * @return int
     */
    public function count()
    {
        list($filters, $options) = $this->getFiltersAndOptions(false);

        return $this->collection->count($filters, $options);
    }


    /**
     * Reset the query and clear all filters and options
     *
     * @return $this
     */
    public function resetQuery()
    {
        $this->query['filters'] = new Filters;
        $this->query['options'] = new Options;

        return $this;
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


    /**
     * Get all defined filters and options
     *
     * @param  boolean $reset
     * @return array
     */
    protected function getFiltersAndOptions($reset = true)
    {
        $data = [
            $this->query['filters']->getFilters(),
            $this->query['options']->getOptions(),
        ];

        if ($reset) {
            $this->resetQuery();
        }

        return $data;
    }
}
