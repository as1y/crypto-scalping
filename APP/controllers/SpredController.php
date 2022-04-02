<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class SpredController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";

    public $ApiKey = "U5I2AoIrTk4gBR7XLB";
    public $SecretKey = "HUfZrWiVqUlLM65Ba8TXvQvC68kn1AabMDgE";

    private $BaseKurs = 0;

    private $TickerBinance =[];

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
        $exchange = new \ccxt\binance (array (
          //  'verbose' => true,
            'timeout' => 30000,
        ));


        $this->BaseKurs = $exchange->fetch_ticker ("USDT/RUB")['close'];
        $this->TickerBinance = $exchange->fetch_tickers();


        $TickersBDIN = $this->LoadTickersBD("IN");
        $TickersBDOUT = $this->LoadTickersBD("OUT");

        echo "<h1>СПРЕДЫ НА ВХОД</h1>";

        $RENDER['BestSpred'] = 0;

        foreach ($TickersBDIN as $TickerWork)
        {

            if ($TickerWork['price'] == "none") continue;

            $RENDER =  $this->RenderPercent($RENDER, $TickerWork);

            echo "<b>СИМВОЛ:</b> ".$RENDER['Symbol']." <br>";
            echo "Цена BINANCE ".$RENDER['BinancePrice']."<br>";
            echo "Цена BestChange ".$RENDER['ObmenPrice']."<br>";
            echo "<b> СПРЕД ВХОДА </b> ".$RENDER['Spred']." % <br>";
            echo "<hr>";


        }


        show($RENDER['BestSpredSymbol']);
        show($RENDER['BestSpred']);

















//        $this->set(compact(''));

    }





    private function RenderPercent($RENDER, $TickerWork)
    {

        $symbolbinance = $TickerWork['ticker']."/USDT";
        $BinancePRICE = $this->TickerBinance[$symbolbinance]['close'];
        $BinancePRICE = $BinancePRICE*$this->BaseKurs;
        $RENDER['Symbol'] = $symbolbinance;
        $RENDER['BinancePrice'] = $BinancePRICE;
        $RENDER['ObmenPrice'] = $TickerWork['price'];


        $spredzahoda = 100 - $BinancePRICE/$TickerWork['price']*100;
        $spredzahoda = round($spredzahoda, 2);
        $RENDER['Spred'] = $spredzahoda;



        if ($RENDER['BestSpred'] == 0) $RENDER['BestSpred'] = $spredzahoda;

        if ($RENDER['BestSpred'] >= $spredzahoda) {
            $RENDER['BestSpred'] = $spredzahoda;
            $RENDER['BestSpredSymbol'] = $symbolbinance;
        }




        return $RENDER;


    }




    public function GetBal(){
        $balance = $this->EXCHANGECCXT->fetch_balance();
        return $balance;
    }


    private function GetTreksBD($side)
    {
        $terk = R::findAll($this->namebdex, 'WHERE emailex =? AND workside=?', [$this->emailex, $side]);
        return $terk;
    }


    private function LoadTickersBD($type)
    {
        $table = R::findAll("basetickers", 'WHERE type =?', [$type]);
        return $table;
    }







}
?>