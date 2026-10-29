<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    protected $fillable = ['ticket_id', 'actor_id', 'action', 'note'];

    public function actor(): BelongsTo
    {
        // Tampilkan nama petugas lama walaupun akunnya dinonaktifkan/dihapus.
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
