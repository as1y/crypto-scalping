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

    private $CENTER = 34100;

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

        $Panel = new Panel();

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
        $ARR['countplus'] = 0;
        $ARR['symbol'] = $this->symbol;
        $ARR['lever'] = $this->leverege;
        $ARR['count'] = $this->CountOrders;
        $ARR['step'] = $this->step;
        $ARR['rangeh'] = $rangeh;
        $ARR['rangel'] = $rangel;
        $ARR['avg'] = $this->CENTER;
        $ARR['minst'] = $this->minst;
        $ARR['workside'] = "LONG";
        $ARR['startbalance'] = $this->BALANCE;
        $ARR['date'] = date("h:i:s");
        $ARR['stamp'] = time();

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


        // Выключаем BOOST если он отработан
        if ($TREK['boost'] == 1 && $TREK['countboost'] < 1){
            $ARRTREK['boost'] = 0;
            $ARRTREK['countboost'] = 0; // Кол-во шагов, которые надо отработать
            $ARRTREK['countplus'] = 0;
            $this->ChangeARRinBD($ARRTREK, $TREK['id']);
        }



        if ($WorkSide == "HIEND" || $WorkSide == "LOWEND"){
            echo "<b><font color='#8b0000'>Цена вышла из коридора!!!</font></b>";
            //$this->ControlExit($TREK);
            return true;
        }


        echo "<hr>";



        if ($TREK['workside'] != $WorkSide){
            // На случай смены активной стороны

            echo "<b>Переход из одной позиции в другую</b>";

            // ВКЛЮЧАЕМ BOOST
            $countboost = 0;
          //  $countboost = R::count("orders", 'WHERE idtrek =? AND stat=?', [$TREK['id'], 2]);
            //$countboost = $countboost*$this->compensator;
            // Включаем BOOST
            if ($countboost > 0) $ARRTREK['boost'] = 1;


            // Отмена СТОП ордеров
            $this->CancelStopOrd($TREK);



            // Закрытие позиции по стопу и отмена ордеров
            $this->CloseStopPosition($TREK, $TREK['workside']);



            $ARRTREK['countboost'] = $countboost;
            $ARRTREK['workside'] = $WorkSide;
            $this->ChangeARRinBD($ARRTREK, $TREK['id']);

            // Отмена ордеров с другой стороны


            $TREK['workside'] = $WorkSide;

            // На случай смены активной стороны
        }




        // Статус ордера - ОРДЕРА на НАРАЩИВАНИЕ ПОЗИЦИИ
        if ($TREK['action'] == "ControlOrders") $this->ActionControlOrders($TREK, $pricenow);
        // Ордера на фиксирование позиции

        // Контроллер ситуации

        return true;

    }


    // Статус 2 - Вышли из коридора
    private function WorkStatus2($TREK){

        echo "<h1><font color='green'>КОРИДОР ЗАВЕРШИЛ РАБОТУ</font></h1>";




    }


    private function ActionControlOrders($TREK, $pricenow){

        echo  "<b>Запускаем Action ControlOrders. Контролируем работу ордеров</b> <br>";

        $OrdersBD = $this->GetOrdersBD($TREK);




        foreach ($OrdersBD as $key=>$OrderBD) {

            echo "<hr>";
            echo "#".$OrderBD['id']." СТАТУС ОРДЕРА <b>".$OrderBD['stat']."</b> - ".$OrderBD['orderid']." - <b>".$OrderBD['side']."</b> <br>";


            // Ордер на наращивание позиции
            if ($OrderBD['stat'] == 1) {


                    // Выставление первых ордеров
                    if ($OrderBD['orderid'] == NULL){


                        echo "Текущая цена".$pricenow."<br>";
                        echo "Цена для выставления ордера".$OrderBD['price']."<br>";



                        // Скоринг на выставление
                        $resultscoring =  $this->CheckFirstOrder($TREK, $pricenow, $OrderBD);


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
                        // Проверяем на скоринг необходимости выставления ордера по маркету
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

                    // Проверяем ордера MARKET на расстояние. Отменяем не актуальные
                    if ($TREK['workside'] == "LONG"){ // ЛОНГ
                        if ($pricenow < ($OrderBD['price'] - $TREK['step']*3) ){ // Если цена находиться ниже чем 3 шага от нужной цены, то отменяем ордер

                            $params = [
                                'stop_order_id' => $OrderBD['orderid'],
                            ];
                            // Функция отмены стоп ордера
                            $this->EXCHANGECCXT->cancel_order($OrderBD['orderid'], $this->symbol,$params) ;

                            $order['id'] = NULL;
                            $this->ChangeIDOrderBD($order, $OrderBD['id']);


                        }
                    } // ЛОНГ
                    if ($TREK['workside'] == "SHORT"){ // ЛОНГ
                        if ($pricenow > ($OrderBD['price'] + $TREK['step']*3) ){ // Если цена находиться выше чем 3 шага от нужной цены, то отменяем ордер

                            $params = [
                                'stop_order_id' => $OrderBD['orderid'],
                            ];
                            // Функция отмены стоп ордера
                            $this->EXCHANGECCXT->cancel_order($OrderBD['orderid'], $this->symbol,$params) ;

                            $order['id'] = NULL;
                            $this->ChangeIDOrderBD($order, $OrderBD['id']);


                        }
                    } // ЛОНГ


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

    private function ControlContrPosition($TREK){

        $contrside = ($TREK['workside'] == "LONG") ? "short" : "long";
        echo "Противоположный статус:<br>";
        show($contrside);

        $LastOrder = false;

        if ($contrside == "long"){
            $LastOrder = R::findOne("orders", 'WHERE idtrek =? AND side=? AND stat=? ORDER by `price` DESC', [$TREK['id'], $contrside, 2]);
        }
        // Получение ордеров из CONTR позиции
        if ($contrside == "short"){
            $LastOrder = R::findOne("orders", 'WHERE idtrek =? AND side=? AND stat=? ORDER by `price` ASC', [$TREK['id'], $contrside, 2]);
        }


        if ($LastOrder == false){
            echo "Убыточной позиции не обнаружено";
            return true;
        }


        echo "Локализацием нижний ордер<br>";




        // Закрываем позицию убыточную
        $params = [
            'reduce_only' => true,
        ];

        $side = $this->GetTextSide($TREK['workside']);

        if ($LastOrder['boost'] == 1) $LastOrder['amount'] = $LastOrder['amount']*$this->boostsize;

      //  $side = "buy";
        $order = $this->EXCHANGECCXT->create_order($this->symbol,"market",$side, $LastOrder['amount'], null, $params);
        show($order);

        // Обнуляем его положение в БД
        $changeorder = R::load("orders", $LastOrder['id']);
        $changeorder->stat = 1;
        $changeorder->orderid = NULL;
        R::store($changeorder);
        // Берем противоположные ордера со статусом два


        // Отменяем крайний лимитный ордер
  //      $cancel = $this->EXCHANGECCXT->cancel_order($LastOrder['orderid'], $this->symbol);
//        show($cancel);

        echo "<b><font color='#8b0000'>Позиция сокращена на 1 единицу</font></b><br>";

        $srez = 1;
        $this->AddTrackHistoryBD($TREK, $LastOrder, $order, $srez);



        return true;


    }

    private function ControlExit($TREK){

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
        $this->ChangeARRinBD($ARRTREK, $TREK['id']);

        echo "<h3><font color='green'>ЦИКЛ ЗАВЕРШЕН!!!</font> </h3>";

        return true;
    }


    private function CloseStopPosition($TREK, $side){

        $POSITION = $this->LookHPosition();
        $param = [
            'reduce_only' => true,
        ];

        if ($side == "LONG" && $POSITION[0]['size'] > 0){
            echo "Закрытие остатков LONG позиции<br>";
            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market","sell", $POSITION[0]['size'], null, $param);
            show($order);

            $LastOrder = [
            ];
            $srez = 1;
            $this->AddTrackHistoryBD($TREK, $LastOrder, $order, $srez);


        }

        if ($side == "SHORT" && $POSITION[1]['size'] > 0){
            echo "Закрытие остатков SHORT позиции<br>";
            $order = $this->EXCHANGECCXT->create_order($this->symbol,"market","buy", $POSITION[1]['size'], null, $param);
            show($order);

            $LastOrder = [
            ];
            $srez = 1;
            $this->AddTrackHistoryBD($TREK, $LastOrder, $order, $srez);


        }


        // Отмена закрывающих ордеров
        $OrdersBD = R::findAll("orders", 'WHERE idtrek =? AND side=?', [$TREK['id'], $TREK['workside']]);

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


    private function CheckFirstOrder($TREK, $pricenow, $OrderBD){


        $STEP = ($TREK['step']/100)*$this->skolz;
        $STEP = round($STEP);


        if ($TREK['workside'] == "LONG"){

          if ($OrderBD['first'] == 1){

              if ($pricenow < $OrderBD['price']){
                  // Приближаемся к зоне покупки
                  if ($pricenow > ($OrderBD['price'] - $TREK['step']*3) ) return "MARKET";
              }

          }
            if ($OrderBD['first'] == 0){
                if ($pricenow < $OrderBD['price']){
                    // Приближаемся к зоне покупки
                    if ($pricenow < ($OrderBD['price'] - $TREK['step']*0.5)) return "MARKET";


                }
            }
        }
        if ($TREK['workside'] == "SHORT"){

            if ($OrderBD['first'] == 1){
                if ($pricenow > $OrderBD['price']){
                    // Приближаемся к зоне покупки
                    if ($pricenow < ($OrderBD['price'] + $TREK['step']*3) ) return "MARKET";
                }

            }

            if ($OrderBD['first'] == 0){
                echo "НОВЫЙ ОРДЕР<br>";
                $ss = $OrderBD['price'] + $TREK['step']*0.8;
                echo "Текущая цена:".$pricenow."<br>";
                echo "Цена ордера:".$OrderBD['price']."<br>";
                echo "Рынок должны выставлять при цене".$ss."<br>";

                if ($pricenow > $OrderBD['price']){
                    // Приближаемся к зоне покупки
                    if ($pricenow > ($OrderBD['price'] + $TREK['step']*0.5)) return "MARKET";


                }
            }

        }


            echo "Цена не корректна для выставления ордеров в данном коридоре<br>";
            return false;

    }

    private function GetWorkSide($pricenow, $TREK){

        echo "Средняя цена коридора:".$TREK['avg']."<br>";

        if ($pricenow > ($TREK['rangeh'] + $TREK['step']*2) ) return "HIEND";
        if ($pricenow < ($TREK['rangel'] - $TREK['step']*2) ) return "LOWEND";


        if ($pricenow > $TREK['avg'] ) return "LONG";
         if ($pricenow < $TREK['avg'] ) return "SHORT";



    }

    private function CancelStopOrd($TREK){


        echo "Отмена стоп ордеров при переходе <br>";

        // Отмена стоп ордеров
        $OrdersBD = R::findAll("orders", 'WHERE idtrek =? AND side=? AND stat=?', [$TREK['id'], $TREK['workside'], 1]);

        foreach ($OrdersBD as $key=>$ORD){

            if ($ORD['orderid'] == NULL) continue;

            $params = [
                'stop_order_id' => $ORD['orderid'],
            ];
            // Функция отмены стоп ордера
            $this->EXCHANGECCXT->cancel_order($ORD['orderid'], $this->symbol,$params) ;
            $ORD->orderid = NULL;
            $ORD->first = 1;
            R::store($ORD);


        }

        // очищене обычных ордеров





            return true;


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

    public function LookingSpacePositions($CONTRPOSTION){

        $RES = [];

        $POSITIONS = $this->EXCHANGECCXT->fetch_positions([$this->symbol]);
        // 0 - Позиция в BUY
        // 1 - Позиция в SELL

        $POSITIONS[0]['sidecode'] = "long";
        $POSITIONS[1]['sidecode'] = "short";

        if ($CONTRPOSTION['side'] == "long"){
            $RES['raketa'] = $POSITIONS[0];
            $RES['koridor'] = $POSITIONS[1];
        }

        if ($CONTRPOSTION['side'] == "short"){
            $RES['raketa'] = $POSITIONS[1];
            $RES['koridor'] = $POSITIONS[0];
        }


        return $RES;


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
        $MASS = R::findAll("orders", 'WHERE idtrek =? AND side=?', [$TREK['id'], $TREK['workside']]);
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


    private function AddTrackHistoryBD($TREK, $ORD, $ORDERREST, $srez = 0)
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