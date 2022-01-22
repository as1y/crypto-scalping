<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class FlowController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";

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

    private $lot = 0.001; // Базовый заход
    private $RangeH = 70000;
    private $RangeL = 40000;
    private $step = 15; // Размер шага между ордерами
    private $stoploss = 40; // Размер шага между ордерами
    private $maxposition = 1;
    private      $maDev = 20; // Отклонение МА
    private      $deltacoef = 4; // Коэффицентр треллинга


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

          // Если потоки есть. Дальше работаем с ними
        if (empty($FLOWS)) {
            echo "Потока нет. Создаем первый<br>";
            $PARAMS = [];
            $this->AddFlow($SCRIPT, $PARAMS);
            return true;
        }


        // ЗАПУСК РАБОЧИХ ПОТОКОВ
        echo "Контроль действующих потоков <br>";
        foreach ($FLOWS as $key => $FLOW) {
            echo "КОНТРОЛЬ ПОТОКА  ".$FLOW['id']."<br>";
        }







            echo "<hr>";

            return true;
    }



    public function FlowWork($SCRIPT)
    {


        $AllOrdersREST = $this->GetAllOrdersREST();
        // Проверка ордеров на наличие в БД КОСТЫЛЬ
        if ($AllOrdersREST === FALSE){
            echo  "Выдался пустой REST<br>";
            return false;
        }

        $FLOWS = $this->GetFlowBD($SCRIPT);

        foreach ($FLOWS as $key => $FLOW) {

            echo "<b> ОБРАБОТКА ПОТОКА СО ID  ".$FLOW['id']." </b> <br>";

            $f = 'WorkStatus' . $FLOW['status'];
            $this->$f($FLOW, $AllOrdersREST);

        }

        return true;
    }



    private function WorkStatus1($FLOW, $AllOrdersREST)
    {


            echo "<h3> РАБОТА ПОТОКА. СТАТУС-1 </h3> <br>";

        $pricenow = $this->GetPriceSide($this->symbol, $FLOW['pointer']);

        // show($FLOW);
            // ВЫСТАВЛЯЕМ ПЕРВЫЙ ЛИМИТНИК
        if ($FLOW['limitid'] == NULL){

                echo "Базовый лимитник пустой. Выставляем<br>";
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


            echo "Текущая цена:".$pricenow."<br>";
            echo "Ордер выставлен по цене:".$FLOW['pricelimit']."<br>";


            // ПРОВЕРКА ТЕКУЩЕЙ ЦЕНЫ ЛОНГ
            if ($FLOW['pointer'] == "long" && ($pricenow - $this->Basestep) > $FLOW['pricelimit'])
            {

                echo "<font color='#8b0000'>WORKSIDE: long;  Цена ушла выше. Нужно перевыставлят ордер!!! </font> <br>";
                // Отменяем текущий ордер
                $cancel = $this->EXCHANGECCXT->cancel_order($FLOW['limitid'], $this->symbol);
                show($cancel);

                $ARRCHANGE = [];
                $ARRCHANGE['limitid'] = NULL;
                $ARRCHANGE['pricelimit'] = NULL;
                $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");

                return true;

            }

            if ($FLOW['pointer'] == "short" && ($pricenow + $this->Basestep) < $FLOW['pricelimit'])
            {

                echo "<font color='#8b0000'>WORKSIDE: short;  Цена ушла выше. Нужно перевыставлят ордер!!! </font> <br>";
                // Отменяем текущий ордер
                $cancel = $this->EXCHANGECCXT->cancel_order($FLOW['limitid'], $this->symbol);
                show($cancel);

                $ARRCHANGE = [];
                $ARRCHANGE['limitid'] = NULL;
                $ARRCHANGE['pricelimit'] = NULL;
                $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");

                return true;

            }


            return true;

        }

        echo "<font color='green'>Ордер исполнен</font><br>";


           // show($FLOW);

            $order = $this->MarketOrder($FLOW);
            // ЕСЛИ ЗАШЕЛ, ТО ВЫКУПАТЬ ПО МАРКЕТУ ОБРАТКУ

            show($order);


        $ARRCHANGE = [];
        $ARRCHANGE['limitid'] = NULL;
        $ARRCHANGE['pricemarket'] = $order['price'];
        $ARRCHANGE['status'] = 2;
        //   $ARRCHANGE['lastprice'] = $order['last_exec_price'];
        $this->ChangeARRinBD($ARRCHANGE, $FLOW['id'], "flows");



            //  ЕСЛИ ЗАШЕЛ И ТУДА И СЮДА, ТО МЕНЯТЬ НА СТАТУС 2



        return true;
    }

    private function WorkStatus2($FLOW)
    {

        echo "<h3> РАБОТА ПОТОКА. СТАТУС-2 </h3> <br>";
        $pricenow = $this->GetPriceSide($this->symbol, $FLOW['pointer']);

        $priceENTER = ($FLOW['pricelimit'] + $FLOW['pricemarket'])/2;
        $priceENTER = round($priceENTER);

        echo "<b>Текущая цена по указателю:</b> ".$pricenow."<br>";
        echo "<b>Средняя цена входа в поток:</b> ".$priceENTER."<br>";

        // Определение КОНТР тренда по которму будем треллить
        $contrTREND = ($FLOW['pointer'] == 'long') ? 'short' : 'long';

        echo "Работаем с треллингом по контр тренду: ".$contrTREND."<br>";





        return true;
    }




    private function MarketOrder($FLOW)
    {

        $param = [
            'reduce_only' => false,
        ];

        $inverted_side = ($FLOW['pointer'] == 'long') ? 'Sell' : 'Buy';
        //show($inverted_side);

        $order = $this->EXCHANGECCXT->create_order($this->symbol,"market",$inverted_side, $this->lot, null, $param);

        return $order;

    }


    private function AddFlow($SCRIPT, $PARAMS)
    {

        echo "Добавляем поток! <br>";

        $ARR['scriptid'] = $SCRIPT['id'];
        $ARR['status'] = 1;
        $ARR['pointer'] = "long";
        $ARR['stamp'] = time();


        $this->AddARRinBD($ARR, "flows");
        echo "<b><font color='green'>ДОБАВИЛИ ПОТОК</font></b>";
        // Добавление ТРЕКА в БД

        return true;

    }


    private function GetAllOrdersREST(){
        $RESULT = $this->EXCHANGECCXT->fetch_orders($this->symbol, null, 100);
        return $RESULT;
    }

    public function OrderControl($order){

        if ($order['order_status'] == "Cancelled") return false;


//        if (!empty($order['amount']) && !empty($order['filled'])){
//            if ($order['amount'] == $order['filled']) return true;
//        }

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
        $order['price'] = $order['price'];

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

    private function CreateFirstOrder($FLOW, $pricenow){


        $sideorder = $this->GetTextSide($FLOW['pointer']);
        show($sideorder);

        $params = [
            'time_in_force' => "PostOnly",
            'reduce_only' => false,
        ];



        if ($FLOW['pointer'] == "long") $price = $pricenow - $this->Basestep;
        if ($FLOW['pointer'] == "short") $price = $pricenow;

        $order = $this->EXCHANGECCXT->create_order($this->symbol,"limit",$sideorder, $this->lot, $price, $params);
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