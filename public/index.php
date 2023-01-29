<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $params = ['greeting' => 'Путин хуйло'];
    return $this->get('renderer')->render($response, 'main.phtml', $params);
});

$app->post('/urls', function ($request, $response) use ($router) {                    // 4
    $url = $request->getParsedBodyParam('url');
    $cookies = json_decode($request->getCookieParam('cookie', json_encode([])), true);
    $url['id'] = uniqid();
    $url['date'] = date('Y-m-d H:i:s');
    $errors = [];
    if (filter_var($url['name'], FILTER_VALIDATE_URL) === false) {
        $errors['name'] = 'Некорректный URL';
    }
    if (strlen($url['name']) < 1) {
        $errors['name'] = 'URL не должен быть пустым';
    }
    if (count($errors) === 0) {
        $url['name'] = parse_url($url['name'], PHP_URL_SCHEME) . "://" . parse_url($url['name'], PHP_URL_HOST);
        foreach ($cookies as $cookie) {
            echo $cookie['name'] . " - " . $url['name'];
            if ($cookie['name'] == $url['name']) {
                $nameFound = $cookie;
                $idFound = $cookie['id'];
            }
        }
        if (!$nameFound) {
            $cookies[] = $url;
            $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        } else {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
        }
        $encodedCookies = json_encode($cookies);
        return $response->withHeader('Set-Cookie', "cookie={$encodedCookies};  path=/")->withRedirect($router->urlFor('show_url_info', ['id' => $idFound ?? $url['id']]), 302);
    }
    $params = ['url' => $url, 'errors' => $errors];
    return $this->get('renderer')->render($response, "main.phtml", $params);
});

$app->get('/urls/{id}', function ($request, $response, $args) {        // 2 show
    $cookies = json_decode($request->getCookieParam('cookie', json_encode([])), true);
    foreach ($cookies as $cookie) {
        if ($cookie['id'] == $args['id']) {
            $urlFound = $cookie;
        }
    }
    if (!$urlFound) {
        return $response->withStatus(404);
    }
    $flashes = $this->get('flash')->getMessages();
    $params = ['url' => $urlFound, 'flash' => $flashes];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('show_url_info');

$app->get('/urls', function ($request, $response) {        // 1 index
    $cookies = json_decode($request->getCookieParam('cookie', json_encode([])), true);
    $params = ['urls' => array_reverse($cookies)];
    return $this->get('renderer')->render($response, 'list.phtml', $params);
})->setName('list');

$app->run();
