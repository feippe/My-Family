<?php
namespace App\Core;

class App {
    public function __construct() {
        $cfg = require BASE_PATH . '/app/Config/app.php';
        date_default_timezone_set($cfg['timezone']);
        session_name($cfg['session_name']);
        session_start();

        $router = new Router();
        $this->registerRoutes($router);
        $router->dispatch();
    }

    private function registerRoutes(Router $r): void {
        // Auth
        $r->get('login',     'AuthController@loginForm');
        $r->post('login',    'AuthController@login');
        $r->get('register',  'AuthController@registerForm');
        $r->post('register', 'AuthController@register');
        $r->get('logout',    'AuthController@logout');

        // Calendar
        $r->get('',         'CalendarController@index');
        $r->get('calendar', 'CalendarController@index');

        // Events API
        $r->get('api/events',         'EventController@apiIndex');
        $r->post('api/events',        'EventController@apiCreate');
        $r->get('api/events/{id}',    'EventController@apiShow');
        $r->put('api/events/{id}',    'EventController@apiUpdate');
        $r->delete('api/events/{id}', 'EventController@apiDelete');

        // Notifications API
        $r->get('api/notifications',            'NotificationController@apiIndex');
        $r->get('api/notifications/count',      'NotificationController@apiUnreadCount');
        $r->post('api/notifications/{id}/read', 'NotificationController@apiRead');
        $r->post('api/notifications/read-all',  'NotificationController@apiReadAll');

        // Push API
        $r->post('api/push/subscribe',   'PushController@subscribe');
        $r->post('api/push/unsubscribe', 'PushController@unsubscribe');
        $r->get('api/push/vapid-key',    'PushController@vapidKey');

        // Group
        $r->get('group',               'GroupController@index');
        $r->get('group/create',        'GroupController@createForm');
        $r->post('group',              'GroupController@store');
        $r->post('group/invite',       'GroupController@invite');
        $r->get('group/accept/{token}','GroupController@accept');

        // Settings
        $r->get('settings',                        'SettingsController@profile');
        $r->post('settings/profile',               'SettingsController@updateProfile');
        $r->get('settings/categories',             'SettingsController@categories');
        $r->post('settings/categories',            'SettingsController@createCategory');
        $r->put('settings/categories/{id}',        'SettingsController@updateCategory');
        $r->delete('settings/categories/{id}',     'SettingsController@deleteCategory');
        $r->get('settings/members',                'SettingsController@members');
    }
}
