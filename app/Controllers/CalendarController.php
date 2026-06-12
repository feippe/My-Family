<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;
use App\Models\User;

class CalendarController extends Controller {
    public function index(array $p = []): void {
        $this->requireAuth();

        $groupId = $this->auth->groupId();
        if (!$groupId) $this->redirect('group/create');

        $categories = (new Category())->forGroup($groupId);
        $members    = (new User())->getGroupMembers($groupId);

        $this->view->render('calendar/index', [
            'categories' => $categories,
            'members'    => $members,
        ]);
    }
}
