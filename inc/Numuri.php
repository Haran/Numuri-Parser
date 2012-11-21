<?php

/**
 * Класс парсинга numuri.lv
 *
 * В качестве исключений выбрасывает SoapFault, соответственно, если подключать
 * класс вне контекста SOAP, необходимо сделать extend стандартного Exception.
 *
 * Публичный метод только один: getNumber( string $number )
 */
class Numuri
{

    /**
     * URL начальной страницы
     * @var string
     */
    private $init_url = 'http://www.numuri.lv/default.aspx';

    /**
     * URL, куда отправляется POST
     * @var string
     */
    private $post_url = 'http://www.numuri.lv/default.aspx';

    /**
     * Name текстбокс-полей
     * @var string
     */
    private $txtBoxName = 'nusa:id_txtBox_';

    /**
     * Айдишник текстбокс-полей
     * @var string
     */
    private $txtBoxId = 'nusa_id_txtBox_';

    /**
     * Маскировка под какой-то браузер
     * @var string
     */
    private $useragent = 'Opera/9.80 (Windows NT 6.1; WOW64; U; en) Presto/2.10.289 Version/12.02';

    /**
     * Номер телефона, задаваемый через getNumber
     * @var string
     */
    private $phone;

    /**
     * Начальная часть таймера
     * @var float
     */
    private $starttime;

    /**
     * Массив, содержащий данные $_POST
     * @var array
     */
    private $data;


    /**
     * Старт таймера
     *
     * @return void
     */
    private function initTimer()
    {
        $this->starttime = explode(' ', microtime());
        $this->starttime = $this->starttime[1] + $this->starttime[0];
    }


    /**
     * Завершение работы таймера таймера
     *
     * @return float
     */
    private function endTimer()
    {
        $endtime = explode(' ', microtime());
        return sprintf('%.5f', ($endtime[0] + $endtime[1] - $this->starttime) );
    }


    /**
     * Получение стартовой страницы
     *
     * @return string
     */
    private function retrieveIndex()
    {

        $options = array(
            CURLOPT_URL            => $this->init_url,
            CURLOPT_HEADER         => true,
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => $this->useragent,
        );

        $ch = curl_init();
              curl_setopt_array($ch, $options);

        $ce = curl_exec( $ch );
              curl_close( $ch );

        return $ce;

    }


    /**
     * Отправка POST и получение результата
     *
     * @return string
     */
    private function sendPost()
    {

        $options = array(
            CURLOPT_URL            => $this->post_url,
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $this->data,
            CURLOPT_REFERER        => $this->init_url,
            CURLOPT_USERAGENT      => $this->useragent,
        );

        $ch = curl_init();
              curl_setopt_array($ch, $options);

        $ce = curl_exec( $ch );
              curl_close( $ch );

        return $ce;

    }


    /**
     * Вычисление номера контрольного поля
     *
     * @param string $keyfield
     * @param int $c
     * @throws SoapFault
     * @return int
     */
    private function r( $keyfield, $c )
    {
        if( !function_exists("D") ) {
            throw new SoapFault( "Server", "Function D() is not defined" );
        }

        return D( $keyfield ) % $c;
    }


    /**
     * Выдирание таблицы из результата и ее парсинг в ассоциативный массив
     *
     * @param string $input
     * @throws SoapFault
     * @return array
     */
    private function parseTable( $input ) {

        $table = '';

        if ( preg_match('%<table.+class="number_block".+</table>%si', $input, $table) ) {

            // Разбиваем таблицу по рядам
            $table = $table[0];
            $trows = explode("</td></tr>", $table);
            $table = array();

            for( $i=0; $i<count($trows); $i++ ) {

                // Разбиваем ряды по ячейкам
                $table[$i] = explode( '</td><td>', $trows[$i]);

                // Обходим полученные ячейки
                $table[$i] = array_map(

                // ... лямбда-функцией
                    function($element) {

                        // Чистим их от тэгов
                        $element = trim(strip_tags( $element ));

                        // Ушлые ребятки умышленно портят html-сущности, убирая из них
                        // точку с запятой, уповая на особенность браузера дополнять их автоматически. Фиксим и отдаём:
                        return html_entity_decode( preg_replace( '/(&#[\d]+)/i', '$1;', $element ) , ENT_NOQUOTES, 'UTF-8');

                    }, $table[$i]

                );

                // Очистка массива от пустых элементов
                if( empty($table[$i][0]) ) {
                    unset($table[$i]);
                }

            }

            $trows = null;
            $final = array();

            // Превращаем результат в ассоциативный массив отфильтровывая пробелы и диакритику
            foreach( $table as $trows ) {
                $key = strtolower( str_replace( ' ', '', iconv( 'UTF-8', 'US-ASCII//TRANSLIT', $trows[0] ) ) );
                $final[$key] = $trows[1];
            }

            return $final;

        }
        else {

            // Таблица по регулярке не найдена
            throw new SoapFault( "Server", "Couldn't find table in resultset" );

        }

    }


    /**
     * Главный метод
     * Не особо он объектный, конечно, но в рамках текущей задачи - так проще.
     *
     * @param $number
     * @return array
     * @throws SoapFault
     */
    public function getNumber( $number )
    {

        $this->initTimer();

        // Очистка вводимых данных
        $this->phone = preg_replace('/[^0-9]/', "", $number);

        // Проверка вводимых данных
        if( empty($this->phone) || strlen($this->phone)>8 || strlen($this->phone)<2 ) {
            throw new SoapFault( "Server", "Invalid phone number" );
        }


        /**
         * ------------------------------------------------------------------------
         * Получаем данные
         */
        $ce = $this->retrieveIndex();

        /**
         * ------------------------------------------------------------------------
         * Выцепляем ключевые поля
         */
        preg_match('/<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="(.+)"/', $ce, $viewstate);
        preg_match('/<input name="nusa:keyField" id="nusa_keyField" type="hidden" value="(.+)"/', $ce, $keyfield);

        $viewstate = $viewstate[1];
        $keyfield  = $keyfield[1];

        if( empty($viewstate) || empty($keyfield) ) {
            throw new SoapFault( "Server", "Invalid keyfield or viewstate data retrieved. Can't continue" );
        }


        /**
         * ------------------------------------------------------------------------
         * Выдираем функцию D
         * Вычисляет контрольную сумму по keyfield ключу. Функция меняется динамически,
         * поэтому ее преобразование тоже динамическое. Возможно, предусмотрены не все случаи.
         */
        $funcD = array();

        // Получаем текст JS-функции
        preg_match('/(function D\(A\).+return h;\})/i', $ce, $funcD);

        if( empty($funcD) ) {
            throw new SoapFault( "Server", "No D-function found. Can't continue." );
        }

        // Превращаем ее в PHP-функцию
        $funcD = str_replace(
            array('D(A)', 'var ', 'A.length', 'A.charCodeAt(i)'),
            array('D($A)', '', 'strlen($A)', ' ord($A{$i})'),
            preg_replace(
                '/(\s|\*|\{|;)(h|i)/i',
                '$1\$$2',
                $funcD[0]
            )
        );

        eval($funcD);


        /**
         * ------------------------------------------------------------------------
         * Определяем количество txtBox полей и то, какое поле
         * будет являться реальным контрольным полем из всех ловушек
         */
        $matches  = array();

        // Получаем значения реального поля (первый элемент массива [1]) и общее количество txtBox полей, включая фейковое (второй элемент [2])
        preg_match('/<input\s+name.+type="submit".+onclick="r\( document\.getElementById\(\''.$this->txtBoxId.'(\d+)\'\),.+,.+,(\d+)\)/i', $ce, $matches);

        if( empty($matches) || count($matches)<3 || empty($matches[1]) || empty($matches[2]) ) {
            throw new SoapFault( "Server", "Can't determine txtBoxes amount or real field" );
        }

        // Присваиваем полученные значения
        $txtBoxes = $matches[2];
        $realBox  = $matches[1];


        /**
         * ------------------------------------------------------------------------
         * Вычисляем контрольное поле
         */
        $controlField = $this->r($keyfield, $txtBoxes);


        /**
         * ------------------------------------------------------------------------
         * Формируем массив на отправку $_POST
         */
        $this->data = array(
            '__VIEWSTATE' 		=> $viewstate,
            'nusa:keyField' 	=> $keyfield,
            '__EVENTTARGET'		=> 'nusa:nusaBtnDetermineNumber',
            '__EVENTARGUMENT'	=> ''
        );

        // Вносим в массив txtBox поля
        for( $i=0; $i<$txtBoxes; $i++ ) {

            // Создаём порядковое имя txtBox поля
            $txtBoxName = $this->txtBoxName.$i;

            if( $i == $realBox || $i == $controlField ) {
                // Если номер поля совпадает с реальным или контрольным полями, запихиваем туда телефон
                $this->data[ $txtBoxName ] = $this->phone;
            }
            else {
                // В остальных случаях - это поле-ловушка и его оставляем пустым
                $this->data[ $txtBoxName ] = '';
            }

        }


        /**
         * ------------------------------------------------------------------------
         * Отправляем данные
         */
        $ce = $this->sendPost();


        /**
         * ------------------------------------------------------------------------
         * Обрабатываем полученные данные
         */
        $res = $this->parseTable( $ce );


        /**
         * ------------------------------------------------------------------------
         * Чистим полученные данные
         */
        $res['operator']    = (empty($res['pakalpojumanodrosinatajs'])) ? '' : $res['pakalpojumanodrosinatajs'];
        $res['belonging']   = (empty($res['numurapielietojums']))       ? '' : $res['numurapielietojums'];
        $res['sourceowner'] = (empty($res['numeracijapieskirta']))      ? '' : $res['numeracijapieskirta'];


        /**
         * ------------------------------------------------------------------------
         * Возвращаем данные
         */
        return array(
            'operator'    => $res['operator'],
            'belonging'   => $res['belonging'],
            'sourceowner' => $res['sourceowner'],
            'querytime'   => $this->endTimer()
        );

    }

}