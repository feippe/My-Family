<?php
namespace App\Core;

class View {
    private array $shared = [];

    public function share(string $key, mixed $value): void {
        $this->shared[$key] = $value;
    }

    public function render(string $tpl, array $data = [], string $layout = 'app'): void {
        $data    = array_merge($this->shared, $data);

        $tplPath = BASE_PATH . "/app/Views/$tpl.php";
        if (!file_exists($tplPath)) throw new \RuntimeException("View not found: $tpl");

        // Render the template in THIS scope so any variables it sets
        // (pageTitle, pageScripts, …) remain visible when the layout runs.
        extract($data, EXTR_SKIP);
        ob_start();
        require $tplPath;
        $content = ob_get_clean();

        $layoutPath = BASE_PATH . "/app/Views/layouts/$layout.php";
        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            echo $content;
        }
    }

    public function capture(string $tpl, array $data = []): string {
        $path = BASE_PATH . "/app/Views/$tpl.php";
        if (!file_exists($path)) throw new \RuntimeException("View not found: $tpl");
        extract(array_merge($this->shared, $data), EXTR_SKIP);
        ob_start();
        require $path;
        return ob_get_clean();
    }

    public static function e(mixed $val): string {
        return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
