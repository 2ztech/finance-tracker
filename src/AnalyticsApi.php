<?php

declare(strict_types=1);

/**
 * JSON analytics API.
 *
 * This is the only HTTP surface the UI needs. It is dispatched from
 * public/index.php before the HTML CSRF/auth redirects so unauthenticated and
 * CSRF failures return JSON instead of redirects.
 */
final class AnalyticsApi
{
    public static function handleIfApi(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path)) {
            return;
        }
        $path = self::normalizePath($path);
        if ($path !== '/api/analytics' && !str_starts_with($path, '/api/analytics/')) {
            return;
        }

        if (!Auth::isLoggedIn()) {
            self::emit(self::error(401, 'unauthorized', 'Authentication is required.'));
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $body = [];
        $invalidJson = false;
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if ($method === 'POST') {
            if (str_contains($contentType, 'application/json')) {
                $raw = file_get_contents('php://input');
                if (is_string($raw) && trim($raw) !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $body = $decoded;
                    } else {
                        $invalidJson = true;
                    }
                }
            } else {
                $body = $_POST;
            }
        }

        if ($invalidJson) {
            self::emit(self::error(400, 'invalid_json', 'Request body must be valid JSON.'));
        }

        $headers = [];
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $headers['x-csrf-token'] = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        $response = self::dispatch(
            $method,
            $path,
            $_GET,
            $body,
            $headers,
            Auth::isLoggedIn(),
            is_string($sessionToken) ? $sessionToken : null
        );
        self::emit($response);
    }

    /**
     * Testable dispatcher. Returns a status/body pair; never exits.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array{status:int,body:array<string, mixed>,allow?:string}
     */
    public static function dispatch(
        string $method,
        string $path,
        array $query,
        array $body,
        array $headers,
        bool $loggedIn,
        ?string $sessionToken
    ): array {
        $method = strtoupper($method);
        $path = self::normalizePath($path);

        if (!$loggedIn) {
            return self::error(401, 'unauthorized', 'Authentication is required.');
        }

        try {
            if ($path === '/api/analytics') {
                if ($method !== 'GET') {
                    return self::error(405, 'method_not_allowed', 'Use GET for analytics data.', ['allow' => 'GET']);
                }
                return self::dataResponse($query, $sessionToken);
            }

            if ($path === '/api/analytics/layout') {
                if ($method !== 'POST') {
                    return self::error(405, 'method_not_allowed', 'Use POST to save a layout.', ['allow' => 'POST']);
                }
                if (!self::csrfValid($body, $headers, $sessionToken)) {
                    return self::error(403, 'csrf_failed', 'Invalid or missing CSRF token.');
                }
                $layout = $body['layout'] ?? null;
                if (is_string($layout)) {
                    $decoded = json_decode($layout, true);
                    $layout = is_array($decoded) ? $decoded : null;
                }
                if (!is_array($layout)) {
                    return self::error(422, 'invalid_layout', 'layout must be an object.');
                }
                $errors = Widgets::validate($layout);
                if ($errors !== []) {
                    return self::error(422, 'invalid_layout', 'The layout is invalid.', ['details' => $errors]);
                }
                $saved = Widgets::save($layout);
                return ['status' => 200, 'body' => ['ok' => true, 'layout' => $saved]];
            }

            if ($path === '/api/analytics/layout/reset') {
                if ($method !== 'POST') {
                    return self::error(405, 'method_not_allowed', 'Use POST to reset the layout.', ['allow' => 'POST']);
                }
                if (!self::csrfValid($body, $headers, $sessionToken)) {
                    return self::error(403, 'csrf_failed', 'Invalid or missing CSRF token.');
                }
                return ['status' => 200, 'body' => ['ok' => true, 'layout' => Widgets::reset()]];
            }

            return self::error(404, 'not_found', 'Unknown analytics endpoint.');
        } catch (InvalidArgumentException $e) {
            return self::error(422, 'invalid_request', $e->getMessage());
        } catch (Throwable $e) {
            AppLog::error('analytics_api_error', [
                'msg' => $e->getMessage(),
                'path' => $path,
            ]);
            return self::error(500, 'server_error', 'Could not load analytics data.');
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status:int,body:array<string, mixed>}
     */
    private static function dataResponse(array $query, ?string $sessionToken): array
    {
        $range = Analytics::range($query);
        $filter = Analytics::filter($query);
        $layout = Widgets::load();
        $catalog = Widgets::catalog();
        $index = Widgets::index();

        $widgets = [];
        foreach ($layout['widgets'] as $widget) {
            $key = (string) $widget['key'];
            $definition = $index[$key];
            $data = Analytics::build($key, $widget['config'], $range, $filter);
            $widgets[] = [
                'id' => (string) $widget['id'],
                'key' => $key,
                'title' => (string) $definition['label'],
                'group' => (string) $definition['group'],
                'type' => (string) $definition['type'],
                'description' => (string) $definition['description'],
                'size' => (string) $widget['size'],
                'config' => $widget['config'],
                'meta' => [
                    'scope' => (string) $definition['scope'],
                    'range_semantics' => (string) $definition['range_semantics'],
                    'range_applied' => true,
                    'institution_applied' => true,
                    'currency' => Analytics::CURRENCY,
                ],
                'empty' => Analytics::isEmpty($key, $data),
                'data' => $data,
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'generated_at' => date(DATE_ATOM),
                'currency' => Analytics::CURRENCY,
                'filters' => [
                    'ranges' => Analytics::ranges(),
                    'institutions' => Analytics::institutions(),
                    'range' => $range,
                    'institution_id' => (int) $filter['institution_id'],
                ],
                'catalog' => $catalog,
                'layout' => $layout,
                'default_layout' => Widgets::defaultLayout(),
                'csrf_token' => $sessionToken ?? Csrf::token(),
                'widgets' => $widgets,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private static function csrfValid(array $body, array $headers, ?string $sessionToken): bool
    {
        $provided = $headers['x-csrf-token']
            ?? $headers['X-CSRF-Token']
            ?? $body['csrf_token']
            ?? '';
        if ($sessionToken === null || $sessionToken === '' || !is_string($provided) || $provided === '') {
            return false;
        }
        return hash_equals($sessionToken, $provided);
    }

    /** @return array{status:int,body:array<string, mixed>,allow?:string} */
    private static function error(int $status, string $code, string $message, array $extra = []): array
    {
        $body = [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        if (isset($extra['details'])) {
            $body['error']['details'] = $extra['details'];
        }
        $response = ['status' => $status, 'body' => $body];
        if (isset($extra['allow'])) {
            $response['allow'] = (string) $extra['allow'];
        }
        return $response;
    }

    /** @param array{status:int,body:array<string, mixed>,allow?:string} $response */
    private static function emit(array $response): void
    {
        http_response_code($response['status']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (isset($response['allow'])) {
            header('Allow: ' . $response['allow']);
        }
        echo json_encode($response['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }
}
