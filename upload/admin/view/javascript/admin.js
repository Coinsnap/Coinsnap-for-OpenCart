
$(document).ready(function() {
    
    if($('#input-provider').length){
        console.log('Provider 2');
        setProvider();
        $('#input-provider').change(function(){
            setProvider();
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
        else {console.log('Coinsnap');
            $('div.btcpay').hide();
            $('div.btcpay input[type=text]').removeAttr('required');
            $('div.coinsnap').show();
            $('div.coinsnap input[type=text]').attr('required','required');
            
            
            
        }
    }
});

