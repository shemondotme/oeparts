<?php

namespace App\Models;

use App\Enums\EmailTemplate;
use App\Enums\LogStatus;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'to_email', 'subject', 'template_type',
        'related_id', 'related_type', 'status', 'error_message', 'sent_at',
    ];

    protected $casts = [
        'template_type' => EmailTemplate::class,
        'status'        => LogStatus::class,
        'sent_at'       => 'datetime',
    ];
}
