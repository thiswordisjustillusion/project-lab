<?php

namespace App\Controllers;

use Phalcon\Mvc\Controller;

/**
 * Контроллер для страниц с ошибками.
 */
class ErrorsController extends Controller
{
    /**
     * Страница с ошибкой 404 – страница не найдена.
     */
    public function indexAction($message = null)
    {
        $this->view->setVar('message', $message);
    }
}
