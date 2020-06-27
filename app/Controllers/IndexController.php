<?php

namespace App\Controllers;

use Phalcon\Mvc\Controller;

/**
 * Контроллер для передачи полученных со страничек данных в обработчик google.
 *    (обработчик google находится в файле app/Common/Google/GoogleSheets.php)
 */
class IndexController extends Controller
{
    /**
     * Страница "Главная".
     *
     * @link /index/index
     */
    public function indexAction()
    {
        // Запускает функцию baseInfo - она находится в конце текущего файла
        // $this->baseInfo() - означает, что мы обращаемся к функции baseInfo() в рамках текущего класса (строчка 88)
        $this->baseInfo();
    }

    /**
     * Страница "Удалить запись".
     *
     *
     * @link /index/delete
     */
    public function deleteAction()
    {
        $this->baseInfo();
    }

    /**
     * Страница "Изменить запись".
     *
     * @link /index/edit
     */
    public function editAction()
    {
        $this->baseInfo();
    }

    /**
     * Метод "Удалить запись".
     *
     *
     * @link /index/deleterow
     */
    public function deleteRowAction()
    {
        // В данной функции мы получаем переменные $_GET['deleteRow'] и $_GET['name']

        // Необходимо:
        // 1. добавить отправку данных для последующей обработки в файле app/Common/Google/GoogleSheets.php
        // 2. возвращать полученные от обработчика данные обратно на страницу изменения записи
    }

    /**
     * Метод "Изменить запись".
     *
     * @link /index/editrow
     */
    public function editRowAction()
    {
        // В данной функции мы получаем переменные $_GET['array'] (массив из трёх переменных, где первая - это id записи)
        // и $_GET['name'] (название листа таблицы)

        // Необходимо:
        // 1. добавить отправку данных для последующей обработки в файле app/Common/Google/GoogleSheets.php
        // 2. возвращать полученные от обработчика данные обратно на страницу изменения записи
    }

    /**
     * Метод "Добавить запись".
     *
     * @link /index/add
     */
    public function addAction()
    {
        // Передаёт полученные параметры "array" и "name" в обработчик для
        // добавления значений в таблицу
        if ($_GET['array'] && $_GET['name']) {
            // Результат выполнения "addrow(..)" записываем в переменную result
            $result = $this->google->addRow($_GET['array'], $_GET['name']);
            // Выводим полученный результат result обратно в скрипт на страницу index.phtml
            return $this->response
                // Формат json - это текстовый формат обмена данными с javascript
                ->setContentType('application/json')
                ->setJsonContent(
                    [
                        'result' => $result,
                    ]
                );
        }

        return false;
    }

    /**
     * Передаёт обязательные данные на все страницы
     */
    private function baseInfo()
    {
        // Проверка авторизации текущего пользователя
        $this->google->configGoogle();

        // Получаем названия листов таблиц в переменную names
        $names = $this->google->sheetNames();
        // Передаём переменную names на страничку .phtml (в нашем случае - в файл header.phtml)
        $this->view->setVar('names', $names);

        // Получаем ID таблицы в переменную sheetId для предоставления ссылки на эту таблицу
        $sheetId = $this->google->getSheetId();
        // Передаём переменную sheetId на страничку .phtml (в нашем случае - в файл header.phtml)
        $this->view->setVar('sheetId', $sheetId);
    }

    /**
     * Выбрать другую таблицу
     *
     * @link /index/changeSheetId
     */
    public function changeSheetIdAction()
    {
        // Передаёт полученный параметр "newLink" в обработчик changeSheetId для смены таблицы
        if ($_GET['newLink']) {
            $this->google->changeSheetId($_GET['newLink']);
        }
    }
}
