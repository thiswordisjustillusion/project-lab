<?php

return [
    // Сервис для работы с моделями Phalcon на MongoDB.
    ['collectionManager', function () {
        return new \Phalcon\Mvc\Collection\Manager();
    }, true],

    // Сервис для работы с MongoDB
    ['mongo', function () {
        $mongo = new \Phalcon\Db\Adapter\MongoDB\Client();

        return $mongo->selectDatabase('project');
    }, true],

    // Сервис для работы с GoogleSheets
    ['google', function () {
        return new \App\Common\Google\GoogleSheets();
    }, true],

    // Сервис для логирования
    ['log', function () {
        return new \Phalcon\Logger\Adapter\File(ROOT . '/log/' . date('Ymd') . '.log');
    }, true]
];
