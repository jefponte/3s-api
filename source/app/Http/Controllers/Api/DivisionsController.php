<?php

namespace App\Http\Controllers\Api;


use App\Http\Resources\DivisionResource;
use App\Models\Division;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DivisionsController extends BasicCrudController
{

    public function __construct()
    {
        $this->authorizeResource(Division::class, 'division');
    }
    private $rules = [
        'name' => 'required|max:255'
    ];

    protected function model()
    {
        return Division::class;
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
        return DivisionResource::class;
    }
}