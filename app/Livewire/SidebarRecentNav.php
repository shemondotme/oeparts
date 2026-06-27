<?php

namespace App\Livewire;

use App\Services\AdminNavService;
use Livewire\Component;

class SidebarRecentNav extends Component
{
    public function render()
    {
        $admin = auth('admin')->user();

        return view('livewire.sidebar-recent-nav', [
            // Validation logic lives in AdminNavService::validRecent() so the
            // topbar command palette's idle state can reuse it identically —
            // see CLAUDE.md rule #28.
            'recent' => AdminNavService::validRecent($admin),
        ]);
    }
}
