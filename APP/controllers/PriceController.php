<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class PriceController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";

    public $ApiKey = "l2xzXGEQVJcYKk1uNB1ynDPATLmL9oUEMH0zlCLZ4F9QzQ7UaFkFLTFzQdEFpDBl";
    public $SecretKey = "hJOj8RnPJwf0Y4zmcDqGLPrdIGsGvx8NDMmwidKBTSCbNLK3qqzI9Vainm77YSf6";


    public $symbol = "BTC/USDT";


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


        $date = "25.11.2021";

        // Высчитываем сколько прошло минут с начала дня
        $timenow = time();

        // Время на старт дня

        echo "Входящий параметр ДАТЫ: - ".$date."<br><br>";


        echo "Время UNIX сейчас сейчас: - ".$timenow."<br><br>";


        $convertnow =  date('m/d/Y', $timenow);
        echo "Время сейчас конвертируем в D-M-Y: - ".$convertnow."<br><br>";




     //   $KLINES = $this->EXCHANGECCXT->fetch_ohlcv($this->symbol, '1m');
     //   show($KLINES);


        // Получение ТРЕКОВ

        // Получение статистики по трекам (трекхистори)

        // Чтение таблицы с историей баланса


//        $this->set(compact(''));

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


    private function GetBalTable()
    {
        $table = R::findAll("balancehistory");
        return $table;
    }







}
?>