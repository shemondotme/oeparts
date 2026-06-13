<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named, per-admin dashboard canvas.
 *
 * layout: list of {id, x, y, w, h} entries — `id` is a widget id from
 * WidgetPreferenceService::WIDGETS; presence in the layout means the widget
 * is shown on this dashboard.
 */
class AdminDashboard extends Model
{
    protected $fillable = ['admin_id', 'name', 'slug', 'layout', 'is_default'];

    protected $casts = [
        'layout' => 'array',
        'is_default' => 'boolean',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
