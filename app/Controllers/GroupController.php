<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\FamilyGroup;
use App\Models\User;
use App\Models\Invitation;
use App\Models\Category;

class GroupController extends Controller {
    public function index(array $p = []): void {
        $this->requireAuth();
        $userId     = $this->auth->id();
        $groupModel = new FamilyGroup();
        $group      = $groupModel->getByUser($userId);

        if (!$group) { $this->redirect('group/create'); }

        $members = (new User())->getGroupMembers($group['id']);
        $this->view->render('group/index', ['group' => $group, 'members' => $members]);
    }

    public function createForm(array $p = []): void {
        $this->requireAuth();
        if ($this->auth->groupId()) $this->redirect('calendar');
        $this->view->render('group/create', []);
    }

    public function store(array $p = []): void {
        $this->requireAuth();
        if ($this->auth->groupId()) $this->redirect('calendar');

        $name = trim($this->input('name', ''));
        if (!$name) {
            $this->view->render('group/create', ['error' => 'El nombre es requerido.']);
            return;
        }

        $userId     = $this->auth->id();
        $groupModel = new FamilyGroup();
        $groupId    = $groupModel->createWithAdmin($name, $userId);

        $catModel = new Category();
        foreach (Category::defaults() as $d) {
            $catModel->create($groupId, $d['name'], $d['color'], $d['icon']);
        }

        $this->auth->setGroup($groupId);
        $this->redirect('calendar');
    }

    public function invite(array $p = []): void {
        $this->requireAuth();
        $data    = $this->body();
        $email   = trim($data['email'] ?? '');
        $groupId = $this->auth->groupId();
        $userId  = $this->auth->id();

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Email inválido'], 422);
        }

        $invModel = new Invitation();
        $token    = $invModel->generate($groupId, $email, $userId);
        $base     = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/');
        $link     = "$base/group/accept/$token";

        $this->json(['success' => true, 'link' => $link]);
    }

    public function accept(array $p = []): void {
        $token    = $p['token'] ?? '';
        $invModel = new Invitation();
        $inv      = $invModel->findValid($token);

        if (!$inv) {
            http_response_code(400);
            echo '<h2>Invitación inválida o expirada.</h2>';
            return;
        }

        if ($this->auth->check()) {
            $groupModel = new FamilyGroup();
            $groupModel->addMember($inv['group_id'], $this->auth->id(), 'member');
            $invModel->markAccepted($token);
            $this->auth->setGroup($inv['group_id']);
            $this->redirect('calendar');
        } else {
            $_SESSION['invitation_token'] = $token;
            $_SESSION['invitation_email'] = $inv['email'];
            $this->redirect('register');
        }
    }
}
