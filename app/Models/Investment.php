<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    use HasFactory;

    protected $fillable = [
        'investor_name',
        'amount',
        'entry_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function capitalExpenses(): HasMany
    {
        return $this->hasMany(CapitalExpense::class);
    }
}
