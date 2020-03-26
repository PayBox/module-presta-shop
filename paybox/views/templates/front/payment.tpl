<form id="paybox_payment_form" name="payment" action="{$action}" method="post" enctype="application/x-www-form-urlencoded" accept-charset="utf-8">
    {foreach $arrFields as $key => $val}
        <input type="hidden" name="{$key}" value="{$val}">
    {/foreach}
</form>
<script type="text/javascript">
    function formAutoSubmit () {
        var frm = document.getElementById("paybox_payment_form");
        frm.submit();
    }
    window.onload = formAutoSubmit;
</script>
