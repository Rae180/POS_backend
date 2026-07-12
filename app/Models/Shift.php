<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'opened_by',
        'closed_by',
        'opening_float',
        'closing_cash_counted',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opening_float' => 'decimal:2',
        'closing_cash_counted' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOpen(): bool
    {
        return is_null($this->closed_at);
    }

    /**
     * All payments recorded during this shift (cash AND card, until
     * payment_method exists to filter — see note in the chat).
     */
    public function totalPayments(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function totalCashPayments(): float
    {
        return (float) $this->payments()
            ->where('payment_method', \App\Models\Payment::METHOD_CASH)
            ->sum('amount');
    }

    public function totalCardPayments(): float
    {
        return (float) $this->payments()
            ->where('payment_method', \App\Models\Payment::METHOD_CARD)
            ->sum('amount');
    }

    public function expectedCash(): float
    {
        return round((float) $this->opening_float + $this->totalCashPayments(), 2);
    }

    public function variance(): ?float
    {
        if (is_null($this->closing_cash_counted)) {
            return null;
        }
        return round((float) $this->closing_cash_counted - $this->expectedCash(), 2);
    }

    public static function current(): ?self
    {
        return static::whereNull('closed_at')->latest('opened_at')->first();
    }
}