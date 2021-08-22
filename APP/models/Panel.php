<?php
namespace APP\models;
use APP\core\Mail;
use Psr\Log\NullLogger;
use RedBeanPHP\R;


class Panel extends \APP\core\base\Model {


    public function GetTickerRest($exchange = "binance"){

        $TICKERS = [];

        if ($exchange == "binance"){

            $binance = new \ccxt\binance ();

            $TICKERS = $binance->fetchTickers();

        }




        return $TICKERS;



    }


    public function GetBalance(){

            $binance = new \ccxt\binance ();

            $balance = $binance->fetchTickers();


        return $balance;



    }





}
?>