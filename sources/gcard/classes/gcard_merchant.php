<?php
/*
	g-card merchant class
	----------------------------------
	sovgvd@gmail.com 2016
*/


class gcard_merchant {

	function make ($data,$config) {
		// data must be okay by default
		$out=array(
			"ok"=>"ok",
			"d"=>false,
			"e"=>array()
		);
		$gcard = new PS_class(array(
			'merchantId' => $config['merchantId'],                       
			'merchantSign' => $config['merchantSign'],
			'site_result_url' => $config['result_url'], 
			'site_success_url' => $config['success_url'], 
			'site_cancel_url' => $config['fail_url'],
			'site_language' => 'EN', // TODO            
			'currency' => $data['currency'],
			'cache_dir' => $config['cache_dir']
			)
		);
		$out['d']=array("form"=>$gcard->html(array(
				"order_nr"=>time(), //$data['order_id'],
				"amount"=>$data['total'],
				"desc"=>$data['description'],
				"customer_email"=>$data['user_data']['d']['email'],
				"customer_name"=>$data['user_data']['d']['login'],
				"info"=>array("ORDERID"=>$data['order_id'])
			),true));
		return $out;
	}

	function result ($p,$config) {
		$p=$p['data'];
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		$gcard = new PS_class(array(
			'merchantId' => $config['merchantId'],                       
			'merchantSign' => $config['merchantSign'],
			'site_result_url' => $config['result_url'], 
			'site_success_url' => $config['success_url'], 
			'site_cancel_url' => $config['fail_url'],
			'site_language' => 'EN', // TODO            
            'currency' => 'USD',	// TODO
            'cache_dir' => $config['cache_dir']
			)
		);
		$raw_result=$gcard->getResult();

		if ($raw_result && $raw_result['STATUS']==1) {
			$out['ok']='ok';
			$out['d']=array(
					"payee_account"=>"",
					"total"=>$raw_result['AMOUNT'],
					"total_currency"=>$raw_result['CURRENCY'],
					"order_id"=>$raw_result['ORDERID'],
					"gateway_invoice"=>$raw_result['ORDER_NR'],
					"gateway_transaction"=>$raw_result['PAYMENT_CODE'],
					"gateway_user_account"=>$raw_result['PAN'].";".$raw_result['SYSTEM'],
					"gateway_user_id"=>$raw_result['INVOICE_ID'],
					"gateway_sign"=>$raw_result['STATUS_HASH'].";".$raw_result['HASH'],
					"gateway_dt_unix"=>$raw_result['INVOICE_TIME_TIMESTAMP'],
					"gateway_dt_raw"=>$raw_result['INVOICE_TIME'],
					"description"=>$raw_result['DESC']
				);
			$out['d']['special_result']="OK";
		} else if ($raw_result && $raw_result['STATUS']==2) {
				$out['ok']='error';
				$out['e'][]="canceled";
		} else if ($raw_result && $raw_result['STATUS']==3) {
				$out['ok']='error';
				$out['e'][]="payment_error";
		} else if ($raw_result===false) {
				$out['ok']='error';
				$out['e'][]="wrong_sign";
		}
		return $out;
	}

	function fail ($p,$config) {
		//todo
	}

	function success ($p,$config) {
		//todo
	}

}


// SDK class (a little bit changed for uniteapi-php-payments class)

class PS_class
{
    var $merchantUrl            = 'https://paysmobile.com/process/payment';
    var $merchantId 			= '';
	var $merchantSign 			= '';
    var $currency			    = 'EUR';
	var $site_language			= 'EN';
    
    var $site_result_url		= null;		
	var $site_result_method		= 'POST';
	var $site_success_url		= null;		
	var $site_success_method	= 'POST';
	var $site_cancel_url		= null;		
	var $site_cancel_method		= 'POST';
    
    var $site_sys_error_url		= null;		
	var $site_sys_error_method	= 'POST';
    
    var $pc_method              = 'POST';
	var $transparent			= 1;
    var $bill                   = 0;
    var $auto_reg               = 0;
    
    var $cache_dir = false;
    
    var $map = array(
        'PAYMENT_MERCHANT_ID'       => 'merchantId',
        'PAYMENT_ORDER_NR'          => 'order_nr',
        'PAYMENT_AMOUNT'            => 'amount',
        'PAYMENT_CURRENCY'          => 'currency',
        'PAYMENT_DESC'              => 'desc',
        'PAYMENT_RESULT_URL'        => 'site_result_url',
        'PAYMENT_RESULT_METHOD'     => 'site_result_method',
        'PAYMENT_SUCCESS_URL'       => 'site_success_url',
        'PAYMENT_SUCCESS_METHOD'    => 'site_success_method',
        'PAYMENT_CANCEL_URL'        => 'site_cancel_url',
        'PAYMENT_CANCEL_METHOD'     => 'site_cancel_method',
        'PAYMENT_HASH'              => 'created_hash',
        'PAYMENT_RESPONS_KEY'       => 'key',
        'PAYMENT_TRANSPARENT'       => 'transparent',
        'PAYMENT_CUSTOMER_EMAIL'    => 'customer_email',
        'PAYMENT_CUSTOMER_PHONE'    => 'customer_phone',
        'PAYMENT_CUSTOMER_NAME'     => 'customer_name',
        'PAYMENT_CUSTOMER_FNAME'    => 'customer_fname',
        'PAYMENT_CUSTOMER_LNAME'    => 'customer_lname',
        'PAYMENT_CUSTOMER_CITY'     => 'customer_city',
        'PAYMENT_CUSTOMER_COUNTRY'  => 'customer_country',
        'PAYMENT_AUTO_REG'          => 'auto_reg',
        'PAYMENT_BILL'              => 'bill',
        
        '-lang'                     => 'site_language'
    );
        
    function __construct($params=array())
	{
		foreach($params as $n=>$v) $this->$n = $v;
	}
	
	private function _cache($id,$data=false) {
		if ($this->cache_dir) {
			// todo not in files?
			// todo clear old
			$f=$this->cache_dir.md5($id)."-".sha1($id);
			if ($data===false && file_exists($f)) {
				return json_decode(file_get_contents($f),true);
			} else if ($data!==false) {
				file_put_contents($f,json_encode($data));
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
    
    function html($params=array(), $raw=false){
        foreach($params as $n=>$v) $this->$n = $v;
        $this->generate_hash($this->order_nr);
        $this->get_key();
        if ($raw) $html=array("form"=>array(),"inputs_hidden"=>array());
        
        if(isset($this->respons_code) && $this->respons_code=='200'){
			if ($raw) {
				$html['form']=array("method"=>$this->pc_method, "action"=>$this->merchantUrl);
			} else {
				$html = "<form method='{$this->pc_method}' id='{$this->order_nr}' action='{$this->merchantUrl}'>\n";
			}
            foreach($this->map as $key => $val){
		if (isset($this->$val)) {
			$val_input = $this->$val;
		} else {
			$val_input = '';
		}
                if ($raw) {
			$html['inputs_hidden'][$key]=$val_input;
		} else {
			$html .= "<input type='hidden' name='{$key}' value='{$val_input}' />\n";
		}
            }
            if(isset($params['info']) && is_array($params['info']) && count($params['info'])){
                foreach($params['info'] as $key => $val){
					if ($raw) {
						$html['inputs_hidden']['INFO_'.$key]=$val;
					} else {
						$html .= "<input type='hidden' name='INFO_{$key}' value='{$val}' />\n";
					}
                }
            }
        
        }else{
			if ($raw) {
				$html['form']=array("method"=>$this->site_sys_error_method, "action"=>$this->site_sys_error_url);
			} else {
				$html = "<form method='{$this->site_sys_error_method}' id='{$this->order_nr}' action='{$this->site_sys_error_url}'>\n";
			}
            if ($raw) {
				$html['inputs_hidden']['order_nr']=$this->order_nr;
			} else {
				$html .= "<input type='hidden' name='order_nr' value='{$this->order_nr}' />\n";            
			}
            if(isset($params['info']) && is_array($params['info']) && count($params['info'])){
                foreach($params['info'] as $key => $val){
					if ($raw) {
						$html['inputs_hidden'][$key]=$val;
					} else {
						$html .= "<input type='hidden' name='{$key}' value='{$val}' />\n";
					}
                }
            }
            
        }
        if ($raw) {
		} else {
			$html .= "</form>\n";
		}
        
        return $html;
    }
    
    function bill($params=array()){
        foreach($params as $n=>$v) $this->$n = $v;
        $this->generate_hash($this->order_nr);
        $this->get_key();
        
        $array = array();
        foreach($this->map as $key => $val){
            $array[$key] = $this->$val;
        }
        
        $array['PAYMENT_AUTO_REG'] = 1;
        
        if(isset($params['info']) && is_array($params['info']) && count($params['info'])){
            foreach($params['info'] as $key => $val){
                $array['INFO_'.$key] = $val;
            }
        }
        
        $array['PAYMENT_BILL'] = 1;
        if(isset($this->respons_code) && $this->respons_code=='200'){
            $return = $this->UrlNotify($this->merchantUrl, $this->pc_method, $array);
            if($return['code']==200){
                $result = unserialize($return['respons']);
                return json_encode(array('url'=>str_replace('payment', '', $this->merchantUrl).$result['url'], 'code'=>$result['code']));
            }else 
                return 'Error code: '.$return['code'].'<br />'.$return['respons'];
        }else{
            return 'Error get key code: '.$this->respons_code.'<br />'.$this->key;
        }
    }
    
    function getPaymetStatus($code){
        $array = array(
            'get_payment_status' => 1,
            'payment_code' => $code
        );
        $return = $this->UrlNotify($this->merchantUrl, $this->pc_method, $array);
        $result = unserialize($return['respons']);
        if($result['STATUS_HASH']!=md5("PC:{$result['STATUS']}|{$result['STATUS_STRING']}")) return false;
        else return json_encode($result);
    }
    
    function getResult(){
        $params = $this->site_result_method=='POST' ? $_POST:$_GET;
        if(!isset($params['STATUS_HASH'])) return false;
        if(!isset($params['HASH'])) return false;
        if(!isset($params['STATUS'])) return false;
        if(!isset($params['STATUS_STRING'])) return false;
        if(!isset($params['SYSTEM'])) return false;
        
        $hash=md5("{$params['HASH']}|{$params['STATUS']}|{$params['STATUS_STRING']}|{$params['SYSTEM']}|{$params['AMOUNT']}|".$this->merchantSign);
        if ($hash==$params['RESPONSE_HASH']) {
	    return $params;
	} else {
	    return false;
	}
    }
    
    private function generate_hash($order_nr){
		if ($this->cache_dir) {
			//$this->_cache($order_nr.'_merchantSign',$this->merchantSign);
		} else {
			$_SESSION[$order_nr.'_merchantSign'] = $this->merchantSign;
		}
        $this->created_hash = md5("$this->merchantId|$this->order_nr|$this->amount|$this->currency|$this->merchantSign");
    }
    
    private function get_key(){
        $return = $this->UrlNotify($this->merchantUrl, $this->pc_method, array(
            'get_key_shop' => $this->merchantId,
            'order_nr'      => $this->order_nr,
            'amount'        => $this->amount,
            'created_hash'  => $this->created_hash
        ));
        $this->key = $return['respons'];
        $this->respons_code = $return['code'];
    }
    
    private function UrlNotify($url, $method, $params)
	{
		set_time_limit(0);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		if (isset($_SERVER['HTTP_REFERER'])) curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);

		if (strtoupper($method)=='GET') {
			curl_setopt($ch, CURLOPT_HTTPGET, 1);
			curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($params));
		} else {
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}

		$contents = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
        
		return array('code'=>$code, 'respons'=>$contents);
	}
}

?>
