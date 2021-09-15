<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class WorkController extends AppController {
	public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";


    public $ApiKey = "9juzIdfqflVMeQtZf9";
    public $SecretKey = "FwUD2Ux5sjLo8DyifqYr4cfWgxASblk7CZo7";

    // Переменные для стратегии
    public $summazahoda = 5; // Сумма захода с оригинальным балансом
    public $leverege = 90;
    public $symbol = "BTC/USDT";
    public $emailex  = "raskrutkaweb@yandex.ru"; // Сумма захода USD
    public $namebdex = "treks";

    private $CENTER = ""; // Если значение пустое, то центр будет определяться автоматом при старте

    private $CountOrders = 10; // Общее кол-во ордеров

    // Переменные для стратегии

    // ТЕХНИЧЕСКИЕ ПЕРЕМЕННЫЕ
    private $WORKTREKS = [];
    private $ORDERBOOK = [];
    private $EXCHANGECCXT = [];
    private $BALANCE = [];
    private $esymbol = "";
    private $MASSORDERS = [];
    private $step = "";


    // Переменные для стратегии
    private $minst = 0.17; // Коэфицент минимального шага

    private $skolz = 10; // Процент выше которого выставляется лимитник

    private $boostsize = 3; // На какое кол-во увеличиваеться ордер, если включен BOOST

    private $coeff = 0.5; // Коэффицент на сколько удлинняется длинна шага при бусте
    private $maxposition = 4; // Максимальный размер набираемый позиции


    private $deepposition = 3; // Глубина ордеров после которых убивать позицию при ПЕРЕХОДЕ ЧЕРЕЗ 0
    private $stopfixorder = 4; // Глубина при которой закрывается позиция при уходе вниз

    private $stopfix = 5;

    private $timerestart = 2; // Через сколько перезапускать скрипт после остановки

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

        $this->esymbol = $this->EkranSymbol();

        $this->BALANCE = $this->GetBal()['USDT']['free'];


        $this->ORDERBOOK = $this->GetOrderBook($this->symbol);


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
        sleep("1");

    }

    private function AddTrek()
    {

        $this->SetLeverage($this->leverege);

        $pricenow = $this->GetPriceSide($this->symbol, "long");

        $KLINES30M = $this->EXCHANGECCXT->fetch_ohlcv($this->symbol, '30m', null, 15);
        $SCORING30M = SCORING($KLINES30M);
        $SCORING30M = json_encode($SCORING30M, true);


    //    if ($this->CENTER == "")  $this->CENTER = round(GetKoridorSize($KLINES30M, 2)['AVG']);

            if ($this->CENTER == "")  $this->CENTER = round($pricenow);



        echo "Рассчет ордеров<br>";


        $minimumstep = ($pricenow/100)*$this->minst;
        $minimumstep = round($minimumstep);

            // Проверка на минимальный шаг
          echo "ШАГ ЦЕНЫ: ".$minimumstep."<br>";
        $this->step = $minimumstep;


        if ($this->BALANCE < $this->summazahoda*$this->CountOrders/$this->leverege){
            echo "НЕ ХВАТАЕТ БАЛАНСА";
            exit;
        }


        // РАСЧЕТ ШАГОВ
        $this->MASSORDERS = $this->GenerateStepPrice();

        // Добавляем в массив ордеров сумму захода
        $this->CalculatePriceOrders();

        // Дополняем массив ордеров детальными значениями (сторона и quantity)
        $this->AddMassOrders();


        // Добавление ТРЕКА в БД

        $rangeh = $this->CENTER + ($this->step*$this->CountOrders);
        $rangel = $this->CENTER - ($this->step*$this->CountOrders);





        $ARR['emailex'] = $this->emailex;
        $ARR['status'] = 1;
        $ARR['action'] = "ControlOrders";
        $ARR['boost'] = 0;
        $ARR['countboost'] = 0;
        $ARR['contrpoz'] = 0;
        $ARR['symbol'] = $this->symbol;
        $ARR['lever'] = $this->leverege;
        $ARR['count'] = $this->CountOrders;
        $ARR['step'] = $this->step;
        $ARR['rangeh'] = $rangeh;
        $ARR['rangel'] = $rangel;
        $ARR['avg'] = $this->CENTER;
        $ARR['minst'] = $this->minst;
        $ARR['countstop'] = $this->stopfix;
        $ARR['workside'] = "LONG";
        $ARR['startbalance'] = $this->BALANCE;
        $ARR['date'] = date("H:i:s");
        $ARR['stamp'] = time();
        $ARR['scoring30'] = $SCORING30M;

        $idtrek = $this->AddARRinBD($ARR);
        echo "<b><font color='green'>ДОБАВИЛИ ТРЕК</font></b>";
        // Добавление ТРЕКА в БД


        // Добавление ордеров в БД
        foreach ($this->MASSORDERS['long'] as $key=>$val){
            $ARR = [];
            $ARR['idtrek'] = $idtrek;
            $ARR['stat'] = 1;
            $ARR['count'] = $key;
            $ARR['side'] = "long";
            $ARR['amount'] = $val['quantity'];
            $ARR['price'] = $val['price'];
            $ARR['first'] = 1;
            $this->AddARRinBD($ARR, "orders");
        }
        foreach ($this->MASSORDERS['short'] as $key=>$val){
            $ARR = [];
            $ARR['idtrek'] = $idtrek;
            $ARR['stat'] = 1;
            $ARR['count'] = $key;
            $ARR['side'] = "short";
            $ARR['amount'] = $val['quantity'];
            $ARR['price'] = $val['price'];
            $ARR['first'] = 1;
            $this->AddARRinBD($ARR, "orders");
        }


        // Добавление ордеров в БД
        return true;

    }

    // Статус 1 - когда находимся в КОРИДОРЕ
    private function WorkStatus1($TREK)
    {

        $pricenow = $this->GetPriceSide($this->symbol, $TREK['workside']);
        $WorkSide = $this->GetWorkSide($pricenow, $TREK);
        show($WorkSide);

        // Вывод рабочей информации
        echo "<h3>Базовые параметры</h3>";
        echo "<b>BOOST:</b>".$TREK['boost']."<br>";
        echo "<b>Верхняя граница коридора:</b>".$TREK['rangeh']."<br>";
        echo "<b>Нижняя граница коридора:</b>".$TREK['rangel']."<br>";



        if ($WorkSide == "HIEND" || $WorkSide == "LOWEND"){
            echo "<b><font color='#8b0000'>Цена вышла из коридора!!!</font></b>";
            $this->CloseCycle($TREK);
            return true;
        }


        echo "<hr>";



        // Смена рабочей активной стороны
        if ($TREK['workside'] != $WorkSide){
            // На случай смены активной стороны

            echo "<b>Переход из одной позиции в другую</b>";

            // ВКЛЮЧАЕМ BOOST
           // $countboost = 0;
          //  $countboost = R::count("orders", 'WHERE idtrek =? AND stat=?', [$TREK['id'], 2]);
            //$countboost = $countboost*$this->compensator;
            // Включаем BOOST
           // if ($countboost > 0) $ARRTREK['boost'] = 1;


              $contrpoz = R::count("orders", 'WHERE idtrek =? AND stat=?', [$TREK['id'], 2]);
              if ($contrpoz > 0) $contrpoz = 1;

            // Закрытие позиции по стопу
         //   $this->CloseStopPosition($TREK, $TREK['workside']);


            $ARRTREK['contrpoz'] = $contrpoz;
            $ARRTREK['workside'] = $WorkSide;
            $this->ChangeARRinBD($ARRTREK, $TREK['id']);

            // Отмена ордеров с другой стороны


            $TREK['workside'] = $WorkSide;

            // На случай смены активной стороны
        }


        // Проверка на глубину цены
        // Проверяем кол-во выставленных ордеров. Если оно равно глубине, то закрываем контр позицию
        $this->ControlDeepPosition($TREK, $pricenow);


        // Работа с Тейк профитом на весь цикл
        $this->ControlTakeProfitCycle($TREK, $pricenow);

         $countminus =  $this->GlobalStop($TREK, $pricenow);
         echo "<b>Цикл в минусе на </b>: <b>".$countminus."</b> пунктов<br>";



        // Статус ордера - ОРДЕРА на НАРАЩИВАНИЕ ПОЗИЦИИ
        if ($TREK['action'] == "ControlOrders") $this->ActionControlOrders($TREK, $pricenow);
        // Ордера на фиксирование позиции

        // Контроллер ситуации

        return true;

    }


    // Статус 2 - Вышли из коридора
    private function WorkStatus2($TREK){

        echo "<h1><font color='green'>КОРИДОР ЗАВЕРШИЛ РАБОТУ</font></h1>";

        $timework = time() - $TREK['stampexit'];
        $minute = $timework/60;
        echo "Время ожидания после закрытия ".$minute."<br>";

        $timealltrack = time() - $TREK['stamp'];
        $minutetrek = $timealltrack/60;
        echo "Время работы всего скрипта ".$minutetrek."<br>";


        if ($minute > $this->timerestart && $this->CENTER == ""){

            $ACTBAL = $this->GetBal()['USDT']['total'];
            $profit = $ACTBAL - $TREK['startbalance'];

            $SCORING = json_decode($TREK['scoring30'], true);


            $ARR = [];
            $ARR['timestart'] = $TREK['date'];
            $ARR['timeclose'] = date("H:i:s");
            $ARR['minutework'] = $minutetrek;
            $ARR['center'] = $TREK['avg'];
            $ARR['startbalance'] = $TREK['startbalance'];
            $ARR['close'] = $ACTBAL;
            $ARR['profit'] = $profit;
            $ARR['minst'] = $TREK['minst'];
            $ARR['stop'] = $TREK['stop'];
            $ARR['rsi'] = $SCORING['RSI'];
            $ARR['korsize'] = $SCORING['KORSIZE'];
            $ARR['avgkor'] = $SCORING['AVGKOR'];
            $ARR['color'] = $SCORING['COLOR'];
            $ARR['dlinna'] = $SCORING['DLINNA'];

            $this->AddARRinBD($ARR, "cycle");



            // Перезапускаем ЦИКЛ
            R::wipe("treks");

            // Удаляем ТРЕК


        }





        return true;

    }


    private function ActionControlOrders($TREK, $pricenow){

        echo  "<b>Запускаем Action ControlOrders. Контролируем работу ордеров</b> <br>";

        $OrdersBD = $this->GetOrdersBD($TREK);




        foreach ($OrdersBD as $key=>$OrderBD) {

            echo "<hr>";
            echo "#".$OrderBD['id']." СТАТУС ОРДЕРА <b>".$OrderBD['stat']."</b> - ".$OrderBD['orderid']." - <b>".$OrderBD['side']."</b> ".$OrderBD['count']." <br>";


            // Ордер на наращивание позиции
            if ($OrderBD['stat'] == 1) {


                    // Выставление первых ордеров
                    if ($OrderBD['orderid'] == NULL){


                        echo "Текущая цена".$pricenow."<br>";
                        echo "Цена для выставления ордера".$OrderBD['price']."<br>";


                        $count = $this->CountActiveOrders($TREK);

                        // Скоринг на выставление
                        $resultscoring =  $this->CheckFirstOrder($TREK, $pricenow, $OrderBD, $count);


                        echo "Откупаем ордер по типу:<br>";
                        var_dump($resultscoring);
                        if ($resultscoring === FALSE) continue;

                        $order = $this->CreateFirstOrder($OrderBD, $resultscoring, $TREK);
                        // Записываем
                       // show($order);

                        $ARRCHANGE = [];
                        $ARRCHANGE['orderid'] = $order['id'];
                        $ARRCHANGE['type'] = $resultscoring;
                        $ARRCHANGE['boost'] = $TREK['boost'];
                        $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");


                        continue;
                         }



                echo "Информация об ордере из REST<br>";
                $OrderREST = $this->GetOneOrderREST($OrderBD['orderid']);

                // ВНЕЗАПНАЯ ПОПАДАНИЕ В СТАТУС "CANCELED"
                if ($OrderREST['status'] == "canceled"){
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

                    $count = $this->CountActiveOrders($TREK);

                    if ($count >= $this->maxposition){

                        echo "<b><font color='red'>ОРДЕР ЛИШНИЙ НАДО ОТМЕНЯТЬ</font></b>";

                        // Отменяем ордер
                        $cancel = $this->EXCHANGECCXT->cancel_order($OrderBD['orderid'], $this->symbol);
                        show($cancel);

                        $order['id'] = NULL;
                        $this->ChangeIDOrderBD($order, $OrderBD['id']);


                        continue;
                    }

                    continue;
                }



                // ОРДЕР ОТКУПИОСЯ. ВЫСТАВЛЯЕМ РЕВЕРС

                    // Цена по которой нужно выставлять реверсный
                    $pricenow = $this->GetPriceSide($this->symbol, $OrderBD['side']);

                    if ($OrderBD['side'] == "long")  {
                        $price = $OrderREST['average'] + $TREK['step'];
                         if ($TREK['boost'] == 1)  $price = $price + round($TREK['step']*$this->coeff);
                    }
                    if ($OrderBD['side'] == "short")  {
                        $price = $OrderREST['average'] - $TREK['step'];
                        if ($TREK['boost'] == 1)  $price = $price - round($TREK['step']*$this->coeff);
                    }



                    echo "<font color='green'>Ордер откупился по цене</font> ".$OrderREST['average']."<br>";
                    echo "Будем выставлять по: ".$price."<br>";
                    echo "Текущая цена - ".$pricenow."<br>";


                    // ВЫСТАВЛЕНИЕ РЕВЕРСНОГО ОРДЕРА
                    // Если текущая цены выше цены которой мы планировали выставлять
                    $order = $this->CreateReverseOrder($pricenow, $price, $OrderREST, $OrderBD, $TREK);

                    $this->AddTrackHistoryBD($TREK, $OrderBD, $OrderREST);


                // Сокращаем счетчик BOOST
                if ($TREK['boost'] == 1 && $TREK['countboost'] > 0){
                    $countboost = $TREK['countboost'] - 1;
                    $ARRTREK['countboost'] = $countboost;
                    $this->ChangeARRinBD($ARRTREK, $TREK['id']);
                }
                // Сокращаем счетчик BOOST



                    $ARRCHANGE = [];
                    $ARRCHANGE['stat'] = 2;
                    $ARRCHANGE['orderid'] = $order['id'];
                    $ARRCHANGE['type'] = "LIMIT";
                    $ARRCHANGE['first'] = 0;
                    $ARRCHANGE['lastprice'] = $OrderREST['average'];
                    $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");




                    continue;




            }

            if ($OrderBD['stat'] == 2){

                echo "<b>Работа СТАТУС 2</b><br>";
                echo "Информация об ордере из REST<br>";
                $OrderREST = $this->GetOneOrderREST($OrderBD['orderid']);

                // Проверка на cancel (перевыставление)
                if ($OrderREST['status'] == "canceled"){
                    echo "<font color='#8b0000'>ОРДЕР отменен (canceled)!!! </font> <br>";
                    show($OrderREST);

                    $pricenow = $this->GetPriceSide($this->symbol, $OrderBD['side']);


                    if ($OrderBD['side'] == "long")  $price = $OrderREST['price'] + $TREK['step'];
                    if ($OrderBD['side'] == "short")  $price = $OrderREST['price'] - $TREK['step'];

                    echo "Текущая цена: ".$pricenow."<br>";
                    echo "Мы выставляем ордера по цене: ".$price."<br>";


                    echo "Перевыставляем ордер на 2-м статусе! Обнуляем ID ордера!  <br>";
                   $order =  $this->CreateReverseOrder($pricenow, $price, $OrderREST, $OrderBD, $TREK);
                    $this->ChangeIDOrderBD($order, $OrderBD['id']);
                    continue;
                }

                // Проверка на исоплненность
                if ($this->OrderControl($OrderREST) === FALSE){
                    // Проверка ОРДЕРА НА СТОП!!!
                    //$this->ControlStopOrder2($TREK, $pricenow, $OrderBD);
                    echo "ОРДЕР не откупился <br>";
                    continue;
                }


                // ОРДЕР ИСПОЛНЕН





                $this->AddTrackHistoryBD($TREK, $OrderBD, $OrderREST);


                $ARRCHANGE = [];
                $ARRCHANGE['stat'] = 1;
                $ARRCHANGE['orderid'] = NULL;
                $ARRCHANGE['type'] = NULL;
                $ARRCHANGE['boost'] = NULL;
                $this->ChangeARRinBD($ARRCHANGE, $OrderBD['id'], "orders");


            }




        }

            // Если откупились на первый первый статус (откупился первый раз после выставления)





        return true;

    }



    // Закрытие части позиции
    private function ControlStopOrder2($TREK, $pricenow, $OrderBD){
        echo "<b>Проверяем ордер на вторую часть</b><br>";


        $contrside = ($TREK['workside'] == "LONG") ? "short" : "long";

        if ($TREK['workside'] == "LONG" && $pricenow < $OrderBD['price'] - $TREK['step']*$this->stopfixorder){

            // Режем текущую позицию на единицу!
            echo "<font color='#8b0000'><b>Цена в ЗОНЕ STOP</b></font>";
            $this->Close1StepPosition($TREK, $OrderBD, $contrside);

            return true;
        }


        if ($TREK['workside'] == "SHORT" && $pricenow > $OrderBD['price'] + $TREK['step']*$this->stopfixorder){
            // Режем текущую позицию на единицу!
            echo "<font color='#8b0000'><b>Цена в ЗОНЕ STOP</b></font>";
            $this->Close1StepPosition($TREK, $OrderBD, $contrside);
            return true;

        }



            echo "Цена в допустимой зоне<br>";

        return true;
    }


    private function GlobalStop($TREK, $pricenow){

       $OrdersBD =  $this->GetAllOrdersBD($TREK['id']);

       $count = 0;

       foreach ($OrdersBD as $key=>$ORDER){


            if ($ORDER['stat'] == 2 && $ORDER['side'] == "long"){
                if ($pricenow > $ORDER['price']) continue;

                $delta = $ORDER['price'] - $pricenow;
                $delta = round($delta/$TREK['step']);
                show($delta);
                $count = $count + $delta;

            }


            if ($ORDER['stat'] == 2 && $ORDER['side'] == "short"){
                if ($pricenow < $ORDER['price']) continue;

                $delta = $pricenow - $ORDER['price'];
                $delta = round($delta/$TREK['step']);
                $count = $count + $delta;


            }



       }



        return $count;

    }



    // Закрытие Противоположной позиции по глубине
    private function ControlDeepPosition($TREK, $pricenow){

        if ( $TREK['contrpoz'] == 0) return false;

        echo  "<b>У нас имееться КОНТР-ПОЗИЦИЯ!</b><br><br>";
        echo "Текущая цена: ".$pricenow."<br>";

        if ($TREK['workside'] == "LONG" && $pricenow > $TREK['avg'] + $TREK['step']*$this->deepposition){
            $contrside = ($TREK['workside'] == "LONG") ? "short" : "long";
            $this->CloseStopPosition($TREK, $contrside);
            $ARRTREK['contrpoz'] = 0;
            $this->ChangeARRinBD($ARRTREK, $TREK['id']);
            echo "Позиция зашла дальше глубины. <br>";
        }


        if ($TREK['workside'] == "SHORT" && $pricenow < $TREK['avg'] - $TREK['step']*$this->deepposition){
            $contrside = ($TREK['workside'] == "LONG") ? "short" : "long";
            $this->CloseStopPosition($TREK, $contrside);
            $ARRTREK['contrpoz'] = 0;
            $this->ChangeARRinBD($ARRTREK, $TREK['id']);
            echo "Позиция зашла дальше глубины. <br>";
        }

        return true;


    }

    private function Close1StepPosition($TREK, $OrderBD, $side){

        $params = [
            'reduce_only' => true,
        ];

        $side = $this->GetTextSide($side);

        //  $side = "buy";
        $order = $this->EXCHANGECCXT->create_order($this->symbol,"market",$side, $OrderBD['amount'], null, $params);
        show($order);

        // Обнуляем его положение в БД
        $changeorder = R::load("orders", $OrderBD['id']);
        $changeorder->stat = 1;
        $changeorder->orderid = NULL;
        R::store($changeorder);
        // Берем противоположные ордера со статусом два


        // Отменяем крайний лимитный ордер
        //      $cancel = $this->EXCHANGECCXT->cancel_order($LastOrder['orderid'], $this->symbol);
//        show($cancel);

        echo "<b><font color='#8b0000'>Позиция сокращена на 1 единицу</font></b><br>";

        $srez = 0;
        $stopord = 1;
        $this->AddTrackHistoryBD($TREK, $OrderBD, $srez, $stopord);


        return true;
    }


    private function ControlTakeProfitCycle($TREK, $pricenow){

        $count = $this->CpuntHiOrders($TREK);


        // Закрываем ЦИКЛ!
        if ($count < $this->stopfixorder ){
            echo "Позиция в цикле в допустимом диапозоне<br>";
            return true;
        }


        if ($TREK['workside'] == "LONG" && $pricenow < $TREK['rangeh'] - ($TREK['step']*$this->stopfixorder) + 1){
            echo "<font color='#8b0000'><b>Цена в ЗОНЕ STOP</b></font>";
            $stop = 1;
            // Закрываем позицию!!
            $this->CloseCycle($TREK, $stop);
            return true;

        }

        if ($TREK['workside'] == "SHORT" && $pricenow > $TREK['rangel'] + ($TREK['step']*$this->stopfixorder) +1){
            echo "<font color='#8b0000'><b>Цена в ЗОНЕ STOP</b></font>";
            $stop = 1;
            // Закрываем позицию!!
            $this->CloseCycle($TREK, $stop);
            return true;

        }


        return false;




    }


    private function CloseCycle($TREK, $stop =0){

        $POSITION = $this->LookHPosition();

       // show($POSITION);

        $param = [
            'reduce_only' => true,
        ];

        // Подсраховка на случай остатков не закрытых позиций при выходе из коридора
        if ($POSITION[0]['size'] > 0){
            echo "Закрытие остатков LONG позиции<br>";
            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market","sell", $POSITION[0]['size'], null, $param);
            show($order);
        }
        if ($POSITION[1]['size'] > 0){
            echo "Закрытие остатков SHORT позиции<br>";
            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market","buy", $POSITION[1]['size'], null, $param);
            show($order);
        }


        // Отмена всех ордеров
        $cancelall = $this->EXCHANGECCXT->cancel_all_orders($this->symbol);
        show($cancelall);


        // Очистка БД с ордерами
        R::wipe("orders");



        // Смена Статуса
        $ARRTREK['status'] = 2;
        $ARRTREK['stop'] = $stop;
        $ARRTREK['stampexit'] = time();
        $this->ChangeARRinBD($ARRTREK, $TREK['id']);

        echo "<h3><font color='green'>ЦИКЛ ЗАВЕРШЕН!!!</font> </h3>";

        return true;
    }


    private function CloseStopPosition($TREK, $side){

        $POSITION = $this->LookHPosition();
        $param = [
            'reduce_only' => true,
        ];

        if ($side == "long" && $POSITION[0]['size'] > 0){
            echo "Закрытие остатков LONG позиции<br>";
            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market","sell", $POSITION[0]['size'], null, $param);
            show($order);

            $LastOrder = [
            ];
            $srez = 1;
            $this->AddTrackHistoryBD($TREK, $LastOrder, $order, $srez);


        }

        if ($side == "short" && $POSITION[1]['size'] > 0){
            echo "Закрытие остатков SHORT позиции<br>";
            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market","buy", $POSITION[1]['size'], null, $param);
            show($order);

            $LastOrder = [
            ];
            $srez = 1;
            $this->AddTrackHistoryBD($TREK, $LastOrder, $order, $srez);


        }


        // Отмена закрывающих ордеров
        $OrdersBD = R::findAll("orders", 'WHERE idtrek =? AND side=?', [$TREK['id'], $side]);

        foreach ($OrdersBD as $key=>$ORD){

            if ($ORD['orderid'] == NULL) continue;

            // Функция отмены стоп ордера
         //   $this->EXCHANGECCXT->cancel_order($ORD['orderid'], $this->symbol) ;
            $ORD->orderid = NULL;
            $ORD->stat = 1;
            $ORD->first = 1;
            R::store($ORD);

        }





        return true;

    }


    private function CheckFirstOrder($TREK, $pricenow, $OrderBD, $count){


        $STEP = ($TREK['step']/100)*$this->skolz;
        $STEP = round($STEP);


        if ($count >= $this->maxposition) {
            echo "Достигнут лимит максимальных кол-ва ордеров<br>";
            return false;
        }


        if ($TREK['workside'] == "LONG"){

            if ($pricenow > $OrderBD['price'] + $STEP) return "LIMIT";

//          if ($OrderBD['first'] == 1){
//
//              if ($pricenow < $OrderBD['price']){
//                  // Приближаемся к зоне покупки
//                  if ($pricenow > ($OrderBD['price'] - $TREK['step']*3) ) return "MARKET";
//              }
//
//          }
//
//            if ($OrderBD['first'] == 0){
//                if ($pricenow < $OrderBD['price']){
//                    // Приближаемся к зоне покупки
//                    if ($pricenow < ($OrderBD['price'] - $TREK['step']*0.5)) return "MARKET";
//
//
//                }
//            }





        }


        if ($TREK['workside'] == "SHORT"){


            if ($pricenow < $OrderBD['price'] - $STEP) return "LIMIT";


//            if ($OrderBD['first'] == 1){
//
//                if ($pricenow > $OrderBD['price']){
//                    // Приближаемся к зоне покупки
//                    if ($pricenow < ($OrderBD['price'] + $TREK['step']*3) ) return "MARKET";
//                }
//
//            }
//
//            if ($OrderBD['first'] == 0){
//                echo "НОВЫЙ ОРДЕР<br>";
//                $ss = $OrderBD['price'] + $TREK['step']*0.8;
//                echo "Текущая цена:".$pricenow."<br>";
//                echo "Цена ордера:".$OrderBD['price']."<br>";
//                echo "Рынок должны выставлять при цене".$ss."<br>";
//
//                if ($pricenow > $OrderBD['price']){
//                    // Приближаемся к зоне покупки
//                    if ($pricenow > ($OrderBD['price'] + $TREK['step']*0.5)) return "MARKET";
//
//
//                }
//            }




        }

            echo "Цена не корректна для выставления ордеров в данном коридоре<br>";
            return false;

    }

    private function GetWorkSide($pricenow, $TREK){

        echo "Средняя цена коридора:".$TREK['avg']."<br>";

        if ($pricenow > ($TREK['rangeh'] + $TREK['step']) ) return "HIEND";
        if ($pricenow < ($TREK['rangel'] - $TREK['step']) ) return "LOWEND";


        if ($pricenow > $TREK['avg'] ) return "LONG";
         if ($pricenow < $TREK['avg'] ) return "SHORT";



    }

    private function CreateFirstOrder($OrderBD, $type, $TREK){


        $sideorder = $this->GetTextSide($OrderBD['side']);
        show($sideorder);
        var_dump($OrderBD['amount']);
        show($OrderBD['price']);

        // Проверка на BOOST
        if ($TREK['boost'] == 1) $OrderBD['amount'] = $OrderBD['amount']*$this->boostsize;
        // Проверка на BOOST


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

    private function CreateReverseOrder($pricenow, $price, $OrderREST, $OrderBD, $TREK){


        $params = [
            'time_in_force' => "PostOnly",
            'reduce_only' => true,
        ];

        if ($OrderBD['side'] == "long") {
            if ($pricenow > $price) $price = $pricenow + round($TREK['step']/2);
            $side = "sell";
        }

        if ($OrderBD['side'] == "short") {
            if ($pricenow < $price) $price = $pricenow - round($TREK['step']/2);
            $side = "buy";
        }


        $order = $this->EXCHANGECCXT->create_order($this->symbol,"limit",$side, $OrderREST['amount'] , $price, $params);



        echo "<font color='#8b0000'>Создали реверсный ордер </font><br>";


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


    private function CpuntHiOrders($TREK, $stat = 2){

        $Orders = R::findAll("orders", 'WHERE idtrek =? AND side=? ORDER by `count` DESC LIMIT 8 ', [$TREK['id'], $TREK['workside']]);

        $count = 0;

        foreach ($Orders as $key=>$val){
            if ($val['stat'] == 2) $count = $count+1;
        }

        echo "Кол-во верхних ордеров: ".$count." <br>";



        return $count;

    }

    private function CountActiveOrders($TREK, $stat = 2){

        $count = 0;
        if ($stat == 1 ||$stat == 2 ){
            $count = R::count("orders", 'WHERE idtrek =? AND side=? AND stat=? AND orderid IS NOT NULL', [$TREK['id'], $TREK['workside'], $stat]);
        }

        if ($stat == "all"){
            $count = R::count("orders", 'WHERE idtrek =? AND side=? AND orderid IS NOT NULL', [$TREK['id'], $TREK['workside']]);
        }

        echo "Активных ордеров: ".$count."<br>";

        return $count;
    }


    public function GetTextSide($textside){
        if ($textside == "long" || $textside == "LONG") $sideorder = "Buy";
        if ($textside == "short" || $textside == "SHORT") $sideorder = "sell";
        return $sideorder;
    }




    public function OrderControl($order){

        if ($order['amount'] == $order['filled']) return true;

        if ($order['status'] == "open") return false;

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

        foreach ($this->MASSORDERS['long'] as $key=>$val){
            $quantity = $this->GetQuantityBTC($val['summazahoda'] , $val['price']);
            $this->MASSORDERS['long'][$key]['quantity'] = $quantity;
        }

        foreach ($this->MASSORDERS['short'] as $key=>$val){
            $quantity = $this->GetQuantityBTC($val['summazahoda'] , $val['price']);
            $this->MASSORDERS['short'][$key]['quantity'] = $quantity;
        }




        return true;
    }

    public function CalculatePriceOrders(){

        $allbal = $this->summazahoda * $this->leverege;


        $zahod = round($allbal/$this->CountOrders);

        if ($zahod < 30){
            echo "Размер захода на 1 ордер".$zahod."<br>";
            echo "Не хватает баланса на такое кол-во ордеров";
            exit();
        }

        foreach ($this->MASSORDERS['long'] as $key=>$val){
            $this->MASSORDERS['long'][$key]['summazahoda'] = $zahod;
        }

        foreach ($this->MASSORDERS['short'] as $key=>$val){
            $this->MASSORDERS['short'][$key]['summazahoda'] = $zahod;
        }




        return true;
    }

    public function GenerateStepPrice(){
        $MASS = [];

        for ($i = 1; $i <= $this->CountOrders; $i++) {
            $MASS['long'][]['price'] = $this->CENTER + $this->step*$i;
        }

            $MASS['avg']['price'] = $this->CENTER;

        for ($i = 1; $i <= $this->CountOrders; $i++) {
            $MASS['short'][]['price'] = $this->CENTER - $this->step*$i;
        }



        return $MASS;
    }

    public function SetLeverage($leverage){

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

    private function GetQuantityBTC($summazahoda, $price){

        $quantity = $summazahoda/$price;

        $quantity = round($summazahoda/$price, 5);

        return $quantity;
    }

    private function EkranSymbol()
    {
        $newsymbol = str_replace("/", "", $this->symbol);
        return $newsymbol;
    }


    private function GetContrPosition($TREK)
    {
        $cp = R::findOne("contrposition", 'WHERE idtrek =?', [$TREK['id']]);
        return $cp;
    }


    private function GetTreksBD()
    {
        $terk = R::findAll($this->namebdex, 'WHERE emailex =? ORDER by status', [$this->emailex]);
        return $terk;
    }

    private function GetOrdersBD($TREK)
    {
        $MASS = R::findAll("orders", 'WHERE idtrek =? AND side=? ORDER by `count` ASC', [$TREK['id'], $TREK['workside']]);
        return $MASS;
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


    private function GetOneOrderREST($id)
    {
        $order = $this->EXCHANGECCXT->fetchOrder($id,$this->symbol);
       // $MASS[$order['id']] = $order;
        return $order;

    }


    private function AddTrackHistoryBD($TREK, $ORD, $ORDERREST, $srez = 0, $stopord = 0)
    {

        $dollar = 0;

        if ($ORD['stat'] == 1){
            if ($ORD['type'] == "MARKET") $dollar = $ORDERREST['amount']*$ORDERREST['average']*(-0.075)/100;
            if ($ORD['type'] == "LIMIT") $dollar = $ORDERREST['amount']*$ORDERREST['average']*(0.025)/100;
        }


        if ($ORD['stat'] == 2 && $ORD['side'] == "long"){
            $enter = $ORD['lastprice'];
            $pexit = $ORDERREST['average'];

            $delta = changemet($enter, $pexit) + 0.025;

            $dollar = ($ORD['price']/100)*$delta*$ORDERREST['amount'];

        }
        if ($ORD['stat'] == 2 && $ORD['side'] == "short"){
            $enter = $ORD['lastprice'];
            $pexit = $ORDERREST['average'];
            $delta = changemet($pexit, $enter) + 0.025;
            $dollar = ($ORD['price']/100)*$delta*$ORDERREST['amount'];
        }


        if ($srez == 1){
            $avgminus = 2*$TREK['minst'] + 0.075;
            $dollar = $ORDERREST['amount']*$ORDERREST['average']*(-$avgminus)/100;
        }


        $ACTBAL = $this->GetBal()['USDT']['total'];

        $MASS = [
            'trekid' => $TREK['id'],
            'side' => $TREK['workside'],
            'orderid' => $ORD['id'],
            'type' => $ORD['type'],
            'statusorder' => $ORD['stat'],
            'timeexit' => date("H:i:s"),
            'lastprice' => $ORD['lastprice'],
            'amount' => $ORDERREST['amount'],
            'fact' => $ORDERREST['average'],
            'srez' => $srez,
            'stopord' => $stopord,
            'boost' => $ORD['boost'],
            'bal' => $ACTBAL,
            'dollar' => $dollar,
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