<?php

namespace App\Http\Controllers\Api;


use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UsersController extends BasicCrudController
{

    private $rules = [
        'name' => 'required|max:255'
    ];

    protected function model()
    {
        return User::class;
    }
    public function show($id)
    {
        $user = User::with('division')->findOrFail($id);

        return new UserResource($user);
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
        return UserResource::class;
    }
}