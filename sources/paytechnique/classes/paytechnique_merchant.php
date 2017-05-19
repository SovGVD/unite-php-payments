<?php
/*
	standalone paytechnique merchant class
	----------------------------------
	sovgvd@gmail.com 2016
	too crutchy

*/


class paytechnique_merchant {
	private $udb=false;
	var $key_chars="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";

	function make ($data,$config,$_db=false) {
		if ($_db!==false) $this->udb=$_db;
		// data must be okay by default
		$out=array(
			"ok"=>"ok",
			"d"=>false,
			"e"=>array()
		);

		if (isset($data['extra']) && isset($data['extra']['full_form']) && $data['extra']['full_form']===true) {
			$out['d']=array(
				"user_form"=>array(
					"form"=>array("method"=>"POST", "action"=>$config['pregateway_url']),
					"inputs_hidden"=>array(
						),
					"inputs"=>array(
						"phone"=>array("value"=>rand(1000000,9999999)),							// TODO
						"city"=>array("value"=>str_replace(array(0,1,2,3,4,5,6,7,8,9),"",md5(rand(0,999999)))),		// TODO
						"address"=>array("value"=>"na, na, 1"),								// TODO
						"zip"=>array("value"=>rand(1000,999999)),							// TODO
						"total"=>array("value"=>number_format((float)$data['total'],2,'.',''),"ro"=>true,"colspan"=>2),
						"email"=>array("value"=>$data['user_data']['d']['email'],"colspan"=>2),		/// TODO, fill user data for all values
						"currency"=>array("value"=>"USD","ro"=>true,"colspan"=>2),
						"order_id"=>array("value"=>$data['order_id'],"ro"=>true,"colspan"=>2),
						"description"=>array("value"=>$data['description'],"ro"=>true,"colspan"=>2),
						"country"=>array("field"=>'list',"type"=>'country',"colspan"=>2, "selected_key"=>@geoip_country_code3_by_name($_SERVER['REMOTE_ADDR'])),
						"country_state"=>array("field"=>'list',"colspan"=>2,"type"=>'country_state',"parent"=>'country'),
						"full_name"=>array("colspan"=>2),		/// TODO, prechecks!!!
						"card_number"=>array("type"=>'card_number',"colspan"=>2),
						"card_type"=>array("field"=>'list',"type"=>'card_type',"parent"=>'card_number',"colspan"=>2),
						//"card_expiration_month"=>array("field"=>'list', "type"=>'month',"colspan"=>2),
						//"card_expiration_year"=>array("field"=>'list', "type"=>'year',"colspan"=>2),
						"card_expiration"=>array("field"=>'multi',"fields"=>array("card_expiration_month"=>array("field"=>'list', "type"=>'month'),"card_expiration_year"=>array("field"=>'list', "type"=>'year'))),
						"card_vc"=>array("type"=>'card_vc',"colspan"=>2,"width"=>"30%")	// Verification Code CVV/2
					),
				)
			);
		} else {
			$out['d']=array(
				"user_form"=>array(
					"form"=>array("method"=>"POST", "action"=>$config['pregateway_url']),
					"inputs_hidden"=>array(
						"phone"=>rand(1000000,9999999),
						"city"=>str_replace(array(0,1,2,3,4,5,6,7,8,9),"",md5(rand(0,999999))),
						"address"=>"na, na, 1",
						"zip"=>rand(1000,999999)
						),
					"inputs"=>array(
						"total"=>array("value"=>number_format((float)$data['total'],2,'.',''),"ro"=>true,"colspan"=>2),
						"email"=>array("value"=>$data['user_data']['d']['email'],"colspan"=>2),		/// TODO, fill user data for all values
						"currency"=>array("value"=>"USD","ro"=>true,"colspan"=>2),
						"order_id"=>array("value"=>$data['order_id'],"ro"=>true,"colspan"=>2),
						"description"=>array("value"=>$data['description'],"ro"=>true,"colspan"=>2),
						"country"=>array("field"=>'list',"type"=>'country',"colspan"=>2, "selected_key"=>@geoip_country_code3_by_name($_SERVER['REMOTE_ADDR'])),
						"country_state"=>array("field"=>'list',"colspan"=>2,"type"=>'country_state',"parent"=>'country'),
						"full_name"=>array("colspan"=>2),		/// TODO, prechecks!!!
						"card_number"=>array("type"=>'card_number',"colspan"=>2),
						"card_type"=>array("field"=>'list',"type"=>'card_type',"parent"=>'card_number',"colspan"=>2),
						//"card_expiration_month"=>array("field"=>'list', "type"=>'month',"colspan"=>2),
						//"card_expiration_year"=>array("field"=>'list', "type"=>'year',"colspan"=>2),
						"card_expiration"=>array("field"=>'multi',"fields"=>array("card_expiration_month"=>array("field"=>'list', "type"=>'month'),"card_expiration_year"=>array("field"=>'list', "type"=>'year'))),
						"card_vc"=>array("type"=>'card_vc',"colspan"=>2,"width"=>"30%")	// Verification Code CVV/2
					),
				)
			);
		}
		if (isset($data['subscription_period']) && in_array($data['subscription_period'],array("day","month","year"))) {
			$out['d']['user_form']['inputs_hidden']['subscription']=1;
			$out['d']['user_form']['inputs_hidden']['subscription_period']=$data['subscription_period'];
		} 
		if (isset($data['user_data']['name']) && $data['user_data']['name']!='' && isset($data['user_data']['surname']) && $data['user_data']['surname']!='') {
			//$out['d']['user_form']['inputs_hidden']['']
		}

		//address?
		if (isset($data['user_data']['phone']) && $data['user_data']['phone']!='') {
			$out['d']['user_form']['inputs_hidden']['phone']=str_replace(array("+","-"," ","(",")"),"",$data['user_data']['phone']);
		}

		//var_dump($out); die("\n");
		return $out;
	}

	function submit($p,$config,$_db=false) {
		if ($_db!==false) $this->udb=$_db;
		$p=$p['data'];
		//var_dump($config,$p);
		//die("123\n");
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		if (!isset($p['order_id'])) {
			$out['e'][]='wrong_data';
			return $out;
		}
		$sale_params=array();
		$sale_params['merchantID']           = $config['merchant_id'];
		$sale_params['orderReference']       = $this->transactionID($p['order_id']);
		$sale_params['orderAmount']          = number_format((float)$p['total'],2,'.','');
		$sale_params['orderCurrency']        = 'USD';		//todo recheck it in future! must be $config['currency']
		$sale_params['orderDescription']     = $p['description'];
		$sale_params['customerName']         = $p['full_name'];
		$sale_params['customerEmail']        = $p['email'];
		$sale_params['customerPhoneNumber']  = $p['phone'];
		$sale_params['customerIP']           = $_SERVER['REMOTE_ADDR'];
		$sale_params['customerCountry']      = $p['country'];
		if (isset($p['country_state'])) { $sale_params['customerState'] = substr($p['country_state'],strlen($p['country'])+1); }
		$sale_params['customerCity']         = $p['city'];
		$sale_params['customerAddress']      = $p['address'];
		$sale_params['customerZipCode']      = $p['zip'];
		$sale_params['cardType']             = $p['card_type'];
		$sale_params['cardNumber']           = str_replace(array("-"," "),"",$p['card_number']);
		$sale_params['cardExpiration']       = $p['card_expiration_month'].$p['card_expiration_year'];		// todo recheck for int values ex. 00 -> 0 - bad
		$sale_params['cardVerificationCode'] = $p['card_vc'];
		$sale_params['returnUrl']            = $config['return_url']."paytechnique/".urlencode($sale_params['orderReference']);
		if (isset($p['subscription']) && $p['subscription']==1) {
			$sale_params['initRecurring']="YES";
		}
		//if (strlen($sale_params['customerState'])==0) $sale_params['customerState']="none";
		var_dump($sale_params);
		$sale_params['signature'] = $this->_sign($sale_params,$config['password']);
		$_cache_data=array(
					"payee_account"=>substr($sale_params['cardNumber'],0,4)."*".substr($sale_params['cardNumber'],-4),
					"total"=>$sale_params['orderAmount'],
					"total_currency"=>$sale_params['orderCurrency'],
					"order_id"=>$p['order_id'],
					"gateway_invoice"=>"",
					"gateway_transaction"=>$sale_params['orderReference'],
					"gateway_user_account"=>$sale_params['customerEmail'],
					"gateway_user_id"=>$sale_params['customerPhoneNumber'],
					"gateway_sign"=>$sale_params['signature'],
					"gateway_dt_unix"=>time(),
					"gateway_dt_raw"=>time(),
					"description"=>$sale_params['orderDescription']
				);
		if ($p['subscription_period']) {
			$_cache_data['subscription_period']=$p['subscription_period'];
		}
		$this->_cache($sale_params['orderReference'],$_cache_data,$config);
		// prepare signature
		$soap_client = new SoapClient($config['WSDL']);
		try {
			$sale_response = $soap_client->sale($sale_params);
			print "---SALES---";
			var_export($sale_response);
			//var_dump($sale_response->transactionStatus,$sale_response->transactionType);
			//var_export($sale_response['transactionType'],$sale_response['transactionStatus']);
			if (in_array($sale_response->transactionType,array('AUTH','SALE','REBILL')) && in_array($sale_response->transactionStatus, array('ONHOLD', 'SUCCESS'))) {
				if ($sale_params['initRecurring']=="YES" && $config['scheduled_recurring']===true) {
					$schedule_params=array();
					$schedule_params['merchantID']             = $config['merchant_id'];
					$schedule_params['orderAmount']            = $sale_params['orderAmount'];
					$schedule_params['orderDescription']       = $sale_params['orderDescription'];
					$schedule_params['originalOrderReference'] = $sale_params['orderReference'];
					$schedule_params['recurringToken']         = $sale_response->recurringToken;
					$schedule_params['scheduleCount']          = 0;
					if (isset($p['subscription_period']) && in_array($p['subscription_period'],array("day","month","year"))) {
						if ($p['subscription_period']=="day") {
							$schedule_params['schedulePeriod'] = 1;
						} else if ($p['subscription_period']=="month") {
							$schedule_params['schedulePeriod'] = 30;	// not good
						} else {
							$schedule_params['schedulePeriod'] = 365;
						}
					}
					$_cache_data['subscription_id']=$sale_response->recurringToken;
					$schedule_params['signature'] = $this->_sign($schedule_params,$config['password']);
					print "\n---SCHEDULE params---\n";
					var_dump($schedule_params,$_cache_data);
					$this->_cache($schedule_params['originalOrderReference'],$_cache_data,$config);
					try {
						$schedule_response = $soap_client->schedule($schedule_params);
						print "\n---SCHEDULE respose---\n";
						var_export($schedule_response);
						if (in_array($schedule_response->transactionType,array('SCHEDULE')) && in_array($schedule_response->transactionStatus, array('SUCCESS'))) {
							$out['ok']="ok";
							$out['d']=$this->_cache($sale_params['orderReference'],false,$config,false);
						} else {
							$out['e'][]="subscription_error";	// todo
							if (isset($schedule_response->transactionError)) {
								$out['e'][]=$schedule_response->transactionError;	//todo
							} else {
								$out['e'][]="subscription_error";
							}
						}
					} catch (SoapFault $f) {	// WTF??? for production? 
						echo "SoapFault:\n";
						echo $f->getMessage();
						echo "\n";
						if (isset($f->detail) && $f->detail=='204 Order already exists') {
							$out['e'][]="order_error";
						} else if ($f->getMessage()=='400 Bad Request') {
							$out['e'][]="form_error";
							//$out['e'][]="form_error2";
						}
						if(isset($f->detail)) {
							echo $f->detail;
							$out['e'][]=$f->detail;	// todo
						}
					}
				/*} else if ($sale_params['initRecurring']=="YES" && $config['scheduled_recurring']===false) {
					$_cache_data['subscription_id']=$sale_response->recurringToken;
					$this->_cache($sale_params['orderReference'],$_cache_data,$config);
					//$_rebill=array();
					//$this->rebill($p,$config);
					$out['ok']="ok";
					$out['d']=$this->_cache($sale_params['orderReference'],false,$config,true);*/
				} else {
					$out['ok']="ok";
					$out['d']=$this->_cache($sale_params['orderReference'],false,$config,true);
					if (isset($sale_response->recurringToken)) $out['d']['subscription_id']=$sale_response->recurringToken;
				}
				//$out['dbg']=array($sale_params['orderReference']);
			} else if ($sale_response->transactionStatus=='VERIFY') {
				$out['ok']="ok";
				parse_str($sale_response->verificationParams, $verification_params);
				$out['d']=array("do"=>'redirect',"type"=>"3ds".(empty($sale_response->verificationParams)?'_get':'_post'),"url"=>$sale_response->verificationUrl,"params"=>$verification_params);
			} else if ($sale_response->transactionStatus=='FAIL') {
				$out['e'][]=$sale_response->transactionError;	//todo
			}
		} catch (SoapFault $f) {	// WTF??? for production? 
			echo "SoapFault:\n";
			echo $f->getMessage();
			echo "\n";
			if (isset($f->detail) && $f->detail=='204 Order already exists') {
				$out['e'][]="order_error";
			} else if ($f->getMessage()=='400 Bad Request') {
				$out['e'][]="form_error";
				//$out['e'][]="form_error3";
			}
			if(isset($f->detail)) {
				echo $f->detail;
				$out['e'][]=$f->detail;	// todo
			}
		}
		var_dump($out);
		//die("\n");
		return $out;
	}

	function result ($p,$config,$_db=false) {
		if ($_db!==false) $this->udb=$_db;
		$p=$p['data'];
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		print "\n--- PT result---\n";
		var_dump($p);
		//$_order_data=$this->_cache($sale_params['orderReference'],false,$config,true);
		//$_order_data=$this->_cache($p['orderReference'],false,$config,false);
		$signature=$p['signature']; unset($p['signature']);
		$signature_check=$this->_sign($p,$config['password']);
		var_dump($signature_check,$signature);
		if ($signature_check==$signature) {
				
			if (in_array($p['transactionType'],array('SALE','REBILL')) && in_array($p['transactionStatus'], array('SUCCESS'))) {
				if (isset($p['recurringToken']) && $config['scheduled_recurring']===true) {
				// bad copypaste! TODO
					$soap_client = new SoapClient($config['WSDL']);
					$tmp=$this->_cache($p['orderReference'],false,$config,false);
					$schedule_params=array();
					$schedule_params['merchantID']             = $config['merchant_id'];
					$schedule_params['orderAmount']            = $tmp['total'];
					$schedule_params['orderDescription']       = $tmp['description'];
					$schedule_params['originalOrderReference'] = $tmp['gateway_transaction']; //$tmp['order_id'];
					$schedule_params['recurringToken']         = $p['recurringToken'];
					$schedule_params['scheduleCount']          = 0;
					if (isset($tmp['subscription_period']) && in_array($tmp['subscription_period'],array("day","month","year"))) {
						if ($tmp['subscription_period']=="day") {
							$schedule_params['schedulePeriod'] = 1;
						} else if ($tmp['subscription_period']=="month") {
							$schedule_params['schedulePeriod'] = 30;	// too bad
						} else {
							$schedule_params['schedulePeriod'] = 365;
						}
					}
					$tmp['subscription_id']=$p['recurringToken'];
					$schedule_params['signature'] = $this->_sign($schedule_params,$config['password']);
					print "\n---SCHEDULEinresult params---\n";
					var_dump($schedule_params,$tmp);
					$this->_cache($p['orderReference'],$tmp,$config);
					try {
						$schedule_response = $soap_client->schedule($schedule_params);
						print "\n---SCHEDULEinresult respose---\n";
						var_export($schedule_response);
						if (in_array($schedule_response->transactionType,array('SCHEDULE')) && in_array($schedule_response->transactionStatus, array('SUCCESS'))) {
							$out['ok']="ok";
							$out['d']=$this->_cache($schedule_params['originalOrderReference'],false,$config,true);
							$out['d']['special_result']="ACCEPTED";
						} else {
							$out['e'][]="subscription_error";	// todo
							if (isset($schedule_response->transactionError)) {
								$out['e'][]=$schedule_response->transactionError;	//todo
							} else {
								$out['e'][]="subscription_error";
							}
						}
					} catch (SoapFault $f) {	// WTF??? for production? 
						echo "SoapFault:\n";
						echo $f->getMessage();
						echo "\n";
						if (isset($f->detail) && $f->detail=='204 Order already exists') {
							$out['e'][]="order_error";
						} else if ($f->getMessage()=='400 Bad Request') {
							$out['e'][]="form_error";
							//$out['e'][]="form_error4";
						}
						if(isset($f->detail)) {
							echo $f->detail;
							$out['e'][]=$f->detail;	// todo
						}
					}
				/*} else if (isset($p['recurringToken']) && $config['scheduled_recurring']==false) {
					//$tmp=$this->_cache($p['orderReference'],false,$config,false);
					//$tmp['subscription_id']=$p['recurringToken'];
					//$this->_cache($p['orderReference'],$_cache_data,$config);
					//$_rebill=array();
					//$this->rebill($p,$config);
					$out['ok']="ok";
					$out['d']=$this->_cache($p['orderReference'],false,$config,true);
					$out['d']['subscription_id']=$p['recurringToken'];
					$out['dbg']='ok1';
					//todo*/
				} else if ($p['transactionType']=='REBILL') {
					$out['ok']="ok";
					$p['order_id']=explode("-",$p['originalOrderReference']); $p['order_id']=$p['order_id'][0];
					$out['d']=$p;
					$out['d']['special_result']="ACCEPTED";
				} else {
					$out['ok']="ok";
					$out['d']=$this->_cache($p['orderReference'],false,$config,true);
					if (isset($p['recurringToken'])) $out['d']['subscription_id']=$p['recurringToken'];
					$out['d']['special_result']="ACCEPTED";
					//$out['dbg']="ok2[".$p['recurringToken']."][".($config['scheduled_recurring']?'ok':'no')."]";
				}
			} else if ($p['transactionStatus']=='FAIL') {
				$out['e'][]="uknown_error";
				$out['d']['special_result']="FAIL: Invalid callback data";
			}
		} else {
			$out['ok']='error';
			$out['d']['special_result']="FAIL: Invalid signature";
			$out['e'][]="wrong_sign";
		}
		print "\n--- PT result OUT---\n";
		var_dump($out);
		return $out;
	}

	function rebill ($p,$config,$_db=false) {
		if ($_db!==false) $this->udb=$_db;
		$p=$p['data'];
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		if (!isset($p['order_id'])) {
			$out['e'][]='wrong_data';
			return $out;
		}
		/*
		    $p=array(
			"order_id",
			"total"
			"total_currency"
			"subscription_order_id"
			"subscription_id"
			"description"
		    );
		*/
		$sale_params=array();
		$sale_params['merchantID']             = $config['merchant_id'];
		$sale_params['orderReference']         = $this->transactionID($p['order_id']);
		//$sale_params['orderReference']         = $p['order_id'];
		$sale_params['orderAmount']            = number_format((float)$p['total'],2,'.','');
		$sale_params['originalOrderReference'] = $this->rebillTransactionID($p['subscription_order_id']);	// orderID by transactionID
		$sale_params['orderDescription']       = $p['description'];
		$sale_params['recurringToken']         = $p['subscription_id'];
		$sale_params['signature'] = $this->_sign($sale_params,$config['password']);
		$_cache_data=array(
					"payee_account"=>"",
					"total"=>$sale_params['orderAmount'],
					"total_currency"=>$p['total_currency'],
					"order_id"=>$p['order_id'],
					"gateway_invoice"=>"",
					"gateway_transaction"=>$sale_params['orderReference'],
					"gateway_user_account"=>"",
					"gateway_user_id"=>"",
					"gateway_sign"=>$sale_params['signature'],
					"gateway_dt_unix"=>time(),
					"gateway_dt_raw"=>time(),
					"description"=>$sale_params['orderDescription']
				);
		if ($p['subscription_id']) {
			$_cache_data['subscription_id']=$p['subscription_id'];
		}
		$this->_cache($sale_params['orderReference'],$_cache_data,$config);
		$soap_client = new SoapClient($config['WSDL']);
		//print "--REBILL init--";
		//var_dump($sale_params);
		try {
			$sale_response = $soap_client->rebill($sale_params);
			if (in_array($sale_response->transactionType,array('AUTH','SALE','REBILL')) && in_array($sale_response->transactionStatus, array('ONHOLD', 'SUCCESS'))) {
				$out['ok']="ok";
				$out['d']=$this->_cache($sale_params['orderReference'],false,$config,true);
				$out['d']['special_result']="ACCEPTED";
				$out['d']['_req_internal_processing']=true;		// do job in subscriptions for add payments
			} else if ($sale_response->transactionStatus=='FAIL') {
				$out['e'][]=$sale_response->transactionError;	//todo
			}
		} catch (SoapFault $f) {	// WTF??? for production? 
			echo "SoapFault:\n";
			echo $f->getMessage();
			echo "\n";
			if ($f->getMessage()=='400 Bad Request') {
				$out['e'][]="rebill_error";
			}
			if(isset($f->detail)) {
				echo $f->detail;
				$out['e'][]=$f->detail;	// todo
			}
		}
		//print "\n--- REBILL result OUT---\n";
		//var_dump($out);
		return $out;
	}

	function fail ($p,$config,$_db=false) {
		if ($_db!==false) $this->udb=$_db;
		//todo
	}

	function success ($p,$config,$_db=false) {
		if ($_db!==false) $this->udb=$_db;
		//todo
	}

	private function transactionID($order_id) {
		/*$tmp=explode("-",$order_id);
		if (strstr($order_id,"-") && is_numeric($tmp) && is_numeric($tmp)) {
			return $order_id;
		} else {*/
			$l=10;
			$_id=$this->_uniqueID();
			$sql="INSERT INTO `uniteapi_payments_transactions_paytechnique` SET `created_ts`=".time().", `transaction_id`='".$this->udb->real_escape_string($_id)."', `order_id`='".$this->udb->real_escape_string($order_id)."'";
			while ($this->udb->query($sql)===false && $l>0) {
				$_id=$this->_uniqueID();
				$sql="INSERT INTO `uniteapi_payments_transactions_paytechnique` SET `created_ts`=".time().", `transaction_id`='".$this->udb->real_escape_string($_id)."', `order_id`='".$this->udb->real_escape_string($order_id)."'";
				$l--;
			}
			return $_id;
		//}
	}


	private function rebillTransactionID($order_id) {
		$check=$this->udb->query("SELECT `transaction_id` FROM `uniteapi_payments_transactions_paytechnique` WHERE `order_id`='".$this->udb->real_escape_string($order_id)."' ORDER BY `updated_ts` DESC LIMIT 1");
		if ($check && $check->num_rows>0) {
			return $check->fetch_object()->transaction_id;
		} else if ($order_id!='') {
			return $order_id;
		} else {
			return false;
		}
	}

	private function orderID($transaction_id) {
		$check=$this->udb->query("SELECT `order_id` FROM `uniteapi_payments_transactions_paytechnique` WHERE `transaction_id`='".$this->udb->real_escape_string($transaction_id)."'");
		if ($check->num_rows>0) {
			return $check->fetch_object()->order_id;
		} else {
			return false;
		}
	}

	private function _uniqueID() {
		$_l=16;
		$_max=strlen($this->key_chars)-1;
		mt_srand((double)microtime()*1000000);
		$_r="";
		while ($_l>0) {
			$_r.=$this->key_chars{mt_rand(0,$_max)};
			$_l--;
		}
		return $_r;
	}

	private function _cache($id,$p=false,$config,$remove=false) {
		if ($p!==false) {
			@unlink($config['cache_dir'].md5($id));
			return @file_put_contents($config['cache_dir'].md5($id),json_encode($p,JSON_UNESCAPED_UNICODE));
		} else {
			$out=json_decode(file_get_contents($config['cache_dir'].md5($id)),true);
			if ($remove) @unlink($config['cache_dir'].md5($id));
			return $out;
		}
	}

	private function _sign($p,$_sign) {
		ksort($p);
		$p = md5(implode('', $p) . $_sign);
		return $p;
	}
}
?>
