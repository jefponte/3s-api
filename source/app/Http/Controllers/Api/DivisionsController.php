<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\DivisionResource;
use App\Models\Division;
use Illuminate\Http\Request;

class DivisionsController extends BasicCrudController
{
    public function __construct()
    {
        $this->authorizeResource(Division::class, 'division');
    }

    public function show(Division $division)
    {
        return new DivisionResource($division);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Division $division)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
            'description' => 'required|max:255',
            'email' => 'required|max:255',
        ]);

        $division->update($request->all());

        return response()->json($division, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Division $division)
    {
        $division->delete();

        return response()->json(null, 204);
    }

    private $rules = [
        'name' => 'required|max:255',
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
