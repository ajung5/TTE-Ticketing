<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSurveyResponse extends Model {
    protected $fillable = [
        'ticket_id', 'overall_rating', 'staff_rating', 'clarity_rating', 'speed_rating', 'feedback'
    ];

    public function ticket(): BelongsTo {
        return $this->belongsTo(Ticket::class);
    }
}
