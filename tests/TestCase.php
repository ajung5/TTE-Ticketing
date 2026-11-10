<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {
    /**
     * TTE v30: CSRF middleware dilewati hanya di HTTP Feature Test.
     * Middleware autentikasi, otorisasi, signed URL, session, dan rate-limit tetap aktif.
     * Kelas ini hanya dipakai oleh PHPUnit/Pest; tidak dipakai aplikasi web.
     */
    /**
     * TTE v31: isolated test bootstrap before RefreshDatabase.
     *
     * Laravel's parent::setUp() can run RefreshDatabase. Guarding database
     * settings AFTER that call is too late. Instead, set up a dedicated
     * in-memory test connection in createApplication() before traits execute.
     */
    public function createApplication() {
        // Never bootstrap cached production configuration in a test process.
        if (is_file(dirname(__DIR__) . '/bootstrap/cache/config.php')) {
            throw new \RuntimeException(
                'TTE TEST SAFETY: config cache ditemukan. Jalankan php artisan config:clear sebelum test.',
            );
        }

        // These variables are process-local to PHPUnit/Pest. They do not
        // modify .env, production configuration, or any database on disk.
        $testingEnvironment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];
        foreach ($testingEnvironment as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        $application = parent::createApplication();
        if (!$application->environment('testing')) {
            throw new \RuntimeException(
                'TTE TEST SAFETY: Laravel tidak berjalan pada APP_ENV=testing. Database tidak boleh dimigrasi.',
            );
        }

        // Force the effective connection, not just environment variables.
        // This occurs BEFORE RefreshDatabase and protects against a .env
        // override or a stale connection previously resolved in the container.
        $settings = $application->make('config');
        $settings->set('database.default', 'sqlite');
        $settings->set('database.connections.sqlite.url', null);
        $settings->set('database.connections.sqlite.database', ':memory:');
        $settings->set('cache.default', 'array');
        $settings->set('session.driver', 'array');
        $settings->set('queue.default', 'sync');
        $settings->set('mail.default', 'array');
        $application->make('db')->purge('sqlite');

        if (
            $settings->get('database.default') !== 'sqlite' ||
            $settings->get('database.connections.sqlite.database') !== ':memory:' ||
            $settings->get('database.connections.sqlite.url') !== null
        ) {
            throw new \RuntimeException(
                'TTE TEST SAFETY: koneksi SQLite :memory: tidak dapat dijamin. Test dibatalkan.',
            );
        }

        return $application;
    }

    protected function setUp(): void {
        parent::setUp();

        // Migration baru hanya membuat skema. Test memakai preset asli dari seeder
        // tanpa membuat akun awal (DB SQLite :memory: sudah dipaksa saat bootstrap).
        if (\Illuminate\Support\Facades\Schema::hasTable('wa_message_templates')) {
            $this->seed(\Database\Seeders\WaTemplatePresetsSeeder::class);
        }

        // Only TestCase's HTTP requests skip CSRF; production still enforces it.
        // Authentication, authorization, signed URLs and throttling stay active.
        $this->withoutMiddleware(
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        );
    }
}
