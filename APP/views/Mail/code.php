Здравствуйте, <?=$_SESSION['confirm']['username']?>!<br>

Добро пожаловать в сервис <?=APPNAME?><br>
<p> Для дальнейшей работы подтвердите, пожалуйста, вашу почту по ссылке:<br>
  <b>https://<?=CONFIG['DOMAIN']?>/user/confirmEmail/?code=<?=$_SESSION['confirm']['code']?>&email=<?=$_SESSION['confirm']['email']?></b>

</p>
Данные для входа в систему:<br>
<b>Ваш Логин:</b> <?=$_SESSION['confirm']['email']?><br>
<b>Ваш Пароль:</b> <?=$_SESSION['confirm']['password2']?><br>
