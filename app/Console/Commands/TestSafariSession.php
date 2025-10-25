<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;

class TestSafariSession extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'session:test-safari';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Safari session configuration and fix';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Safari Session Configuration...');
        $this->newLine();

        // Test session configuration
        $this->info('📋 Session Configuration:');
        $this->line('  Driver: ' . config('session.driver'));
        $this->line('  Lifetime: ' . config('session.lifetime') . ' minutes');
        $this->line('  SameSite: ' . config('session.same_site'));
        $this->line('  Secure: ' . (config('session.secure') ? 'true' : 'false'));
        $this->line('  Domain: ' . (config('session.domain') ?: 'null'));
        $this->line('  HttpOnly: ' . (config('session.http_only') ? 'true' : 'false'));
        $this->newLine();

        // Test session creation
        try {
            $this->info('🔧 Testing Session Creation:');

            Session::start();
            $sessionId = Session::getId();
            $csrfToken = Session::token();

            $this->line('  Session ID: ' . $sessionId);
            $this->line('  CSRF Token: ' . $csrfToken);
            $this->line('  Session Started: ' . (Session::isStarted() ? '✅ true' : '❌ false'));

            // Test session data
            Session::put('safari_test_key', 'safari_test_value');
            $testValue = Session::get('safari_test_key');
            $this->line('  Session Data Test: ' . ($testValue === 'safari_test_value' ? '✅ PASS' : '❌ FAIL'));

            // Test CSRF token regeneration
            $oldToken = Session::token();
            Session::regenerateToken();
            $newToken = Session::token();
            $this->line('  CSRF Token Regeneration: ' . ($oldToken !== $newToken ? '✅ PASS' : '❌ FAIL'));

            // Test session save
            Session::save();
            $this->line('  Session Save: ✅ PASS');

            $this->newLine();
            $this->info('✅ Safari Session Test Completed Successfully!');
            $this->newLine();

            // Test Safari-specific settings
            $this->info('🍎 Safari-Specific Settings:');
            $this->line('  SameSite Policy: ' . config('session.same_site'));
            $this->line('  Secure Cookie: ' . (config('session.secure') ? 'true' : 'false'));
            $this->line('  Domain Setting: ' . (config('session.domain') ?: 'null (recommended for Safari)'));

            if (config('session.same_site') === 'lax' && !config('session.secure') && !config('session.domain')) {
                $this->line('  Safari Compatibility: ✅ OPTIMAL');
            } else {
                $this->line('  Safari Compatibility: ⚠️  SUBOPTIMAL');
            }

        } catch (\Exception $e) {
            $this->error('❌ Safari Session Test Failed: ' . $e->getMessage());
            Log::error('Safari Session Test Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }

        $this->newLine();
        $this->info('💡 Recommendations for Safari:');
        $this->line('  1. Ensure SESSION_SAME_SITE=lax in .env');
        $this->line('  2. Ensure SESSION_SECURE_COOKIE=false for HTTP');
        $this->line('  3. Ensure SESSION_DOMAIN= (empty) in .env');
        $this->line('  4. Use SafariSessionFixMiddleware for enhanced handling');
        $this->line('  5. Check browser console for Safari-specific logs');

        return 0;
    }
}
