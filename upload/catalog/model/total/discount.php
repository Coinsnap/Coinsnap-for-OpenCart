<?php
namespace Opencart\Catalog\Model\Extension\Coinsnap\Total;

class Discount extends \Opencart\System\Engine\Model {
    
    /**
	 * Get Total
	 *
	 * @param array<int, array<string, mixed>> $totals
	 * @param array<int, float>                $taxes
	 * @param float                            $total
	 *
	 * @return void
    */
    public function getTotal(array &$totals, array &$taxes, float &$total): void {

        $order_total = $this->cart->getSubTotal();
        
        $this->load->language('extension/coinsnap/total/discount');
        
        if($order_total === 0){
            return;
        }
        
        $discount_enabled = $this->config->get('payment_coinsnap_discount_enabled');
        $isDiscount = false;
        
        $payment_method = (isset($this->session->data['payment_method']['code']))? $this->session->data['payment_method']['code'] : '';
        
        if ($discount_enabled && $payment_method === 'coinsnap.coinsnap'){
            
            $discount_type = $this->config->get('payment_coinsnap_discount_type');
            $discount_amount = (null !== $this->config->get('payment_coinsnap_discount_amount'))? $this->config->get('payment_coinsnap_discount_amount') : 0;
            $discount_percentage = (null !== $this->config->get('payment_coinsnap_discount_percentage'))? floatval($this->config->get('payment_coinsnap_discount_percentage')) : 0;
        
            if($discount_type === 'fixed' && floatval($discount_amount) > 0){
                
                $discount_amount = round(floatval($discount_amount),2);
                $discount_amount_limit = (null !== $this->config->get('payment_coinsnap_discount_amount_limit'))? floatval($this->config->get('payment_coinsnap_discount_amount_limit')) : 0;
                
                if($discount_amount_limit >= 0 && $discount_amount_limit < 100){
                    if($discount_amount > ($order_total * $discount_amount_limit / 100)){
                        $discount_amount = round($order_total * $discount_amount_limit / 100,2);
                    }
                        
                    if($discount_amount < $order_total){
                        $isDiscount = true;
                        $discount_title = '';
                    }
                }
            }
            elseif($discount_type === 'percentage' && $discount_percentage > 0) {
                if($discount_percentage > 0 && $discount_percentage < 100){
                    $isDiscount = true;
                    $discount_title = ' '.$discount_percentage . '%';
                    $discount_amount = round($order_total * $discount_percentage / 100,2);
                }
            }
        
            if($isDiscount && $discount_amount > 0){
            
                $totals[] = [
                    'extension'  => 'coinsnap',
                    'code'       => 'discount',
                    'title'      => $this->language->get('text_discount'),
                    'value'      => -$discount_amount,
                    'sort_order' => $this->config->get('total_discount_sort_order')
                ];

                $total -= $discount_amount;
            }    
        }
    }
}
