<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderStatusLogResource;
use App\Models\OrderStatusLog;
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

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OrderStatusLog $orderStatusLog)
    {
        $orderStatusLog->update($request->all());

        return response()->json($orderStatusLog, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(OrderStatusLog $orderStatusLog)
    {
        $orderStatusLog->delete();

        return response()->json(null, 204);
    }

    private $rules = [
        'name' => 'required|max:255',
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
