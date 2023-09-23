<?php

namespace App\Http\Controllers\Api;


use App\Http\Resources\OrderMessageResource;
use App\Models\OrderMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderMessagesController extends BasicCrudController
{

    public function __construct()
    {
        $this->authorizeResource(OrderMessage::class, 'orderMessage');
    }

    private $rules = [
        'name' => 'required|max:255'
    ];

    protected function model()
    {
        return OrderMessage::class;
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
        return OrderMessageResource::class;
    }
}