<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'method', 'amount', 'received_amount', 'change_amount', 'status', 'paid_at', 'confirmed_by'])]
class Payment extends Model
{
    public const METHOD_CASH = 'CASH';

    public const STATUS_PAID = 'PAID';

    /**
     * Get the sale associated with the payment.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the user who confirmed the payment.
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
