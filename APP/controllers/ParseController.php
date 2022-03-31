<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class ParseController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";

    public $ApiKey = "U5I2AoIrTk4gBR7XLB";
    public $SecretKey = "HUfZrWiVqUlLM65Ba8TXvQvC68kn1AabMDgE";

    private $BaseKurs = 0;

    public $TICKERSqiwiIN = [];


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
        // ПАРАМЕТРЫ ДЛЯ БАЗОВОЙ ТАБЛИЦЫ!


       $this->TICKERSqiwiIN = [
            "https://www.bestchange.ru/qiwi-to-bitcoin.html" => "BTC",
"https://www.bestchange.ru/qiwi-to-bitcoin-cash.html" => "BCH",
"https://www.bestchange.ru/qiwi-to-bitcoin-gold.html" => "BTG",
"https://www.bestchange.ru/qiwi-to-ethereum.html" => "ETH",
"https://www.bestchange.ru/qiwi-to-ethereum-classic.html" => "ETC",
"https://www.bestchange.ru/qiwi-to-litecoin.html" => "LTC",
"https://www.bestchange.ru/qiwi-to-ripple.html" => "XRP",
"https://www.bestchange.ru/qiwi-to-monero.html" => "XMR",
"https://www.bestchange.ru/qiwi-to-dogecoin.html" => "DOGE",
"https://www.bestchange.ru/qiwi-to-polygon.html" => "MATIC",
"https://www.bestchange.ru/qiwi-to-dash.html" => "DASH",
"https://www.bestchange.ru/qiwi-to-zcash.html" => "ZEC",
"https://www.bestchange.ru/qiwi-to-nem.html" => "XEM",
"https://www.bestchange.ru/qiwi-to-neo.html" => "NEO",
"https://www.bestchange.ru/qiwi-to-eos.html" => "EOS",
"https://www.bestchange.ru/qiwi-to-cardano.html" => "ADA",
"https://www.bestchange.ru/qiwi-to-stellar.html" => "XLM",
"https://www.bestchange.ru/qiwi-to-tron.html" => "TRX",
"https://www.bestchange.ru/qiwi-to-waves.html" => "WAVES",
"https://www.bestchange.ru/qiwi-to-omg.html" => "OMG",
"https://www.bestchange.ru/qiwi-to-binance-coin.html" => "BNB",
"https://www.bestchange.ru/qiwi-to-bat.html" => "BAT",
"https://www.bestchange.ru/qiwi-to-qtum.html" => "QTUM",
"https://www.bestchange.ru/qiwi-to-chainlink.html" => "LINK",
"https://www.bestchange.ru/qiwi-to-cosmos.html" => "ATOM",
"https://www.bestchange.ru/qiwi-to-tezos.html" => "XTZ",
"https://www.bestchange.ru/qiwi-to-polkadot.html" => "DOT",
"https://www.bestchange.ru/qiwi-to-uniswap.html" => "UNI",
"https://www.bestchange.ru/qiwi-to-ravencoin.html" => "RVN",
"https://www.bestchange.ru/qiwi-to-solana.html" => "SOL",
"https://www.bestchange.ru/qiwi-to-vechain.html" => "VET",
"https://www.bestchange.ru/qiwi-to-algorand.html" => "ALGO",
"https://www.bestchange.ru/qiwi-to-maker.html" => "MKR",
"https://www.bestchange.ru/qiwi-to-avalanche.html" => "AVAX",
"https://www.bestchange.ru/qiwi-to-yearn-finance.html" => "YFI",
"https://www.bestchange.ru/qiwi-to-terra.html" => "LUNA",
        ];


        // БАЗОВАЯ ТАБЛИЦА С ТИКЕРАМИ
       $basetable =  $this->GetBaseTable();
        $this->WorkTable($basetable);
        // БАЗОВАЯ ТАБЛИЦА С ТИКЕРАМИ



        // Инициализация парсера
        $aparser = new \Aparser('http://217.25.90.106:9091/API', '', array('debug'=>'false'));


        // Получаем статус таблицы парсинга
        $statustable =  $this->GetStatusTable();

        // Если таблица статуса парсинга пустая, то запускаем парсинг
        if (empty($statustable))
        {
            // ЕСЛИ ТАБЛИЦА СТАТУС ПУСТАЯ, ТО СОЗДАЕМ ЗАПИСЬ!!
            foreach ($this->TICKERSqiwiIN as $url => $ticker)
            {
                $TICKERSqiwiINZAPROSI[] = $url;
            }
            $taskUid = $aparser->addTask('default', 'best', 'text', $TICKERSqiwiINZAPROSI);
            $this->AddTaskBD($taskUid);
            return true;

        }

        // Смотрим СТАТУС!
        $AparserSTAT =   $aparser->getTaskState($statustable['taskid']);

        echo "<font color='#8b0000'>ПАРСИНГ В РАБОТЕ</font><br>";


        if ($AparserSTAT['status'] == "completed"){

            echo "<font color='green'>ПАРСИНГ ЗАКОНЧЕН</font><br>";

            $result = $aparser->getTaskResultsFile($statustable['taskid']);
            $content = file_get_contents($result);
            $content = str_replace(" ", "", $content); // Убираем пробелы
            $content = explode("\n", $content);

            // ОБНОВЛЯЕМ ТАБЛИЦУ
           // show($content);

            // Обновляем в БД цены
            $this->RenewTickers($content);

            // Очищаем статус таблицу
            R::trash($statustable);


        }











//        $this->set(compact(''));

    }



    private function WorkTable($basetable)
    {

        if (empty($basetable))
        {
            // СОЗДАЕМ ТАБЛИЦУ!!
            foreach ($this->TICKERSqiwiIN as $url => $ticker)
            {
                $ZAPIS['global'] = "QIWI";
                $ZAPIS['type'] = "IN";
                $ZAPIS['url'] = $url;
                $ZAPIS['ticker'] = $ticker;
                $this->AddTable($ZAPIS);
            }
            echo "<hr>";
            echo "<font color='green'>Таблица с тикерами создана!</font> <br>";
        }


        return true;

    }


    private function RenewTickers($content)
    {


        echo "МАССИВ ПАРСИНГА<br>";
//        show($content);

        // Преобразовываем массив в примемлемый вид
        $MASSIV = [];
        foreach ($content as $key=>$value)
        {
            if (empty($value)) continue;
            $value = explode(";", $value);
 //           show($value);
            $MASSIV[$value[0]][] = $value[1];
            $MASSIV[$value[0]][] = $value[2];
        }



        echo "МАССИВ ПО БД<br>";
        $TICKERS = $this->GetBaseTable();
        // Добавляем в БД данные из спарсенного контента!
        foreach ($TICKERS as $ticker)
        {
           $ticker->price = $MASSIV[$ticker['url']][0];
           $ticker->limit = $MASSIV[$ticker['url']][1];
            R::store($ticker);

        }




    }



    private function AddTaskBD($taskid)
    {

        $ARR['taskid'] = $taskid;
        $this->AddARRinBD($ARR, "statustable");
        echo "<b><font color='green'>Добавили запись</font></b>";
        // Добавление ТРЕКА в БД

    }




    private function AddTable($ZAPIS)
    {

        $ARR['global'] = $ZAPIS['global'];
        $ARR['type'] = $ZAPIS['type'];
        $ARR['url'] = $ZAPIS['url'];
        $ARR['ticker'] = $ZAPIS['ticker'];


        $this->AddARRinBD($ARR, "basetickers");
        echo "<b><font color='green'>Добавили запись</font></b>";
        // Добавление ТРЕКА в БД


        return true;

    }


    private function GetBaseTable()
    {
        $table = R::findAll("basetickers");
        return $table;
    }


    private function GetStatusTable()
    {
        $table = R::findOne("statustable");
        return $table;
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




}
?>