
var Category = "";
var Company = "";
var Sortirovka = "";
var Brand = "";


function ChangeFilter() {
    str = getFilterParamsParams();


    let addr = '/catalog/' + str;

    // Если выбран только бренд




    window.location.href = addr;

    return true;

}

function changePage(page){


    str = getFilterParamsParams ();


    $('#CouponContainer').empty();

    $.ajax(
        {
            url : document.location.pathname,
            type: 'POST',
            data: str + '&page=' + page,
            cache: false,
            success: function( coupons ) {

                $('#CouponContainer').append(coupons);
                window.scrollTo(0, 0);


            }
        }
    );


}

function clck(couponid)
{
    // // Устанавливаем промокод в сессиию
    document.cookie = "runmodal="+couponid;
    window.open("#");
}


function getFilterParamsParams() {
    Category = "";
    Company = "";
    Sortirovka = "";
    Brand = "";

    Category = $('select[name=category]').val();
    Company = $('select[name=company]').val();
    Sortirovka = $('select[name=sort]').val();

    str =  '?Category=' + Category + '&Company=' + Company + '&Brand=' + Brand + '&sort=' + Sortirovka;


    return str;

}

function getUrlParams(url = location.search){
    var regex = /[?&]([^=#]+)=([^&#]*)/g, params = {}, match;
    while(match = regex.exec(url)) {
        params[match[1]] = match[2];
    }
    return params;
}

 


