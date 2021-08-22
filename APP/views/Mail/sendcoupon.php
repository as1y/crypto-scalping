<h3>Промокод <?=$coupon->companies['name']?></h3>

<a href="<?=$coupon['gotolink']?>" target="_blank" ><img border="0" src="<?=CONFIG['DOMAIN'].$coupon->companies['logo']?>"> </a><br>

Вы заказали отправку промокода на сайте <?=CONFIG['DOMAIN']?><br>

Тип промокода: <b> <?=json_decode($coupon['types'], true)[0]['name']?></b> <br>

    <?php if (!empty($coupon['short_name'])):?>
<i><?=$coupon['short_name']?><br></i>
    <?php endif; ?>
<h2>ПРОМОКОД: <?=$coupon['promocode']?></h2>

<a href="//<?=CONFIG['DOMAIN']?>/go/?coupon=<?=$coupon['id']?>" target="_blank"  class="btn btn-warning">ПЕРЕЙТИ НА САЙТ</a>





