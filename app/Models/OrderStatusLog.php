<?php

namespace App\Models;

use App\ModelFilters\OrderStatusLogFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

class OrderStatusLog extends Model
{
    use Filterable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'order_status_logs';

    /**
     * The database primary key value.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['id', 'order_id', 'status', 'message', 'user_id'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function modelFilter()
    {
        return $this->provideFilter(OrderStatusLogFilter::class);
    }
}
