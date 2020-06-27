<?php

// Константа указывает на корень проекта
define('ROOT', __DIR__ . '/..');
// Константа указывает на корень приложения
define('APP', ROOT . '/app');
// Константа указывает на отображения приложения
define('VIEWS', ROOT . '/resources/views');
// Константа для формирования ссылок на сайт
define('URL', 'http://' . $_SERVER['HTTP_HOST'] . '/');

// Выводим все ошибки
error_reporting(E_ALL);
ini_set("display_errors", 1);

require ROOT . '/vendor/autoload.php';

// Запускаем приложение
$app = new \App\Core\Application();
$app->handle();
