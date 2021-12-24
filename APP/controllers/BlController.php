<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class BlController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";


    // BINANCE

    public $ApiKey = "9juzIdfqflVMeQtZf9";
    public $SecretKey = "FwUD2Ux5sjLo8DyifqYr4cfWgxASblk7CZo7";


    // Переменные для стратегии
    public $leverege = 90;
    public $symbol = "BTC/USDT";
    public $Basestep = 0.5;
    public $emailex  = "raskrutkaweb@yandex.ru"; // Сумма захода USD
    public $namebdex = "treks";


    // ПАРАМЕТРЫ СТРАТЕГИИ
    private $workside = "long";

    private $lot = 0.002; // Базовый заход
    private $RangeH = 70000;
    private $RangeL = 40000;
    private $step = 30; // Размер шага между ордерами
    private $stoploss = 6; // Размер шага между ордерами
    private $maxposition = 1;
    private      $maVAL = 6; // Коэффицент для МА
    private      $maDev = 3; // Отклонение МА
    private      $maxRSI = 70; // Фильтр по RSI
    private      $minRSI = 30; // Фильтр по RSI
    private      $deltacoef = 5; // Коэффицентр треллинга


    // ТЕХНИЧЕСКИЕ ПЕРЕМЕННЫЕ
    private $WORKTREKS = [];
    private $ORDERBOOK = [];
    private $EXCHANGECCXT = [];
    private $BALANCE = [];
    private $KLINES15M = [];
    private $KLINES30M = [];
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

        // ГЛОБАЛЬНЫЙ СКОРИНГ
        $this->SCORING = $this->CheckGlobalSCORING(); // Скоринг при запуске скрипта
        echo "<i> SCORING</i><br>";
        var_dump($this->SCORING);


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

        // ДОБАВЛЕНИЕ ТРЕКА

        echo "Рассчет ордеров<br>";

        $delta = $this->RangeH - $this->RangeL;

        $CountOrders = round($delta/$this->step);

        // Проверка на минимальный шаг
        echo "ШАГ ЦЕНЫ: ".$this->step."<br>";
        echo "КОЛ-ВО ОРДЕРОВ: ".$CountOrders."<br>";


        //$pricenow = $this->GetPriceSide($this->symbol, "long");
        // $pricenow = round($pricenow);


        $this->MASSORDERS = $this->GenerateStepPrice($CountOrders);


        // Добавление ТРЕКА в БД
        $rangeh = $this->RangeH;
        $rangel = $this->RangeL;



        $ARR['emailex'] = $this->emailex;
        $ARR['status'] = 1;
        $ARR['action'] = "ControlOrders";
        $ARR['symbol'] = $this->symbol;
        $ARR['lever'] = $this->leverege;
        $ARR['workside'] = $this->workside;
        $ARR['step'] = $this->step;
        $ARR['rangeh'] = $rangeh;
        $ARR['rangel'] = $rangel;
        $ARR['startbalance'] = $this->BALANCE['total'];
        $ARR['date'] = date("Y-m-d H:i:s");
        $ARR['stamp'] = time();


        $idtrek = $this->AddARRinBD($ARR);
        echo "<b><font color='green'>ДОБАВИЛИ ТРЕК</font></b>";
        // Добавление ТРЕКА в БД

        // Добавление ордеров в БД
        foreach ($this->MASSORDERS as $key=>$val){
            $ARR = [];
            $ARR['idtrek'] = $idtrek;
            $ARR['count'] = $key;
            $ARR['workside'] = $this->workside;
            $ARR['status'] = 1;
            $ARR['amount'] = $this->lot;
            $ARR['price'] = $val['price'];
            $this->AddARRinBD($ARR, "orders");
        }



        // Добавление ордеров в БД
        return true;

    }

    // Статус 1 - РАБОЧИЙ СТАТУС
    private function WorkStatus1($TREK)
    {

        $pricenow = $this->GetPriceSide($this->symbol, $this->workside);


        // Контроль коридора
        $WorkSide = $this->GetWorkSide($pricenow, $TREK);

        if ($WorkSide == "HIEND" || $WorkSide == "LOWEND"){
            echo "<b><font color='#8b0000'>Цена вышла из технического коридора!!!</font></b>";
            // Логирование выхода
            $this->CloseCycle($TREK, "LEAVE"); // Закрытие цикла при выходе из коридора
            return true;
        }


        // ГЛОБАЛЬНЫЙ СКОРИНГ


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

        echo "<hr>";



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



        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1
        $this->WorkStat1($TREK, $pricenow);
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1

        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 2
        $this->WorkStat2($TREK, $AllOrdersREST, $pricenow);
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 2


        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 3
        $this->WorkStat3($TREK, $AllOrdersREST, $pricenow);
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 3

        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 3
        $this->WorkStat4($TREK, $AllOrdersREST, $pricenow);
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 3




        return true;

    }



    // СТАТУС1 - ВЫСТАВЛЕНИЕ МАРКЕТ ОРДЕРОВ
    private function WorkStat1($TREK, $pricenow){

        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1
        $OrdersBD = $this->GetOrdersBD($TREK, 1);

        echo "<h3>ОБРАБОТКА ОРДЕРОВ СТАТУСА 1</h3>";

        // ОБРАБОТКА SHORT


        // Ордер на наращивание позиции
        foreach ($OrdersBD as $key=>$OrderBD) {


            // Проверка на актуальность ордеров обработки ордера
            $distance = $this->CheckDistance($pricenow, $OrderBD);
            // Проверка на актуальность ордеров обработки ордера

            if ($distance > 5 || $distance < -5) continue;


            echo "#".$OrderBD['id']." СТАТУС ОРДЕРА <b>".$OrderBD['stat']."</b> - ".$OrderBD['orderid']." - <b>".$OrderBD['side']."</b> <br>";
            echo  "Дистанция по БД ".$distance."<br>";

            // Определение LONG/SHORT

            // Выставление первых ордеров
            if ($OrderBD['orderid'] == NULL){

                echo "Текущая цена".$pricenow."<br>";
                echo "Цена для выставления ордера".$OrderBD['price']."<br>";

                // Скоринг на первичное выставление выставление
                $resultscoring =  $this->CheckFirstOrder($TREK, $distance);
                show($resultscoring);
                // Скоринг на первичное выставление выставление

                // Счетчик ордеров с неедупдейтам

                if ($resultscoring['result'] === FALSE) continue;


                $order = $this->CreateFirstOrder($OrderBD, $resultscoring, $pricenow);

                // Записываем
                show($order);

                $ARRCHANGE = [];
                $ARRCHANGE['orderid'] = $order['id'];
                $ARRCHANGE['side'] = $order['side'];
                $ARRCHANGE['status'] = 2;
                $ARRCHANGE['type'] = $resultscoring['result'];
                $ARRCHANGE['maxprice'] = 0;
                //   $ARRCHANGE['lastprice'] = $order['last_exec_price'];
                $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");



            }




        }
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1




        return true;
    }


    // СТАТУС2 - КОНТРОЛЬ ОТКУПА ПЕРВОГО ЛИМИТНИКА
    private function WorkStat2($TREK, $AllOrdersREST, $pricenow){

        $OrdersBD = $this->GetOrdersBD($TREK, 2);
        echo "<h3>ОБРАБОТКА ОРДЕРОВ СТАТУСА 2</h3>";



        foreach ($OrdersBD as $key=>$OrderBD) {

            echo "#".$OrderBD['id']." СТАТУС ОРДЕРА <b>".$OrderBD['stat']."</b> - ".$OrderBD['orderid']." - <b>".$OrderBD['side']."</b> <br>";

            // Работа с ордерами вторго статуса
            echo "<b>Работа СТАТУС 2</b><br>";
            echo "Информация об ордере из REST<br>";


            $OrderREST = $this->GetOneOrderREST($OrderBD['orderid'], $AllOrdersREST); // Ордер РЕСТ статус 2

            show($OrderREST);
            var_dump($this->OrderControl($OrderREST));

            // Проверка лимитника на ИСПОЛНЕННОСТЬ
            if ($this->OrderControl($OrderREST) === FALSE){

                // ВНЕЗАПНАЯ ПОПАДАНИЕ В СТАТУС "CANCELED"
                if ($OrderREST['order_status'] == "Cancelled"){
                    echo "<font color='#8b0000'>ОРДЕР отменен (canceled)!!! </font> <br>";
                    echo "Отменяем его выставление!  <br>";
                    $ARRCHANGE = [];
                    $ARRCHANGE['orderid'] = NULL;
                    $ARRCHANGE['type'] = NULL;
                    $ARRCHANGE['side'] = NULL;
                    $ARRCHANGE['status'] = 1;
                    $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

                    continue;
                }


                echo "Текущая цена лонга:".$pricenow."<br>";
                echo "Ордер выставлен по цене:".$OrderREST['price']."<br>";


                // ПРОВЕРКА ТЕКУЩЕЙ ЦЕНЫ
                if ($this->workside == "long" && ($pricenow - $this->Basestep) > $OrderREST['price'])
                {

                    echo "<font color='#8b0000'>WORKSIDE: long;  Цена ушла выше. Нужно перевыставлят ордер!!! </font> <br>";
                    // Отменяем текущий ордер
                    $cancel = $this->EXCHANGECCXT->cancel_order($OrderBD['orderid'], $this->symbol);
                    show($cancel);

                    $ARRCHANGE = [];
                    $ARRCHANGE['orderid'] = NULL;
                    $ARRCHANGE['type'] = NULL;
                    $ARRCHANGE['side'] = NULL;
                    $ARRCHANGE['status'] = 1;
                    $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

                    continue;

                }

                continue;

            }


            echo "<font color='green'>Ордер статуса 2 исполнен</font><br>";
            // ОРДЕР ИСПОЛНЕН

            $CurrentSTOP = 0;
            if ($OrderREST['side'] == "Sell")
            {
                $CurrentSTOP = $OrderREST['price'] + $this->step*$this->stoploss;
            }

            if ($OrderREST['side'] == "Buy")
            {
                $CurrentSTOP = $OrderREST['price'] - $this->step*$this->stoploss;
            }


            $ARRCHANGE = [];
            $ARRCHANGE['status'] = 3;
            $ARRCHANGE['currentstop'] = $CurrentSTOP;
            $ARRCHANGE['lastprice'] = $OrderREST['price'];
            $ARRCHANGE['side'] = $OrderREST['side'];
            $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

            $this->AddTracDealTradeBD($TREK, $OrderREST['price'] , $OrderREST['side'],"in");

            echo "<hr>";
        }



        return true;
    }




    // СТАТУС3 - ТРЕЛЛИНГ ИЛИ СТОП-ЛОСС
    private function WorkStat3($TREK, $AllOrdersREST, $pricenow){

        $OrdersBD = $this->GetOrdersBD($TREK, 3);
        echo "<h3>ОБРАБОТКА ОРДЕРОВ СТАТУСА 3</h3>";


        foreach ($OrdersBD as $key=>$OrderBD) {

            echo "#".$OrderBD['id']." - <b>".$OrderBD['side']."</b> <br>";

            // Работа с ордерами вторго статуса
            echo "<b>Работа СТАТУС 3</b><br>";
            echo "Цена по БД:".$OrderBD['price']."<br>";
            echo "Откупился ПО:".$OrderBD['lastprice']."<br>";
            echo "<b>Текущая цена:</b> ".$pricenow."<br>";
            echo "Текущий стоп:".$OrderBD['currentstop']."<br>";

            // Выставляем стоп кондишинл по маркету
            if ($OrderBD['trallingorderid'] == NULL){
                echo "Выставляем ПЕРВЫЙ СТОП ордер на ТЕЙК ПОЗИЦИИ!!<br><br>";
                $this->CreateStop($OrderBD,$OrderBD['currentstop'], $TREK);
                continue;
            }

            $OrderREST = $this->GetOneOrderREST($OrderBD['trallingorderid'], $AllOrdersREST); // Ордер РЕСТ статус 2
            echo "<b>REST STOP ORDER: </b> <br>";
           // show($OrderREST);

            if ($OrderREST['order_status'] == "Filled")
            {
                echo "<font color='#8b0000'>СТОП ОРДЕР ИСПОЛНИЛСЯ!!!</font> <br>";

                $this->AddTrackHistoryBD($TREK, $OrderBD, $OrderREST); // Исполнен статус 2

                // ОЧИЩАЕМ БД!
                $ARRCHANGE = [];
                $ARRCHANGE['status'] = 1;
                $ARRCHANGE['currentstop'] = NULL;
                $ARRCHANGE['lastprice'] = NULL;
                $ARRCHANGE['trallingorderid'] = NULL;
                $ARRCHANGE['side'] = NULL;
                $ARRCHANGE['orderid'] = NULL;
                $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

                $this->AddTracDealTradeBD($TREK, $OrderREST['avgPrice'] , $OrderREST['side'],"out");

                continue;
            }


            $TRALLINGSTATUS = $this->TrallingControl($OrderBD, $pricenow);

            echo "СТАТУСЫ ТРЕЛЛИНГА<br>";
            var_dump($TRALLINGSTATUS);

            if ($TRALLINGSTATUS === TRUE)
            {
                echo "Пора фиксировать позицию лимитником<br>";

                $ARRCHANGE = [];
                $ARRCHANGE['status'] = 4;
                $ARRCHANGE['currentstop'] = NULL;
                $ARRCHANGE['lastprice'] = NULL;
                $ARRCHANGE['trallingorderid'] = NULL;
                $ARRCHANGE['side'] = NULL;
                $ARRCHANGE['orderid'] = NULL;
                $ARRCHANGE['maxprice'] = NULL;
                $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

                continue;



            }




        }



        return true;
    }


    // СТАТУС4 - ПРОДАЖА ПО ТРЕЛЛИНГУ
    private function WorkStat4($TREK, $AllOrdersREST, $pricenow)
    {
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 4
        $OrdersBD = $this->GetOrdersBD($TREK, 4);

        echo "<h3>ОБРАБОТКА ОРДЕРОВ СТАТУСА 4</h3>";
        foreach ($OrdersBD as $key=>$OrderBD)
        {

            echo "#".$OrderBD['id']." - <b>".$OrderBD['side']."</b> <br>";

            // Работа с ордерами вторго статуса
            echo "<b>Работа СТАТУС 4</b><br>";

            if ($OrderBD['orderid'] == NULL){

                echo "Выставляем первый лимитник!!!<br><br>";
                $resultscoring['side'] = ($OrderBD['workside'] == 'long') ? 'short' : 'long';
                $resultscoring['result'] = "LIMIT";

                $order = $this->CreateFirstOrder($OrderBD, $resultscoring, $pricenow);
                show($order);

                $ARRCHANGE = [];
                $ARRCHANGE['orderid'] = $order['id'];
                $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

                continue;

            }

            $OrderREST = $this->GetOneOrderREST($OrderBD['orderid'], $AllOrdersREST);
            echo "<b>REST LIMIT ORDER: </b> <br>";
            show($OrderREST);


            if ($this->OrderControl($OrderREST) === FALSE){

                // ВНЕЗАПНАЯ ПОПАДАНИЕ В СТАТУС "CANCELED"
                if ($OrderREST['order_status'] == "Cancelled"){
                    echo "<font color='#8b0000'>ОРДЕР отменен (canceled)!!! </font> <br>";
                    echo "Отменяем его выставление!  <br>";
                    $ARRCHANGE = [];
                    $ARRCHANGE['orderid'] = NULL;
                    $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

                    continue;
                }


                echo "Текущая цена лонга:".$pricenow."<br>";
                echo "Ордер выставлен по цене:".$OrderREST['price']."<br>";


                continue;

            }







        }



    }


    private function TrallingControl($OrderBD, $pricenow)
    {

        // Запрашиваем заново $pricenow
        $this->ORDERBOOK = $this->GetOrderBook($this->symbol);
        $pricenow = $this->GetPriceSide($this->symbol, $this->workside);
        // Запрашиваем заново $pricenow

        echo "Цена необходимая чтобы зайти в зону треллинга:".($OrderBD['lastprice'] + $this->step)."<br>";

        if ($OrderBD['side'] == "Buy")
        {

            $delta = 0;
            if ($pricenow > ($OrderBD['lastprice'] + $this->step) )
            {
                echo "<font color='green'> ЗАШЛИ В ЗОНУ ТРЕЛЛИНГА</font><br>";
                // Перезаписываем максцену в треллинге
                if ($pricenow > $OrderBD['maxprice'])
                {
                    echo "Перезаписываем MAXPRICE<br> ";
                    $ARRCHANGE['maxprice'] = $pricenow;
                    $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");
                    $OrderBD['maxprice'] = $pricenow;
                }

                $delta = $OrderBD['maxprice'] - $pricenow;

                echo "Максимально зафиксированная цена: ".$OrderBD['maxprice']."<br>";
                echo "Отклонение от максимально зафиксированной цены: ".$delta."<br>";

            }

            if ($delta > $this->step/$this->deltacoef) return true;



        }




        return false;


    }

    private function CreateStop($OrderBD, $StopPrice, $TREK)
    {


        if ($OrderBD['workside'] == "long") $bp = $TREK['rangeh'];
        if ($OrderBD['workside'] == "short") $bp = $TREK['rangel'];

        $params = [
            'stop_px' => $StopPrice, // trigger $price, required for conditional orders
            'base_price' => $bp,
            'trigger_by' => 'LastPrice', // IndexPrice, MarkPrice
            'reduce_only' => true,
        ];

        $inverted_side = ($OrderBD['side'] == 'Buy') ? 'Sell' : 'Buy';

        show($inverted_side);

        $order = $this->EXCHANGECCXT->create_order($this->symbol,"market", $inverted_side, $OrderBD['amount'], null, $params);


        $ARRCHANGE = [];
        $ARRCHANGE['trallingorderid'] = $order['id'];
        $ARRCHANGE['currentstop'] = $StopPrice;

        $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");




        return true;

    }

    private function CheckGlobalSCORING()
    {


        return true;


        $otklonenie = 0;
        $pricenow = $this->GetPriceSide($this->symbol, "long");

        $this->KLINES30M = $this->EXCHANGECCXT->fetch_ohlcv($this->symbol, '30m', null, 9);
        $MaVAL = GetMA($this->KLINES30M);
        //  show($MaVAL);


        $this->KLINES15M = $this->EXCHANGECCXT->fetch_ohlcv($this->symbol, '30m', null, 15);
        $RSI =  GetRSI($this->KLINES15M);
        // show($RSI);



        $otklonenie = $pricenow - $MaVAL;
        if ($pricenow < $MaVAL) $otklonenie = $MaVAL - $pricenow;

        show($MaVAL);
        show($otklonenie);
        show($RSI);

        if ($otklonenie > $this->maDev*$this->step) return false;

        if ($RSI > $this->maxRSI) return false;
        if ($RSI < $this->minRSI) return false;

        if ($RSI > 50 && $this->workside == "long") return true;
        if ($RSI < 50 && $this->workside == "short") return true;


        return true;
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

    private function CheckFirstOrder($TREK, $distance){


        // Проверка на дистанцию
        echo "Расстояние ордера до цены : ".$distance."<br>";

        // Самое первое выставление

        $RETURN['result'] = FALSE;

        if ($this->SCORING === FALSE) return $RETURN;

        // Проверка на глобальный скоринг


        $CountPosition = $this->CountActiveOrders($TREK, "2");
        $CountPosition = $CountPosition + $this->CountActiveOrders($TREK, "3");
        $CountPosition = $CountPosition + $this->CountActiveOrders($TREK, "4");

        if ($distance < 0 && $this->workside == "long") return $RETURN;
        if ($distance > 0 && $this->workside == "short") return $RETURN;

        //show($this->workside);
        if ($distance == 0)
        {

            if ($CountPosition >= $this->maxposition)
            {
                echo "Достигнут лимит размера позиции<br>";
                return $RETURN;
            }

            $RETURN['result'] = "LIMIT";
            $RETURN['side'] = $this->workside;
            return $RETURN;

        }


        return $RETURN;

        // СКОРИНГ НА ЗАПУСКЕ!!!




    }

    private function CheckDistance($pricenow, $OrderBD){

        $distance = 0;

        $distance = $OrderBD['price'] - $pricenow;

        $distance = $distance/$this->step;
        $distance = round($distance);

        return $distance;
    }


    private function GetWorkSide($pricenow, $TREK){


        if ($pricenow > $TREK['rangeh'] && $TREK['workside'] == "long" ) return "HIEND";
        if ($pricenow < $TREK['rangel'] && $TREK['workside'] == "short" ) return "LOWEND";

        return false;



    }



    private function CreateFirstOrder($OrderBD, $resultscoring, $pricenow){


        $sideorder = $this->GetTextSide($resultscoring['side']);


        show($sideorder);
        var_dump($OrderBD['amount']);
        show($OrderBD['price']);


        if ($resultscoring['result'] == "LIMIT"){


            $params = [
                'time_in_force' => "PostOnly",
                'reduce_only' => false,
            ];

            if ($sideorder == "long") $price = $pricenow - $this->Basestep;
            if ($sideorder == "short") $price = $pricenow + $this->Basestep;

            $order = $this->EXCHANGECCXT->create_order($this->symbol,"limit",$sideorder, $OrderBD['amount'], $price, $params);
            return $order;



        }



        return false;


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
            $count = R::count("orders", 'WHERE idtrek =? AND status=? AND orderid IS NOT NULL', [$TREK['id'], $stat]);
        }


        if ($stat == 2 ){
            $count = R::count("orders", 'WHERE idtrek =? AND status=?', [$TREK['id'], $stat]);
        }


        if ($stat == 3 ){
            $count = R::count("orders", 'WHERE idtrek =? AND status=?', [$TREK['id'], $stat]);
        }

        if ($stat == 4 ){
            $count = R::count("orders", 'WHERE idtrek =? AND status=?', [$TREK['id'], $stat]);
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
        if ($textside == "long" || $textside == "LONG") $sideorder = "buy";
        if ($textside == "short" || $textside == "SHORT") $sideorder = "sell";
        return $sideorder;
    }




    public function OrderControl($order){

        if ($order['order_status'] == "Cancelled") return false;


//        if (!empty($order['amount']) && !empty($order['filled'])){
//            if ($order['amount'] == $order['filled']) return true;
//        }

        if ($order['order_status'] == "Filled") return true;

        return false;

    }


    public function GetOrderBook($symbol){
        $orderbook[$symbol] = $this->EXCHANGECCXT->fetch_order_book($symbol, 20);
        return $orderbook;

    }



    public function GenerateStepPrice($CountOrders){
        $MASS = [];

        for ($i = 1; $i <= $CountOrders; $i++) {
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


    private function EkranSymbol()
    {
        $newsymbol = str_replace("/", "", $this->symbol);
        return $newsymbol;
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


    private function GetTreksBD()
    {
        $terk = R::findAll($this->namebdex, 'WHERE emailex =? AND workside=?', [$this->emailex, $this->workside]);
        return $terk;
    }


    private function GetOrdersBD($TREK, $status)
    {

        $MASS = R::findAll("orders", 'WHERE idtrek =? AND status=? AND workside=? ORDER by `count` DESC', [$TREK['id'], $status, $this->workside]);
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

        $RESULT = $this->EXCHANGECCXT->fetch_orders($this->symbol, null, 100);



        return $RESULT;


    }


    private function GetOneOrderREST($id, $AllOrdersREST)
    {

        foreach ($AllOrdersREST as $key=>$val)
        {
            if ($val['id'] == $id) return $val['info'];


        }

        $order = $this->EXCHANGECCXT->fetch_order($id,$this->symbol)['info'];
        //    $order['status'] = $order['status'];
        $order['amount'] = $order['qty'];
        $order['price'] = $order['price'];

        // $MASS[$order['id']] = $order;
        return $order;



    }


    private function AddTracDealTradeBD($TREK, $price, $side, $type)
    {


        // Цена захода

        $MASS = [
            'trekid' => $TREK['id'],
            'trekside' => $TREK['workside'],
            'type' => $type,
            'side' => $side,
            'time' => date("Y-m-d H:i:s"),
            'price' => $price,
        ];

        //ДОБАВЛЯЕМ В ТАБЛИЦУ
        $tbl3 = R::dispense("dealhistory");
        //ДОБАВЛЯЕМ В ТАБЛИЦУ

        //ДОБАВЛЯЕМ В ТАБЛИЦУ
        foreach ($MASS as $name => $value) {
            $tbl3->$name = $value;
        }
        R::store($tbl3);

        echo "Сохранили запись о сделке в БД <br>";


        return true;

    }



    private function AddTrackHistoryBD($TREK, $OrderBD, $OrderREST, $SCORING = FALSE)
    {
        $dollar = 0;

        // Цена захода

        if ($OrderBD['side'] == "BUY")
        {
            $enter = $OrderBD['lastprice'];
            $pexit = $OrderREST['avgPrice'];
            $delta = changemet($enter, $pexit) - 0.072;
            $dollar = ($OrderBD['lastprice']/100)*$delta*$OrderREST['origQty'];
        }

        if ($OrderBD['side'] == "SELL")
        {
            $enter = $OrderBD['lastprice'];
            $pexit = $OrderREST['avgPrice'];
            $delta = changemet($pexit, $enter) - 0.072;
            $dollar = ($OrderBD['lastprice']/100)*$delta*$OrderREST['origQty'];
        }

        $ACTBAL = $this->GetBal()['USDT']['total'];

        $MASS = [
            'trekid' => $TREK['id'],
            'side' => $OrderBD['side'],
            'dateex' => date("d-m-Y"),
            'timeexit' => date("H:i:s"),
            'enter' => $OrderBD['lastprice'],
            'exit' => $OrderREST['avgPrice'],
            'amount' => $OrderREST['origQty'],
            'dollar' => $dollar,
            'delta' => $delta,
            'bal' => $ACTBAL,
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