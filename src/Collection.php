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
     * Add an and where clause
     *
     * @param  Closure|string $key
     * @param  mixed          $type
     * @param  mixed          $value
     * @return $this
     */
    public function andWhere($key, $type = null, $value = null)
    {
        $this->query['filters']->andWhere($key, $type, $value);

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
     * Set order by
     *
     * @param  array  $order
     * @return $this
     */
    public function orderBy($order)
    {
        $this->query['options']->orderBy($order);

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
     * Pluck a specific field from the matched documents
     *
     * @param  string $field
     * @return array
     */
    public function pluck($field)
    {
        // Remove any other select-values
        $this->select(['*']);
        $this->select([$field]);
        $result = $this->get();

        if (!empty($result[0]) && array_key_exists($field, $result[0])) {
            $result = array_unique(array_column($result, $field));
        }

        return $result;
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
     * Update one document
     *
     * @param  array $data
     * @return int
     */
    public function updateOne(array $data)
    {
        if (!$data) {
            return 0;
        }

        list($filters, $options) = $this->getFiltersAndOptions();

        $updated = $this->collection->updateOne($filters, [
            '$set' => $data
        ]);

        return $updated->getModifiedCount();
    }


    /**
     * Update many documents
     *
     * @param  array $data
     * @return int
     */
    public function updateMany(array $data)
    {
        if (!$data) {
            return 0;
        }

        list($filters, $options) = $this->getFiltersAndOptions();

        $updated = $this->collection->updateMany($filters, [
            '$set' => $data
        ]);

        return $updated->getModifiedCount();
    }


    /**
     * Replace one document
     *
     * @param  array $data
     * @return int
     */
    public function replaceOne(array $data)
    {
        if (!$data) {
            return 0;
        }

        list($filters, $options) = $this->getFiltersAndOptions();

        $replaced = $this->collection->replaceOne($filters, $data);

        return $replaced->getModifiedCount();
    }


    /**
     * Replace many documents
     *
     * @param  array $data
     * @return int
     */
    public function replaceMany(array $data)
    {
        if (!$data) {
            return 0;
        }

        list($filters, $options) = $this->getFiltersAndOptions();

        $replaced = $this->collection->replaceMany($filters, $data);

        return $replaced->getModifiedCount();
    }


    /**
     * Delete a document
     *
     * @return bool
     */
    public function deleteOne()
    {
        list($filters, $options) = $this->getFiltersAndOptions();

        $deleted = $this->collection->deleteOne($filters);

        return $deleted->getDeletedCount();
    }


    /**
     * Delete many documents
     *
     * @return bool
     */
    public function deleteMany()
    {
        list($filters, $options) = $this->getFiltersAndOptions();

        $deleted = $this->collection->deleteMany($filters);

        return $deleted->getDeletedCount();
    }


    /**
     * Get total count of matched documents
     *
     * @param  bool $reset
     * @return int
     */
    public function count($reset = true)
    {
        list($filters, $options) = $this->getFiltersAndOptions(false);

        if ($reset) {
            $this->resetQuery();
        }

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
     * Get the query and options as array
     *
     * @return array
     */
    public function getQuery()
    {
        return $this->getFiltersAndOptions(false);
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
