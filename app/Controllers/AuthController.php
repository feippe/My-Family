<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\FamilyGroup;
use App\Models\Category;
use App\Models\Invitation;
use App\Models\PasswordReset;
use App\Services\MailService;

class AuthController extends Controller {
    private User $users;

    public function __construct() {
        parent::__construct();
        $this->users = new User();
    }

    public function loginForm(array $p = []): void {
        if ($this->auth->check()) $this->redirect('calendar');
        $this->view->render('auth/login', [], 'auth');
    }

    public function login(array $p = []): void {
        $email    = trim($this->input('email', ''));
        $password = $this->input('password', '');
        $errors   = [];

        if (!$email)    $errors[] = 'El email es requerido.';
        if (!$password) $errors[] = 'La contraseña es requerida.';

        if (!$errors) {
            $user = $this->users->findByEmail($email);
            if ($user && $this->users->verify($password, $user['password'])) {
                $groupId = $this->users->getGroupId($user['id']);
                $this->auth->login($user['id'], $groupId);
                $this->redirect($groupId ? 'calendar' : 'group/create');
            } else {
                $errors[] = 'Email o contraseña incorrectos.';
            }
        }

        $this->view->render('auth/login', ['errors' => $errors, 'email' => $email], 'auth');
    }

    public function registerForm(array $p = []): void {
        if ($this->auth->check()) $this->redirect('calendar');
        $prefillEmail = $_SESSION['invitation_email'] ?? null;
        $this->view->render('auth/register', ['prefillEmail' => $prefillEmail], 'auth');
    }

    public function register(array $p = []): void {
        $name    = trim($this->input('name', ''));
        $email   = trim($this->input('email', ''));
        $pass    = $this->input('password', '');
        $pass2   = $this->input('password_confirm', '');
        $gName   = trim($this->input('group_name', ''));
        $errors  = [];
        $hasInvite = !empty($_SESSION['invitation_token']);

        if (!$name)                                       $errors[] = 'El nombre es requerido.';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (strlen($pass) < 8)                            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($pass !== $pass2)                             $errors[] = 'Las contraseñas no coinciden.';
        if (!$hasInvite && !$gName)                       $errors[] = 'El nombre del grupo familiar es requerido.';

        if (!$errors && $this->users->findByEmail($email)) {
            $errors[] = 'Este email ya está registrado.';
        }

        if (!$errors) {
            $userId = $this->users->create($name, $email, $pass);

            if ($hasInvite) {
                $invModel   = new Invitation();
                $invitation = $invModel->findValid($_SESSION['invitation_token']);
                if ($invitation) {
                    $groupModel = new FamilyGroup();
                    $groupModel->addMember($invitation['group_id'], $userId, 'member');
                    $invModel->markAccepted($_SESSION['invitation_token']);
                    $this->auth->login($userId, $invitation['group_id']);
                    unset($_SESSION['invitation_token'], $_SESSION['invitation_email']);
                    $this->redirect('calendar');
                }
            }

            $groupModel = new FamilyGroup();
            $groupId    = $groupModel->createWithAdmin($gName, $userId);
            $catModel   = new Category();
            foreach (Category::defaults() as $d) {
                $catModel->create($groupId, $d['name'], $d['color'], $d['icon']);
            }
            $this->auth->login($userId, $groupId);
            $this->redirect('calendar');
        }

        $this->view->render('auth/register', [
            'errors'     => $errors,
            'name'       => $name,
            'email'      => $email,
            'group_name' => $gName,
        ], 'auth');
    }

    public function logout(array $p = []): void {
        $this->auth->logout();
        $this->redirect('login');
    }

    public function forgotForm(array $p = []): void {
        if ($this->auth->check()) $this->redirect('calendar');
        $this->view->render('auth/forgot', [], 'auth');
    }

    public function forgot(array $p = []): void {
        $email = trim($this->input('email', ''));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view->render('auth/forgot', ['errors' => ['Email inválido.']], 'auth');
            return;
        }

        // Always show the same success message (don't leak whether email exists)
        $user = $this->users->findByEmail($email);
        if ($user) {
            $resetModel = new PasswordReset();
            $token = $resetModel->create($email);
            $appUrl = rtrim((require BASE_PATH . '/app/Config/app.php')['url'], '/');
            $resetUrl = $appUrl . '/reset-password/' . $token;

            $mail = new MailService();
            $html = $mail->buildPasswordResetEmail($user['name'], $resetUrl);
            $mail->send($email, 'Restablecer contraseña — Familia', $html);
        }

        $this->view->render('auth/forgot', ['sent' => true], 'auth');
    }

    public function resetForm(array $p = []): void {
        if ($this->auth->check()) $this->redirect('calendar');
        $token = $p['token'] ?? '';
        $resetModel = new PasswordReset();
        $row = $resetModel->findValid($token);
        if (!$row) {
            $this->view->render('auth/reset', ['invalid' => true], 'auth');
            return;
        }
        $this->view->render('auth/reset', ['token' => $token], 'auth');
    }

    public function reset(array $p = []): void {
        $token = $p['token'] ?? '';
        $pass  = $this->input('password', '');
        $pass2 = $this->input('password_confirm', '');

        $resetModel = new PasswordReset();
        $row = $resetModel->findValid($token);
        if (!$row) {
            $this->view->render('auth/reset', ['invalid' => true], 'auth');
            return;
        }

        $errors = [];
        if (strlen($pass) < 8)  $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($pass !== $pass2)   $errors[] = 'Las contraseñas no coinciden.';

        if ($errors) {
            $this->view->render('auth/reset', ['token' => $token, 'errors' => $errors], 'auth');
            return;
        }

        $user = $this->users->findByEmail($row['email']);
        if ($user) {
            $this->users->setPassword($user['id'], $pass);
        }
        $resetModel->delete($token);

        $this->view->render('auth/login', ['success' => '¡Contraseña restablecida! Ya podés iniciar sesión.'], 'auth');
    }
}
