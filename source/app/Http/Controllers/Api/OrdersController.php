<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationResource;
use App\Http\Resources\OrderMessageResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderStatusLog;
use Illuminate\Database\Eloquent\Builder;
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

        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));

        $orderMessagesQuery = OrderMessage::with(['order', 'user'])->latest();
        $orderStatusLogsQuery = OrderStatusLog::with(['order', 'user'])->latest();


        $orderMessagesQuery->whereHas('order', function ($q) {
            $q->whereIn('status', [
                'opened',
                'reopened',
                'pending customer response',
                'reserved',
                'pending it resource',
                'in progress'
            ]);
        });

        $orderStatusLogsQuery->whereHas('order', function ($q) {
            $q->whereIn('status', [
                'opened',
                'reopened',
                'pending customer response',
                'reserved',
                'pending it resource',
                'in progress'
            ]);
        });

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
        return $combinedQuery;
    }
}
