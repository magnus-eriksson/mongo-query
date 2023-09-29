<?php

namespace Maer\MongoQuery;

use Closure;
use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Collection as MongoCollection;
use Traversable;

class Collection
{
    /**
     * @var MongoCollection
     */
    protected MongoCollection $collection;

    /**
     * @var array
     */
    protected array $options;

    /**
     * The current query
     * @var array
     */
    protected array $query;


    /**
     * @param MongoCollection $collection
     * @param array $options
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
     * @return MongoCollection
     */
    public function getCollection(): MongoCollection
    {
        return $this->collection;
    }


    /**
     * Add a where clause
     *
     * @param  string $key
     * @param  string $type
     * @param  mixed $value
     * @return self
     */
    public function where(string $key, string $type, mixed $value = null): self
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
     * @return self
     */
    public function orWhere(Closure|string $key, mixed $type = null, mixed $value = null): self
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
     * @return self
     */
    public function andWhere(Closure|string $key, mixed $type = null, mixed $value = null): self
    {
        $this->query['filters']->andWhere($key, $type, $value);

        return $this;
    }


    /**
     * Exists in list
     *
     * @param  string $key
     * @param  mixed $value
     * @return self
     */
    public function inList(string $key, mixed $value): self
    {
        $this->query['filters']->inList($key, $value);

        return $this;
    }


    /**
     * Does not exist in list
     *
     * @param  string $key
     * @param  mixed $value
     * @return self
     */
    public function notInList(string $key, mixed $value): self
    {
        $this->query['filters']->notInList($key, $value);

        return $this;
    }


    /**
     * Select fields for the result
     *
     * @param  array  $fields
     * @return self
     */
    public function select(array $fields): self
    {
        $this->query['options']->select($fields);

        return $this;
    }


    /**
     * Limit the result
     *
     * @param  int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->query['options']->limit($limit);

        return $this;
    }


    /**
     * Skip number of documents
     *
     * @param  int $skip
     * @return self
     */
    public function skip(int $skip): self
    {
        $this->query['options']->skip($skip);

        return $this;
    }


    /**
     * Set order by
     *
     * @param  array  $order
     * @return self
     */
    public function orderBy(array $order): self
    {
        $this->query['options']->orderBy($order);

        return $this;
    }


    /**
     * Get results
     *
     * @return array
     */
    public function get(): array
    {
        list($filters, $options) = $this->getFiltersAndOptions();

        $result = $this->collection->find($filters, $options);

        return $this->processResult($result, []);
    }


    /**
     * Find a document based on one column
     *
     * @param mixed $value
     * @param string $column
     *
     * @return array
     */
    public function find(mixed $value, string|array $column = '_id'): array
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
    public function first(): array
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
    public function pluck(string $field): array
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
    public function insert(array $data): mixed
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
    public function updateOne(array $data): int
    {
        if (!$data) {
            return 0;
        }

        [$filters] = $this->getFiltersAndOptions();

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
    public function updateMany(array $data): int
    {
        if (!$data) {
            return 0;
        }

        [$filters] = $this->getFiltersAndOptions();

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
    public function replaceOne(array $data): int
    {
        if (!$data) {
            return 0;
        }

        [$filters] = $this->getFiltersAndOptions();

        $replaced = $this->collection->replaceOne($filters, $data);

        return $replaced->getModifiedCount();
    }


    /**
     * Delete a document
     *
     * @return bool
     */
    public function deleteOne(): bool
    {
        [$filters] = $this->getFiltersAndOptions();

        $deleted = $this->collection->deleteOne($filters);

        return $deleted->getDeletedCount();
    }


    /**
     * Delete many documents
     *
     * @return bool
     */
    public function deleteMany(): bool
    {
        [$filters] = $this->getFiltersAndOptions();

        $deleted = $this->collection->deleteMany($filters);

        return $deleted->getDeletedCount();
    }


    /**
     * Get total count of matched documents
     *
     * @param  bool $reset
     * @return int
     */
    public function count(bool $reset = true): int
    {
        list($filters, $options) = $this->getFiltersAndOptions(false);

        if ($reset) {
            $this->resetQuery();
        }

        return $this->collection->countDocuments($filters, $options);
    }


    /**
     * Reset the query and clear all filters and options
     *
     * @return self
     */
    public function resetQuery(): self
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
    public function getQuery(): array
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
    protected function processResult($result, $fallback = []): array
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
     * @return bool
     */
    protected function isSingle(array &$data): bool
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
    protected function getFiltersAndOptions($reset = true): array
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
