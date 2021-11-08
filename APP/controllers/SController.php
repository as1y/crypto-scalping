<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class SController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";


    // BINANCE

    public $ApiKey = "qzaF8Ut8SmDPK6XkA4fbgCZSuoKV11lMKVLMJU6rlrEPKcz3b6uLGHTRQ1YM9Anv";
    public $SecretKey = "8891gvmiOXz6ITGd8m3QWGEkQXHvBg5LeMOJIy8nvSbqQNviLSBQf7z7YK2mGSbv";


    // Переменные для стратегии
    public $leverege = 90;
    public $symbol = "BTC/USDT";
    public $Basestep = 0.5;
    public $emailex  = "raskrutkaweb@yandex.ru"; // Сумма захода USD
    public $namebdex = "treks";


    // ПАРАМЕТРЫ СТРАТЕГИИ
    private $workside = "short";
    private $lot = 0.001; // Базовый заход
    private $RangeH = 75000;
    private $RangeL = 50000;
    private $step = 120; // Размер шага между ордерами
    private $stoploss = 8; // Размер шага между ордерами
    private $maxposition = 3;
    private      $maVAL = 14; // Коэффицент для МА
    private      $maDev = 3; // Отклонение МА
    private      $countPosition = 3; // Счетчик ордеров?
    private      $maxRSI = 70; // Фильтр по RSI
    private      $minRSI = 30; // Фильтр по RSI
    private      $deltacoef = 6; // Коэффицентр треллинга


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
        $this->EXCHANGECCXT = new \ccxt\binance (array(
            'apiKey' => $this->ApiKey,
            'secret' => $this->SecretKey,
            'timeout' => 30000,
            'enableRateLimit' => true,
            //         'marketType' => "linear",
            'options' => array(
                'defaultType' => 'future'
                //  'marketType' => "linear"
            )
        ));


        $this->BALANCE = $this->GetBal()['USDT'];

        $this->ORDERBOOK = $this->GetOrderBook($this->symbol);

        // СВЕЧИ 15
        $this->KLINES15M = $this->EXCHANGECCXT->fetch_ohlcv($this->symbol, '15m', null, 15);

        //СВЕЧИ 30
        $this->KLINES30M = $this->EXCHANGECCXT->fetch_ohlcv($this->symbol, '30m', null, 15);


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
        $ARR['date'] = date("H:i:s");
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

        $pricenow = $this->GetPriceSide($this->symbol, "long");


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
                $resultscoring =  $this->CheckFirstOrder($distance);
                show($resultscoring);
                // Скоринг на первичное выставление выставление

                // Счетчик ордеров с неедупдейтам

                if ($resultscoring['result'] === FALSE) continue;



                $order = $this->CreateFirstOrder($OrderBD, $resultscoring, $TREK);

                // Записываем
                show($order);

                $ARRCHANGE = [];
                $ARRCHANGE['orderid'] = $order['id'];
                $ARRCHANGE['side'] = $order['side'];
                $ARRCHANGE['status'] = 2;
                $ARRCHANGE['type'] = $resultscoring['result'];
                //   $ARRCHANGE['lastprice'] = $order['last_exec_price'];
                $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");



            }




        }
        // ОБРАБОТКА ОРДЕРОВ СТАТУСА 1




        return true;
    }


    // СТАТУС2 - ПРОВЕРКА НА ИСПОЛНЕННОСТЬ
    private function WorkStat2($TREK, $AllOrdersREST, $pricenow){

        $OrdersBD = $this->GetOrdersBD($TREK, 2);
        echo "<h3>ОБРАБОТКА ОРДЕРОВ СТАТУСА 2</h3>";


        foreach ($OrdersBD as $key=>$OrderBD) {

            echo "#".$OrderBD['id']." СТАТУС ОРДЕРА <b>".$OrderBD['stat']."</b> - ".$OrderBD['orderid']." - <b>".$OrderBD['side']."</b> <br>";

            // Работа с ордерами вторго статуса
            echo "<b>Работа СТАТУС 2</b><br>";
            echo "Информация об ордере из REST<br>";


            $OrderREST = $this->GetOneOrderREST($OrderBD['orderid'], $AllOrdersREST); // Ордер РЕСТ статус 2


            // Проверка на исоплненность
            if ($this->OrderControl($OrderREST) === FALSE){
                // Проверка ОРДЕРА НА СТОП!!!

                // Проверка на актуальность ордеров обработки ордера
                $distance = $this->CheckDistance($pricenow, $OrderBD);
                // Проверка на актуальность ордеров обработки ордера
                echo  "Дистанция по БД ".$distance."<br>";
                echo  "Ордер выставлен по цене: ".$OrderBD['price']."<br>";

                // Ордер слишком далеко. Снимаем его из-за ограничений биржи
                if ($distance >= 5 && $OrderBD['workside'] == "long") $this->CancelStatus2($OrderBD, $OrderREST);

                if ($distance <= -5 && $OrderBD['workside'] == "short") $this->CancelStatus2($OrderBD,$OrderREST);

                if ($this->SCORING === FALSE) $this->CancelStatus2($OrderBD,$OrderREST);

                echo "Ордер не откупился<br>";
                continue;

            }


            echo "<font color='green'>Ордер статуса 2 исполнен</font><br>";
            // ОРДЕР ИСПОЛНЕН

            $CurrentSTOP = 0;
            if ($OrderREST['side'] == "SELL")
            {
                $CurrentSTOP = $OrderREST['avgPrice'] + $this->step*$this->stoploss;
            }

            if ($OrderREST['side'] == "BUY")
            {
                $CurrentSTOP = $OrderREST['avgPrice'] - $this->step*$this->stoploss;
            }


            $ARRCHANGE = [];
            $ARRCHANGE['status'] = 3;
            $ARRCHANGE['currentstop'] = $CurrentSTOP;
            $ARRCHANGE['lastprice'] = $OrderREST['avgPrice'];
            $ARRCHANGE['side'] = $OrderREST['side'];
            $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

            echo "<hr>";
        }



        return true;
    }


    // СТАТУС2 - ПРОВЕРКА НА ЗАКРЫТИЯ ПО СТОП ЛОССУ ( вместе с треллингом)
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


            // Если пустой TrallingID, то выставляем его
            if ($OrderBD['trallingorderid'] == NULL){
                echo "Выставляем ПЕРВЫЙ СТОП ордер на ТЕЙК ПОЗИЦИИ!!<br><br>";
                $this->CreateStopTrallingOrder($OrderBD,$OrderBD['currentstop']);
                continue;
            }

            // Проверяем статус ТРЕЛЛИНГА!
            $OrderREST = $this->GetOneOrderREST($OrderBD['trallingorderid'], $AllOrdersREST); // Ордер РЕСТ статус 2
            echo "<b>REST STOP ORDER: </b> <br>";
            show($OrderREST);


            // Если ордер не ИСПОЛНЕН, то контролируем треллинг
            if ($this->OrderControl($OrderREST) === FALSE){

                // Если треллинг не передвинут. То проверяем статус этого ордера
                echo "<b><font color='purple'>СТОП ОРДЕР ЖДЕТ КОГДА ОТКУПИТЬСЯ</font></b><br>";
                // Если он есть в БД, то значит он выставлен и мы проверяем его статус

                $canceled = false;
                if ($OrderREST['status'] == "CANCELED") $canceled = true;

                $this->TrallingControl($OrderBD, $pricenow, $canceled);

                continue;

            }


            echo "<font color='green'>Ордер исполнился!</font><br>";

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



            continue;


        }



        return true;
    }




    private function CancelStatus2($OrderBD,$OrderREST)
    {

        if ($OrderREST['status'] != "CANCELED") $this->EXCHANGECCXT->cancel_order($OrderBD['orderid'], $this->symbol) ;

        $ARRCHANGE = [];
        $ARRCHANGE['status'] = 1;
        $ARRCHANGE['orderid'] = NULL;
        $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");

        return true;
    }



    private function TrallingControl($OrderBD, $pricenow, $canceled)
    {

        // Запрашиваем заново $pricenow
        $this->ORDERBOOK = $this->GetOrderBook($this->symbol);
        $pricenow = $this->GetPriceSide($this->symbol, "long");
        // Запрашиваем заново $pricenow

        if ($OrderBD['side'] == "BUY")
        {
            echo "<b>СТОРОНА LONG</b><br>";
            if ($pricenow > ($OrderBD['lastprice'] + $this->step) )
            {
                echo "<font color='green'> В ЗОНЕ ТРЕЛЛИНГА</font><br>";
                $delta = $pricenow - $OrderBD['currentstop'];
                echo "ДЕЛЬТА: ".$delta."<br>";

                if ($delta > $this->step/$this->deltacoef)
                {

                    echo "<font color='green'> В СТАДИИ ПОДПОРКИ</font><br>";
                    $ActualTrallingStop = $pricenow - $this->step/$this->deltacoef;
                    // Отменяем текущий

                    // Проверка вдруг ордер уже отменен
                    if ($canceled == false)
                    {
                        $this->EXCHANGECCXT->cancel_order($OrderBD['trallingorderid'], $this->symbol);
                    }else{
                        echo "Ордер уже был отменен<br>";
                    }



                    // Выставляем новый и ПЕРЕЗАПИСЫВАЕМ в БД
                    $this->CreateStopTrallingOrder($OrderBD,$ActualTrallingStop);

                    echo "<b><font color='green'>Треллинг перевыставлен!</font></b><br>";


                    return true;
                }



            }

        }



        if ($OrderBD['side'] == "SELL")
        {
            echo "<b>СТОРОНА LONG</b><br>";
            if ($pricenow < ($OrderBD['lastprice'] + $this->step) )
            {
                echo "<font color='green'> В ЗОНЕ ТРЕЛЛИНГА</font><br>";
                $delta = $OrderBD['currentstop'] - $pricenow;
                echo "ДЕЛЬТА: ".$delta."<br>";
                if ($delta > $this->step/$this->deltacoef)
                {
                    $ActualTrallingStop = $pricenow + $this->step/$this->deltacoef;
                    // Отменяем текущий

                    // Проверка вдруг ордер уже отменен
                    if ($canceled == false)
                    {
                        $this->EXCHANGECCXT->cancel_order($OrderBD['trallingorderid'], $this->symbol);
                    }

                    // Выставляем новый и ПЕРЕЗАПИСЫВАЕМ в БД
                    $this->CreateStopTrallingOrder($OrderBD,$ActualTrallingStop);
                    echo "<b><font color='green'>Треллинг перевыставлен!</font></b><br>";
                    return true;
                }
            }

        }




        return false;

    }




    private function CreateStopTrallingOrder($OrderBD, $StopPrice)
    {

        $params = [
            'stopPrice'=> $StopPrice,  # your stop price
        ];

        $inverted_side = ($OrderBD['side'] == 'BUY') ? 'sell' : 'buy';
        $order = $this->EXCHANGECCXT->create_order($this->symbol,"stop_market", $inverted_side, $OrderBD['amount'], null, $params);

        $ARRCHANGE = [];
        $ARRCHANGE['trallingorderid'] = $order['id'];
        $ARRCHANGE['currentstop'] = $StopPrice;

        $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");




        return true;

    }



    private function CheckGlobalSCORING()
    {


        $otklonenie = 0;
        $pricenow = $this->GetPriceSide($this->symbol, "long");

        $RSI =  GetRSI($this->KLINES15M);
        // show($RSI);

        $MaVAL = GetMA($this->KLINES30M);
        //  show($MaVAL);

        $otklonenie = $pricenow - $MaVAL;
        if ($pricenow < $MaVAL) $otklonenie = $MaVAL - $pricenow;

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



    private function CheckFirstOrder($distance){


        // Проверка на дистанцию
        echo "Расстояние ордера до цены : ".$distance."<br>";

        // Самое первое выставление

        $RETURN['result'] = FALSE;

        if ($this->SCORING === FALSE) return $RETURN;

        // Проверка на глобальный скоринг



        if ($distance < 0 && $this->workside == "long") return $RETURN;
        if ($distance > 0 && $this->workside == "short") return $RETURN;

        //show($this->workside);
        // ПРОВЕРКА НА ЛОНГ
        if ($distance >= 0)
        {

            if ($distance > $this->maxposition)
            {
                echo "Ордер дальше дистнации максимальной позиции<br>";
                return $RETURN;
            }

            if ($distance < 1)
            {
                echo "Не корректная дистанция";
                return $RETURN;
            }

            $RETURN['result'] = "MARKET";
            $RETURN['side'] = "long";
            return $RETURN;

        }

        // СКОРИНГ НА ШОРТ
        if ($distance <= 0)
        {
            echo "ПОПАЛИ В ШОРТОВУЮ ТЕМУ";

            if ($distance*(-1) > $this->maxposition)
            {
                echo "Ордер дальше дистнации максимальной позиции<br>";
                return $RETURN;
            }

            if ($distance*(-1) < 1)
            {
                echo "Не корректная дистанция";
                return $RETURN;
            }

            $RETURN['result'] = "MARKET";
            $RETURN['side'] = "short";
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



    private function CreateFirstOrder($OrderBD, $resultscoring, $TREK){


        $sideorder = $this->GetTextSide($resultscoring['side']);

        // $inverted_side = ($resultscoring['side'] == 'buy') ? 'sell' : 'buy';


        show($sideorder);
        var_dump($OrderBD['amount']);
        show($OrderBD['price']);


        if ($resultscoring['result'] == "MARKET"){


            $params = [
                'stopPrice'=> $OrderBD['price'],  # your stop price
            ];


            $order = $this->EXCHANGECCXT->create_order($this->symbol,"stop_market", $sideorder, $OrderBD['amount'], null, $params);

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
        if ($textside == "long" || $textside == "LONG") $sideorder = "buy";
        if ($textside == "short" || $textside == "SHORT") $sideorder = "sell";
        return $sideorder;
    }




    public function OrderControl($order){

        if (!empty($order['origQty']) && !empty($order['executedQty'])){
            if ($order['origQty'] == $order['executedQty']) return true;
        }

        if ($order['status'] == "FILLED") return true;

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

        $market = $this->EXCHANGECCXT->market($this->symbol);

        $params = array(
            'symbol' => $market['id'], // convert a unified CCXT symbol to an exchange-specific market id
            'leverage' => 10,
        );

        $this->EXCHANGECCXT->fapiPrivate_post_leverage($params);

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
        $order['amount'] = $order['origQty'];
        $order['last'] = $order['avgPrice'];

        // $MASS[$order['id']] = $order;
        return $order;



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