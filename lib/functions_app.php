<?php

function changemet($open, $close)
{

    /*
    $change =  (($close*100)/$open) - 100;
    $change = round($change,2);
    */


    if($close == 0){
        return 0;
    }
    $change = 100 - ($open / $close) * 100;
    $change = round($change, 2);

    /*
       $change =  (($close*100)/$open) - 100;
       $change = round($change,2);
      */

    return $change;
}


function plusperc($PRICE, $tochkabid, $count)
{
    //СКОЛЬКО ТОЧЕК ПОСЛЕ ЗАПЯТОЙ У БИДА
    $sat = "1";
    for ($x = 0; $x < $tochkabid; $x++) $sat .= "0";
    //СКОЛЬКО ТОЧЕК ПОСЛЕ ЗАПЯТОЙ У БИДА
    $PRICE = $PRICE * $sat;

    $add = ($PRICE / 100) * $count;
    $PRICE = $PRICE + ceil($add);
    $PRICE = $PRICE / $sat;
    return number_format($PRICE, $tochkabid, '.', '');
}

function minusperc($PRICE, $tochkabid, $count)
{
    //СКОЛЬКО ТОЧЕК ПОСЛЕ ЗАПЯТОЙ У БИДА
    $sat = "1";
    for ($x = 0; $x < $tochkabid; $x++) $sat .= "0";
    //СКОЛЬКО ТОЧЕК ПОСЛЕ ЗАПЯТОЙ У БИДА
    $PRICE = $PRICE * $sat;

    $add = ($PRICE / 100) * $count;
    $PRICE = $PRICE - ceil($add);
    $PRICE = $PRICE / $sat;
    return number_format($PRICE, $tochkabid, '.', '');
}



function SCORING($KLINES, $pricenow){


    $CountKlines = count($KLINES);

    $endKlines = $KLINES[$CountKlines-1];
    $prevKlines = $KLINES[$CountKlines-2];


    $MASS = [];
    $MASS['RSI'] = GetRSI($KLINES);


//    $MASS['KORSIZE'] = GetKoridorSize($KLINES, 5)['KORSIZE'];
//    $MASS['AVGKOR'] = GetKoridorSize($KLINES, 5)['AVG'];
//    $MASS['COLOR'] = GetColorLastKline($KLINES);
//    $MASS['DLINNA'] = abs(GetDlinneKline($KLINES));
 //   $MASS['VOL'] = round(end($endKlines)/$pricenow);
 //   $MASS['VOLPREV'] = round(end($prevKlines)/$pricenow);

    return $MASS;
}




function GetMA($KLINES)
{

    $sum = 0;

    show($KLINES);
 



}



function GetRSI($KLINES){


    $sumplus = 0;
    $summinus = 0;

    for ($i=1; $i < count($KLINES); $i++ ){

        $change = $KLINES[$i]['4'] - $KLINES[$i - 1]['4'];

       // echo "ТФ ".$i." | ЗАКРЫТИЕ  ".$KLINES[$i]['4']." | Change: $change <br>   ";

        if ($change >= 0) $sumplus = $sumplus + $change;
        if ($change < 0) $summinus = $summinus + abs($change);

    }


    $sumplus = $sumplus / (count($KLINES) - 1);
    $summinus = $summinus / (count($KLINES) - 1);


    $RS = $sumplus / $summinus;


//echo "<br>Средняя сумма плюсовых: $sumplus | Средняя сумма плюсовых: $summinus <br> RS: $RS <br>";


    $RSI = 100 - (100 / (1 + $RS));
    $RSI = round($RSI, 2);
//echo "<h1>RSI: $RSI </h1>";


    return $RSI;





}

function GetKoridorSize($klines, $interval){

    $count = count($klines); // Считаем сколько всего свечей

    // Стартовая свеча
    $startKlineNumber = $count - $interval;

  //  show($startKlineNumber);

    $sumHigh = 0;
    $sumLow = 0;

    for ($i = $startKlineNumber; $i <= $count-1; $i++) {

    //    echo "Свеча".$i."<br>";

        $sumHigh = $sumHigh + $klines[$startKlineNumber][2];
        $sumLow = $sumLow + $klines[$startKlineNumber][3];

    }

    $sumHigh = ($sumHigh / $interval);
    $sumLow = ($sumLow / $interval);


    $AVG = ($sumHigh + $sumLow) / 2;

    $KORSIZE = 100 - ($sumLow / $sumHigh) * 100;
    $KORSIZE = round($KORSIZE, 2);

    $AB['KORSIZE'] = $KORSIZE;
    $AB['AVG'] = $AVG;
    $AB['sumHigh'] = $sumHigh;
    $AB['sumLow'] = $sumLow;


    return $AB;


}

function GetColorLastKline($KLINES){

    $KLINES15MLAST = end($KLINES);

    if ($KLINES15MLAST[1] < $KLINES15MLAST[4])
       return  "GREEN";
    else
        return "RED";


}

function GetDlinneKline($KLINES){

    $KLINES15MLAST = end($KLINES);

    $DLINNANOW = 0;
    $DLINNANOW = 100 - ($KLINES15MLAST[1] / $KLINES15MLAST[4]) * 100;
    $DLINNANOW = round($DLINNANOW, 2);

    return $DLINNANOW;

}


?>