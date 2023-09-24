<?php

namespace App\Models;

use App\ModelFilters\OrderFilter;
use Illuminate\Database\Eloquent\Model;
use EloquentFilter\Filterable;

class Order extends Model
{
    use Filterable;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'orders';

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
    protected $fillable = [
        'division_id',
        'service_id',
        'division_sig_id',
        'customer_user_id',
        'description',
        'campus',
        'tag',
        'phone_number',
        'division_sig',
        'solution',
        'rating',
        'email',
        'created_at',
        'service_at',
        'finished_at',
        'committed_at',
        'provider_user_id',
        'attachment',
        'place',
        'priority',
        'updated_at',
        'status'
    ];
    protected $enums = [
        'status' => OrderStatus::class,
    ];
    public function division() {
        return $this->belongsTo(Division::class);
    }
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    public function messages()
    {
        return $this->hasMany(OrderMessage::class);
    }
    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class);
    }
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }
    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function modelFilter()
    {
        return $this->provideFilter(OrderFilter::class);
    }

}
