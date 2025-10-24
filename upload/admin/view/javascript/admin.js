
$(document).ready(function() {
    
    if($('#input-provider').length){
        console.log('Provider 2');
        setProvider();
        $('#input-provider').change(function(){
            setProvider();
        });
    }
    
    if($('#discount-type').length){
        
        enableDiscount();
        
        $('#discount-enabled').change(function(){
            enableDiscount();
        });
        
        $('#discount-amount-limit').change(function(){
            if(parseFloat($(this).val()) < 0){
                $(this).val(0);
            }
            if(parseFloat($(this).val()) > 100){
                $(this).val(100);
            }
        });
        
        $('#discount-percentage').change(function(){
            if(parseFloat($(this).val()) < 0){
                $(this).val(0);
            }
            if(parseFloat($(this).val()) > 100){
                $(this).val(100);
            }
        });
        
        $('.discount').keyup(function() {
            $(this).val($(this).val().replace(/[^0-9,]/g,''));
        });
        
        if($('#discount-enabled').prop('checked')){
            setDiscount();
        }
        
        $('#discount-type').change(function(){
            setDiscount();
        });
    }
    
    function setProvider(){
        
        if($('#input-provider').val() === 'btcpay'){
            console.log('BTCPay');
            $('div.coinsnap').hide();
            $('div.coinsnap input[type=text]').removeAttr('required');
            $('div.btcpay').show();
            $('div.btcpay input[type=text]').attr('required','required');
        }
        else {
            console.log('Coinsnap');
            $('div.btcpay').hide();
            $('div.btcpay input[type=text]').removeAttr('required');
            $('div.coinsnap').show();
            $('div.coinsnap input[type=text]').attr('required','required');
        }
    }
    
    function enableDiscount(){
        if($('#discount-enabled').prop('checked')){
            $('.discount').show();
            setDiscount();
        }
        else {
            $('.discount').hide();
        }
    }
    
    function setDiscount(){
        if($('#discount-type').val() === 'fixed'){
            $('.discount.discount-percentage').hide();
            $('.discount.discount-amount').show();
        }
        else {
            $('.discount.discount-amount').hide();
            $('.discount.discount-percentage').show();
        }
    }
    
});

