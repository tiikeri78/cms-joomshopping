<style>
    #ym-btn-back {
        margin: 10px 0;
        background: #fc0;
        border: none;
        border-radius: 3px;
        color: #333;
        height: 35px;
        line-height: 35px;
        font-family: Arial, serif;
    }
</style>
<script src="https://kassa.yandex.ru/checkout-ui/v2.js"></script>
<script>
    const checkout = new window.YandexCheckout({
        confirmation_token: '<?= $token; ?>',
        return_url: '<?= $returnUrl; ?>',
        embedded_3ds: true,
        error_callback: function (error) {
            if (error.error === 'token_expired') {
                document.location.redirect('<?= $returnUrl; ?>');
            }
            console.log(error);
        }
    });
</script>

<div id="ym-widget-checkout-ui"></div>
<button onclick="history.go(-1);" id="ym-btn-back">Вернуться назад</button>

<script>
    document.addEventListener("DOMContentLoaded", function (event) {
        checkout.render('ym-widget-checkout-ui');
    });
</script>