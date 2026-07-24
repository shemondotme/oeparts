<?php

namespace App\Http\Controllers;

use App\Services\Install\InstallManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallerController extends Controller
{
    public function __construct(private readonly InstallManager $installer) {}

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
        $suggestedTimezone = $this->detectTimezone();
        $suggestedLocale = $this->detectLocale();

        return view('installer.step3-site-settings', compact('currentStep', 'suggestedTimezone', 'suggestedLocale'));
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
     * Step 6: show the AJAX-polled installation-progress screen and (if this
     * is a fresh visit, not a resumed/retried one) kick off the run.
     *
     * GET, not POST — a POST-only route here was a pre-existing bug: step 5's
     * form redirects here after a successful submission, and browsers always
     * follow a redirect with a GET, so the old `Route::post('/install/run', …)`
     * could never actually be reached by a real user clicking "Save & Install".
     */
    public function run()
    {
        if ($redirect = $this->requireStep('installer.email_setup_done')) {
            return $redirect;
        }

        $currentStep = 6;

        if (! $this->installer->isRunning() && ! $this->installer->hasFailedRun()) {
            $this->installer->start($this->collectInstallInput());
        }

        return view('installer.step6-progress', compact('currentStep'));
    }

    /**
     * AJAX endpoint: perform exactly one chunk of the install and report
     * progress. Polled repeatedly by step6-progress.blade.php's JS — this is
     * what keeps a single HTTP request from ever running the full
     * migrate+seed+admin-creation pipeline (a real timeout risk on shared
     * hosting, see InstallManager's docblock).
     */
    public function advance()
    {
        return response()->json($this->installer->advance());
    }

    /** Discard a failed run's state so the user can retry from the top. */
    public function retry(): RedirectResponse
    {
        $this->installer->reset();

        return redirect()->route('installer.install');
    }

    /**
     * Step 6: Installation complete. Deliberately NOT gated by the
     * `installer` middleware (see routes/installer.php) — that middleware
     * blocks as soon as storage/installed.lock exists, which InstallManager's
     * last step just wrote a moment ago, so gating this route the same way
     * would bounce the user straight to '/' and they'd never see this page.
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
            'create_database' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('installer.database')
                ->withErrors($validator)
                ->withInput();
        }

        if ($request->boolean('create_database')) {
            try {
                $this->createDatabaseIfMissing(
                    $request->db_host,
                    $request->db_port,
                    $request->db_username,
                    $request->db_password,
                    $request->db_name
                );
            } catch (\Throwable $e) {
                return redirect()->route('installer.database')
                    ->withErrors(['db_connection' => 'Could not create the database: '.$e->getMessage()])
                    ->withInput();
            }
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

            $this->installer->updateEnvFile([
                'DB_HOST' => $request->db_host,
                'DB_PORT' => $request->db_port,
                'DB_DATABASE' => $request->db_name,
                'DB_USERNAME' => $request->db_username,
                'DB_PASSWORD' => $request->db_password ?: '',
            ]);

            session(['installer.db_configured' => true]);

            return redirect()->route('installer.site-settings')
                ->with('success', 'Database connection successful!');
        } catch (\Throwable $e) {
            return redirect()->route('installer.database')
                ->withErrors(['db_connection' => 'Could not connect to database: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Attempt to create the target database on the given MySQL server, if it
     * doesn't already exist. Connects WITHOUT selecting a database first
     * (Laravel's config-based connections always select one) — raw PDO is
     * the simplest way to do that mid-request. Requires the DB user to have
     * CREATE DATABASE privilege; if it doesn't, the resulting exception
     * message is surfaced to the user as-is (it already explains why).
     */
    private function createDatabaseIfMissing(string $host, string|int $port, string $username, ?string $password, string $name): void
    {
        $pdo = new \PDO(
            "mysql:host={$host};port={$port}",
            $username,
            $password ?: '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $escapedName = str_replace('`', '``', $name);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$escapedName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /**
     * Process site settings.
     */
    public function processSiteSettings(Request $request)
    {
        if ($redirect = $this->requireStep('installer.db_configured')) {
            return $redirect;
        }

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
            'installer.site_settings_done' => true,
        ]);

        return redirect()->route('installer.admin-account');
    }

    /**
     * Process admin account creation.
     */
    public function processAdminAccount(Request $request)
    {
        if ($redirect = $this->requireStep('installer.site_settings_done')) {
            return $redirect;
        }

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
            'installer.admin_account_done' => true,
        ]);

        return redirect()->route('installer.email-setup');
    }

    /**
     * Process email setup.
     */
    public function processEmailSetup(Request $request)
    {
        if ($redirect = $this->requireStep('installer.admin_account_done')) {
            return $redirect;
        }

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
            'installer.email_setup_done' => true,
        ]);

        return redirect()->route('installer.install');
    }

    /**
     * Send a one-off test email using whatever SMTP settings are currently
     * in the step 5 form (not yet saved to .env). Deliberately synchronous
     * (not queued, unlike rule #14's transactional emails) — the whole point
     * is immediate pass/fail feedback for a form the user is looking at
     * right now, before there's a queue worker, a database, or even an app
     * key to trust for job serialization.
     */
    public function testMail(Request $request)
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
            'test_to' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        config([
            'mail.default' => $request->mail_driver,
            'mail.mailers.smtp.host' => $request->mail_host,
            'mail.mailers.smtp.port' => $request->mail_port,
            'mail.mailers.smtp.username' => $request->mail_username,
            'mail.mailers.smtp.password' => $request->mail_password,
            'mail.mailers.smtp.encryption' => $request->mail_encryption ?: null,
            'mail.from.address' => $request->mail_from_address,
            'mail.from.name' => $request->mail_from_name,
        ]);

        try {
            Mail::raw(
                'This is a test email from the OeParts installer. If you received it, your mail settings are correct.',
                fn ($message) => $message->to($request->test_to)->subject('OeParts installer — test email')
            );

            return response()->json(['success' => true, 'message' => 'Test email sent to '.$request->test_to.'.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not send: '.$e->getMessage()], 422);
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
     * blocks installation. The app ships defaulting to file/sync cache+queue
     * drivers (zero setup, works on any host); Redis or Memcached are only
     * needed when the operator opts into them for performance. Missing the
     * `redis` PHP extension specifically isn't a dead end either —
     * `predis/predis` (pure PHP, bundled via composer) talks to the same
     * Redis server with no native extension at all — set REDIS_CLIENT=predis.
     */
    private function checkRecommended()
    {
        return [
            'Redis PHP Extension' => extension_loaded('redis'),
            'Memcached PHP Extension' => extension_loaded('memcached'),
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

    /** Server's own configured timezone — no guessing needed if it's a real one. */
    private function detectTimezone(): string
    {
        $tz = date_default_timezone_get();

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    /** Best-effort locale suggestion from the browser's Accept-Language header. */
    private function detectLocale(): string
    {
        $supported = ['en', 'de', 'lt', 'fr', 'es'];
        $header = (string) request()->server('HTTP_ACCEPT_LANGUAGE', '');

        foreach (explode(',', $header) as $part) {
            $lang = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));

            if (in_array($lang, $supported, true)) {
                return $lang;
            }
        }

        return 'en';
    }

    /**
     * Require that an earlier wizard step actually completed before letting
     * a later one process — without this, POSTing straight to (say)
     * /install/admin-account would happily create settings rows with null
     * values for the site-settings step that was skipped entirely.
     */
    private function requireStep(string $flag): ?RedirectResponse
    {
        if (session($flag)) {
            return null;
        }

        return redirect()->route('installer.index')
            ->with('error', 'Please complete the installer steps in order.');
    }

    /** Snapshot everything InstallManager needs, captured once at run start. */
    private function collectInstallInput(): array
    {
        return [
            'admin_name' => session('installer.admin_name'),
            'admin_email' => session('installer.admin_email'),
            'admin_password_hash' => session('installer.admin_password'),
            'site_name' => session('installer.site_name'),
            'site_url' => session('installer.site_url'),
            'default_locale' => session('installer.default_locale'),
            'timezone' => session('installer.timezone'),
            'mail_driver' => session('installer.mail_driver', 'smtp'),
            'mail_host' => session('installer.mail_host', ''),
            'mail_port' => session('installer.mail_port', '587'),
            'mail_username' => session('installer.mail_username', ''),
            'mail_password' => session('installer.mail_password', ''),
            'mail_encryption' => session('installer.mail_encryption', 'tls'),
            'mail_from_address' => session('installer.mail_from_address', ''),
            'mail_from_name' => session('installer.mail_from_name', ''),
            'import_demo_data' => (bool) session('installer.import_demo_data'),
        ];
    }
}
