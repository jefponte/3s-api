<?php

namespace App\Models;

use App\ModelFilters\DivisionFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use Filterable;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'divisions';

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
    protected $fillable = ['name', 'description', 'email'];

    public function modelFilter()
    {
        return $this->provideFilter(DivisionFilter::class);
    }
}
