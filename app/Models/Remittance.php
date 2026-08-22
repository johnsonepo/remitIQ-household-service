<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remittance extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'household_id',
        'transfer_provider_id',
        'amount_sent',
        'sent_currency_code',
        'amount_received',
        'received_currency_code',
        'exchange_rate',
        'rate_source',
        'sent_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_sent' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'exchange_rate' => 'decimal:10',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TransferProvider::class, 'transfer_provider_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RemittanceAttachment::class);
    }
}
