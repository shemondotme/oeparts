<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Enums\ContactSubjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'email', 'name', 'subject_type', 'order_number', 'oem_number',
        'manufacturer', 'car_model', 'year', 'vin_number',
        'message', 'status', 'otp_verified', 'ip_address',
    ];

    protected $casts = [
        'subject_type' => ContactSubjectType::class,
        'status'       => ContactStatus::class,
        'otp_verified' => 'boolean',
    ];

    public function scopeUnread(Builder $q): Builder
    {
        return $q->where('status', ContactStatus::Unread->value);
    }
}
