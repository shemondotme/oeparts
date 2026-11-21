<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The middleware is registered globally (bootstrap/app.php): through the whole HTTP kernel, a page requested under the
 * /index.php/ prefix is redirected permanently, before routing or the database are involved.
 */
class IndexPhpRedirectTest extends TestCase
{
    #[Test]
    public function a_page_under_the_script_prefix_redirects_to_the_clean_address(): void
    {
        $this->get('/index.php/fr/parts/A3094210211?sort=price')
            ->assertStatus(301)
            ->assertRedirect(url('/fr/parts/A3094210211?sort=price'));
    }

    #[Test]
    public function the_script_on_its_own_redirects_to_the_home_page(): void
    {
        $this->get('/index.php')->assertStatus(301)->assertRedirect(url('/'));
    }
}
