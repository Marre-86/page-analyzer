<?php

// Подключение автозагрузки через composer

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Hexlet\Code\Connection;
use Hexlet\Code\Query;
use Hexlet\Code\Misc;

if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

try {
//    Connection::get()->connect();
//    echo 'A connection to the PostgreSQL database sever has been established successfully.<br>';
    $pdo = Connection::get()->connect();
    if (!Misc\tableExists($pdo, "urls")) {
//        $pdo->exec("TRUNCATE urls");
//    } else {
        $pdo->exec("CREATE TABLE urls (id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY, name varchar(255), created_at timestamp)"); // phpcs:ignore
    }
//    $pdo->exec("CREATE TABLE foxes (name varchar, slug varchar);");
//    echo 'An instance of database connection has been created successfully.<br>';
//    echo 'A table has been created successfully.';
//    $pdo->exec("INSERT INTO foxes VALUES ('black fox', 'bf'), ('red fox', 'rf'), ('iridescent fox', 'if');");
//    $query = new Query($pdo, 'foxes');
//    $query->insertValues('diamond fox', 'df');
//    print_r($pdo->query("SELECT * FROM foxes;")->fetchAll(\PDO::FETCH_ASSOC));
} catch (\PDOException $e) {
    echo $e->getMessage();
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
// 4
$app->post('/urls', function ($request, $response) use ($router) {
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
        $pdo = Connection::get()->connect();
        $currentUrls = $pdo->query("SELECT * FROM urls")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($currentUrls as $item) {
            if ($item['name'] === $url['name']) {
                $urlFound = $item;
                $idFound = $item['id'];
            }
        }
        if (!isset($urlFound)) {
            try {
                $pdo = Connection::get()->connect();
                $query = new Query($pdo, 'urls');
                $newId = $query->insertValues($url['name'], $url['date']);
                echo $newId;
            } catch (\PDOException $e) {
                echo $e->getMessage();
            }
            $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        } else {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
        }
        return $response->withRedirect($router->urlFor('show_url_info', ['id' => $idFound ?? $newId]), 302);
    }
    $params = ['url' => $url, 'errors' => $errors];
    return $this->get('renderer')->render($response, "main.phtml", $params);
});
// 2 show
$app->get('/urls/{id}', function ($request, $response, $args) {
    $pdo = Connection::get()->connect();
    $allUrls = $pdo->query("SELECT * FROM urls")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($allUrls as $item) {
        if ($item['id'] == $args['id']) {
            $urlFound = $item;
        }
    }
    if (!isset($urlFound)) {
        return $response->withStatus(404);
    }
    $flashes = $this->get('flash')->getMessages();
    $params = ['url' => $urlFound, 'flash' => $flashes];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('show_url_info');
// 1 index
$app->get('/urls', function ($request, $response) {
    $pdo = Connection::get()->connect();
    $allUrls = $pdo->query("SELECT * FROM urls")->fetchAll(\PDO::FETCH_ASSOC);
    $params = ['urls' => array_reverse($allUrls)];
    return $this->get('renderer')->render($response, 'list.phtml', $params);
})->setName('list');

$app->run();
