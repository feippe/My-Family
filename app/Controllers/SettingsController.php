<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;
use App\Models\ExternalCalendar;
use App\Models\User;

class SettingsController extends Controller {
    public function profile(array $p = []): void {
        $this->requireAuth();
        $this->view->render('settings/profile', ['user' => $this->auth->user()]);
    }

    public function updateProfile(array $p = []): void {
        $this->requireAuth();
        $data   = $this->body();
        $userId = $this->auth->id();
        $model  = new User();
        $update = [];

        if (!empty($data['name'])) $update['name'] = trim($data['name']);

        if (!empty($data['new_password'])) {
            $cur = $model->findById($userId);
            if (!$model->verify($data['current_password'] ?? '', $cur['password'])) {
                $this->json(['error' => 'Contraseña actual incorrecta'], 422);
            }
            if (strlen($data['new_password']) < 8) {
                $this->json(['error' => 'La nueva contraseña debe tener al menos 8 caracteres'], 422);
            }
            $update['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }

        if ($update) $model->updateProfile($userId, $update);
        $this->json(['success' => true]);
    }

    public function categories(array $p = []): void {
        $this->requireAuth();
        $groupId    = $this->auth->groupId();
        $categories = (new Category())->forGroup($groupId);
        $this->view->render('settings/categories', ['categories' => $categories]);
    }

    public function createCategory(array $p = []): void {
        $this->requireAuth();
        $data    = $this->body();
        $groupId = $this->auth->groupId();

        if (empty($data['name']) || empty($data['color'])) {
            $this->json(['error' => 'Nombre y color son requeridos'], 422);
        }

        $model = new Category();
        $id    = $model->create($groupId, $data['name'], $data['color'], $data['icon'] ?? '📅');
        $cat   = $model->findById($id);
        $this->json(['success' => true, 'category' => $cat]);
    }

    public function updateCategory(array $p = []): void {
        $this->requireAuth();
        $catId   = (int)$p['id'];
        $groupId = $this->auth->groupId();
        $model   = new Category();
        $cat     = $model->findById($catId);

        if (!$cat || $cat['group_id'] != $groupId) $this->json(['error' => 'No encontrado'], 404);

        $data   = $this->body();
        $update = [];
        foreach (['name','color','icon'] as $f) {
            if (isset($data[$f])) $update[$f] = $data[$f];
        }
        $model->updateById($catId, $update);
        $this->json(['success' => true]);
    }

    public function deleteCategory(array $p = []): void {
        $this->requireAuth();
        $catId   = (int)$p['id'];
        $groupId = $this->auth->groupId();
        $model   = new Category();
        $cat     = $model->findById($catId);

        if (!$cat || $cat['group_id'] != $groupId) $this->json(['error' => 'No encontrado'], 404);
        $model->deleteById($catId);
        $this->json(['success' => true]);
    }

    public function members(array $p = []): void {
        $this->requireAuth();
        $groupId = $this->auth->groupId();
        $members = (new User())->getGroupMembers($groupId);
        $this->view->render('settings/members', ['members' => $members]);
    }

    public function externalCalendars(array $p = []): void {
        $this->requireAuth();
        $groupId   = $this->auth->groupId();
        $calendars = (new ExternalCalendar())->forGroup($groupId);
        $this->view->render('settings/ext_calendars', ['calendars' => $calendars]);
    }

    public function createExternalCalendar(array $p = []): void {
        $this->requireAuth();
        $data    = $this->body();
        $groupId = $this->auth->groupId();

        $name  = trim($data['name'] ?? '');
        $url   = trim($data['url']  ?? '');
        $color = strtolower(trim($data['color'] ?? '#0891b2'));

        if (!$name || !$url)                         $this->json(['error' => 'Nombre y URL son requeridos'], 422);
        if (!filter_var($url, FILTER_VALIDATE_URL))  $this->json(['error' => 'URL inválida'], 422);
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) $color = '#0891b2';

        // Quick reachability check
        try {
            \App\Core\ICalParser::fetch($url);
        } catch (\Throwable $e) {
            $this->json(['error' => 'No se pudo acceder a la URL: ' . $e->getMessage()], 422);
        }

        $model = new ExternalCalendar();
        $id    = $model->create($groupId, $name, $url, $color);
        $this->json(['success' => true, 'calendar' => $model->findById($id)]);
    }

    public function updateExternalCalendar(array $p = []): void {
        $this->requireAuth();
        $id      = (int)($p['id'] ?? 0);
        $groupId = $this->auth->groupId();
        $model   = new ExternalCalendar();

        if (!$model->belongsToGroup($id, $groupId)) $this->json(['error' => 'No encontrado'], 404);

        $data   = $this->body();
        $update = [];
        if (isset($data['name']))      $update['name']      = trim($data['name']);
        if (isset($data['url']))       $update['url']       = trim($data['url']);
        if (isset($data['color']))     $update['color']     = strtolower(trim($data['color']));
        if (isset($data['is_active'])) $update['is_active'] = (int)$data['is_active'];

        if ($update) {
            $update['updated_at'] = date('Y-m-d H:i:s');
            $model->updateById($id, $update);
        }
        $this->json(['success' => true]);
    }

    public function deleteExternalCalendar(array $p = []): void {
        $this->requireAuth();
        $id      = (int)($p['id'] ?? 0);
        $groupId = $this->auth->groupId();
        $model   = new ExternalCalendar();

        if (!$model->belongsToGroup($id, $groupId)) $this->json(['error' => 'No encontrado'], 404);

        $model->deleteById($id);
        $this->json(['success' => true]);
    }
}
