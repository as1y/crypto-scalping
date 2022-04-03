<?php
namespace APP\controllers;
use APP\core\Cache;
use APP\models\Addp;
use APP\models\Panel;
use APP\core\base\Model;
use RedBeanPHP\R;

class ParseinController extends AppController {
    public $layaout = 'PANEL';
    public $BreadcrumbsControllerLabel = "Панель управления";
    public $BreadcrumbsControllerUrl = "/panel";

    public $ApiKey = "U5I2AoIrTk4gBR7XLB";
    public $SecretKey = "HUfZrWiVqUlLM65Ba8TXvQvC68kn1AabMDgE";

    private $BaseKurs = 0;

    public $TICKERSqiwiIN = [];
    public $TICKERSvisaOUT = [];


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

        $this->TICKERSvisaOUT = [
            'https://www.bestchange.ru/bitcoin-to-visa-mastercard-rub.html' => 'BTC',
            'https://www.bestchange.ru/bitcoin-cash-to-visa-mastercard-rub.html' => 'BCH',
            'https://www.bestchange.ru/bitcoin-gold-to-visa-mastercard-rub.html' => 'BTG',
            'https://www.bestchange.ru/ethereum-to-visa-mastercard-rub.html' => 'ETH',
            'https://www.bestchange.ru/ethereum-classic-to-visa-mastercard-rub.html' => 'ETC',
            'https://www.bestchange.ru/litecoin-to-visa-mastercard-rub.html' => 'LTC',
            'https://www.bestchange.ru/ripple-to-visa-mastercard-rub.html' => 'XRP',
            'https://www.bestchange.ru/monero-to-visa-mastercard-rub.html' => 'XMR',
            'https://www.bestchange.ru/dogecoin-to-visa-mastercard-rub.html' => 'DOGE',
            'https://www.bestchange.ru/polygon-to-visa-mastercard-rub.html' => 'MATIC',
            'https://www.bestchange.ru/dash-to-visa-mastercard-rub.html' => 'DASH',
            'https://www.bestchange.ru/zcash-to-visa-mastercard-rub.html' => 'ZEC',
            'https://www.bestchange.ru/nem-to-visa-mastercard-rub.html' => 'XEM',
            'https://www.bestchange.ru/neo-to-visa-mastercard-rub.html' => 'NEO',
            'https://www.bestchange.ru/eos-to-visa-mastercard-rub.html' => 'EOS',
            'https://www.bestchange.ru/cardano-to-visa-mastercard-rub.html' => 'ADA',
            'https://www.bestchange.ru/stellar-to-visa-mastercard-rub.html' => 'XLM',
            'https://www.bestchange.ru/tron-to-visa-mastercard-rub.html' => 'TRX',
            'https://www.bestchange.ru/waves-to-visa-mastercard-rub.html' => 'WAVES',
            'https://www.bestchange.ru/omg-to-visa-mastercard-rub.html' => 'OMG',
            'https://www.bestchange.ru/binance-coin-to-visa-mastercard-rub.html' => 'BNB',
            'https://www.bestchange.ru/bat-to-visa-mastercard-rub.html' => 'BAT',
            'https://www.bestchange.ru/qtum-to-visa-mastercard-rub.html' => 'QTUM',
            'https://www.bestchange.ru/chainlink-to-visa-mastercard-rub.html' => 'LINK',
            'https://www.bestchange.ru/cosmos-to-visa-mastercard-rub.html' => 'ATOM',
            'https://www.bestchange.ru/tezos-to-visa-mastercard-rub.html' => 'XTZ',
            'https://www.bestchange.ru/polkadot-to-visa-mastercard-rub.html' => 'DOT',
            'https://www.bestchange.ru/uniswap-to-visa-mastercard-rub.html' => 'UNI',
            'https://www.bestchange.ru/ravencoin-to-visa-mastercard-rub.html' => 'RVN',
            'https://www.bestchange.ru/solana-to-visa-mastercard-rub.html' => 'SOL',
            'https://www.bestchange.ru/vechain-to-visa-mastercard-rub.html' => 'VET',
            'https://www.bestchange.ru/algorand-to-visa-mastercard-rub.html' => 'ALGO',
            'https://www.bestchange.ru/maker-to-visa-mastercard-rub.html' => 'MKR',
            'https://www.bestchange.ru/avalanche-to-visa-mastercard-rub.html' => 'AVAX',
            'https://www.bestchange.ru/yearn-finance-to-visa-mastercard-rub.html' => 'YFI',
            'https://www.bestchange.ru/terra-to-visa-mastercard-rub.html' => 'LUNA',

        ];

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
       $basetable =  $this->GetBaseTable("IN");
        $this->WorkTable($basetable);
        // БАЗОВАЯ ТАБЛИЦА С ТИКЕРАМИ



        // Инициализация парсера
        $aparser = new \Aparser('http://217.25.90.106:9091/API', '', array('debug'=>'false'));


        // ОБНОВЛЕНИЕ ПАРСИНГА IN!!!!!

        $Table =  $this->GetStatusTable("IN");

        // Если таблица статуса парсинга пустая, то запускаем парсинг
        if (empty($Table))
        {

            // ДОБАВЛЯЕМ В СПИСОК УРЛ QIWI_IN
            foreach ($this->TICKERSqiwiIN as $url => $ticker)
            {
                $ZaprosiIN[] = $url;
            }
            $taskUid = $aparser->addTask('default', 'BestIN', 'text', $ZaprosiIN);
            $this->AddTaskBD($taskUid, "IN");
            return true;

        }


        // Смотрим СТАТУС!
        $AparserIN =   $aparser->getTaskState($Table['taskid']);
        echo "<font color='#8b0000'>ПАРСИНГ IN В РАБОТЕ</font><br>";


        if ($AparserIN['status'] == "completed"){

            echo "<font color='green'>ПАРСИНГ IN ЗАКОНЧЕН</font><br>";

            $result = $aparser->getTaskResultsFile($Table['taskid']);
            $content = file_get_contents($result);
            $content = str_replace(" ", "", $content); // Убираем пробелы
            $content = explode("\n", $content);

            // ОБНОВЛЯЕМ ТАБЛИЦУ
            show($content);

            // Обновляем в БД цены
            $this->RenewTickers($content, "IN");

            // Очищаем статус таблицу
            R::trash($Table);


        }






//        $this->set(compact(''));

    }



    private function WorkTable($basetable)
    {

        if (empty($basetable))
        {
            // СОЗДАЕМ ТАБЛИЦУ НА КИВИ ВХОД
            foreach ($this->TICKERSqiwiIN as $url => $ticker)
            {
                $ZAPIS['global'] = "QIWI";
                $ZAPIS['type'] = "IN";
                $ZAPIS['url'] = $url;
                $ZAPIS['ticker'] = $ticker;
                $this->AddTable($ZAPIS);
            }
            echo "<hr>";

            // СОЗДАЕМ ТАБЛИЦУ НА КАРТУ ВЫХОД
            foreach ($this->TICKERSvisaOUT as $url => $ticker)
            {
                $ZAPIS['global'] = "VISA";
                $ZAPIS['type'] = "OUT";
                $ZAPIS['url'] = $url;
                $ZAPIS['ticker'] = $ticker;
                $this->AddTable($ZAPIS);
            }
            echo "<hr>";




            echo "<font color='green'>Таблица с тикерами создана!</font> <br>";
        }


        return true;

    }


    private function RenewTickers($content, $type)
    {


        echo "МАССИВ ПАРСИНГА<br>";


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



        $TICKERS = $this->GetBaseTable($type);


        // Добавляем в БД данные из спарсенного контента!
        foreach ($TICKERS as $ticker)
        {
           $ticker->price = $MASSIV[$ticker['url']][0];
           $ticker->limit = $MASSIV[$ticker['url']][1];
            R::store($ticker);

        }


        return true;


    }



    private function AddTaskBD($taskid, $type)
    {

        $ARR = [];

        if ($type == "IN")
        {
            $ARR['taskid'] = $taskid;
            $ARR['type'] = "IN";
        }
        if ($type == "OUT"){
            $ARR['taskid'] = $taskid;
            $ARR['type'] = "OUT";

        }
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


    private function GetBaseTable($type)
    {
        $table = R::findAll("basetickers", "WHERE type=?", [$type]);
        return $table;
    }


    private function GetStatusTable($type)
    {
        $table = R::findOne("statustable", "WHERE type=?", [$type]);
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