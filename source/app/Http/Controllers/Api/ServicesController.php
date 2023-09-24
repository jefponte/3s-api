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

    public function show(Service $service)
    {
        return new ServiceResource($service);
    }
        /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Service $service)
    {
        $this->validate($request, [
			'name' => 'required|max:255'
		]);

        $service->update($request->all());
        return response()->json($service, 200);
    }
        /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json(null, 204);
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