<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource{
    public function toArray($request)
    {
        return [
            'type' => $this['type'],
            'created_at' => $this['created_at'],
            'message' => $this['message'],
            'user' => $this['user'],
            'order' => $this['order']
        ];
    }
}
