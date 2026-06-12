<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;
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
}
