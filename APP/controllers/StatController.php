<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class StatController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";

    public $ApiKey = "wxWygsLmxxSw9fOrWj";
    public $SecretKey = "WZUfQHGXgRYvf4HprQ5LFpev4ysAtmk6lYa2";

    public $ApiKey2 = "TvLAD7I4Qz5cEBvaBh";
    public $SecretKey2 = "2z3NSPXjryoUZQdz44xAf0THglGheTsarSmO";


    public $ApiKey3 = "vimtBRUg9IVGusKZ07";
    public $SecretKey3 = "QCqA3DN84LrG6cceMNxhmrVanGgZ6dirb2jW";


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

        $this->EXCHANGECCXT2 = new \ccxt\bybit (array(
            'apiKey' => $this->ApiKey2,
            'secret' => $this->SecretKey2,
            'timeout' => 30000,
            'enableRateLimit' => true,
            'marketType' => "linear",
            'options' => array(
                // 'code'=> 'USDT',
                //  'marketType' => "linear"
            )
        ));


        $this->EXCHANGECCXT3 = new \ccxt\bybit (array(
            'apiKey' => $this->ApiKey3,
            'secret' => $this->SecretKey3,
            'timeout' => 30000,
            'enableRateLimit' => true,
            'marketType' => "linear",
            'options' => array(
                // 'code'=> 'USDT',
                //  'marketType' => "linear"
            )
        ));



        $rnd1 = mt_rand(8750, 8760)/100;

        $rnd2 = mt_rand(5000, 5020)/100;
        $rnd3 = mt_rand(4990, 5010)/100;


        $this->BALANCE = round($this->GetBal1()['USDT']['total'], 2);
        $this->BALANCE2 = round($this->GetBal2()['USDT']['total'], 2)  + $rnd1;
        $this->BALANCE3 = round($this->GetBal3()['USDT']['total'], 2) + $rnd2;
        $this->BALANCE4 = round($this->GetBal3()['USDT']['total'], 2) + $rnd3;


        $Balyesterday = 7;

        echo "<b>АККАУНТ 1:</b><br>";
        echo "ТЕКУЩИЙ БАЛАНС:".$this->BALANCE."<br>";
        echo "<hr>";

        echo "<b>АККАУНТ 2:</b><br>";
        echo "ТЕКУЩИЙ БАЛАНС:".$this->BALANCE2."<br>";
        echo "<hr>";





        // Получение ТРЕКОВ

        // Получение статистики по трекам (трекхистори)

        // Чтение таблицы с историей баланса


//        $this->set(compact(''));

    }


    public function GetBal1(){
        $balance = $this->EXCHANGECCXT->fetch_balance();
        return $balance;
    }

    public function GetBal2(){
        $balance = $this->EXCHANGECCXT2->fetch_balance();
        return $balance;
    }

    public function GetBal3(){
        $balance = $this->EXCHANGECCXT3->fetch_balance();
        return $balance;
    }



    private function GetTreksBD($side)
    {
        $terk = R::findAll($this->namebdex, 'WHERE emailex =? AND workside=?', [$this->emailex, $side]);
        return $terk;
    }


    private function GetBalTable()
    {
        $table = R::findAll("balancehistory");
        return $table;
    }







}
?>