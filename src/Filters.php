<?php namespace Maer\MongoQuery;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Closure;

class Filters
{
    /**
     * @var array
     */
    protected $where = [];

    /**
     * @var array
     */
    protected $or = [];

    /**
     * @var array
     */
    protected $inList = [];


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

        list($key, $value) = $this->parseWhere($key, $type, $value);

        $this->where[$key] = $value;

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
        if ($key instanceof Closure) {
            $filters = new Filters;
            $key($filters);

            $this->or[] = $filters->getFilters();

            return $this;
        }

        if (func_num_args() == 2) {
            $value = $type;
            $type  = '=';
        }

        list($key, $value) = $this->parseWhere($key, $type, $value);
        $this->or[] = [$key => $value];

        return $this;
    }


    /**
     * Exists in list
     *
     * @param  string       $key
     * @param  array|string $value
     * @return $this
     */
    public function inList($key, $value)
    {
        $query = [];
        $key  .= '.$elemMatch';

        if (!is_array($value)) {
            $value = [$value];
        }

        $this->setArray($key, ['$in' => $value], $query);

        $this->inList = array_replace($this->inList, $query);

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
        $query = [];
        $key  .= '.$nin';
        $this->setArray($key, [$value], $query);

        $this->inList = array_replace($this->inList, $query);

        return $this;
    }


    /**
     * Build the query
     *
     * @return array
     */
    public function getFilters()
    {
        $filters = [];

        if ($this->where) {
            $filters = array_merge($filters, $this->where);
        }

        if ($this->inList) {
            $filters = array_merge($filters, $this->inList);
        }

        if ($this->or) {
            $filters['$or'] = $this->or;
        }

        return $filters;
    }


    /**
     * Parse a where clause
     *
     * @param  string $key
     * @param  string $type
     * @param  string $val
     * @return $this
     */
    protected function parseWhere($key, $type, $val = null)
    {
        if (func_num_args() == 2) {
            $val  = $type;
            $type = '=';
        }

        if ($key == 'id') {
            $key = '_id';
        }

        if ($key == '_id') {
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

        return [$key, $val];
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
}
