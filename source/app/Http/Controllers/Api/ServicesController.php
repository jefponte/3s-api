<?php

namespace App\Http\Controllers\Api;


use App\Http\Resources\ServicesResource;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ServicesController extends BasicCrudController
{

    public function __construct()
    {
        $this->authorizeResource(Service::class, 'service');
    }
    private $rules = [
        'name' => 'required|max:255'
    ];

    protected function model()
    {
        return Service::class;
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
        return ServiceResource::class;
    }
}