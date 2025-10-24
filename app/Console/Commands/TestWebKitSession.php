<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class TestWebKitSession extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'session:test-webkit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WebKit session configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing WebKit Session Configuration...');

        // Test session configuration
        $this->info('Session Driver: ' . config('session.driver'));
        $this->info('Session Lifetime: ' . config('session.lifetime') . ' minutes');
        $this->info('Session SameSite: ' . config('session.same_site'));
        $this->info('Session Secure: ' . (config('session.secure') ? 'true' : 'false'));
        $this->info('Session Domain: ' . (config('session.domain') ?: 'null'));

        // Test session creation
        try {
            Session::start();
            $sessionId = Session::getId();
            $csrfToken = Session::token();

            $this->info('Session ID: ' . $sessionId);
            $this->info('CSRF Token: ' . $csrfToken);
            $this->info('Session Started: ' . (Session::isStarted() ? 'true' : 'false'));

            // Test session data
            Session::put('test_key', 'test_value');
            $testValue = Session::get('test_key');
            $this->info('Session Data Test: ' . ($testValue === 'test_value' ? 'PASS' : 'FAIL'));

            // Test CSRF token regeneration
            $oldToken = Session::token();
            Session::regenerateToken();
            $newToken = Session::token();
            $this->info('CSRF Token Regeneration: ' . ($oldToken !== $newToken ? 'PASS' : 'FAIL'));

            $this->info('✅ WebKit Session Test Completed Successfully!');

        } catch (\Exception $e) {
            $this->error('❌ WebKit Session Test Failed: ' . $e->getMessage());
            Log::error('WebKit Session Test Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
