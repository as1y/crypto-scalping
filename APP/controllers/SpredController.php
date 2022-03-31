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


        $TickersBD = $this->LoadTickersBD();

        $this->BaseKurs = $exchange->fetch_ticker ("USDT/RUB")['close'];

        $TickersBinance = $exchange->fetch_tickers();

         // show($TickersBinance);
         // exit("11");

        echo "<h1>СПРЕДЫ НА ВХОД</h1>";

        $BestIN = 0;

        foreach ($TickersBD as $TickerWork)
        {

            $symbolbinance = $TickerWork['ticker']."/USDT";

            $BinancePRICE = $TickersBinance[$symbolbinance]['close'];
            $BinancePRICE = $BinancePRICE*$this->BaseKurs;


            $BestChangePRICE = $TickerWork['price'];
            $spredzahoda = 100 - $BinancePRICE/$BestChangePRICE*100;
            $spredzahoda = round($spredzahoda, 2);


            if ($BestIN == 0) $BestIN = $spredzahoda;

            if ($BestIN > $spredzahoda) {
                $MASSIV = [];
                $BestIN = $spredzahoda;
                $MASSIV[$symbolbinance] = $spredzahoda;
            }

            echo "<b>СИМВОЛ:</b> ".$symbolbinance." <br>";
            echo "Цена BINANCE ".$BinancePRICE."<br>";
            echo "Цена BestChange ".$BestChangePRICE."<br>";
            echo "<b> СПРЕД ВХОДА </b> ".$spredzahoda." % <br>";
            echo "<hr>";



        }


        show($MASSIV);



















//        $this->set(compact(''));

    }



    public function GetBinancePrice($exchange, $symbol){

        $price = 0;

        if ($symbol == 'USDT/RUB') $price = $exchange->fetch_ticker ("USDT/RUB")['close'];
        if ($symbol == 'XRP/RUB') $price = $exchange->fetch_ticker ("XRP/RUB")['close'];

        if ($symbol == 'BCH/USDT') $price = $exchange->fetch_ticker ("BCH/USDT")['close']*$this->BaseKurs;
        if ($symbol == 'ETC/USDT') $price = $exchange->fetch_ticker ("ETC/USDT")['close']*$this->BaseKurs;
        if ($symbol == 'XMR/USDT') $price = $exchange->fetch_ticker ("XMR/USDT")['close']*$this->BaseKurs;
        if ($symbol == 'SHIB/USDT') $price = $exchange->fetch_ticker ("SHIB/USDT")['close']*$this->BaseKurs;
        if ($symbol == 'MKR/USDT') $price = $exchange->fetch_ticker ("MKR/USDT")['close']*$this->BaseKurs;


        if ($symbol == 'WAVES/USDT') $price = $exchange->fetch_ticker ("WAVES/USDT")['close']*$this->BaseKurs;


        return $price;

    }


    public function GetBestChange($symbol){

        $price = 0;

        if ($symbol == "USDT/RUB") $price = 115.1;


        if ($symbol == "BCH/USDT") $price = 43840;
        if ($symbol == "ETC/USDT") $price = 5379;
        if ($symbol == "XRP/RUB") $price = 97;
        if ($symbol == "XMR/USDT") $price = 22817;
        if ($symbol == "SHIB/USDT") $price = 0.0028735632;

        if ($symbol == "MKR/USDT") $price = 279646;
        if ($symbol == "WAVES/USDT") $price = 3963;


      //  $page = fCURL($url);

       // var_dump($page);










        return $price;



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


    private function LoadTickersBD()
    {
        $table = R::findAll("basetickers");
        return $table;
    }







}
?>