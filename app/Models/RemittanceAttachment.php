<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemittanceAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'remittance_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(Remittance::class);
    }
}
