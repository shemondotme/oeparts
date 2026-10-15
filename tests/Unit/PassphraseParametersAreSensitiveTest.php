<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SensitiveParameter;

/**
 * A passphrase that reaches a stack trace (in a log, an error page, a report) is a leaked passphrase. Every parameter in app/
 * whose name says "passphrase" must carry #[\SensitiveParameter], so PHP replaces it with a placeholder in traces.
 */
class PassphraseParametersAreSensitiveTest extends TestCase
{
    #[Test]
    public function every_passphrase_parameter_in_the_application_is_marked_sensitive(): void
    {
        require_once dirname(__DIR__, 2).'/vendor/autoload.php';

        $missing = [];
        $checked = 0;

        foreach ($this->classesUnderApp() as $class) {
            if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                foreach ($method->getParameters() as $parameter) {
                    if (stripos($parameter->getName(), 'passphrase') === false) {
                        continue;
                    }

                    // A flag such as $needsPassphrase holds no secret.
                    if ((string) $parameter->getType() === 'bool') {
                        continue;
                    }

                    $checked++;

                    if ($parameter->getAttributes(SensitiveParameter::class) === []) {
                        $missing[] = $class.'::'.$method->getName().'($'.$parameter->getName().')';
                    }
                }
            }
        }

        $this->assertGreaterThan(10, $checked, 'the scan found the passphrase parameters at all');
        $this->assertSame([], $missing, "These parameters need #[\SensitiveParameter]:\n".implode("\n", $missing));
    }

    /** @return list<class-string> */
    private function classesUnderApp(): array
    {
        $root = dirname(__DIR__, 2).'/app';
        $classes = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            if (stripos($source, 'passphrase') === false) {
                continue;
            }

            if (preg_match('/^namespace\s+([^;]+);/m', $source, $ns) && preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|trait|interface|enum)\s+(\w+)/m', $source, $name)) {
                $classes[] = $ns[1].chr(92).$name[1];
            }
        }

        return $classes;
    }
}
