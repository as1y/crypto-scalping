<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">


    <?php \APP\core\base\View::getMeta()?>


    <!-- Global stylesheets -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,300,100,500,700,900" rel="stylesheet" type="text/css">
    <link href="/global_assets/css/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="/assets/css/bootstrap_limitless.min.css" rel="stylesheet" type="text/css">
    <link href="/assets/css/layout.min.css" rel="stylesheet" type="text/css">
    <link href="/assets/css/components.min.css" rel="stylesheet" type="text/css">
    <link href="/assets/css/colors.min.css" rel="stylesheet" type="text/css">
    <!-- /global stylesheets -->

    <!-- Core JS files -->
    <script src="/global_assets/js/main/jquery.min.js"></script>
    <script src="/global_assets/js/main/bootstrap.bundle.min.js"></script>
    <script src="/global_assets/js/plugins/loaders/blockui.min.js"></script>
    <!-- /core JS files -->

    <!-- Theme JS files -->

    <?php \APP\core\base\View::getAssets("js");?>

    <script src="/assets/js/app.js"></script>


    <!-- /theme JS files -->

</head>

<body>

<!-- Main navbar -->
<div class="navbar navbar-expand-md navbar-dark">

    <?php if (empty($_SESSION['ulogin'])):?>
        <a href="/" class="navbar-nav-link " >
            <b><?=APPNAME?></b>
        </a>

    <?endif; ?>

    <?php if (!empty($_SESSION['ulogin'])):?>
    <a href="/panel/" class="navbar-nav-link " >
        <!--        <img src="/global_assets/images/dribbble.png" class="align-top mr-2 rounded" width="20" height="20" alt="">-->
        <b><?=APPNAME?> </b>   <?= ($_SESSION['ulogin']['role'] == "O") ? '<span class="badge-secondary">Кабинет ОПЕРАТОРА</span>' : ' <span class="badge-secondary">Кабинет РЕКЛАМОДАТЕЛЯ</span>'?>
    </a>

        <span class="navbar-text ml-xl-3">
   Пользователей онлайн:  <span class="badge bg-success"><b><?= \APP\core\base\Model::countonline()?></b></span>
        </span>


    <?php endif;?>





    <div class="d-md-none">
        <ul class="navbar-nav ml-auto">
            <li>

                <a href="/user/register/" type="button" class="btn btn-success"><i class="icon-user-plus mr-2"></i> Регистрация</a>
                <a href="/user/" type="button" class="btn btn-success"><i class="icon-circle-right2 mr-2"></i> Войти</a>

            </li>
        </ul>

    </div>




    <div class="collapse navbar-collapse" id="navbar-mobile">


        <ul class="navbar-nav ml-auto">
        <li>

            <?php if(empty($_SESSION['ulogin'])):?>
            <a href="/user/register/" type="button" class="btn bg-teal-400"><i class="icon-user-plus mr-2"></i> Регистрация</a>
            <a href="/user/" type="button" class="btn btn-success"><i class="icon-circle-right2 mr-2"></i> Войти</a>
            <?php endif;?>

            <?php if (!empty($_SESSION['ulogin'])):?>
            <li class="nav-item dropdown">
                <a href="/panel/dialog/" class="navbar-nav-link dropdown-toggle caret-0">
                    <i class="icon-bubbles4"></i>
                    <span class="d-md-none ml-2">Сообщения</span>
                    <span class="badge badge-pill bg-warning-400 ml-auto ml-md-0"><?= \APP\core\base\Model::countnewmessages()?></span>
                </a>

            </li>
            <li class="nav-item dropdown dropdown-user">
                <a href="/panel/profile/" class="navbar-nav-link d-flex align-items-center dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                    <img src="<?=$_SESSION['ulogin']['avatar']?>" class="rounded-circle mr-2" height="34" alt="">
                    <span><?=$_SESSION['ulogin']['username']?></span>
                </a>

                <div class="dropdown-menu dropdown-menu-right">
                    <a href="/panel/balance/" class="dropdown-item"><i class="icon-wallet"></i> Баланс  &nbsp;<span class="badge badge-success"><b><?=$_SESSION['ulogin']['bal']?></b> Р.</span></a>
                    <a href="/panel/profile/" class="dropdown-item"><i class="icon-user-plus"></i> Мой профиль</a>
                    <a href="/panel/refferal/" class="dropdown-item"><i class="icon-cash"></i> Партнерскся программа</a>

                    <a href="/panel/faq/" class="dropdown-item"><i class="icon-question3"></i> F.A.Q</a>

                    <div class="dropdown-divider"></div>
                    <a href="/panel/settings/" class="dropdown-item"><i class="icon-cog5"></i> Настройки аккаунта</a>
                    <a href="/user/logout/" class="dropdown-item"><i class="icon-switch2"></i> Выход</a>
                </div>
            </li>
            <?endif;?>


        </li>
        </ul>



    </div>



</div>
<!-- /main navbar -->





<!-- Page content -->
<div class="page-content">

    <!-- Main content -->
    <div class="content-wrapper">

        <?php if(isset($_SESSION['errors'])): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert"><span>×</span></button>
                <span class="font-weight-semibold">Ошибка!</span> <br><?=$_SESSION['errors']; unset($_SESSION['errors']);?>
            </div>
        <?php endif;?>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert"><span>×</span></button>
                <span class="font-weight-semibold">Успех!</span> <?=$_SESSION['success']; unset($_SESSION['success']);?>
            </div>

        <?php endif;?>

        <!-- Content area -->
        <div class="content d-flex justify-content-center align-items-center">


            <?=$content?>


        </div>
        <!-- /content area -->




    </div>
    <!-- /main content -->

</div>
<!-- /page content -->

<!-- Footer -->
<div class="navbar navbar-expand-lg navbar-dark">
    <div class="text-center d-lg-none w-100">
        <button type="button" class="navbar-toggler dropdown-toggle" data-toggle="collapse" data-target="#navbar-footer">
            <i class="icon-unfold mr-2"></i>
            Подвал сайта
        </button>
    </div>

    <div class="navbar-collapse collapse" id="navbar-footer">
			<span class="navbar-text">
				&copy; 2020 <b><a href="/panel/"><?=APPNAME?></a></b> - Биржа удаленных операторов на телефоне.
			</span>

        <ul class="navbar-nav ml-lg-auto">
            <li class="nav-item"><a href="mailto: <?=CONFIG['BASEMAIL']['email']?>" class="navbar-nav-link" target="_blank"><i class="icon-mail-read mr-2"></i> <?=CONFIG['BASEMAIL']['email']?></a></li>


        </ul>
    </div>
</div>
<!-- /footer -->

<!-- Yandex.Metrika counter -->
<script type="text/javascript" >
    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(61998925, "init", {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        webvisor:true
    });
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/61998925" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->


</body>
</html>








