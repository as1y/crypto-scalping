<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class ShortController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";


    public $ApiKey = "U5I2AoIrTk4gBR7XLB";
    public $SecretKey = "HUfZrWiVqUlLM65Ba8TXvQvC68kn1AabMDgE";

    // Переменные для стратегии
    public $summazahoda = 0.001; // Сумма захода в монете актива на 1 ордер

    public $leverege = 90;
    public $symbol = "BTC/USDT";
    public $Basestep = 0.5;
    public $emailex  = "raskrutkaweb@yandex.ru"; // Сумма захода USD
    public $namebdex = "treks";

    // Переменные для стратегии
    private $RangeH = 49820;
    private $RangeL = 45180;
    private $side = "short"; // LONG или SHORT
    private $step = 15; // Размер шага между ордерами
    private $maxposition = 40; // Максимальный размер позиции (кол-во ордеров)
    private $GOAL = 100;


    // ТРЕЛЛИНГ ОРДЕРОВ
    private $StartTrellingOrderSTEP = 4; // С какого шага начинаем треллить ордер
    private $TrellingProsadkaOrderSTEP = 2; // Расстояние треллинга



    //СКОРИНГ
    private $limitmoneta = 5000; // Лимит объемов торгов для скоринга

    // МАНИ МЕНЕДЖМЕНТ 1
    private $stopl = 5; // Выключение всего скрипта при просадке депозита
    private $maxprofit = 4; // Профит с которого начинаем трелить
    private $trellingstep = 2; // Профит с которого будем выходить
    private $timew = 30; // В минутах ожидание после завершение цикла
    private $timestopinterval = 120;


    private $skolz = 1; // ШАГ выше которого выставляется лимитник


    private $MARKET = false; // Работа по маркету или нет


    // ТЕХНИЧЕСКИЕ ПЕРЕМЕННЫЕ
    private $WORKTREKS = [];
    private $ORDERBOOK = [];
    private $EXCHANGECCXT = [];
    private $BALANCE = [];
    private $KLINES = [];
    private $SCORING = [];
    private $esymbol = "";
    private $MASSORDERS = [];





    // ТЕХНИЧЕСКИЕ ПЕРЕМЕННЫЕ
    public function indexAction()
    {

        $this->layaout = false;

        date_default_timezone_set('UTC');
        // Браузерная часть
        $Panel =  new Panel();
        $META = [
            'title' => 'Панель BURAN',
            'description' => 'Панель BURAN',
            'keywords' => 'Панель BURAN',
        ];
        $BREADCRUMBS['HOME'] = ['Label' => $this->BreadcrumbsControllerLabel, 'Url' => $this->BreadcrumbsControllerUrl];
        $BREADCRUMBS['DATA'][] = ['Label' => "FAQ"];
        \APP\core\base\View::setBreadcrumbs($BREADCRUMBS);
        $ASSETS[] = ["js" => "/global_assets/js/plugins/tables/datatables/datatables.min.js"];
        $ASSETS[] = ["js" => "/assets/js/datatables_basic.js"];
        \APP\core\base\View::setAssets($ASSETS);
        \APP\core\base\View::setMeta($META);
        \APP\core\base\View::setBreadcrumbs($BREADCRUMBS);
        // Браузерная часть


        //  show(\ccxt\Exchange::$exchanges); // print a list of all available exchange classes

        //Запуск CCXT
        $this->EXCHANGECCXT = new \ccxt\bybit (array(
            'apiKey' => $this->ApiKey,
            'secret' => $this->SecretKey,
            'timeout' => 30000,
            'enableRateLimit' => true,
            'marketType' => "linear",
            'options' => array(
                // 'code'=> 'USDT',
                //  'marketType' => "linear"
            )
        ));


        $this->BALANCE = $this->GetBal()['USDT'];


        $this->ORDERBOOK = $this->GetOrderBook($this->symbol);

        $this->KLINES = $this->EXCHANGECCXT->fetch_ohlcv($this->symbol, '1h', null, 15);
        $pricenow = $this->GetPriceSide($this->symbol, "long");

        $this->SCORING = $this->CheckOrderSCORING($pricenow); // Скоринг при запуске скрипта

        // РАСЧЕТ ОРДЕРОВ
        $this->work();


//        $this->set(compact(''));

    }

    public function work(){


        $TREK = $this->GetTreksBD();



        foreach ($TREK as $key => $row) {
            //Проверка на работу трека
            if ($row['work'] == 1) {
                echo "Скрипт в работе. Пропускаем цикл<br>";
                continue;
            }
            $this->StartTrek($row);
            //Проверка на работу трека

            $this->WORKTREKS[] = $row['symbol'];

            echo "<h2>СИМВОЛ: " . $row['symbol'] . " - STATUS - " . $row['status'] . " | " . $row['side'] . " | " . $row['id'] . "   </h2>";

            $f = 'WorkStatus' . $row['status'];
            $this->$f($row);
        }


        // Логирование запусков

        if (empty($TREK)) $this->AddTrek();

        $this->LogZapuskov($TREK);
        $this->StopTrek($TREK);


    }

    private function AddTrek()
    {

        $this->SetLeverage($this->leverege);


        echo "Рассчет ордеров<br>";

        $delta = $this->RangeH - $this->RangeL;

        $CountOrders = $delta/$this->step;

        // Проверка на минимальный шаг
        echo "ШАГ ЦЕНЫ: ".$this->step."<br>";
        echo "ШАГ ОРДЕРОВ: ".$CountOrders."<br>";

        $pricenow = $this->GetPriceSide($this->symbol, "long");
        $pricenow = round($pricenow);


        $this->MASSORDERS = $this->GenerateStepPrice();

        // Добавляем в массив ордеров сумму захода
        $this->CalculatePriceOrders();

        // Дополняем массив ордеров детальными значениями (сторона и quantity)
        $this->AddMassOrders();

        // Добавление ТРЕКА в БД


        $rangeh = $this->RangeH;
        $rangel = $this->RangeL;


        $ARR['emailex'] = $this->emailex;
        $ARR['status'] = 1;
        $ARR['action'] = "ControlOrders";
        $ARR['contrpoz'] = 0;
        $ARR['market'] = $this->MARKET;
        $ARR['symbol'] = $this->symbol;
        $ARR['lever'] = $this->leverege;
        $ARR['step'] = $this->step;
        $ARR['rangeh'] = $rangeh;
        $ARR['rangel'] = $rangel;
        $ARR['countplus'] = 0;
        $ARR['countstop'] = 0;
        $ARR['workside'] = $this->side;
        $ARR['startbalance'] = $this->BALANCE['total'];
        $ARR['maxprofit'] = 0;
        $ARR['date'] = date("H:i:s");
        $ARR['stamp'] = time();


        $idtrek = $this->AddARRinBD($ARR);
        echo "<b><font color='green'>ДОБАВИЛИ ТРЕК</font></b>";
        // Добавление ТРЕКА в БД


        // Добавление ордеров в БД
        foreach ($this->MASSORDERS as $key=>$val){
            $ARR = [];
            $ARR['idtrek'] = $idtrek;
            $ARR['stat'] = 1;
            $ARR['count'] = $key;
            $ARR['side'] = $this->side;
            $ARR['amount'] = $val['quantity'];
            $ARR['price'] = $val['price'];
            $ARR['first'] = 1;
            $ARR['maxdelta'] = 0;
            $this->AddARRinBD($ARR, "orders");
        }


        // Добавление ордеров в БД
        return true;

    }

    // Статус 1 - когда находимся в КОРИДОРЕ
    private function WorkStatus1($TREK)
    {

        //ЗАКРЫТИЕ ТРЕКА
        // $this->KillCycle($TREK);

        // Блокировка входа


        $pricenow = $this->GetPriceSide($this->symbol, $TREK['workside']);


        // Контроль коридора
        $WorkSide = $this->GetWorkSide($pricenow, $TREK);
        show($WorkSide);


        // Контроль баланса
        $this->ControlBalance($TREK);




        // Вывод рабочей информации
        echo "<h3>Базовые параметры</h3>";
        echo "<b>Верхняя граница коридора:</b>".$TREK['rangeh']."<br>";
        echo "<b>Нижняя граница коридора:</b>".$TREK['rangel']."<br>";



        if ($WorkSide == "HIEND" || $WorkSide == "LOWEND"){
            echo "<b><font color='#8b0000'>Цена вышла из коридора!!!</font></b>";
            // Логирование выхода
            $this->CloseCycle($TREK, "LEAVE"); // Закрытие цикла при выходе из коридора
            return true;
        }


        if ($this->SCORING === FALSE){
            echo "<b color='RED'>СКОРИНГ НЕ ПРОЙДЕН</b>";

            $this->CloseCycle($TREK, "SCORING"); // Закрытие цикла при выходе из коридора

            return false;
        }



        // РАБОТА С ОРДЕРАМИ
        if ($TREK['action'] == "ControlOrders") $this->ActionControlOrders($TREK, $pricenow);


        // Контроллер ситуации

        return true;

    }

    // Статус 2 - ЦИКЛ ВНЕ РАБОЧЕМ СОСТОЯНИИ
    private function WorkStatus2($TREK){

        echo "<h1><font color='green'>КОРИДОР ЗАВЕРШИЛ РАБОТУ</font></h1>";

        $LASTZAPIS = R::findOne("cycle", 'WHERE trekid =?', [$TREK['id']]);

        // Результат выхода

        show($LASTZAPIS['typeclose']);


        $timewait = time() - $LASTZAPIS['timeclose'];
        $timewait =  round($timewait/60);

        echo "Время нахождение в статусе2:  ".$timewait."<br>";


        echo "Текущий статус скоринга:<br>";
        var_dump($this->SCORING);
        echo "<hr>";

        if ($LASTZAPIS['typeclose'] == "LEAVE"){

            echo "Вышли из глобального коридора";
            return false;
            //if ($timewait > $this->timewait)  R::trash($TREK);
            // Запускаем через время оиждания
        }

        if ($LASTZAPIS['typeclose'] == "GOAL"){

            echo "Достигли ЦЕЛИ!";
            return false;
            //if ($timewait > $this->timewait)  R::trash($TREK);
            // Запускаем через время оиждания
        }


        if ($this->SCORING === FALSE){
            echo "Ждем положительного скоринга";
            return false;
        }

        if ($LASTZAPIS['typeclose'] == "STOP"){
            if ($timewait > $this->timestopinterval*6)  R::trash($TREK);
            // Запускаем через время оиждания
        }

        if ($LASTZAPIS['typeclose'] == "TRELLING"){

            echo "TW:".$timewait." - ".$this->timew."<br>";

            if ($timewait > $this->timew)  R::trash($TREK);
            // Запускаем через время оиждания
        }



        if ($LASTZAPIS['typeclose'] == "SCORING") R::trash($TREK);






        return true;

    }




    private function ActionControlOrders($TREK, $pricenow){


        echo  "<b>Запускаем Action ControlOrders. Контролируем работу ордеров</b> <br>";
        $AllOrdersREST = $this->GetAllOrdersREST();
        // Проверка ордеров на наличие в БД КОСТЫЛЬ


        if ($AllOrdersREST === FALSE){
            echo  "Выдался пустой REST<br>";
            return false;
        }


        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 2
        $this->WorkStat2($TREK, $AllOrdersREST, $pricenow);
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 2


        // КОНТРОЛЛЕР ЛИШНЕЙ ПОЗИЦИИ
        // КОНТРОЛЛЕР ЛИШНЕЙ ПОЗИЦИИ


        echo "<hr>";
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1
        $this->WorkStat1($TREK, $AllOrdersREST, $pricenow);
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1



        return true;

    }


    private function WorkStat2($TREK, $AllOrdersREST, $pricenow){

        $OrdersBD = $this->GetOrdersBD($TREK, 2);
        echo "<h3>ОБРАБОТКА ОРДЕРОВ СТАТУСА 2</h3>";
        // Ордер на наращивание позиции


        foreach ($OrdersBD as $key=>$OrderBD) {

            // Проверка на актуальность ордеров обработки ордера
            $distance = $this->CheckDistance($TREK, $pricenow, $OrderBD);
            // Проверка на актуальность ордеров обработки ордера

            echo "#".$OrderBD['id']." СТАТУС ОРДЕРА <b>".$OrderBD['stat']."</b> - ".$OrderBD['orderid']." - <b>".$OrderBD['side']."</b> <br>";
            echo  "Дистанция по БД: ".$distance."<br>";

            // Начинается треллинг

            // ПОДГОТОВКА К ВЫСТАВЛЕНИЮ ОРДЕРА
            if ($OrderBD['orderid'] == NULL){


                $DELTA = $this->GetDelta($TREK, $OrderBD, $pricenow); // Получение дельты на текущий ордер

                echo "<b>Дельта ордера в шагах :     </b>".$DELTA['nowdelta']."<br>";

                // Базовая проверка дельты
                if ($DELTA['nowdelta'] < 1){
                    echo "<b><font color='red'>Ордер в минусовой зоне</font></b><br>";
                    continue;
                }

                // Треллинг
                if ($DELTA['maxdelta'] > $this->StartTrellingOrderSTEP){

                    echo "<b><font color='green'>Вошли в положительную зону треллинга</font></b><br>";
                    $Prosadka = $DELTA['maxdelta'] - $DELTA['nowdelta'];

                    echo "Текущая просадка от максимальной дельты: ".$Prosadka."<br>";

                    if ($Prosadka < $this->TrellingProsadkaOrderSTEP){
                        echo "<b><font color='red'>Просадка еще не достигла критической точки</font></b><br>";
                        continue;
                    }




                    echo "<font color='#663399'>Можно выставлять ордер на продажу выставлять ордер на продажу<br></font>";


                    // ВЫСТАВЛЯЕМ ОРДЕР ФИКСАЦИИ

                    $order = $this->CreateReverseOrder($OrderBD); // Создание реверсного ордера при треллинге СТАТУС2



                    continue;




                } // Завершение работы в зоне треллинга


                continue;

            } // Завершение работы с ордерами статуса NULL






            echo "<b>Работа СТАТУС 2</b><br>";
            echo "Информация об ордере из REST<br>";


            $OrderREST = $this->GetOneOrderREST($OrderBD['orderid'], $AllOrdersREST); // Ордер РЕСТ статус 2



            // Проверка на cancel (перевыставление)
            if ($OrderREST['status'] == "Cancelled"){

                echo "<font color='#8b0000'>ОРДЕР отменен (canceled)!!! (второй статус) </font> <br>";
                show($OrderREST);

                // ВЫСТАВЛЯЕМ ОРДЕР ФИКСАЦИИ
                $order = $this->CreateReverseOrder($OrderBD); // Создание реверсного ордера при отмене СТАТУС2


                continue;

            }




            // Проверка на исоплненность
            if ($this->OrderControl($OrderREST) === FALSE){

                echo "Ордер не откупился<br>";


                $DELTA = $this->GetDelta($TREK, $OrderBD, $pricenow); // Получение дельты на текущий ордер
                show($DELTA);

                if ($DELTA['nowdelta'] < 0){

                    echo "<font color='red'>Ордер в минусовой зоне. Надо отменять!</font><br>";
                    $cancel = $this->EXCHANGECCXT->cancel_order($OrderBD['orderid'], $this->symbol);
                    show($cancel);

                    $order['id'] = NULL;
                    $this->ChangeIDOrderBD($order, $OrderBD['id']);

                }

                // Ордер у границы падения
                if ( $DELTA['nowdelta'] > 1){
                    echo "<font color='#663399'>Перевыставляем ордер на текущую цену если он НЕ откупился</font><br>";
                    // Отменяем текущий ордер

                    // Цена по которой ордер был выставлен
                    echo "Цена по которой ордер выставлен: ".$OrderBD['latbid']."<br>";
                    // Текущая цена
                    //   echo "Текущая цена:".$pricenow."<br>";
                    // Цена по которой хотим выставить ордер
                    if ($OrderBD['side'] == "long") $pricebid = $this->GetPriceSide($this->symbol, "short") + $this->Basestep;
                    if ($OrderBD['side'] == "short") $pricebid = $this->GetPriceSide($this->symbol, "long") - $this->Basestep;

                    echo "Цена по которой хотим выставить:".$pricebid."<br>";

                    // Если цена равна той которой хотим выставлять
                    if ($pricebid == $OrderBD['latbid']){
                        echo "Выставленная цена равна текущей цене в стакане<br>";
                        continue;
                    }

                    // Если цена приближается к покупке нашего ордера, то ничего не делаем
                    if ($OrderBD['side'] == "long" ){
                        // Если цена которой хотим выставить ВЫШЕ цены которой УЖЕ выставлено
                        if ($pricebid > $OrderBD['latbid']){
                            echo "<font color='yellow'>Если цена которой хотим выставить ВЫШЕ цены которой УЖЕ выставлено</font><br>";
                            continue;
                        }
                    }

                    if ($OrderBD['side'] == "short" ){
                        // Если цена которой хотим выставить НИЖЕ цены которой УЖЕ выставлено
                        if ($pricebid < $OrderBD['latbid']) {
                            echo "<font color='yellow'>Если цена которой хотим выставить НИЖЕ цены которой УЖЕ выставлено</font><br>";
                            continue;
                        }
                    }

                    echo "Цена ушла. Перевыставляем<br>";

                    $cancel = $this->EXCHANGECCXT->cancel_order($OrderBD['orderid'], $this->symbol);
                    show($cancel);
                    // Выставляем заново
                    $order = $this->CreateReverseOrder($OrderBD); // Перевыставляем ордер на текущую цену если ордер НЕ откупился

                }



                continue;

            }


            echo "<font color='green'>Ордер статуса 2 исполнен</font><br>";
            // ОРДЕР ИСПОЛНЕН


            $SCORING = json_decode($OrderBD['startscoring'], true);

            $this->AddTrackHistoryBD($TREK, $OrderBD, $OrderREST, $SCORING); // Исполнен статус 2

            $countplus = $TREK['countplus'] + 1;
            $ARRTREK['countplus'] = $countplus;
            $this->ChangeARRinBD($ARRTREK, $TREK['id']);


            $ARRCHANGE = [];
            $ARRCHANGE['stat'] = 1;
            $ARRCHANGE['orderid'] = NULL;
            $ARRCHANGE['type'] = NULL;
            $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

            echo "<hr>";
        }



        return true;
    }


    private function WorkStat1($TREK, $AllOrdersREST, $pricenow){


        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1
        $OrdersBD = $this->GetOrdersBD($TREK, 1);



        echo "<h3>ОБРАБОТКА ОРДЕРОВ СТАТУСА 1</h3>";
        // Ордер на наращивание позиции
        foreach ($OrdersBD as $key=>$OrderBD) {

            // Проверка на актуальность ордеров обработки ордера
            $distance = $this->CheckDistance($TREK, $pricenow, $OrderBD);
            // Проверка на актуальность ордеров обработки ордера

            echo "#".$OrderBD['id']." СТАТУС ОРДЕРА <b>".$OrderBD['stat']."</b> - ".$OrderBD['orderid']." - <b>".$OrderBD['side']."</b> <br>";
            echo  "Дистанция по БД ".$distance."<br>";


            // Выставление первых ордеров
            if ($OrderBD['orderid'] == NULL){


                echo "Текущая цена".$pricenow."<br>";
                echo "Цена для выставления ордера".$OrderBD['price']."<br>";

                // Скоринг на первичное выставление выставление
                $resultscoring =  $this->CheckFirstOrder($TREK, $pricenow, $OrderBD, $distance);
                show($resultscoring);
                // Скоринг на первичное выставление выставление


                // Счетчик ордеров с неедупдейтам

                echo "Откупаем ордер по типу:<br>";
                var_dump($resultscoring);
                echo "<br>";
                if ($resultscoring === FALSE) continue;


                $order = $this->CreateFirstOrder($OrderBD, $resultscoring, $TREK);
                // Записываем
                // show($order);

                $starscsoring = SCORING($this->KLINES, $pricenow);
                $starscsoring = json_encode($starscsoring, true);

                $ARRCHANGE = [];
                $ARRCHANGE['orderid'] = $order['id'];
                $ARRCHANGE['type'] = $resultscoring;
                $ARRCHANGE['startscoring'] = $starscsoring;
                $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");


                continue;
            }


            echo "Информация об ордере из REST<br>";
            $OrderREST = $this->GetOneOrderREST($OrderBD['orderid'], $AllOrdersREST); // Ордер РЕСТ статус 1


            // ВНЕЗАПНАЯ ПОПАДАНИЕ В СТАТУС "CANCELED"
            if ($OrderREST['status'] == "Cancelled"){
                echo "<font color='#8b0000'>ОРДЕР отменен (canceled)!!! </font> <br>";
                show($OrderREST);
                echo "Обнуляем ID ордера!  <br>";
                $order['id'] = NULL;
                $this->ChangeIDOrderBD($order, $OrderBD['id']);
                continue;
            }


            // ОРДЕР НЕ ОТКУПИЛСЯ
            if ($this->OrderControl($OrderREST) === FALSE){
                echo "ОРДЕР не откупился <br>";

                $distance = $this->CheckDistance($TREK, $pricenow, $OrderBD);
                echo "Расстояние ордера до цены : ".$distance."<br>";

                // Кол-во ордеров в статусе 2
                // $count = $this->CountActiveOrders($TREK);
                //  $alloworder = $this->maxposition - $count;
                //  echo "Дистанция от цены:".$distance."<br>";
                //  echo "Доступных ордеров для выставления:".$alloworder." <br>";

                // Проверка на дистанцию
                if ($distance >= $this->maxposition){

                    echo "<b><font color='red'>ОРДЕР ЛИШНИЙ НАДО ОТМЕНЯТЬ</font></b> <br>";
                    $cancel = $this->EXCHANGECCXT->cancel_order($OrderBD['orderid'], $this->symbol);
                    show($cancel);

                    $order['id'] = NULL;
                    $this->ChangeIDOrderBD($order, $OrderBD['id']);

                    continue;


                }


                continue;
            }



            // ОРДЕР ОТКУПИОСЯ.

            echo "<font color='green'>Ордер откупился по цене</font> ".$OrderREST['price']."<br>";

            $this->AddTrackHistoryBD($TREK, $OrderBD, $OrderREST); // Исполнен статус 1


            $countplus = $TREK['countplus'] + 1;
            $ARRTREK['countplus'] = $countplus;
            $this->ChangeARRinBD($ARRTREK, $TREK['id']);



            $ARRCHANGE = [];
            $ARRCHANGE['stat'] = 2;
            $ARRCHANGE['orderid'] = NULL;
            $ARRCHANGE['type'] = "LIMIT";
            $ARRCHANGE['first'] = 0;
            $ARRCHANGE['lastprice'] = $OrderREST['last'];

            $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");


            echo  "<hr>";

            continue;



        }
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1




        return true;
    }



    private function GetDelta($TREK, $OrderBD, $pricenow){

        if ($TREK['workside'] == "long") $DELTA['nowdelta'] = $pricenow - $OrderBD['lastprice'];
        if ($TREK['workside'] == "short") $DELTA['nowdelta'] = $OrderBD['lastprice'] - $pricenow;


        $DELTA['nowdelta'] = round($DELTA['nowdelta']/$TREK['step'], 1);
        $DELTA['maxdelta'] = $OrderBD['maxdelta'];


        if ($DELTA['nowdelta'] > $OrderBD['maxdelta']){
            $ARRCHANGE = [];
            $ARRCHANGE['maxdelta'] = $DELTA['nowdelta'];
            $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");
            $DELTA['maxdelta'] = $DELTA['nowdelta'];
        }



        return $DELTA;
    }







    private function CloseCycle($TREK, $typclose =""){

        $POSITION = $this->LookHPosition();



        $param = [
            'reduce_only' => true,
        ];

        // Подсраховка на случай остатков не закрытых позиций при выходе из коридора
        if ($POSITION[0]['size'] > 0 && $TREK['workside'] == "long"){
            echo "Закрытие остатков LONG позиции<br>";
            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market","sell", $POSITION[0]['size'], null, $param);
            show($order);
        }
        if ($POSITION[1]['size'] > 0 && $TREK['workside'] == "short"){
            echo "Закрытие остатков SHORT позиции<br>";
            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market","buy", $POSITION[1]['size'], null, $param);
            show($order);
        }


        // Работа с ордерами
        //$MASS = [];

        $MASS = R::findAll("orders", 'WHERE idtrek =? AND side=?', [$TREK['id'], $TREK['workside']]);

        // Проверка статуса ордера

        $cancelall = $this->EXCHANGECCXT->cancel_all_orders($this->symbol);
        show($cancelall);

        // Удаляем ордера данного трека
        foreach ($MASS as $OrderBD){
            // удаляем ордер из БД
            R::trash($OrderBD);
        }




        $ACTBAL = $this->GetBal()['USDT']['total'];
        $profit = $ACTBAL - $TREK['startbalance'];

        $timealltrack = time() - $TREK['stamp'];
        $minutetrek = $timealltrack/60;
        echo "Время работы всего скрипта ".$minutetrek."<br>";


        $ARR = [];
        $ARR['trekid'] = $TREK['id'];
        $ARR['timestart'] = $TREK['date'];
        $ARR['timeclose'] = time();
        $ARR['typeclose'] = $typclose;
        $ARR['minutework'] = $minutetrek;
        $ARR['rangeh'] = $TREK['rangeh'];
        $ARR['rangel'] = $TREK['rangel'];
        $ARR['startbalance'] = $TREK['startbalance'];
        $ARR['close'] = $ACTBAL;
        $ARR['profit'] = $profit;
        $ARR['countplus'] = $TREK['countplus'];
        $ARR['countstop'] = $TREK['countstop'];
        $ARR['stop'] = $TREK['stop'];
        $ARR['market'] = $TREK['market'];

        $this->AddARRinBD($ARR, "cycle");



        // Смена Статуса
        $ARRTREK['status'] = 2;
        $this->ChangeARRinBD($ARRTREK, $TREK['id']);


        echo "<h3><font color='green'>ЦИКЛ ЗАВЕРШЕН!!!</font> </h3>";

        return exit("CloseCycle");
    }



    private function CheckFirstOrder($TREK, $pricenow, $OrderBD, $distance){


        // Анализ на выставления оредера по СКОРИНГУ




        $STEP = $TREK['step']*$this->skolz;
        $STEP = round($STEP);


        // Проверка на дистанцию
        echo "Расстояние ордера до цены : ".$distance."<br>";


        //Базовая проверка дистанции
        if ($distance >= ($this->maxposition + 1)){
            echo "Ордер находиться вне допустимой дистанции<br>";
            return false;
        }

        // Если в рамках дистанции, то считаем дистанцию на которую можно уйти с учетом ордеров второго уровня


        $count1 = $this->CountActiveOrders($TREK, 1); // Кол-во ордеров первого уровня
        $count2 = $this->CountActiveOrders($TREK); // Кол-во ордеров второго уровня
        $allow1status = $this->maxposition - $count2; // Свободных слотов на кол-во ордеров первого уровня
        $allow1status = $allow1status - $count1;

        echo  "Можем выставить ".$allow1status." ордеров<br>";

        if ($allow1status == 0) {
            echo "Достигнут лимит максимальных кол-ва ордеров 2-го уровня<br>";
            return false;
        }



        if ($TREK['workside'] == "long"){
            if ($pricenow > $OrderBD['price'] + $STEP) return "LIMIT";
        }
        if ($TREK['workside'] == "short"){

            if ($pricenow < $OrderBD['price'] - $STEP) return "LIMIT";
        }




        echo "Цена не корректна для выставления ордеров в данном коридоре<br>";


        return false;

    }


    private function CheckOrderSCORING($pricenow){

        $SCORING = SCORING($this->KLINES, $pricenow);


        if ($SCORING['VOL'] < $this->limitmoneta && $SCORING['VOLPREV'] < $this->limitmoneta){


            echo "<font color='green'>Скоринг пройден</font><br>";
            return true;


        }


        echo "<font color='red'>Скоринг не пройден</font><br>";

        return false;


    }


    private function CheckDistance($TREK, $pricenow, $OrderBD){

        $distance = 0;

        if ($TREK['workside'] == "long"){
            $distance = $OrderBD['price'] - $pricenow;
            $distance = abs($distance);
            $distance = $distance/$TREK['step'];
            $distance = round($distance);
        }

        if ($TREK['workside'] == "short"){
            $distance = $OrderBD['price'] - $pricenow;
            $distance = abs($distance);
            $distance = $distance/$TREK['step'];
            $distance = round($distance);
        }




        return $distance;
    }


    private function ControlBalance($TREK){


        // Профит
        $NOWPROFIT = changemet($TREK['startbalance'],$this->BALANCE['total']);

        if ($NOWPROFIT > $TREK['maxprofit']){
            $ARRTREK['maxprofit'] = $NOWPROFIT;
            $this->ChangeARRinBD($ARRTREK, $TREK['id']);
            // Записываем в трек максимальный баланс
        }

        $DELTATRELLING = $TREK['maxprofit'] - $NOWPROFIT;

        echo "<b>Текущая дельта прибыли</b>: ".$NOWPROFIT." %<br>";

        echo "<b>Зафиксированный максимальный профит</b>: ".$TREK['maxprofit']." %<br>";

        echo "<b>Дельта треллинг</b>:".$DELTATRELLING." %<br>";

        // Закрытие по общему стопу баланса
        if ($NOWPROFIT*(-1) > $this->stopl){
            $this->CloseCycle($TREK, "STOP"); // Закрытие цикла по стопу баланса

        }

        if ($NOWPROFIT > $this->GOAL){
            $this->CloseCycle($TREK, "GOAL"); // Закрытие цикла по стопу баланса
        }


        // Закрытие по треллинг стопу
        if ($TREK['maxprofit'] > $this->maxprofit){ // Если максимальный профит составил более 10% от депозита

            echo "<b color='yellow'>Вошли в фазу треллинга</b><br>";

            if ( $DELTATRELLING > $this->trellingstep ) { // Если разница упала меньше , то закрываем

                $this->CloseCycle($TREK, "TRELLING"); // Закрытие цикла по треллингу

            }

        }




        return true;
    }


    private function GetWorkSide($pricenow, $TREK){

        echo "Направление движения:".$TREK['side']."<br>";

        if ($pricenow > ($TREK['rangeh'] + $TREK['step']) ) return "HIEND";
        if ($pricenow < ($TREK['rangel'] - $TREK['step']) ) return "LOWEND";


        echo "<hr>";

        return $TREK['side'];



    }


    private function Restart(){




        return true;
    }


    private function CreateFirstOrder($OrderBD, $type, $TREK){


        $sideorder = $this->GetTextSide($OrderBD['side']);
        show($sideorder);
        var_dump($OrderBD['amount']);
        show($OrderBD['price']);

        if ($type == "LIMIT"){
            $params = [
                'time_in_force' => "PostOnly",
                'reduce_only' => false,
            ];

            $order = $this->EXCHANGECCXT->create_order($this->symbol,"limit",$sideorder, $OrderBD['amount'], $OrderBD['price'], $params);
            return $order;

        }


        if ($type == "MARKET"){

            if ($OrderBD['side'] == "long") $bp = $TREK['avg'];
            if ($OrderBD['side'] == "short") $bp = $TREK['avg'];

            $params = [
                'stop_px' => $OrderBD['price'], // trigger $price, required for conditional orders
                'base_price' => $bp,
                'trigger_by' => 'LastPrice', // IndexPrice, MarkPrice
                'reduce_only' => false,
            ];

            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market", $sideorder, $OrderBD['amount'], null, $params);

            return $order;



        }



        return false;


    }

    private function CreateReverseOrder($OrderBD , $type = ""){

        $params = [
            'time_in_force' => "PostOnly",
            'close_on_trigger' => false,
            'reduce_only' => true,
        ];

        if ($OrderBD['side'] == "long"){
            $price = $this->GetPriceSide($this->symbol, "short") + $this->Basestep;
            $side = "sell";
        }

        if ($OrderBD['side'] == "short") {
            $price = $this->GetPriceSide($this->symbol, "long") - $this->Basestep;
            $side = "Buy";
        }


        show($side);
        show($OrderBD['amount']);
        show($price);
        show($params);
        //   exit("xD");


        if ($type == "MARKET"){
            // Закрываем позицию убыточную
            $params = [
                'reduce_only' => true,
            ];

            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market",$side, $OrderBD['amount'], null, $params);

            echo "<font color='#8b0000'>Создали реверсный ордер </font><br>";
            return $order;
        }

        $order = $this->EXCHANGECCXT->create_order($this->symbol,"limit",$side, $OrderBD['amount'] , $price, $params);


        echo "<font color='#8b0000'>Создали реверсный ордер </font><br>";

        $ARRCHANGE = [];
        $ARRCHANGE['orderid'] = $order['id'];
        $ARRCHANGE['latbid'] = $order['price'];
        $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");




        return $order;


    }

    public function LookHPosition(){

        $POSITIONS = $this->EXCHANGECCXT->fetch_positions([$this->symbol]);

        $POSITIONS[0]['sidecode'] = "long";
        $POSITIONS[1]['sidecode'] = "short";

        // 0 - Позиция в BUY
        // 1 - Позиция в SELL



        return $POSITIONS;

    }



    private function CountActiveOrders($TREK, $stat = 2){

        $count = 0;
        if ($stat == 1 ){
            $count = R::count("orders", 'WHERE idtrek =? AND side=? AND stat=? AND orderid IS NOT NULL', [$TREK['id'], $TREK['workside'], $stat]);
        }


        if ($stat == 2 ){
            $count = R::count("orders", 'WHERE idtrek =? AND side=? AND stat=?', [$TREK['id'], $TREK['workside'], $stat]);
        }

        if ($stat == "all"){
            $count = R::count("orders", 'WHERE idtrek =? AND side=? AND orderid IS NOT NULL', [$TREK['id'], $TREK['workside']]);
        }

        if ($stat == NULL){
            $count = R::count("orders", 'WHERE idtrek =? AND side=? AND stat=? AND orderid IS NULL', [$TREK['id'], $TREK['workside'], 2]);
        }



        echo "Активных ордеров статуса ".$stat." : ".$count."<br>";

        return $count;
    }


    public function GetTextSide($textside){
        if ($textside == "long" || $textside == "LONG") $sideorder = "Buy";
        if ($textside == "short" || $textside == "SHORT") $sideorder = "sell";
        return $sideorder;
    }




    public function OrderControl($order){

        if (!empty($order['amount']) && !empty($order['filled'])){
            if ($order['amount'] == $order['filled']) return true;
        }

        if ($order['status'] == "Filled") return true;

        return false;

    }


    public function ChangeIDOrderBD($ORD, $id){
        echo "Сменили ID ордера<br>";
        $ordbd = R::load("orders", $id);
        $ordbd->orderid = $ORD['id'];
        R::store($ordbd);
        return true;
    }


    public function GetOrderBook($symbol){
        $orderbook[$symbol] = $this->EXCHANGECCXT->fetch_order_book($symbol, 20);
        return $orderbook;

    }

    public function AddMassOrders(){

//        foreach ($this->MASSORDERS['long'] as $key=>$val){
//            $quantity = $this->GetQuantityBTC($val['summazahoda'] , $val['price']);
//            $this->MASSORDERS['long'][$key]['quantity'] = $quantity;
//        }
//
//        foreach ($this->MASSORDERS['short'] as $key=>$val){
//            $quantity = $this->GetQuantityBTC($val['summazahoda'] , $val['price']);
//            $this->MASSORDERS['short'][$key]['quantity'] = $quantity;
//        }


        foreach ($this->MASSORDERS as $key=>$val){
            $this->MASSORDERS[$key]['quantity'] = $this->summazahoda;
        }







        return true;
    }

    public function CalculatePriceOrders(){

//        $allbal = $this->summazahoda * $this->leverege;
//
//        $zahod = round($allbal/$this->CountOrders);
//
//        if ($zahod < 30){
//            echo "Размер захода на 1 ордер".$zahod."<br>";
//            echo "Не хватает баланса на такое кол-во ордеров";
//            exit();
//        }

        $zahod = $this->summazahoda;

        foreach ($this->MASSORDERS as $key=>$val){
            $this->MASSORDERS[$key]['summazahoda'] = $zahod;
        }




        return true;
    }

    public function GenerateStepPrice(){
        $MASS = [];

        $delta = $this->RangeH - $this->RangeL;
        $steps = $delta/$this->step;

        echo "<hr>";

        for ($i = 1; $i <= $steps; $i++) {
            $MASS[]['price'] = $this->RangeL + $this->step*$i;
        }



        return $MASS;
    }

    public function SetLeverage($leverage){

        $this->esymbol = $this->EkranSymbol();

        $this->EXCHANGECCXT->privateLinearGetPositionList([
                'symbol' => $this->esymbol,
                'leverage' => $leverage
            ]
        );

        return true;
    }

    public function GetBal(){
        $balance = $this->EXCHANGECCXT->fetch_balance();
        return $balance;
    }

    private function GetPriceSide($symbol, $side)
    {
        if ($side == "buy" || $side == "long" || $side = "LONG") $price = $this->ORDERBOOK[$symbol]['bids'][0][0];
        if ($side == "sell" || $side == "short" || $side = "SHORT") $price = $this->ORDERBOOK[$symbol]['asks'][0][0];
        return $price;
    }





    private function EkranSymbol()
    {
        $newsymbol = str_replace("/", "", $this->symbol);
        return $newsymbol;
    }


    private function GetTreksBD()
    {
        $terk = R::findAll($this->namebdex, 'WHERE emailex =? AND workside=?', [$this->emailex, $this->side]);
        return $terk;
    }


    private function GetUpdateOrder2($TREK, $OrderBD){


        if ($TREK['workside'] == "short"){

            // Получаем целевыу цену выставления ордера
            $targetprice = $OrderBD['price'] + $TREK['step']*$this->maxposition;
            // От текущего ордера отнимаем кол-во шагов равной дистанции
            echo "Целевая цена выставления ордера".$targetprice."<br>";

            // Ищем ордер который в данной ценовом диапазоне
            // Берем ордера снизу ВВЕРХ
            $TargetOrder = R::findOne("orders", 'WHERE idtrek =? AND side=? AND price=?', [$TREK['id'], $TREK['workside'], $targetprice]);

            return $TargetOrder;
            // Ищем ордер с данным шагом цены



        }

        if ($TREK['workside'] == "long"){

            // Получаем целевыу цену выставления ордера
            $targetprice = $OrderBD['price'] - $TREK['step']*$this->maxposition;
            // От текущего ордера отнимаем кол-во шагов равной дистанции
            echo "Целевая цена выставления ордера".$targetprice."<br>";

            // Ищем ордер который в данной ценовом диапазоне
            // Берем ордера снизу ВВЕРХ
            $TargetOrder = R::findOne("orders", 'WHERE idtrek =? AND side=? AND price=?', [$TREK['id'], $TREK['workside'], $targetprice]);

            return $TargetOrder;
            // Ищем ордер с данным шагом цены


        }




        return NULL;


    }

    private function GetOrdersBD($TREK, $status)
    {

        // Если позиция LONG и статус 1, то работаем с ВЕРХУ в НИЗ
        if ($TREK['workside'] == "long" && $status == 1){
            $MASS = R::findAll("orders", 'WHERE idtrek =? AND side=? AND stat=? ORDER by `count` DESC', [$TREK['id'], $TREK['workside'], $status]);
            return $MASS;
        }

        // Если ордера в лонге на продажу, то работаем с НИЗУ на ВЕРХ
        if ($TREK['workside'] == "long" && $status == 2){
            $MASS = R::findAll("orders", 'WHERE idtrek =? AND side=? AND stat=? ORDER by `count` ASC', [$TREK['id'], $TREK['workside'], $status]);
            return $MASS;
        }


        // Если позиция SHORT и статус 1, то работаем с НИЗУ на ВЕРХ
        if ($TREK['workside'] == "short" && $status == 1){
            $MASS = R::findAll("orders", 'WHERE idtrek =? AND side=? AND stat=? ORDER by `count` ASC', [$TREK['id'], $TREK['workside'], $status]);
            return $MASS;
        }

        // Если позиция SHORT , то работаем с ВЕРХУ В НИЗ
        if ($TREK['workside'] == "short" && $status == 2){
            $MASS = R::findAll("orders", 'WHERE idtrek =? AND side=? AND stat=? ORDER by `count` DESC', [$TREK['id'], $TREK['workside'], $status]);
            return $MASS;
        }







    }




    private function GetAllOrdersBD($id)
    {
        $MASS = R::findAll("orders", 'WHERE idtrek =?', [$id]);
        return $MASS;
    }



    private function AddARRinBD($ARR, $BD = false)
    {

        if ($BD == false) $BD = $this->namebdex;

        $tbl = R::dispense($BD);
        //ДОБАВЛЯЕМ В ТАБЛИЦУ

        foreach ($ARR as $name => $value) {
            $tbl->$name = $value;
        }

        $id = R::store($tbl);

        echo "<font color='green'><b>ДОБАВИЛИ ЗАПИСЬ В БД!</b></font><br>";

        return $id;


    }


    private function GetAllOrdersREST(){

        $url = "https://api.bybit.com/private/linear/order/list";
        $symbol = $this->EkranSymbol();
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // Загружаем PART1
        $RESULTPart1 = [];
        $params = [
            'limit' => '50',
            'order'=> 'desc',
            'order_status' => '',
            'timestamp' => time() * 1000,
            'symbol' => $symbol,
            'page' => 1,
        ];

        $qs = get_signed_params_bybit($this->ApiKey, $this->SecretKey, $params);
        $url = $url."?".$qs;
        $MASS = fCURL($url, null, $headers);
        // Загружаем FILLED
        $MASS = $MASS['result']['data'];
        if (empty($MASS)) return FALSE;
        foreach ($MASS as $key=>$val){
            $RESULTPart1[$val['order_id']] = $val;
            $RESULTPart1[$val['order_id']]['status'] = $val['order_status'];
            $RESULTPart1[$val['order_id']]['amount'] = $val['qty'];
            $RESULTPart1[$val['order_id']]['last'] = $val['last_exec_price'];

        }
        // Загружаем PART1


        // Загружаем PART2
        $RESULTPart2 = [];
        $params = [
            'limit' => '50',
            'order'=> 'desc',
            'order_status' => '',
            'timestamp' => time() * 1000,
            'symbol' => $symbol,
            'page' => 2,
        ];
        $url = "https://api.bybit.com/private/linear/order/list";
        $qs = get_signed_params_bybit($this->ApiKey, $this->SecretKey, $params);
        $url = $url."?".$qs;

        $MASS = fCURL($url, null, $headers);

        $MASS = $MASS['result']['data'];
        if (empty($MASS)) return FALSE;

        foreach ($MASS as $key=>$val){
            $RESULTPart2[$val['order_id']] = $val;
            $RESULTPart2[$val['order_id']]['status'] = $val['order_status'];
            $RESULTPart2[$val['order_id']]['amount'] = $val['qty'];
            $RESULTPart2[$val['order_id']]['last'] = $val['last_exec_price'];
        }
        // Загружаем PART2




        $RESULT = array_merge ($RESULTPart1, $RESULTPart2);

        return $RESULT;


    }


    private function GetOneOrderREST($id, $AllOrdersREST)
    {

        if (array_key_exists($id, $AllOrdersREST)) {

            echo "Массив содержит элемент <br>";
            return $AllOrdersREST[$id];
        }

        $order = $this->EXCHANGECCXT->fetchOrder($id,$this->symbol)['info'];
        $order['status'] = $order['order_status'];
        $order['amount'] = $order['qty'];
        $order['last'] = $order['last_exec_price'];

        // $MASS[$order['id']] = $order;
        return $order;



    }


    private function AddTrackHistoryBD($TREK, $OrderBD, $OrderREST, $SCORING = FALSE)
    {

        $dollar = 0;

        if ($OrderBD['stat'] == 1){
            if ($OrderBD['type'] == "LIMIT") $dollar = $OrderREST['amount']*$OrderREST['price']*(0.025)/100;
        }


        if ($OrderBD['stat'] == 2 && $OrderBD['side'] == "long"){
            $enter = $OrderBD['lastprice'];
            $pexit = $OrderREST['price'];

            $delta = changemet($enter, $pexit) + 0.025;

            $dollar = ($OrderBD['price']/100)*$delta*$OrderREST['amount'];

        }


        if ($OrderBD['stat'] == 2 && $OrderBD['side'] == "short"){
            $enter = $OrderBD['lastprice'];
            $pexit = $OrderREST['price'];
            $delta = changemet($pexit, $enter) + 0.025;
            $dollar = ($OrderBD['price']/100)*$delta*$OrderREST['amount'];
        }


        $ACTBAL = $this->GetBal()['USDT']['total'];

        if ($SCORING === FALSE)  $SCORING = SCORING($this->KLINES, $OrderREST['price']);


        $delta = 0;
        if ($OrderBD['stat'] == 2){
            if ($TREK['workside'] == "long") $delta = $OrderREST['price'] - $OrderBD['lastprice'];
            if ($TREK['workside'] == "short") $delta = $OrderBD['lastprice'] - $OrderREST['price'];
            $delta = round($delta/$TREK['step'], 1);
        }


        $MASS = [
            'trekid' => $TREK['id'],
            'side' => $TREK['workside'],
            'type' => $OrderBD['type'],
            'statusorder' => $OrderBD['stat'],
            'timeexit' => date("H:i:s"),
            'lastprice' => $OrderBD['lastprice'],
            'amount' => $OrderREST['amount'],
            'delta' => $delta,
            'fact' => $OrderREST['price'],
            'bal' => $ACTBAL,
            'dollar' => $dollar,
            'RSI' => $SCORING['RSI'],
            'korsize' => $SCORING['KORSIZE'],
            'color' => $SCORING['COLOR'],
            'dlinna' => $SCORING['DLINNA'],
        ];
        //ДОБАВЛЯЕМ В ТАБЛИЦУ
        $tbl3 = R::dispense("trekhistory");
        //ДОБАВЛЯЕМ В ТАБЛИЦУ

        //ДОБАВЛЯЕМ В ТАБЛИЦУ
        foreach ($MASS as $name => $value) {
            $tbl3->$name = $value;
        }
        R::store($tbl3);

        echo "Сохранили запись о сделке в БД <br>";


        return true;

    }

    private function LogZapuskov($TREK){

        foreach ($TREK as $key=>$val){
            $tbl = R::findOne("treks", "WHERE id =?", [$val['id']]);
            $tbl->lastrun = date("H:i:s");
            R::store($tbl);
        }

        return true;
    }









    private function StartTrek($TREK){
        $tbl = R::findOne("treks", "WHERE id =?", [$TREK['id']]);
        $tbl->work = 1;
        R::store($tbl);
        return true;
    }

    private function ChangeARRinBD($ARR, $id, $BD = false)
    {

        if ($BD == false) $BD = $this->namebdex;

        echo('-----------------');
        echo('-----------------');
        echo('-----------------');
        show($ARR);
        echo('-----------------');
        echo('-----------------');
        echo('-----------------');

        echo "<hr>";

        $tbl = R::load($BD, $id);
        foreach ($ARR as $name => $value) {
            $tbl->$name = $value;
        }
        R::store($tbl);

        return true;


    }



    private function StopTrek($TREK){
        foreach ($TREK as $key=>$val){
            $tbl = R::findOne("treks", "WHERE id =?", [$val['id']]);
            $tbl->work = 0;
            R::store($tbl);
        }
        return true;
    }


}
?>