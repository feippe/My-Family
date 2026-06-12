<?php
namespace App\Core;

class Router {
    private array $routes = [];

    public function get(string $path, string $handler): void    { $this->add('GET',    $path, $handler); }
    public function post(string $path, string $handler): void   { $this->add('POST',   $path, $handler); }
    public function put(string $path, string $handler): void    { $this->add('PUT',    $path, $handler); }
    public function delete(string $path, string $handler): void { $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, string $handler): void {
        $pattern = '#^' . preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path) . '$#';
        $this->routes[] = compact('method', 'path', 'pattern', 'handler');
    }

    public function dispatch(): void {
        $url = trim($_GET['url'] ?? '', '/');
        $url = filter_var($url, FILTER_SANITIZE_URL);

        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) continue;
            if (!preg_match($r['pattern'], $url, $m)) continue;
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->invoke($r['handler'], $params);
            return;
        }

        http_response_code(404);
        require BASE_PATH . '/app/Views/errors/404.php';
    }

    private function invoke(string $handler, array $params): void {
        [$cls, $method] = explode('@', $handler);
        $fqn = "App\\Controllers\\$cls";
        if (!class_exists($fqn)) throw new \RuntimeException("Controller $fqn not found");
        $ctrl = new $fqn();
        if (!method_exists($ctrl, $method)) throw new \RuntimeException("Method $method not found in $fqn");
        $ctrl->$method($params);
    }
}
