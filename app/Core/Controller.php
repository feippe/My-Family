<?php
namespace App\Core;

abstract class Controller {
    protected View $view;
    protected Auth $auth;

    public function __construct() {
        $this->auth = new Auth();
        $this->view = new View();
        $cfg = require BASE_PATH . '/app/Config/app.php';
        $this->view->share('appUrl', rtrim($cfg['url'], '/'));
        $user = $this->auth->user();
        if ($user) $this->view->share('currentUser', $user);
        $this->csrfToken(); // ensure CSRF token is initialized
    }

    protected function requireAuth(): void {
        if (!$this->auth->check()) {
            $this->redirect('login');
        }
    }

    protected function redirect(string $path): never {
        $base = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/');
        header("Location: $base/$path");
        exit;
    }

    protected function json(mixed $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function input(string $key, mixed $default = null): mixed {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function body(): array {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return $_POST;
    }

    protected function csrfToken(): string {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    protected function csrfCheck(): void {
        $token = $this->input('_csrf') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
            $this->json(['error' => 'Token CSRF inválido'], 403);
        }
    }
}
