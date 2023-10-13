<?php

namespace App\ModelFilters;

class OrderFilter extends DefaultModelFilter
{
    protected $sortable = ['created_at'];

    public function search($search)
    {
        $this->where('description', 'LIKE', "%$search%");
    }

    public function status($status)
    {
        $this->whereIn('status', $status);
    }

    /**
     * Related Models that have ModelFilters as well as the method on the ModelFilter
     * As [relationMethod => [input_key1, input_key2]].
     *
     * @var array
     */
    public $relations = [];
}
