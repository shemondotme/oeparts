<?php

namespace Tests\Unit\Migration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3.10c: every shipped template of the queue worker command serves the migration queue, so a
 * freeze export that is driven by a queue worker can run. An existing install keeps its own old line until
 * its owner edits it (the README says how); nothing in the code assumes the new line.
 */
class DeployTemplatesTest extends TestCase
{
    private const LINE = '--queue=migration,critical,default,low';

    private function read(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/'.$relative);
    }

    #[Test]
    public function the_docker_compose_worker_serves_the_migration_queue_first(): void
    {
        $compose = $this->read('compose.yaml');

        $this->assertStringContainsString(self::LINE, $compose);
        $this->assertStringNotContainsString('--queue=critical,default,low', $compose);
    }

    #[Test]
    public function the_supervisor_template_serves_it_and_explains_why(): void
    {
        $conf = $this->read('deploy/supervisor/oeparts-queue-worker.conf');

        $this->assertStringContainsString('command=php /path/to/oeparts/artisan queue:work redis '.self::LINE, $conf);
        $this->assertStringNotContainsString('--queue=critical,default,low', $conf);
        $this->assertStringContainsString("'migration' comes FIRST", $conf);
    }

    #[Test]
    public function the_readme_gives_the_new_command_and_tells_an_upgrader_what_to_change(): void
    {
        $readme = $this->read('README.md');

        $this->assertStringContainsString('php artisan queue:work redis '.self::LINE, $readme);
        $this->assertStringContainsString('Upgrading from a release before site migration', $readme);
        $this->assertStringContainsString('php artisan queue:restart', $readme);
    }

    #[Test]
    public function the_ci_workflow_does_not_pin_a_worker_line_that_would_have_to_change_too(): void
    {
        $this->assertStringNotContainsString('queue:work', $this->read('.github/workflows/tests.yml'));
    }
}
