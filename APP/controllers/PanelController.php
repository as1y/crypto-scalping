<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class PanelController extends AppController {
	public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";


    public $ApiKey = "9juzIdfqflVMeQtZf9";
    public $SecretKey = "FwUD2Ux5sjLo8DyifqYr4cfWgxASblk7CZo7";

    // Переменные для стратегии
    public $summazahoda = 40; // Сумма захода с оригинальным балансом
    public $leverege = 30;
    public $Exhcnage1 = "bybit";
    public $symbol = "BTC/USDT";
    public $emailex  = "raskrutkaweb@yandex.ru"; // Сумма захода USD
    public $namebdex = "treks";


    private $ORDERBOOK = [];


    // Переменные для стратегии


    // ТЕХНИЧЕСКИЕ ПЕРЕМЕННЫЕ

    // ТЕХНИЧЕСКИЕ ПЕРЕМЕННЫЕ
    public function indexAction()
    {


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
          //  'options' => array('defaultType' => "future")
        ));


        $this->FULLBALANCE = $this->GetBal();

        //   show($this->FULLBALANCE);

        exit("11");

        // Контроллер позиции



        $this->set(compact(''));

    }



    public function GetScoring(){
        $SCORING = [];


        return $SCORING;

    }








    public function GetBal(){
        $balance = $this->EXCHANGECCXT->fetch_balance();
        return $balance;
    }

    public function AllProfit(){

        $TrekHistory = R::findAll("trekhistory");

        $allprofit = 0;
        foreach ($TrekHistory as $key=>$value){
            $allprofit = $allprofit + $value['delta'];
        }


        return $allprofit;

    }


    private function GetTreksBD()
    {
        $terk = R::findAll($this->namebdex, 'WHERE emailex =? ORDER by status', [$this->emailex]);
        return $terk;
    }


    private function CalculateHoldMin($time)
    {

        $hodltime = time() - $time;
        $second = $hodltime;


        $minut = $second / 60;
        $minut = round($minut, 2);
        //СЧИТАЕМ СКОЛЬКО ХОЛДА

        //$days = $minut / 1440;

        return $minut;
    }

    private function GetPriceSide($symbol, $side)
    {
        if ($side == "buy" || $side == "long") $price = $this->ORDERBOOK[$symbol]['bids'][0][0];
        if ($side == "sell" || $side == "short") $price = $this->ORDERBOOK[$symbol]['asks'][0][0];
        return $price;
    }


    public function GetOrderBook($symbol){
        $orderbook[$symbol] = $this->EXCHANGECCXT->fetch_order_book($symbol, 20);
        return $orderbook;

    }

    private function Looking4Position($symbol)
    {

        $POSITION = $this->GetPosition($symbol);

        $POSITION['bid'] = $this->ORDERBOOK[$symbol]['bids'][0][0];
        $POSITION['ask'] = $this->ORDERBOOK[$symbol]['asks'][0][0];


        return $POSITION;
    }

    private function GetARRPOS($POSITION, $TREK)
    {

        $ARRPOS['quantity'] = abs($POSITION['positionAmt']);
        $ARRPOS['enter'] = $POSITION['entryPrice'];
        $ARRPOS['bid'] = $POSITION['bid'];
        $ARRPOS['ask'] = $POSITION['ask'];

        $ARRPOS['side'] = ($POSITION['positionSide'] == "BOTH") ? "long" : "short";
        $ARRPOS['orderside'] = ($ARRPOS['side'] == "long") ? "sell" : "buy";
        $ARRPOS['orderactualprice'] = ($ARRPOS['side'] == "long") ? $POSITION['ask'] : $POSITION['bid'];


        $ARRPOS['raznica'] = $this->GetRaznica($ARRPOS, $TREK);




        return $ARRPOS;
    }

    private function GetPosition($symbol){

        $symbol = $this->EkranSymbol($symbol);
        show($symbol);
        //show($this->FULLBALANCE['info']['positions']);

        if (empty($this->FULLBALANCE['info']['positions'])) return false;

        foreach ($this->FULLBALANCE['info']['positions'] as $val){


            if ($val['symbol'] == $symbol && $val['initialMargin'] != 0){
                $POSTION = $val;
                return $POSTION;

            }
        }



        return false;
    }

    private function EkranSymbol()
    {
        $newsymbol = str_replace("/", "", $this->symbol);
        return $newsymbol;
    }



    private function GetRaznica($ARRPOS, $TREK)
    {

        $ARRPOS['enter'] = round($ARRPOS['enter'], 2);

        if ($ARRPOS['side'] == "long") $raznica = changemet($ARRPOS['enter'], $ARRPOS['ask']);
        if ($ARRPOS['side'] == "short") $raznica = changemet($ARRPOS['bid'], $TREK['enter']);

        return $raznica;
    }


}
?>