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


    <script src="/assets/js/app.js"></script>
    <?php \APP\core\base\View::getAssets("js");?>

    <script src="/coupons_script.js"></script>


</head>

<body>







<!-- Main navbar -->
<div class="navbar navbar-dark navbar-expand-xl rounded-top">

    <a href="/panel/" class="navbar-nav-link " >
        <!--        <img src="/global_assets/images/dribbble.png" class="align-top mr-2 rounded" width="20" height="20" alt="">-->
        <b><?=APPNAME?> </b>
    </a>

    <div class="d-md-none">
        <button class="navbar-toggler sidebar-mobile-main-toggle" type="button">
            <i class="icon-paragraph-justify3"></i>
        </button>
    </div>

    <div class="d-xl-none">
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar-demo1-mobile">
            <i class="icon-grid3"></i>
        </button>
    </div>

    <div class="navbar-collapse collapse" id="navbar-demo1-mobile">


        <ul class="navbar-nav ml-xl-auto">







            <li class="nav-item dropdown dropdown-user">
                <a href="/panel/profile/" class="navbar-nav-link d-flex align-items-center dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                    <img src="<?=$_SESSION['ulogin']['avatar']?>" class="rounded-circle mr-2" height="34" alt="">
                    <span><?=$_SESSION['ulogin']['username']?></span>
                </a>

                <div class="dropdown-menu dropdown-menu-right">
                    <a href="/panel/stat/" class="dropdown-item"><i class="icon-question3"></i> Статистика</a>
                    <a href="/user/logout/" class="dropdown-item"><i class="icon-switch2"></i> Выход</a>
                </div>
            </li>




        </ul>
    </div>



</div>
<!-- /main navbar -->







<div class="page-header">
    <div class="breadcrumb-line breadcrumb-line-light header-elements-md-inline">
        <div class="d-flex">


            <a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
        </div>

        <div class="header-elements d-none">
            <div class="breadcrumb justify-content-center">


<!--                <a href="#" class="btn btn-danger btn-sm">-->
<!--                    <i class="icon-reading mr-2"></i>-->
<!--                   ОБУЧЕНИЕ-->
<!--                </a>-->











            </div>
        </div>
    </div>


</div>



<!-- Page content -->
<div class="page-content">



    <!-- Main sidebar -->
    <div class="sidebar sidebar-dark sidebar-main sidebar-expand-md align-self-start">

        <!-- Sidebar mobile toggler -->
        <div class="sidebar-mobile-toggler text-center">
            <a href="#" class="sidebar-mobile-main-toggle">
                <i class="icon-arrow-left8"></i>
            </a>
            <a href="#" class="sidebar-mobile-expand">
                <i class="icon-screen-full"></i>
                <i class="icon-screen-normal"></i>
            </a>
        </div>
        <!-- /sidebar mobile toggler -->


        <!-- Sidebar content -->
        <div class="sidebar-content nav-item-divider">
            <div class="card card-sidebar-mobile">


                <!-- User menu -->
                <div class="sidebar-user">
                    <div class="card-body">
                        <div class="media">
                            <div class="mr-3">
                                <a href="/panel/profile/"><img src="<?=$_SESSION['ulogin']['avatar']?>" width="38" height="38" class="rounded-circle" alt=""></a>
                            </div>

                            <div class="media-body">
                                <div class="media-title font-weight-semibold"> <?=$_SESSION['ulogin']['username']?> </div>
                            </div>


                        </div>
                    </div>
                </div>
                <!-- /user menu -->

                <?php
                $active[$this->route['action']] = 'active';

                ?>



                <!-- Main navigation -->
                <div class="card-body p-0">
                    <ul class="nav nav-sidebar" data-nav-type="accordion">

                        <li class="nav-item-header">
                            <div class="text-uppercase font-size-sm line-height-sm">ГЛАВНАЯ</div>
                        </li>

                        <li class="nav-item">
                            <a href="/panel/" class="nav-link <?=isset($active['index']) ? $active['index'] : ''; ?>">
                                <i class="icon-home4"></i>
                                <span>	СВОДКА </span>
                            </a>
                        </li>



                    </ul>
                </div>
                <!-- /main navigation -->

            </div>
        </div>
        <!-- /sidebar content -->

    </div>
    <!-- /main sidebar -->






    <!-- Main content -->
    <div class="content-wrapper">


        <!-- Content area -->
        <div class="content">


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



            <?=$content?>



        </div>
        <!-- /content area -->




    </div>
    <!-- /content wrapper -->




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
				&copy; 2020 <b><a href="/panel/"><?=APPNAME?></a></b> - Промокоды, купоны, скидки
			</span>

        <ul class="navbar-nav ml-lg-auto">
            <li class="nav-item"><a href="mailto: <?=CONFIG['BASEMAIL']['email']?>" class="navbar-nav-link" target="_blank"><i class="icon-mail-read mr-2"></i> <?=CONFIG['BASEMAIL']['email']?></a></li>


        </ul>
    </div>
</div>
<!-- /footer -->






</body>
</html>
