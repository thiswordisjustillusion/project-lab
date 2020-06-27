<?php

return [
    // Регистрация объекта для работы с сессиями, которых хранятся в файлах
    // (стандартный подход), при этом сессия инициализируется сразу.
    ['session', function () {
        $session = new \Phalcon\Session\Adapter\Files();
        $session->start();

        return $session;
    }, true],

    // Регистрация стандартного шаблонизатора Phalcon с указанием корня
    // директории с шаблонами.
    ['view', function () {
        $view = new \Phalcon\Mvc\View();
        $view->setViewsDir(VIEWS);

        return $view;
    }, true],

    // Регистрация диспетчера c пространством имён для контроллеров.
    ['dispatcher', function () {
        // Создаем менеджер событий
        $eventsManager = new \Phalcon\Events\Manager();

        // Прикрепляем слушателя с обработкой исключения при некорректном URI
        $eventsManager->attach(
            'dispatch:beforeException',
            function (\Phalcon\Events\Event $event, $dispatcher, \Exception $exception) {
                $dispatcher->forward([
                    'controller' => 'errors',
                    'action'     => 'index',
                    'params'     => [$exception->getMessage()]
                ]);

                return false;
            }
        );


        $dispatcher = new \Phalcon\Mvc\Dispatcher();

        // Прикрепляем менеджер событий к диспетчеру
        $dispatcher->setEventsManager($eventsManager);
        // Определяем нейспейс, где необходимо искать контроллеры
        $dispatcher->setDefaultNamespace('App\\Controllers');

        return $dispatcher;
    }, true],

    // Сервис для работы с моделями Phalcon на MongoDB.
    ['collectionManager', function () {
        return new \Phalcon\Mvc\Collection\Manager();
    }, true],

    // Сервис для работы с MongoDB
    ['mongo', function () {
        $mongo = new \Phalcon\Db\Adapter\MongoDB\Client();

        return $mongo->selectDatabase('project');
    }, true],

    ['router', function () {
        $router = new \Phalcon\Mvc\Router(false);
        // Удаляем слеш в конце URL'а
        $router->removeExtraSlashes(true);
        // Устанавливаем дефолтные экшены и контроллер, если они не заданы в URL'е
        $router->setDefaults([
            'controller' => 'index',
            'action'     => 'index',
        ]);
        // Устанавливаем неймспейс, где надо искать контроллеры
        $router->setDefaultNamespace("App\\Controllers");
        // Стандартный маршрут для вызова контроллера без экшена
        $router->add('/:controller', [
            'controller' => 1,
        ]);
        // Стандартный маршрут для вызова контроллера
        $router->add('/:controller/:action/:params', [
            'controller' => 1,
            'action'     => 2,
            'params'     => 3,
        ]);

        // Маршрут для списка постов
        $router->addGet('/api/v1/posts', [
            'controller' => 'posts_api',
            'action'     => 'list',
        ]);
        // Маршрут для добавления поста
        $router->addPost('/api/v1/posts', [
            'controller' => 'posts_api',
            'action'     => 'add',
        ]);
        // Маршрут для получения поста по slug
        $router->addGet('/api/v1/posts/{slug}', [
            'controller' => 'posts_api',
            'action'     => 'show',
        ]);
        // Маршрут для изменения существующего поста по slug
        $router->addPut('/api/v1/posts/{slug}', [
            'controller' => 'posts_api',
            'action'     => 'update',
        ]);
        // Маршрут для удаления поста по slug
        $router->addDelete('/api/v1/posts/{slug}', [
            'controller' => 'posts_api',
            'action'     => 'remove',
        ]);

        return $router;
    }, true],

    // Сервис для работы с массивом конфигурации
    ['settings', function () {
        $settingsPath = ROOT . '/resources/config/settings.php';

        if (is_readable($settingsPath)) {
            return new \Phalcon\Config(require $settingsPath);
        } else {
            throw new \Exception('Файл настроек не найден');
        }
    }, true],

    // Сервис для работы с GoogleSheets
    ['google', function () {
        return new \App\Common\Google\GoogleSheets();
    }, true],

    // Сервис для логирования
    ['log', function () {
        return new \Phalcon\Logger\Adapter\File(ROOT . '/log/' . date('Ymd') . '.log');
    }, true],
];
