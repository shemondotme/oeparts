<?php

namespace Tests\Feature;

use App\Models\IpBlocklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminIpBlocklistTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function blocked_ip_is_denied_access_to_admin_login(): void
    {
        $blockedIp = '203.0.113.10';

        IpBlocklist::factory()->create(['ip_address' => $blockedIp]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => $blockedIp])
            ->get('/admin/login');

        $response->assertStatus(403);
    }

    #[Test]
    public function non_blocked_ip_can_reach_admin_login(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->get('/admin/login');

        $response->assertStatus(200);
    }

    #[Test]
    public function inactive_blocklist_entry_does_not_block_admin_login(): void
    {
        $ip = '203.0.113.11';

        IpBlocklist::factory()->inactive()->create(['ip_address' => $ip]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->get('/admin/login');

        $response->assertStatus(200);
    }
}
