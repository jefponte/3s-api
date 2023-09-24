<?php

namespace App\Http\Controllers\Api;


use App\Http\Resources\OrderStatusLogResource;
use App\Models\OrderStatusLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderStatusLogsController extends BasicCrudController
{
    public function __construct()
    {
        $this->authorizeResource(OrderStatusLog::class, 'orderStatusLog');
    }
    public function show(OrderStatusLog $orderStatusLog)
    {
        return new OrderStatusLogResource($orderStatusLog);
    }
    private $rules = [
        'name' => 'required|max:255'
    ];

    protected function model()
    {
        return OrderStatusLog::class;
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
        return OrderStatusLogResource::class;
    }
}