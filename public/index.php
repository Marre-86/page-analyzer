<?php

// Подключение автозагрузки через composer

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Hexlet\Code\Connection;
use Hexlet\Code\Query;
use Hexlet\Code\Misc;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use DiDom\Document;

session_start();

if (!isset($_SESSION['start'])) {
    $pdo = Connection::get()->connect();
    if (Misc\tableExists($pdo, "url_checks")) {
        $pdo->exec("TRUNCATE url_checks");
    }
    if (Misc\tableExists($pdo, "urls")) {
        $pdo->exec("TRUNCATE urls CASCADE");
    }
    $_SESSION['start'] = true;
}

if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

try {
    $pdo = Connection::get()->connect();
    if (!Misc\tableExists($pdo, "urls")) {
        $pdo->exec("CREATE TABLE urls (id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                                       name varchar(255),
                                       created_at timestamp)");
    }
    if (!Misc\tableExists($pdo, "url_checks")) {
        $pdo->exec("CREATE TABLE url_checks (id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                                             url_id bigint REFERENCES urls (id),
                                             status_code smallint,
                                             h1 varchar(255),
                                             title varchar(255),
                                             description text,
                                             created_at timestamp)");
    }
} catch (\PDOException $e) {
    echo $e->getMessage();
}

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

if (AppFactory::setContainer($container)) {        // для PhpStan нужно в if завернуть
    $app = AppFactory::setContainer($container);
}

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $params = ['greeting' => 'Путин хуйло'];
    return $this->get('renderer')->render($response, 'main.phtml', $params);
});

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($router) {
    $check['url_id'] = $args['url_id'];
    $check['date'] = date('Y-m-d H:i:s');
    $pdo = Connection::get()->connect();
    $checkedUrl = $pdo->query("SELECT name FROM urls WHERE id = {$args['url_id']}")->fetchColumn();
    try {
        $client = new Client();
        $guzzleResponse = $client->request('GET', $checkedUrl);
        $check['status_code'] = $guzzleResponse->getStatusCode();
    } catch (TransferException $e) {
        $this->get('flash')->addMessage('failure', 'Произошла ошибка при проверке, не удалось подключиться');
    }
    $document = new Document($checkedUrl, true);
    $h1 = optional($document->first('h1'));
    if ($h1 !== null) {
        $check['h1'] = $h1->text();
    }
    $title = optional($document->first('title'));
    if ($title !== null) {
        $check['title'] = $title->text();
    }
    $desc = optional($document->first('meta[name=description]'));
    if ($desc !== null) {
        $check['description'] = $desc->getAttribute('content');
    }
    if (isset($check['status_code'])) {
        try {
            $query = new Query($pdo, 'url_checks');
            $newId = $query->insertValuesChecks($check);
        } catch (\PDOException $e) {
            echo $e->getMessage();
        }
            $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    }
    return $response->withRedirect($router->urlFor('show_url_info', ['id' => $args['url_id']]), 302);
});

// 4
$app->post('/urls', function ($request, $response) use ($router) {
    $url = $request->getParsedBodyParam('url');
    $url['date'] = date('Y-m-d H:i:s');
    $errors = [];
    if ((filter_var($url['name'], FILTER_VALIDATE_URL) === false) or (!in_array(parse_url($url['name'], PHP_URL_SCHEME), ['http', 'https']))) {  // phpcs:ignore
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
        $newId = null;
        if (!isset($urlFound)) {
            try {
                $pdo = Connection::get()->connect();
                $query = new Query($pdo, 'urls');
                $newId = $query->insertValues($url['name'], $url['date']);
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
    return $this->get('renderer')->render($response->withStatus(422), "main.phtml", $params);
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
    $checks = $pdo->query("SELECT * FROM url_checks WHERE url_id = {$args['id']}")->fetchAll(\PDO::FETCH_ASSOC);
    $flashes = $this->get('flash')->getMessages();
    $params = ['url' => $urlFound, 'checks' => array_reverse($checks), 'flash' => $flashes];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('show_url_info');

// 1 index
$app->get('/urls', function ($request, $response) {
    $pdo = Connection::get()->connect();
    $allUrls = $pdo->query("SELECT * FROM urls")->fetchAll(\PDO::FETCH_ASSOC);
    $recentChecks = $pdo->query("SELECT DISTINCT ON (url_id) url_id, created_at, status_code
                                 FROM url_checks
                                 ORDER BY url_id, created_at DESC;")->fetchAll(\PDO::FETCH_ASSOC);
    $combined = array_map(function ($url) use ($recentChecks) {
        foreach ($recentChecks as $recCheck) {
            if ($url['id'] === $recCheck['url_id']) {
                $url['last_check_time'] = $recCheck['created_at'];
                $url['status_code'] = $recCheck['status_code'];
            }
        }
        return $url;
    }, $allUrls);
    $params = ['urls' => array_reverse($combined)];
    return $this->get('renderer')->render($response, 'list.phtml', $params);
})->setName('list');

$app->run();
