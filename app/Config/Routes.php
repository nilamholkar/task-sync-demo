<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
// $routes->get('/', 'Home::index');
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::authenticate');
$routes->get('/logout', 'Auth::logout');

$routes->get('/dashboard', 'Dashboard::index');

$routes->get('/users', 'Users::index');
$routes->get('/users/create', 'Users::create');
$routes->post('/users/store', 'Users::store');
$routes->get('/users/edit/(:num)', 'Users::edit/$1');
$routes->post('/users/update/(:num)', 'Users::update/$1');
$routes->get('/users/delete/(:num)', 'Users::delete/$1');
$routes->get('/users/toggle-status/(:num)', 'Users::toggleStatus/$1');
$routes->get('/github-test', 'GitHubTest::index');
$routes->get(
    '/sync-test/github-to-app',
    'SyncTest::githubToApp'
);
$routes->get(
    '/api/tasks',
    'TaskApi::index'
);

$routes->get(
    '/api/tasks/(:num)',
    'TaskApi::show/$1'
);

$routes->post(
    '/api/tasks',
    'TaskApi::create'
);

$routes->patch(
    '/api/tasks/(:num)',
    'TaskApi::update/$1'
);

$routes->delete(
    '/api/tasks/(:num)',
    'TaskApi::delete/$1'
);

$routes->get(
    '/worker-test',
    'WorkerTest::index'
);

$routes->post(
    '/api/webhooks/github',
    'GitHubWebhook::receive'
);

$routes->get('/api/conflicts', 'ConflictApi::index');
$routes->get('/api/conflicts/(:num)', 'ConflictApi::show/$1');
$routes->post('/api/conflicts/(:num)/resolve', 'ConflictApi::resolve/$1');
