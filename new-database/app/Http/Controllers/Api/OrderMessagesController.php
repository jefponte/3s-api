<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderMessageResource;
use App\Models\OrderMessage;
use Illuminate\Http\Request;

class OrderMessagesController extends BasicCrudController
{
    public function __construct()
    {
        $this->authorizeResource(OrderMessage::class, 'orderMessage');
    }

    public function show(OrderMessage $orderMessage)
    {
        return new OrderMessageResource($orderMessage);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OrderMessage $orderMessage)
    {
        $orderMessage->update($request->all());

        return response()->json($orderMessage, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(OrderMessage $orderMessage)
    {
        $orderMessage->delete();

        return response()->json(null, 204);
    }

    private $rules = [
        'name' => 'required|max:255',
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
