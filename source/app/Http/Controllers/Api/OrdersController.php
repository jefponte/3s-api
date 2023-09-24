<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderStatusLog;
use Illuminate\Http\Request;

class OrdersController extends BasicCrudController
{

    public function __construct()
    {
        $this->authorizeResource(Order::class, 'order');
    }
    public function show(Order $order)
    {
        return new OrderResource($order);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {

        $order->update($request->all());
        return response()->json($order, 200);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return response()->json(null, 204);
    }
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

    public function notifications(Request $request)
    {
        $this->authorize('viewAny', Order::class);
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));

        $orderMessagesQuery = OrderMessage::with(['order', 'user'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', [
                    OrderStatus::opened()->value,
                    OrderStatus::reopened()->value,
                    OrderStatus::pendingCustomerResponse()->value,
                    OrderStatus::reserved()->value,
                    OrderStatus::pendingResource()->value,
                    OrderStatus::inProgress()->value
                ]);
            })
            ->orderBy('order_messages.id', 'desc');

        $orderStatusLogsQuery = OrderStatusLog::with(['order', 'user'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', [
                    OrderStatus::opened()->value,
                    OrderStatus::reopened()->value,
                    OrderStatus::pendingCustomerResponse()->value,
                    OrderStatus::reserved()->value,
                    OrderStatus::pendingResource()->value,
                    OrderStatus::inProgress()->value
                ]);
            })
            ->orderBy('order_status_logs.id', 'desc');
        $combinedQuery = $this->combineNotifications($orderMessagesQuery, $orderStatusLogsQuery);
        if ($hasFilter) {
            $combinedQuery->filter($request->all());
        }
        $notifications = $combinedQuery->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    private function combineNotifications($orderMessagesQuery, $orderStatusLogsQuery)
    {

        $orderMessagesQuery->selectRaw('*, type as type');
        $orderStatusLogsQuery->selectRaw('*, \'status_log\' as type');
        $combinedQuery = $orderMessagesQuery->union($orderStatusLogsQuery);
        $combinedQuery = $combinedQuery->orderBy('id', 'desc');

        return $combinedQuery;
    }
}
