<?php namespace Maer\MongoQuery;

class Options
{
    /**
     * @var array
     */
    protected $orderBy = [];

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $skip;

    /**
     * Columns to select
     * @var array
     */
    protected $select = [];

    /**
     * Set order by
     *
     * @param  array  $order
     * @return $this
     */
    public function orderBy($order)
    {
        if (is_string($order)) {
            $order = [$order => 'asc'];
        }

        $result = [];
        foreach ($order as $col => $dir) {
            if (is_int($col)) {
                $col = $dir;
                $dir = 1;
            }

            $dir = strtolower($dir);
            $dir = $dir == -1 || $dir == 'desc' ? -1 : 1;
            $this->orderBy[$col] = $dir;
        }

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
        if (in_array('*', $fields)) {
            $this->select = [];
            return $this;
        }

        $this->select = array_merge($this->select, $fields);

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
     * Build the query
     *
     * @return array
     */
    public function getOptions()
    {
        $options = [];

        if ($this->orderBy) {
            $options['sort'] = $this->orderBy;
        }

        if (is_int($this->limit) && $this->limit > 0) {
            $options['limit'] = $this->limit;
        }

        if (is_int($this->skip) && $this->skip > 0) {
            $options['skip'] = $this->skip;
        }

        if ($this->select) {
            if (empty($options['projection'])) {
                $options['projection'] = [];
            }

            foreach ($this->select as $field) {
                $options['projection'][$field] = 1;
            }
        }

        return $options;
    }
}
