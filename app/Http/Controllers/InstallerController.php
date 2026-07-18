<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallerController extends Controller
{
    /**
     * Show the installer welcome/requirements step.
     */
    public function index()
    {
        $requirements = $this->checkRequirements();
        $recommended = $this->checkRecommended();
        $permissions = $this->checkPermissions();
        $phpVersion = phpversion();
        $phpRequired = '8.3';
        $currentStep = 1;

        return view('installer.step1-requirements', compact(
            'requirements',
            'recommended',
            'permissions',
            'phpVersion',
            'phpRequired',
            'currentStep'
        ));
    }

    /**
     * Step 2: Database configuration.
     */
    public function database()
    {
        $currentStep = 2;

        return view('installer.step2-database', compact('currentStep'));
    }

    /**
     * Step 3: Site settings.
     */
    public function siteSettings()
    {
        $currentStep = 3;

        return view('installer.step3-site-settings', compact('currentStep'));
    }

    /**
     * Step 4: Admin account creation.
     */
    public function adminAccount()
    {
        $currentStep = 4;

        return view('installer.step4-admin-account', compact('currentStep'));
    }

    /**
     * Step 5: Email setup.
     */
    public function emailSetup()
    {
        $currentStep = 5;

        return view('installer.step5-email-setup', compact('currentStep'));
    }

    /**
     * Step 6: Installation complete.
     * Lock file is written by install() — this view only renders after a
     * successful installation run, so it just checks the lock exists.
     */
    public function complete()
    {
        $currentStep = 6;

        return view('installer.step6-complete', compact('currentStep'));
    }

    /**
     * Process database configuration.
     */
    public function processDatabase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'db_host' => 'required|string',
            'db_port' => 'required|numeric',
            'db_name' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('installer.database')
                ->withErrors($validator)
                ->withInput();
        }

        // Test database connection
        config([
            'database.connections.mysql.host' => $request->db_host,
            'database.connections.mysql.port' => $request->db_port,
            'database.connections.mysql.database' => $request->db_name,
            'database.connections.mysql.username' => $request->db_username,
            'database.connections.mysql.password' => $request->db_password,
        ]);

        try {
            DB::purge('mysql');
            DB::reconnect('mysql');
            DB::connection('mysql')->getPdo();

            // Save to .env file
            $this->updateEnvFile([
                'DB_HOST' => $request->db_host,
                'DB_PORT' => $request->db_port,
                'DB_DATABASE' => $request->db_name,
                'DB_USERNAME' => $request->db_username,
                'DB_PASSWORD' => $request->db_password ?: '',
            ]);

            return redirect()->route('installer.site-settings')
                ->with('success', 'Database connection successful!');
        } catch (\Throwable $e) {
            return redirect()->route('installer.database')
                ->withErrors(['db_connection' => 'Could not connect to database: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Process site settings.
     */
    public function processSiteSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_name' => 'required|string|max:255',
            'site_url' => 'required|url',
            'default_locale' => 'required|string|size:2',
            'timezone' => 'required|timezone',
        ]);

        if ($validator->fails()) {
            return redirect()->route('installer.site-settings')
                ->withErrors($validator)
                ->withInput();
        }

        // Save settings to session for later use
        session([
            'installer.site_name' => $request->site_name,
            'installer.site_url' => $request->site_url,
            'installer.default_locale' => $request->default_locale,
            'installer.timezone' => $request->timezone,
        ]);

        return redirect()->route('installer.admin-account');
    }

    /**
     * Process admin account creation.
     */
    public function processAdminAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        if ($validator->fails()) {
            return redirect()->route('installer.admin-account')
                ->withErrors($validator)
                ->withInput();
        }

        session([
            'installer.admin_name' => $request->name,
            'installer.admin_email' => $request->email,
            'installer.admin_password' => Hash::make($request->password),
        ]);

        return redirect()->route('installer.email-setup');
    }

    /**
     * Process email setup.
     */
    public function processEmailSetup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_driver' => 'required|string|in:smtp,sendmail,log,array',
            'mail_host' => 'required_if:mail_driver,smtp|string',
            'mail_port' => 'required_if:mail_driver,smtp|numeric',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string|in:tls,ssl',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('installer.email-setup')
                ->withErrors($validator)
                ->withInput();
        }

        // Save email settings to session
        session([
            'installer.mail_driver' => $request->mail_driver,
            'installer.mail_host' => $request->mail_host,
            'installer.mail_port' => $request->mail_port,
            'installer.mail_username' => $request->mail_username,
            'installer.mail_password' => $request->mail_password,
            'installer.mail_encryption' => $request->mail_encryption,
            'installer.mail_from_address' => $request->mail_from_address,
            'installer.mail_from_name' => $request->mail_from_name,
            'installer.import_demo_data' => $request->boolean('import_demo_data'),
        ]);

        return redirect()->route('installer.install');
    }

    /**
     * Run the installation.
     */
    public function install()
    {
        try {
            // 1. Run migrations from clean state
            Artisan::call('migrate:fresh', ['--force' => true, '--seed' => false]);

            // 2. Run core seeders (roles must run before admin creation for Spatie).
            // SectionsSeeder is not demo-specific — it's the default homepage
            // builder content (hero, trust bar, etc.); without it the storefront
            // homepage renders with zero sections configured.
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SettingsSeeder',   '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\LanguagesSeeder',  '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesSeeder',      '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SequencesSeeder',  '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\CarriersSeeder',   '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SectionsSeeder',   '--force' => true]);

            // 3. Create super admin (password already hashed from processAdminAccount)
            $admin = Admin::create([
                'name' => session('installer.admin_name'),
                'email' => session('installer.admin_email'),
                'password' => session('installer.admin_password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Assign Spatie role (requires roles to be seeded first)
            $admin->assignRole('super_admin');

            // 4. Persist site settings. Every one of these keys already exists
            // (seeded by SettingsSeeder above) under the 'general' group — the
            // match must include 'group', not just 'key': the settings table's
            // unique constraint is (group, key), and several groups reuse the
            // same key names (e.g. 'timezone'), so a key-only match risks
            // silently updating an unrelated row instead of this one.
            $settingsMap = [
                'site_name' => session('installer.site_name'),
                'site_url' => session('installer.site_url'),
                'default_locale' => session('installer.default_locale'),
                'timezone' => session('installer.timezone'),
            ];

            foreach ($settingsMap as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => 'general', 'key' => $key],
                    ['value' => $value, 'type' => 'string']
                );
            }

            // 5. Persist email settings to .env
            $this->updateEnvFile([
                'MAIL_MAILER' => session('installer.mail_driver', 'smtp'),
                'MAIL_HOST' => session('installer.mail_host', ''),
                'MAIL_PORT' => session('installer.mail_port', '587'),
                'MAIL_USERNAME' => session('installer.mail_username', ''),
                'MAIL_PASSWORD' => session('installer.mail_password', ''),
                'MAIL_ENCRYPTION' => session('installer.mail_encryption', 'tls'),
                'MAIL_FROM_ADDRESS' => session('installer.mail_from_address', ''),
                'MAIL_FROM_NAME' => session('installer.mail_from_name', ''),
            ]);

            // 6. Optional demo catalog data (manufacturers/parts/blog posts —
            // never touches admin/customer accounts). Best-effort: a failure
            // here shouldn't fail an otherwise-successful installation, since
            // the site is fully usable without it.
            if (session('installer.import_demo_data')) {
                try {
                    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--force' => true]);
                } catch (\Throwable $e) {
                    // swallow — demo data is a nice-to-have, not install-critical
                }
            }

            // 7. Write lock file — installer is now disabled
            File::put(storage_path('installed.lock'), 'Installed at '.now()->toDateTimeString());

            // 8. Clear installer session and compiled views
            session()->forget('installer');
            Artisan::call('view:clear');

            return redirect()->route('installer.complete');
        } catch (\Exception $e) {
            return redirect()->route('installer.site-settings')
                ->with('error', 'Installation failed: '.$e->getMessage());
        }
    }

    /**
     * Check hard PHP requirements — the app cannot run at all without these,
     * so they block installation.
     */
    private function checkRequirements()
    {
        $requirements = [
            'PHP >= 8.3' => version_compare(phpversion(), '8.3', '>='),
            'BCMath PHP Extension' => extension_loaded('bcmath'),
            'Ctype PHP Extension' => extension_loaded('ctype'),
            'Fileinfo PHP Extension' => extension_loaded('fileinfo'),
            'JSON PHP Extension' => extension_loaded('json'),
            'Mbstring PHP Extension' => extension_loaded('mbstring'),
            'OpenSSL PHP Extension' => extension_loaded('openssl'),
            'PDO PHP Extension' => extension_loaded('pdo'),
            'Tokenizer PHP Extension' => extension_loaded('tokenizer'),
            'XML PHP Extension' => extension_loaded('xml'),
        ];

        return $requirements;
    }

    /**
     * Check recommended-but-optional extensions — informational only, never
     * blocks installation. The app runs on array/file/sync cache+queue
     * drivers (local dev, and many shared-hosting production installs);
     * Redis is only required when the operator has configured it. The PHP
     * `redis` C extension specifically isn't even the only way to use Redis
     * from Laravel — `predis/predis` (pure PHP, bundled) works with zero
     * extension at all.
     */
    private function checkRecommended()
    {
        return [
            'Redis PHP Extension' => extension_loaded('redis'),
        ];
    }

    /**
     * Check directory permissions.
     */
    private function checkPermissions()
    {
        $permissions = [
            'storage/' => is_writable(storage_path()),
            'bootstrap/cache/' => is_writable(base_path('bootstrap/cache')),
            '.env' => ! file_exists(base_path('.env')) || is_writable(base_path('.env')),
        ];

        return $permissions;
    }

    /**
     * Update .env file with new values.
     */
    private function updateEnvFile(array $values)
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            File::copy(base_path('.env.example'), $envPath);
        }

        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            $escapedKey = preg_quote($key, '/');
            $pattern = "/^{$escapedKey}=.*/m";
            $replacement = "{$key}=\"{$value}\"";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        // Write atomically: a plain File::put() truncates .env in place, so a
        // concurrent web request whose bootstrap reads .env mid-write sees an
        // empty file. Dotenv::safeLoad() then silently loads nothing and every
        // value falls back to Laravel's defaults (DB_DATABASE => "laravel",
        // CACHE_STORE => "database", ...), producing intermittent 500s. Writing
        // to a temp file and renaming makes the swap atomic — readers always see
        // either the old or the new file, never a partial one.
        $tmpPath = $envPath.'.tmp';
        File::put($tmpPath, $envContent);
        if (file_exists($envPath)) {
            @chmod($tmpPath, @fileperms($envPath) & 0777);
        }
        if (! @rename($tmpPath, $envPath)) {
            // Fallback for filesystems where rename() cannot replace: best effort.
            File::put($envPath, $envContent);
            @unlink($tmpPath);
        }
    }
}
