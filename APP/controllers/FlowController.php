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



        show($SCRIPT);

        //$this->StartTrek($SCRIPT);

        // Контроль потоков
        $this->FlowControl();

        // Работа конкретного потока
        $this->FlowWork();






    }



    public function FlowControl()
    {

            echo "<h2> Контроль потоков </h2> <br>";






            echo "<hr>";

            return true;
    }



    public function FlowWork()
    {

        echo "<h2> Работа конкретного потока </h2> <br>";




        return true;
    }

















    private function AddScript()
    {
        $this->SetLeverage($this->leverege);

        echo "Запись о скрипте не создана. Создаем запись.<br>";



        $ARR['emailex'] = $this->emailex;
        $ARR['action'] = "Monitor";
        $ARR['symbol'] = $this->symbol;
        $ARR['lever'] = $this->leverege;
        $ARR['startbalance'] = $this->BALANCE['total'];
        $ARR['date'] = date("Y-m-d H:i:s");


        $this->AddARRinBD($ARR, "script");
        echo "<b><font color='green'>ДОБАВИЛИ СКРИПТ</font></b>";
        // Добавление ТРЕКА в БД


        return true;


    }


    private function AddTrek()
    {



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