<?php

namespace App\Http\Controllers\Api;


use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrdersController extends BasicCrudController
{

    private $rules = [
        'description' => 'required|max:255'
    ];

    protected function model()
    {
        return Order::class;
    }

    protected function rulesStore()
    {
        return $this->rules;
    }

    protected function rulesUpdate()
    {
        return $this->rules;
    }

    protected function resourceCollection()
    {
        return $this->resource();
    }

    protected function resource()
    {
        return OrderResource::class;
    }
}