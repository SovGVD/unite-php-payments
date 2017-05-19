<?php
/*
	standalone webmoney merchant class
	----------------------------------
	sovgvd@gmail.com 2015

*/


class webmoney_merchant {
	private $_purse=array(
				"Z"=>"USD",
				"R"=>"RUB",
				"E"=>"EUR",
				"U"=>"UAH",
				"K"=>"KZT",
				"Y"=>"UZS",
				"B"=>"BYR",
				"G"=>"gold",
				"X"=>"0.001BTC"//WTF?
			);

	function make ($data,$config) {
		// data must be okay by default
		$out=array(
			"ok"=>"ok",
			"d"=>false,
			"e"=>array()
		);
		$out['d']=array(
				"form"=>array(
					"form"=>array("method"=>"POST", "action"=>"https://merchant.webmoney.ru/lmi/payment.asp", "accept-charset"=>"windows-1251"),
					"inputs_hidden"=>array(
						"LMI_PAYMENT_AMOUNT"=>$data['total'],
						"LMI_PAYMENT_NO"=>$data['order_id'],
						"LMI_PAYEE_PURSE"=>$config['wallet'],
						"LMI_PAYMENT_DESC_BASE64"=>base64_encode($data['description'])
					),
				)
			);
		if (isset($config['test']) && $config['test']!==false && is_numeric($config['test'])) {
			$out['d']['form']['inputs_hidden']['LMI_SIM_MODE']=$config['test'];
		}
		return $out;
	}

	function result ($p,$config) {
		// sign is sha256
		// be carefull... time zone expect to be UTC, but we don't know what time zone WebMoney used by default
		$p=$p['data'];
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		if (isset($p['LMI_PREREQUEST']) && $p['LMI_PREREQUEST']==1) {
			// ignore precheck
			$out['ok']='ok';
		} else {
			$out['d']=array(
					"payee_account"=>$p['LMI_PAYEE_PURSE'],
					"total"=>$p['LMI_PAYMENT_AMOUNT'],
					"total_currency"=>"",
					"order_id"=>$p['LMI_PAYMENT_NO'],
					"gateway_invoice"=>$p['LMI_SYS_INVS_NO'],
					"gateway_transaction"=>$p['LMI_SYS_TRANS_NO'],
					"gateway_user_account"=>$p['LMI_PAYER_PURSE'],
					"gateway_user_id"=>$p['LMI_PAYER_WM'],
					"gateway_sign"=>$p['LMI_HASH'],
								//  what about zime zone???
					"gateway_dt_unix"=>strtotime(substr($p['LMI_SYS_TRANS_DATE'],0,4)."-".substr($p['LMI_SYS_TRANS_DATE'],4,2)."-".substr($p['LMI_SYS_TRANS_DATE'],6,2)." ".substr($p['LMI_SYS_TRANS_DATE'],9,8)),
					"gateway_dt_raw"=>$p['LMI_SYS_TRANS_DATE'],
					"description"=>$p['LMI_PAYMENT_DESC']
				);
			if (isset($this->_purse[substr($p['LMI_PAYEE_PURSE'],0,1)])) {
				$out['d']['total_currency']=$this->_purse[substr($p['LMI_PAYEE_PURSE'],0,1)];
			}
			// check sign
			if (isset($p['LMI_HASH']) && strtolower($p['LMI_HASH'])==strtolower($this->_sign($p,$config['sign']))) {
				$out['ok']='ok';
			} else {
				$out['ok']='error';
				$out['e'][]="wrong_sign";
			}
		}
		return $out;
	}

	function fail ($p,$config) {
		//todo
	}

	function success ($p,$config) {
		//todo
	}

	private function _sign($p,$_sign) {
		return hash('sha256', $p['LMI_PAYEE_PURSE'].$p['LMI_PAYMENT_AMOUNT'].$p['LMI_PAYMENT_NO'].$p['LMI_MODE'].$p['LMI_SYS_INVS_NO'].$p['LMI_SYS_TRANS_NO'].$p['LMI_SYS_TRANS_DATE'].$_sign.$p['LMI_PAYER_PURSE'].$p['LMI_PAYER_WM']);
	}
}
?>