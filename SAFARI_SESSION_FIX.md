# Safari Session Fix - Hướng dẫn khắc phục

## Vấn đề

Safari không lưu session trong ứng dụng Laravel do:

1. Safari có chính sách cookie nghiêm ngặt hơn
2. SameSite cookie policy
3. Third-party cookie blocking
4. Session cookie issues

## Giải pháp đã áp dụng

### 1. Cấu hình Session (config/session.php)

-   `same_site` = 'lax' (Safari yêu cầu)
-   `secure` = false (cho HTTP)
-   `lifetime` = 120 phút (2 giờ)
-   `domain` = null (tránh vấn đề Safari)

### 2. Middleware mới

-   `SafariSessionMiddleware.php` - Xử lý session cho Safari
-   `SafariCsrfMiddleware.php` - Xử lý CSRF cho Safari
-   `WebKitSessionMiddleware.php` - Xử lý WebKit

### 3. Helper classes

-   `SafariSessionHelper.php` - Helper cho Safari session
-   `SafariCsrfHelper.php` - Helper cho CSRF (đã cập nhật)

## Cấu hình .env cần thiết

```env
# Session Configuration for Safari
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_EXPIRE_ON_CLOSE=false
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PARTITIONED_COOKIE=false
SESSION_DOMAIN=

# CSRF Configuration for Safari
CSRF_COOKIE_SECURE=false
CSRF_COOKIE_SAME_SITE=lax
CSRF_COOKIE_DOMAIN=

# Safari-specific settings
SAFARI_CSRF_FIX=true
SAFARI_REGENERATE_TOKEN=true
SAFARI_LOG_CSRF=true
```

## Cách sử dụng

### 1. Đăng ký middleware

Thêm vào `app/Http/Kernel.php`:

```php
protected $middleware = [
    // ... existing middleware
    \App\Http\Middleware\SafariSessionMiddleware::class,
];
```

### 2. Sử dụng trong layout

Thêm vào layout chính:

```php
{!! \App\Helpers\SafariSessionHelper::generateSafariMetaTags() !!}
{!! \App\Helpers\SafariSessionHelper::generateSafariSessionScript() !!}
```

### 3. Sử dụng trong controller

```php
use App\Helpers\SafariSessionHelper;

public function index(Request $request)
{
    SafariSessionHelper::handleSafariSession($request);
    // ... rest of your code
}
```

## Kiểm tra

1. Mở Developer Tools trong Safari
2. Kiểm tra Console để xem log
3. Kiểm tra Application > Cookies
4. Kiểm tra Network tab để xem session requests

## Troubleshooting

1. **Session không lưu**: Kiểm tra `SESSION_DOMAIN` = null
2. **CSRF errors**: Kiểm tra `CSRF_COOKIE_SECURE` = false
3. **Cookies bị block**: Kiểm tra `SESSION_SAME_SITE` = 'lax'
4. **Session timeout**: Kiểm tra `SESSION_LIFETIME` = 120

## Log files

Kiểm tra `storage/logs/laravel.log` để xem:

-   Safari session health checks
-   CSRF token regeneration
-   Cookie setting logs
