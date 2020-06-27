<?php

namespace App\Common\Google;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Google_Service_Sheets_Request;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Phalcon\Mvc\User\Component;

const tokenPath   = '/home/ubuntu/project/token.json';
const sheetIdPath = '/home/ubuntu/project/sheetId';

class GoogleSheets extends Component
{
    /**
     * Получает ID используемой Google Sheets
     *            (ID храним в файле sheetId)
     */
    public function getSheetId()
    {
        return file_get_contents(sheetIdPath) ?? null;
    }

    /**
     * Изменяет ID используемой Google Sheets
     *            (ID храним в файле sheetId)
     *
     * @param $newSheetId - на вход получаем параметр "ID новой таблицы"
     */
    public function changeSheetId($newSheetId)
    {
        file_put_contents(sheetIdPath, $newSheetId);
    }

    /**
     * Первичная настройка google_client.
     *
     * @throws \Google_Exception
     * @return Google_Client
     */
    public function configGoogle()
    {
        // В строке ниже мы создаём новый объект-пустышку для работы с пользователем Google
        $client = new Google_Client();

            // Настройка гугл клиента:
        // Получаем параметры пользователя Google
        $client->setAuthConfig('/home/ubuntu/project/client_secret.json');

        // Указываем метод авторизации
        $client->setApplicationName("OAuth2 Login");

        // Указываем области применения - Google Таблицы
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);

        // Указываем тип доступа "оффлайн", чтобы можно было обновлять текущий токен (ключ)
        // без дополнительного вмешательства пользователя (без постоянной авторизации каждые 10 минут)
        $client->setAccessType('offline');

        // Проверяем, есть ли файл по пути tokenPath (строка 13 текущего файла: "token.json")
        if (file_exists(tokenPath)) {

            // С помощью file_get_contents(..) достаём данные из файла
            // С помощью json_decode(..) приводим полученные данные в корректный для работы вид
            $accessToken = json_decode(file_get_contents(tokenPath), true);

            // Если файл не пуст, то используем полученный токен из файла
            // (условие "if ($переменная)" получает значение "true", когда "$переменная" существует и не пуста)
            if ($accessToken) {
                $client->setAccessToken($accessToken);
            }
        }

        // Проверка срока действия токена. "$client->isAccessTokenExpired()" передаёт значение "true",
        // если срок действия токена истёк или токен не корректен
        if ($client->isAccessTokenExpired()) {

            // Если срок действия истёк, то обновить токен. Иначе необходимо провести авторизацию пользователя.
            if ($client->getRefreshToken()) {

                // Используем обновлённый токен для получения доступа:
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Получаем от Google ссылку для авторизации в нашем приложении
                $auth_url = $client->createAuthUrl();

                // Переводим пользователя на страницу авторизации Google
                header('Location: ' . $auth_url);

                // Если мы получили новый код авторизации от Google, то записываем его в переменную $code
                $code = isset($_GET['code']) ? $_GET['code'] : null;

                // Если переменная $code существует, то:
                if (isset($code)) {
                    // В таком случае необходимо сгенерировать новый токен из полученного кода авторизации
                    $accessToken = $client->fetchAccessTokenWithAuthCode($code);

                    // Применяем новый токен для авторизации на сайте
                    $client->setAccessToken($accessToken);
                }
                // Перезапись токена в файл "token.json":
                file_put_contents(tokenPath, json_encode($client->getAccessToken()));
            }
        }

        return $client;
    }

    /**
     * Подготовка Google Sheets
     *            В данной функции мы возвращаем массив из:
     *              1) значений указанного листа таблицы
     *              2) используемого сервиса Google (Google Таблицы)
     *
     * @param $name
     * @param $range
     *
     * @throws \Google_Exception
     * @return array
     */
    private function googleSheetsPreparation($name, $range)
    {
        // Авторизируемся в системе Google
        $client = $this->configGoogle();

        // Указываем используемый текущим пользователем сервис Google: Google Таблицы
        $service = new Google_Service_Sheets($client);

        // В переменной "$range" храним название листа таблицы
        // и область редактирования (по умолчанию: от А1 до последней строки столбца С).
        // Храним данные в виде "НазваниеТаблицы!А1:С"
        $range = $name . $range;

        // Получаем полученные данные из указанного листа таблицы и области редактирования
        $response = $service->spreadsheets_values->get($this->getSheetId(), $range);

        // Значения ячеек записываем в переменную "$values"
        $values = $response->getValues();

        // Возвращаем полученные "значения строк листа таблицы" и "используемый сервис" обратно
        return [$values, $service];
    }

    /**
     * Добавление записи в таблицу.
     *
     * @param array  $array - массив добавляемых в лист таблицы значений
     * @param string $name  - название листа таблицы
     * @param string $range - область редактирования таблицы (по умолчанию: "A1:C")
     *
     * @throws \Google_Exception
     * @return bool
     */
    public function addRow($array, $name, $range = '!A1:C')
    {
        // Получаем значения из листа таблицы "$name" с областью редактирования "$range"
        $valuesService = $this->googleSheetsPreparation($name,  $range);

        // Переменная "$valuesService" - это массив, где
        //      ключ "0" - это значения строчек листа,
        //      ключ "1" - используемый сервис Google
        $values  = $valuesService[0];
        $service = $valuesService[1];

        // "count(..)" расчитывает количество элементов массива, следовательно
        // переменная "$countVal" хранит в себе количество заполненных строк таблицы "+1", где
        //      "+1" необходим для редактирования новой, пустой строки листа таблицы
        // например, если у нас в листе таблицы заполнены 5 строчек, то в переменной "$countVal" будет храниться значение "6"
        $countVal = count($values) + 1;

        // Добавление значений в конец таблицы
        // Создаём новый объект, предназначенный для работы со значениями ячеек листа таблицы
        $valueRange = new Google_Service_Sheets_ValueRange();

        // Устанавливаем значения, которые необходимо добавить в лист таблицы
        $valueRange->setValues(["values" => $array]);

            // Устанавливаем адреса ячеек, в которые необходимо добавить значения
        // В переменной "$rangeStart" записываем начало области: "stristr($range, ':', true)" - выводит текст до ":"
        // В переменной "$rangeEnd" записываем конец области: "stristr($range, ':')" - выводит текст после ":" (включая ":")
        //  "preg_replace('/\d/', '', $string)" - изменяет все цифры в строке $string на пустоту (иными словами - удаляет все цифры)
        $rangeStart = preg_replace('/\d/', '', stristr($range, ':', true));
        $rangeEnd = preg_replace('/\d/', '', stristr($range, ':'));
        // В итоге, используя данные по умолчанию ("А1:С") мы получим: "'название_листа'!А'номер_строки':С"
        $addRange = $name . $rangeStart . $countVal . $rangeEnd;

        // В конфигурационную переменную "$conf" задаём следующее - редактируем построчно
        $conf = ["valueInputOption" => "RAW"];

        // Передаём в Google Таблицы информацию о новой строке листа таблицы. В переменной "$response" храним ответ от Google
        $response = $service->spreadsheets_values->update($this->getSheetId(), $addRange, $valueRange, $conf);

        // Отправляем полученный результат обратно.
        //      Полученный результат содержит в себе данные о заполненных полях ИЛИ данные об ошибке.
        return $response;
    }

    /**
     * Удаление записи из таблицы.
     *
     * @param        $id
     * @param string $name
     * @param string $range
     *
     * @throws \Google_Exception
     * @return bool
     */
    public function deleteRow($id, $name, $range = '!A1:C')
    {
        $valuesService = $this->googleSheetsPreparation($name, $range);
        $values        = $valuesService[0];
        $service       = $valuesService[1];

        // Получаем имена всех листов таблицы в виде матрицы.
        //      Пример полученной матрицы:
        //          "название листа #1": "ID листа #1",
        //          "название листа #2": "ID листа #2",
        //          и так далее
        $matrix = $this->sheetNames(true);

            // Производим поиск удаляемой строки по полученному "$id".
        // Другими словами: ищем значение "$id" в первой колонке указанного листа
        $key = array_search($id, array_column($values, 0));

        // Записываем в "$requests" данные, которые будем отправлять в Google для удаления значений из листа таблицы
        $requests = [
            new Google_Service_Sheets_Request(
                [
                    'deleteRange' => [
                        'range'          => [
                            // Достаём ID нужного нам листа из составленной нами матрицы
                            'sheetId'       => $matrix[$name],
                            // Передаём номер строки, с которой необходимо начать удаление (указанная строка не удаляется)
                            'startRowIndex' => $key,
                            // Передаём следующий номер строки, чтобы на ней окончить удаление (она удаляется)
                            'endRowIndex'   => $key + 1,
                        ],
                        // Говорим Google о том, что удаление производится построчно
                        'shiftDimension' => 'ROWS',
                    ],
                ]
            ),
        ];

            // На самом деле удаление - это получение нового листа таблицы БЕЗ указанных нами строк
        // Получаем новую таблицу в соответствии с данными "$requests"
        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => $requests]);

        // Перезаписываем таблицу. Результат перезаписи храним в переменной "$response"
        $response = $service->spreadsheets->batchUpdate($this->getSheetId(), $batchUpdateRequest);

        // Отправляем полученный результат обратно.
        return $response;
    }

    /**
     * Изменение записи в таблице.
     *
     * @param        $array
     * @param string $name
     *
     * @param string $range
     *
     * @return bool
     */
    public function changeRow($array, $name, $range = '!A1:C')
    {
        $valuesService = $this->googleSheetsPreparation($name, $range);
        $values        = $valuesService[0];
        $service       = $valuesService[1];

        // Создаём переменную "$response", в которой будем хранить результаты изменения
        $response = null;

            // Изменение данных листа таблицы
        // Производим поиск изменяемой строки по первому значению массива "$array" (другими словами - по "ID")
        $key = array_search($array[0], array_column($values, 0)) + 1;

        // Если значение найдено, тогда:
        if ($key != 0) {
            // Создаём новый объект, предназначенный для работы со значениями ячеек листа таблицы
            $valueRange = new Google_Service_Sheets_ValueRange();

            // Устанавливаем новые значения из массива "$array"
            $valueRange->setValues(["values" => $array]);

            // Указываем, какие значения необходимо изменить (по аналогии со строкой 164 функции "addRow(..)")
            $rangeStart = preg_replace('/\d/', '', stristr($range, ':', true));
            $rangeEnd = preg_replace('/\d/', '', stristr($range, ':'));
            // В итоге, используя данные по умолчанию ("А1:С") мы получим: "'название_листа'!А'номер_строки':С"
            $addRange = $name . $rangeStart . $key . $rangeEnd;

            // Указываем для Google, что изменять нужно построчно
            $conf = ["valueInputOption" => "RAW"];

            // Передаём данные для изменения листа таблицы и храним полученные от Google данные об изменении в переменной "$response"
            $response = $service->spreadsheets_values->update($this->getSheetId(), $addRange, $valueRange, $conf);
        }

        // Отправляем полученный результат обратно.
        return $response;
    }

    /**
     * Получить все названия листов таблицы
     *
     * @param null|boolean $needId - это переменная-флаг.
     *                             Если она равна "null" (по умолчанию), то функция вернёт только массив названий
     *                             Если она равна "true", то функция вернёт матрицу ['название листа' => 'ID листа']
     *
     * @throws \Google_Exception
     * @return array
     */
    public function sheetNames($needId = null)
    {
        // Создаём массив для хранения имён листов таблицы
        $array = [];

        $client = $this->configGoogle();

        // Работа с таблицой:
        $service = new Google_Service_Sheets($client);

        // Получаем значения всех листов таблицы
        $response = $service->spreadsheets->get($this->getSheetId());

        // Если требуется вывести матрицу, то:
        if ($needId) {
            // С помощью цикла "foreach" проходим по каждому листу таблицы
            foreach ($response->getSheets() as $sheet) {
                // Достаём свойства текущего листа таблицы
                $sheetProperties = $sheet->getProperties();
                // В матрицу записываем ['Название листа' => 'ID листа']
                $array[$sheetProperties->title] = $sheetProperties->sheetId;
            }
            // Если требуется вывести только названия таблиц, то:
        } else {
            // С помощью цикла "foreach" проходим по каждому листу таблицы
            foreach ($response->getSheets() as $sheet) {
                // В массив добавляем значение 'Название листа'
                $array[] = $sheet->getProperties()->title;
            }
        }

        // Отправляем полученный результат обратно.
        return $array;
    }
}