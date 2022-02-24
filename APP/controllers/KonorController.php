<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class KonorController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";

    public $ApiKey = "wxWygsLmxxSw9fOrWj";
    public $SecretKey = "WZUfQHGXgRYvf4HprQ5LFpev4ysAtmk6lYa2";


    // Переменные для стратегии
    public $leverege = 90;
    public $symbol = "BTC/USDT";
    public $Basestep = 0.5;
    public $emailex  = "as1y@yandex.ru"; // Сумма захода USD


    // ПАРАМЕТРЫ СТРАТЕГИИ

    private $lot = 0.03; // Базовый заход
    private $trellingBEGIN = 100; // Через сколько пунктов начинается треллинг
    private $trellingSTEP = 10; // Через сколько пунктов начинается треллинг

    private $DeltaMA = 200; // Коридор захода в позицию по МА
    private $DeltaMALUFT = 0; // Проверка на точку входа



    private $stoploss = 1500; // Стоп лосс в пунктах актива

    private $urovenbreakzone = 200; // в шагах

    private $limitmoneta = 3000; // Скоринг монеты на объемы

    private $maxflow = 6;


    // ТЕХНИЧЕСКИЕ ПЕРЕМЕННЫЕ
    private $ORDERBOOK = [];
    private $EXCHANGECCXT = [];
    private $BALANCE = [];
    private $KLINES15M = [];
    private $KLINES30M = [];
    private $SCORING = [];


    // ТЕХНИЧЕСКИЕ ПЕРЕМЕННЫЕ
    public function indexAction()
    {

        $this->layaout = false;

        date_default_timezone_set('UTC');
        // Браузерная часть
        $Panel =  new Panel();
        $META = [
            'title' => 'Панель FLOW',
            'description' => 'Панель FLOW',
            'keywords' => 'Панель FLOW',
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


        // СОЗДАНИЕ ЗАПИСИ ОСНОВНОЙ SCRIPT

        $SCRIPT = $this->GetScriptBD();

        if (empty($SCRIPT)) {
            $this->AddScript();
            return true;
        }


        // show($SCRIPT);

        if ($SCRIPT['work'] == 1) {
            echo "Скрипт в работе. Пропускаем цикл<br>";
            $this->StopScript($SCRIPT);
            return true;
        }

        // Старт скрипта
        $this->StartScript($SCRIPT);


        // КОНТРОЛЬ РАБОТЫ ПОТОКОВ. БАЛАНСИРОВКА
        $this->FlowControl($SCRIPT);


        // Работа конкретного потока
        $this->FlowWork($SCRIPT);


        // Завершение скрипта
        $this->StopScript($SCRIPT);
        $this->LogZapuskov($SCRIPT);



    }


    public function FlowControl($SCRIPT)
    {
        echo "<h2> Контроль потоков </h2> <br>";

        $FLOWS = $this->GetFlowBD($SCRIPT);

        // Определяем сколько потоков
        $countflows = count($FLOWS);
        $counbreak = 0;
        $counwork = 0;
        foreach ($FLOWS as $key => $FLOW) {
            echo "КОНТРОЛЬ ПОТОКА ID:  ".$FLOW['id']."<br><br>";
            if ($FLOW['breakzone'] == 1) $counbreak = $counbreak +1;
            if ($FLOW['breakzone'] == 0) $counwork = $counwork +1;

        }

        echo "<b>Всего потоков: </b>".$countflows."<br>";
        echo "<b>Кол-во зависших потоков: </b>".$counbreak."<br>";
        echo "<b>Кол-во РАБОЧИХ потоков: </b>".$counwork."<br>";


        if ($counwork < 1 && $countflows < $this->maxflow)
        {
            echo "<font color='green'> Можем создать еще 1 поток!!! </font><br>";
            $this->AddFlow($SCRIPT);
        }



        echo "<hr>";

        return true;
    }


    public function FlowWork($SCRIPT)
    {


        /*
        $AllOrdersREST = $this->GetAllOrdersREST();
        // Проверка ордеров на наличие в БД КОСТЫЛЬ
        if ($AllOrdersREST === FALSE){
            echo  "Выдался пустой REST<br>";
            return false;
        }
        */

        $AllOrdersREST = [];

        $FLOWS = $this->GetFlowBD($SCRIPT);

        foreach ($FLOWS as $key => $FLOW) {

            echo "<b> ОБРАБОТКА ПОТОКА ID  ".$FLOW['id']." </b> <br>";

            $f = 'WorkStatus' . $FLOW['status'];
            $this->$f($FLOW, $AllOrdersREST, $SCRIPT);
            echo "<hr>";

        }

        return true;
    }


    private function WorkStatus1($FLOW, $AllOrdersREST, $SCRIPT)
    {


        echo "<h3> РАБОТА ПОТОКА. СТАТУС-1 </h3> <br>";

        $pricenow = $this->GetPriceSide($this->symbol, $FLOW['pointer']);


        $Napravlenie = NULL;
        $Napravlenie = $this->GetNapravlenie($SCRIPT);


        // show($FLOW);
        // ВЫСТАВЛЯЕМ ПЕРВЫЙ ЛИМИТНИК
        if ($FLOW['limitid'] == NULL){

            if ($Napravlenie == false)
            {
                echo "<font color='#8b0000'>НЕ ПОДХОДЯЩИЙ СКОРИНГ</font><br>";
                return false;
            }

            echo "Базовый лимитник пустой. Выставляем<br>";
            $FLOW['pointer'] = $Napravlenie;
            $order = $this->CreateFirstOrder($FLOW, $pricenow);
            $ARRCHANGE = [];
            $ARRCHANGE['limitid'] = $order['id'];
            $ARRCHANGE['pricelimit'] = $order['price'];
            $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");

            return true;

        }

        // ПРОВЕРЯТЬ СТАТУС ЛИМИТНИКА
        $OrderREST = $this->GetOneOrderREST($FLOW['limitid'], $AllOrdersREST); // Ордер РЕСТ статус 2

        //show($OrderREST);
        var_dump($this->OrderControl($OrderREST));

        // КОНТРОЛИРУЕМ ОТКУП ЛИМИТНИКА
        if ($this->OrderControl($OrderREST) === FALSE){

            $this->LimitFalse($FLOW, $OrderREST, $pricenow);
            return true;

        }

        echo "<font color='green'>Ордер исполнен</font><br>";

        if ($OrderREST['side'] == "Buy") $Napravlenie = "long";
        if ($OrderREST['side'] == "Sell") $Napravlenie = "short";



        $ARRCHANGE = [];
        $ARRCHANGE['limitid'] = NULL;
        $ARRCHANGE['enterprice'] = $OrderREST['price'];
        $ARRCHANGE['napravlenie'] = $Napravlenie;
        $ARRCHANGE['status'] = 2;
        $ARRCHANGE['ostatok'] = 0;
        $ARRCHANGE['trallingstat'] = FALSE;
        //   $ARRCHANGE['lastprice'] = $order['last_exec_price'];
        $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");



        //  ЕСЛИ ЗАШЕЛ И ТУДА И СЮДА, ТО МЕНЯТЬ НА СТАТУС 2



        return true;
    }

    private function WorkStatus2($FLOW, $AllOrdersREST, $SCRIPT)
    {
        echo "<h3> РАБОТА ПОТОКА. СТАТУС-3 </h3>";

        // КОНТРОЛЬ НА СТОП-ЛОСС



        // Выставление СТОП- ОРДЕРА
        if ($FLOW['stoporder'] == NULL){
            echo "Выставляем ПЕРВЫЙ СТОП ордер на ТЕЙК ПОЗИЦИИ!!<br><br>";
            if ($FLOW['napravlenie'] == "long") $StopPrice = $FLOW['enterprice'] - $this->stoploss;
            if ($FLOW['napravlenie'] == "short") $StopPrice = $FLOW['enterprice'] + $this->stoploss;
            $this->CreateStop($FLOW, $FLOW['napravlenie'], $StopPrice);
            return true;
        }



        // МЕХАНИЗМ ТРЕЛЛИНГА!!!!!



        if ($FLOW['trallingstat'] == FALSE)
        {

            // ПРОВЕРКА НА ИСПОЛНЕНОСТЬ СТОП ОРДЕРА
            $OrderREST = $this->GetOneOrderREST($FLOW['stoporder'], $AllOrdersREST); // Ордер РЕСТ статус 2
            //echo "<b>REST STOP ORDER: </b> <br>";
            // show($OrderREST);
            if ($OrderREST['order_status'] == "Filled")
            {
                echo "<font color='#8b0000'>СТОП ОРДЕР ИСПОЛНИЛСЯ!!!</font> <br>";
                //show($OrderREST);
                $this->AddFlowHistoryBD($FLOW, $OrderREST, $STOP = true); // Исполнен статус 4 ВЫХОД ИЗ СДЕЛКИ
                R::trash($FLOW);
            }




            // Определение направления

            $pricenow = $this->GetPriceSide($this->symbol, $FLOW['napravlenie']);

            echo "<b>Текущая цена:</b> ".$pricenow."<br>";
            echo "<b>Точка от которой будем треллить:</b> ".$FLOW['enterprice']."<br>";

            $globaldelta = 0;
            if ($FLOW['napravlenie'] == "long")  $globaldelta = $pricenow - $FLOW['enterprice'];
            if ($FLOW['napravlenie'] == "short")  $globaldelta = $FLOW['enterprice'] - $pricenow;

            echo "Направление треллинга: ".$FLOW['napravlenie']."<br>";
            echo "ОБЩАЯ ДЕЛЬТА: ".$globaldelta."<br>";



            // ПРОВЕРКА НА БРЕКЗОНУ

            $functionzone = $this->CheckBreakZone($FLOW, $globaldelta);

            if ($functionzone == false)
            {
                echo "<b><font color='#8b0000'>БрекЗона по функции:</font></b>".$functionzone."<br>";
            }



            // Смена БрекЗоны
            if ($FLOW['breakzone'] == false && $functionzone == true)
            {
                $ARRCHANGE = [];
                $ARRCHANGE['breakzone'] = $functionzone;
                $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");
            }



            $TRALLINGSTATUS = false;
            // Проверяем в треллинге мы или нет
            if ($globaldelta > $this->trellingBEGIN)
            {
                $TRALLINGSTATUS = $this->TrallingControl($FLOW, $FLOW['napravlenie'], $pricenow);
            }

            echo "<b>СТАТУСЫ ТРЕЛЛИНГА</b><br>";
            var_dump($TRALLINGSTATUS);

            if ($TRALLINGSTATUS == true)
            {

                $params = [
                    'stop_order_id' => $FLOW['stoporder'],
                ];
                // Функция отмены стоп ордера
                $this->EXCHANGECCXT->cancel_order($FLOW['stoporder'], $this->symbol,$params);


                $ARRCHANGE = [];
                $ARRCHANGE['trallingstat'] = TRUE;
                $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");

            }


            // Записываем в БД, что мы треллим этот поток


        }

        // Если наш статус уже определен. Выставляем ордера
        if ($FLOW['trallingstat'] == TRUE)
        {


            $pricenow = $this->GetPriceSide($this->symbol, $FLOW['napravlenie']);

            if ($FLOW['limitid'] == NULL){

                echo "<font color='#8b0000'> Выставляем ЛИМИТНИК на ТРЕЛЛИНГ </font><br>";
                $order = $this->CreateFirstOrder($FLOW, $pricenow, true);
                show($order);

                $ARRCHANGE = [];
                $ARRCHANGE['limitid'] = $order['id'];
                $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");

                return true;

            }

            $OrderREST = $this->GetOneOrderREST($FLOW['limitid'], $AllOrdersREST);
            echo "<b>REST LIMIT ORDER: </b> <br>";
            show($OrderREST);

            // ЕСЛИ ЛИМИТНИК НЕ ОТКУПИЛСЯ!
            if ($this->OrderControl($OrderREST) === FALSE){
                $FLOW['pointer'] = $FLOW['napravlenie'];
                $this->LimitFalse($FLOW, $OrderREST, $pricenow);
                return true;
            }


            if ($OrderREST['order_status'] == "Filled")
            {

                echo "<b><font color='green'>РАБОТА ПОТОКА ЗАВЕРШЕНА!!!!!!</font></b> <br>";

                // ЗАПИСЬ СТАТИСТИКИ. ЛОГИРОВАНИЕ ЗАХОДОВ

                $this->AddFlowHistoryBD($FLOW, $OrderREST); // Исполнен статус 4 ВЫХОД ИЗ СДЕЛКИ

                R::trash($FLOW);


            }


        }


        return true;

    }


    private function CheckSCORING()
    {

        echo "<b><font color='#663399'>Проводим скоринг...</font></b><br>";


        $otklonenie = 0;
        $pricenow = $this->GetPriceSide($this->symbol, "long");

        $this->KLINES30M = $this->EXCHANGECCXT->fetch_ohlcv($this->symbol, '30m', null, 5);
        $MaVAL = GetMA($this->KLINES30M);
        //  show($MaVAL);

        // БАЗОВЫЙ СКОРИНГ
        $SCORING = SCORING($this->KLINES30M, $pricenow);


        if ($SCORING['VOL'] > $this->limitmoneta) return false; // Фильтр на объем торгов

        $otklonenie = $pricenow - $MaVAL;

        //     echo "Отклонение по МА: ".$otklonenie."<br>";

        // Проверка на точку входа
        if ($otklonenie > 0 && $otklonenie < $this->DeltaMALUFT) return false;
        if ($otklonenie < 0 && $otklonenie*(-1) < $this->DeltaMALUFT) return false;


        if ($otklonenie > 0 && $otklonenie < $this->DeltaMA) return "long";
        if ($otklonenie < 0 && $otklonenie*(-1) < $this->DeltaMA) return "short";



        return false;

    }


    private function GetNapravlenie($SCRIPT)
    {

        // Получение всех потоков
        $FLOWS = $this->GetFlowBD($SCRIPT);

        $LASTFLOW = $this->GetLastFlowBD($FLOWS, $SCRIPT);


        $SCORING = $this->CheckSCORING();


        if ($SCORING == "long")
        {
            foreach ($FLOWS as $key=>$FLOW)
            {
                if ($FLOW['breakzone'] == 0 && $FLOW['napravlenie'] == "long") break; // Если есть открытый лонг, то завершаем
            }

            if ($LASTFLOW == false) return "long";
            if ($LASTFLOW == "short")  return "long"; // Открываем есть поток, то проверяем, чтобы последний был противоположный

        }


        if ($SCORING == "short")
        {
            foreach ($FLOWS as $key=>$FLOW)
            {
                if ($FLOW['breakzone'] == 0 && $FLOW['napravlenie'] == "short") break; // Если есть открытый шорт
            }

            if ($LASTFLOW == false) return "short";
            if ($LASTFLOW == "long")  return "short"; // Открываем есть поток, то проверяем, чтобы последний был противоположный

        }


        return false;


    }




    private function CheckBreakZone($FLOW, $globaldelta)
    {

        // Проверка на время
        $rabotapotoka = time() - $FLOW['stamp'];
        $rabotapotoka = $rabotapotoka/60;
        $rabotapotoka = round($rabotapotoka);

        echo "Работа потока в минутах:".$rabotapotoka."<br>";


        //   if ($rabotapotoka > $this->timebreakzone) return true;

        if ($globaldelta*(-1) > $this->urovenbreakzone) return true;




        return false;
    }


    private function AddFlowHistoryBD($FLOW, $OrderREST, $STOP = false)
    {
        $dollar = 0;


        if ($FLOW['napravlenie'] == "long")
        {
            $enter = $FLOW['enterprice'];
            $pexit = $OrderREST['price'];

            if ($STOP == false) $delta = changemet($enter, $pexit) + 0.05;
            if ($STOP == true)
            {
                $pexit = $OrderREST['last_exec_price'];
                $delta = changemet($enter, $pexit) - 0.05;
            }


            $dollar = ($OrderREST['last_exec_price']/100)*$delta*$OrderREST['qty'];
        }

        if ($FLOW['napravlenie'] == "short")
        {
            $enter = $FLOW['enterprice'];
            $pexit = $OrderREST['price'];

            if ($STOP == false) $delta = changemet($pexit, $enter ) + 0.05;
            if ($STOP == true) {
                $pexit = $OrderREST['last_exec_price'];
                $delta = changemet($pexit, $enter) - 0.05;
            }


            $dollar = ($OrderREST['last_exec_price']/100)*$delta*$OrderREST['qty'];
        }

        $ACTBAL = $this->GetBal()['USDT']['total'];

        $MASS = [
            'flowid' => $FLOW['id'],
            'napravlenie' => $FLOW['napravlenie'],
            'date' => date("d-m-Y"),
            'timeexit' => date("H:i:s"),
            'enter' => $FLOW['enterprice'],
            'exit' => $pexit,
            'amount' => $OrderREST['qty'],
            'dollar' => $dollar,
            'delta' => $delta,
            'bal' => $ACTBAL,
        ];
        //ДОБАВЛЯЕМ В ТАБЛИЦУ
        $tbl3 = R::dispense("flowhistory");
        //ДОБАВЛЯЕМ В ТАБЛИЦУ

        //ДОБАВЛЯЕМ В ТАБЛИЦУ
        foreach ($MASS as $name => $value) {
            $tbl3->$name = $value;
        }
        R::store($tbl3);

        echo "Сохранили запись о сделке в БД <br>";


        return true;

    }

    private function CreateStop($FLOW, $Napravlenie,  $StopPrice)
    {


        if ($Napravlenie == "long") $bp = $FLOW['enterprice'] + $this->stoploss*2;
        if ($Napravlenie == "short") $bp = $FLOW['enterprice'] - $this->stoploss*2;

        $params = [
            'stop_px' => $StopPrice, // trigger $price, required for conditional orders
            'base_price' => $bp,
            'trigger_by' => 'LastPrice', // IndexPrice, MarkPrice
            'reduce_only' => true,
        ];

        $inverted_side = ($Napravlenie == 'long') ? 'Sell' : 'Buy';

        show($inverted_side);

        $order = $this->EXCHANGECCXT->create_order($this->symbol,"market", $inverted_side, $FLOW['lot'], null, $params);


        $ARRCHANGE = [];
        $ARRCHANGE['stoporder'] = $order['id'];

        $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");




        return true;

    }



    private function LimitFalse($FLOW, $OrderREST, $pricenow)
    {

        if (empty($OrderREST['order_status'])) return true;


        // Проверка СТАТУСА НА ПАРТИФИЛЛЕД
        if (!empty($OrderREST['amount']) && !empty($OrderREST['filled'])){

            if ($OrderREST['amount'] =! $OrderREST['filled'])
            {

                $ostatok = $OrderREST['amount'] - $OrderREST['filled'];

                echo "<font color='yellow'>ОРДЕР ОТКУПЛЕН ЧАСТИЧНО! </font> <br>";
                echo "Отменяем его выставление!  <br>";
                $ARRCHANGE = [];
                $ARRCHANGE['limitid'] = NULL;
                $ARRCHANGE['ostatok'] = $ostatok;
                $ARRCHANGE['pricelimit'] = NULL;
                $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");

                return true;

            }

        }


        // ВНЕЗАПНАЯ ПОПАДАНИЕ В СТАТУС "CANCELED"
        if ($OrderREST['order_status'] == "Cancelled"){
            echo "<font color='#8b0000'>ОРДЕР отменен (canceled)!!! </font> <br>";
            echo "Отменяем его выставление!  <br>";
            $ARRCHANGE = [];
            $ARRCHANGE['limitid'] = NULL;
            $ARRCHANGE['pricelimit'] = NULL;
            $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");

            return true;
        }


        echo "Направление ".$FLOW['pointer']."<br>";
        echo "Текущая цена: <b>".$pricenow."</b><br>";
        echo "Ордер выставлен по цене:".$OrderREST['price']."<br>";



        if ($FLOW['pointer'] == "long" && $pricenow > ($OrderREST['price'] + $this->Basestep*2))
        {
            echo "<font color='#8b0000'>WORKSIDE: long;  Цена ушла выше. Нужно перевыставлят ордер!!! </font> <br>";
            $this->CancelLimit($FLOW);
        }



        if ($FLOW['pointer'] == "long" && $pricenow < ($OrderREST['price'] - $this->Basestep*5))
        {
            echo "<font color='#8b0000'>WORKSIDE: long;  Цена ушла НИЖЕ. Нужно перевыставлят ордер!!! </font> <br>";
            $this->CancelLimit($FLOW);
        }




        if ($FLOW['pointer'] == "short" && $pricenow < ($OrderREST['price'] - $this->Basestep*2) )
        {

            echo "<font color='#8b0000'>WORKSIDE: short;  Цена ушла выше. Нужно перевыставлят ордер!!! </font> <br>";
            $this->CancelLimit($FLOW);

        }

        if ($FLOW['pointer'] == "short" && $pricenow > ($OrderREST['price'] + $this->Basestep*5) )
        {

            echo "<font color='#8b0000'>WORKSIDE: short;  Цена ушла ВЫШЕ. Нужно перевыставлят ордер!!! </font> <br>";
            $this->CancelLimit($FLOW);

        }




        return true;

    }



    private function TrallingControl($FLOW, $Napravlenie, $pricenow){

        if ($Napravlenie == NULL) return false;

        $delta = 0;

        if ($Napravlenie == "short")
        {

            echo "<font color='green'> РАБОТАЕМ В ЗОНЕ ТРЕЛЛИНГА</font><br>";

            // Перезаписываем максцену в треллинге
            if ($pricenow < $FLOW['maxprice'] || $FLOW['maxprice'] == 0 )
            {
                echo "Перезаписываем MAXPRICE<br> ";
                $ARRCHANGE['maxprice'] = $pricenow;
                $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");
                $FLOW['maxprice'] = $pricenow;
            }

            // Отклонение от максимальной цены
            $delta = $pricenow - $FLOW['maxprice'];
            echo "Максимально зафиксированная цена: ".$FLOW['maxprice']."<br>";
            echo "Отклонение от максимально зафиксированной цены: ".$delta."<br>";

            if ($delta > $this->trellingSTEP) return true;

        }


        if ($Napravlenie == "long")
        {

            echo "<font color='green'> РАБОТАЕМ В ЗОНЕ ТРЕЛЛИНГА</font><br>";

            // Перезаписываем максцену в треллинге
            if ($pricenow > $FLOW['maxprice'] )
            {
                echo "Перезаписываем MAXPRICE<br> ";
                $ARRCHANGE['maxprice'] = $pricenow;
                $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");
                $FLOW['maxprice'] = $pricenow;
            }

            // Отклонение от максимальной цены
            $delta = $FLOW['maxprice'] -$pricenow;
            echo "Максимально зафиксированная цена: ".$FLOW['maxprice']."<br>";
            echo "Отклонение от максимально зафиксированной цены: ".$delta."<br>";

            if ($delta > $this->trellingSTEP) return true;

        }




        return false;

    }


    private function MarketOrder($FLOW)
    {

        $param = [
            'reduce_only' => false,
        ];

        $inverted_side = ($FLOW['pointer'] == 'long') ? 'Sell' : 'Buy';
        //show($inverted_side);

        if ($FLOW['ostatok'] > 0) $FLOW['lot'] = $FLOW['lot'] - $FLOW['ostatok'];

        $order = $this->EXCHANGECCXT->create_order($this->symbol,"market",$inverted_side, $FLOW['lot'], null, $param);

        return $order;

    }


    private function CancelLimit($FLOW)
    {

        $cancel = $this->EXCHANGECCXT->cancel_order($FLOW['limitid'], $this->symbol);
        show($cancel);

        $ARRCHANGE = [];
        $ARRCHANGE['limitid'] = NULL;
        $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");

        return true;

    }



    private function AddFlow($SCRIPT, $PARAMS = [])
    {

        echo "Добавляем поток! <br>";

        $ARR['scriptid'] = $SCRIPT['id'];
        $ARR['status'] = 1;
        $ARR['lot'] = $this->lot;
        $ARR['pointer'] = "long";
        $ARR['maxprice'] = 0;
        $ARR['breakzone'] = false;
        $ARR['stamp'] = time();


        $this->AddARRinBD($ARR, "flows");
        echo "<b><font color='green'>ДОБАВИЛИ ПОТОК</font></b>";
        // Добавление ТРЕКА в БД



        return true;

    }


    private function GetAllOrdersREST(){

        $url = "https://api.bybit.com/private/linear/order/list";
        $symbol  = str_replace("/", "", $this->symbol);
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


        // $RESULT = $this->EXCHANGECCXT->fetch_orders($this->symbol, null, 50);

        //  return $RESULT;

    }

    public function OrderControl($order){

        if ($order['order_status'] == "Cancelled") return false;



        if (!empty($order['amount']) && !empty($order['filled'])){


            if ($order['amount'] =! $order['filled']) return false;


        }




        if ($order['order_status'] == "Filled") return true;

        return false;

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


        // $MASS[$order['id']] = $order;
        return $order;



    }



    public function GetTextSide($textside){
        if ($textside == "long" || $textside == "LONG") $sideorder = "buy";
        if ($textside == "short" || $textside == "SHORT") $sideorder = "sell";
        return $sideorder;
    }

    private function GetPriceSide($symbol, $side)
    {
        if ($side == "buy" || $side == "long" || $side = "LONG") $price = $this->ORDERBOOK[$symbol]['bids'][0][0];
        if ($side == "sell" || $side == "short" || $side = "SHORT") $price = $this->ORDERBOOK[$symbol]['asks'][0][0];
        return $price;
    }

    private function CreateFirstOrder($FLOW, $pricenow, $reduceonly = false){

        $params = [
            'time_in_force' => "PostOnly",
            'reduce_only' => $reduceonly,
        ];

        if ($reduceonly == false)
        {
            $sideorder = $FLOW['pointer'];
            if ($sideorder == "long") $price = $pricenow - $this->Basestep;
            if ($sideorder == "short") $price = $pricenow;
        }

        if ($reduceonly == true)
        {
            $sideorder = ($FLOW['napravlenie'] == 'long') ? 'short' : 'long';

            if ($sideorder == "long") $price = $pricenow - $this->Basestep;
            if ($sideorder == "short") $price = $pricenow ;
        }


        $sideorder = $this->GetTextSide($sideorder);
        show($sideorder);
        show($price);

        if ($FLOW['ostatok'] > 0) $FLOW['lot'] = $FLOW['lot'] - $FLOW['ostatok'];

        $order = $this->EXCHANGECCXT->create_order($this->symbol,"limit",$sideorder, $FLOW['lot'], $price, $params);
        return $order;


    }






    private function AddScript()
    {
        $this->SetLeverage($this->leverege);

        echo "Запись о скрипте не создана. Создаем запись.<br>";



        $ARR['emailex'] = $this->emailex;
        $ARR['symbol'] = $this->symbol;
        $ARR['lever'] = $this->leverege;
        $ARR['startbalance'] = $this->BALANCE['total'];
        $ARR['date'] = date("Y-m-d H:i:s");


        $this->AddARRinBD($ARR, "script");
        echo "<b><font color='green'>ДОБАВИЛИ СКРИПТ</font></b>";
        // Добавление ТРЕКА в БД


        return true;


    }




    public function GetOrderBook($symbol){
        $orderbook[$symbol] = $this->EXCHANGECCXT->fetch_order_book($symbol, 20);
        return $orderbook;

    }




    public function SetLeverage($leverage){

        $esymbol = str_replace("/", "", $this->symbol);

        $this->EXCHANGECCXT->privateLinearGetPositionList([
                'symbol' => $esymbol,
                'leverage' => $leverage
            ]
        );

        return true;
    }




    public function GetBal(){
        $balance = $this->EXCHANGECCXT->fetch_balance();
        return $balance;
    }





    private function GetScriptBD()
    {
        $terk = R::findOne("script", 'WHERE emailex =?', [$this->emailex]);
        return $terk;
    }


    private function GetFlowBD($SCRIPT)
    {
        $flows = R::findAll("flows", 'WHERE scriptid =?', [$SCRIPT['id']]);
        return $flows;
    }


    private function GetLastFlowBD($FLOWS, $SCRIPT)
    {

        $countflows = count($FLOWS);
        if ($countflows == 1) return false;


        $LastFLOWS = R::findAll("flows", 'WHERE scriptid =? ORDER BY id DESC LIMIT 2', [$SCRIPT['id']]);

        $count = 0;
        foreach ( $LastFLOWS as $lastFLOW)
        {
            if ($count == 0) $LF = $lastFLOW;

        }

        return $LF['napravlenie'];

    }




    private function AddARRinBD($ARR, $BD = false)
    {


        $tbl = R::dispense($BD);
        //ДОБАВЛЯЕМ В ТАБЛИЦУ

        foreach ($ARR as $name => $value) {
            $tbl->$name = $value;
        }

        $id = R::store($tbl);

        echo "<font color='green'><b>ДОБАВИЛИ ЗАПИСЬ В БД!</b></font><br>";

        return $id;


    }





    private function ChangeARRinBD($ARR, $id, $BD = false)
    {


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




    private function StartScript($SCRIPT){
        $tbl = R::findOne("script", "WHERE id =?", [$SCRIPT['id']]);
        $tbl->work = 1;
        R::store($tbl);
        return true;
    }

    private function StopScript($SCRIPT){
        $tbl = R::findOne("script", "WHERE id =?", [$SCRIPT['id']]);
        $tbl->work = 0;
        R::store($tbl);
        return true;
    }


    private function LogZapuskov($SCRIPT){
        $tbl = R::findOne("script", "WHERE id =?", [$SCRIPT['id']]);
        $tbl->lastrun = date("H:i:s");
        R::store($tbl);

        return true;
    }




}
?>