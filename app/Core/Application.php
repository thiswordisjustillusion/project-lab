<?php

namespace App\Core;

use Phalcon\Mvc\View;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Application as BaseApplication;
use App\Controllers\ErrorsController;

class Application extends BaseApplication
{
    /**
     * В конструкторе происходит инициализация сервисов и приложения в целом.
     *
     * @return void
     */
    public function __construct()
    {
        // Создаем DI с дефолтным набором сервисов
        $di = new FactoryDefault();

        // Добавляем в DI компоненты описанные с впециальном файле
        $services = include ROOT . '/resources/config/services.php';
        foreach ($services as $service) {
            $di->set($service[0], $service[1], (isset($service[2]) ? $service[2] : false));
        }

        parent::__construct($di);
    }

    /**
     * Метод оборачивает такой же стандартный метод родителя для того, чтобы
     * при обработке исключения был доступен тот же функционал, что и при
     * обработке самого запроса.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $response = parent::handle();
            $response->send();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}
