<?php

namespace App\Services\Migration\Ux;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * The words the migration feature shows.
 *
 * Two modes, because the web installer runs on a server whose database does not exist yet:
 * the Laravel translator in this project reads database overrides (DatabaseTranslationLoader),
 * which queries the database and logs a warning when it is not there. Installer mode therefore
 * reads lang/en/migration.php straight from disk with no translator, no database and no log.
 * The admin panel and the command line use the normal translator.
 */
class MigrationText
{
    /** @var array<string, mixed>|null */
    private static ?array $installerCatalogue = null;

    /** @param  array<string, scalar|null>  $params */
    public function get(string $key, array $params = [], bool $installer = false): string
    {
        if ($installer) {
            $text = $this->fromFile($key);
        } else {
            $text = __('migration.'.$key);
            // An unknown key comes back as itself; show English from disk rather than a key name.
            $text = $text === 'migration.'.$key ? $this->fromFile($key) : $text;
        }

        return $this->interpolate($text, $params);
    }

    /**
     * The three texts of an error code.
     *
     * @param  array<string, scalar|null>  $params
     * @return array{title: string, message: string, fix: string}
     */
    public function error(MigrationErrorCode $code, array $params = [], bool $installer = false): array
    {
        return [
            'title' => $this->get('error.'.$code->value.'.title', $params, $installer),
            'message' => $this->get('error.'.$code->value.'.message', $params, $installer),
            'fix' => $this->get('error.'.$code->value.'.fix', $params, $installer),
        ];
    }

    /** @return array{title: string, message: string, fix: string} */
    public function forException(MigrationException $e, bool $installer = false): array
    {
        return $this->error($e->errorCode, $e->params, $installer);
    }

    private function fromFile(string $key): string
    {
        self::$installerCatalogue ??= (array) (require lang_path('en/migration.php'));

        $value = self::$installerCatalogue;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $key;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : $key;
    }

    /** @param  array<string, scalar|null>  $params */
    private function interpolate(string $text, array $params): string
    {
        foreach ($params as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }
}
