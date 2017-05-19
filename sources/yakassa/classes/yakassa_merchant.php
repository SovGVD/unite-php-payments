<?php
/*
	standalone yandex money/kassa merchant class with repeated payments (rebill)
	-----------------------------------------------------------------------------
	sovgvd@gmail.com 2016
	
	based on 
	https://github.com/yandex-money/yandex-money-kassa-example/blob/master/src/YaMoneyCommonHttpProtocol.php
	9c0d654eb9e78569d94e1c2b0a875a573cc4454b @mckeydonelly mckeydonelly committed on Feb 24
*/


class yakassa_merchant {

	private function _URL_payment($test=false) {
		if ($test===false) {
			return "https://money.yandex.ru/eshop.xml";
		} else {
			return "https://demomoney.yandex.ru/eshop.xml";
		}
	}

	private function _URL_mws($test=false) {
		if ($test===false) {
			return "https://penelope.yamoney.ru/webservice/mws/api/";	// TODO
		} else {
			return "https://penelope-demo.yamoney.ru/webservice/mws/api/";	// TODO
		}
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


	function make ($data,$config) {
		// data must be okay by default
		$out=array(
			"ok"=>"ok",
			"d"=>false,
			"e"=>array()
		);
		//var_dump($config['currency_convert']);
		// TODO shopSuccessURL and others
		$out['d']=array(
				"form"=>array(
					"form"=>array("method"=>"POST", "action"=>$this->_URL_payment($config['test'])),
					"inputs_hidden"=>array(
						"shopDefaultUrl"=>"https://sovgvd.info/",	// TODO
						"shopId"=>$config['shopId'],
						"scid"=>$config['scid'],
						"sum"=>bcmul($data['total'],$config['currency_convert']['usd2rub'],2),		// RUB ONLY!!!
						"customerNumber"=>$data['user_data']['d']['login'],
						"orderNumber"=>$data['order_id'],
						"cps_email"=>$data['user_data']['d']['email'],
						"paymentType"=>($data['subscription']==1)?"AC":"",
						"rebillingOn"=>($data['subscription']==1)?"true":"false",
						"extra_cur"=>"usd2rub",
						"extra_sub"=>$data['subscription_period'],
						"extra_s"=>md5($data['order_id']."|".$data['subscription_period']."|".$config['shopId']."|".$config['extra_salt'])
					)
				)
			);
		$this->_cache($data['order_id'],array(
				"order_id"=>$data['order_id'],
				"sum"=>$out['d']['form']['inputs_hidden']['sum'],
				"raw_sum"=>$data['total'],
				"convert"=>$config['currency_convert']['usd2rub'],
				"convert_type"=>$out['d']['form']['inputs_hidden']['extra_cur'],
				"sign"=>$out['d']['form']['inputs_hidden']['extra_s']
				), 
				$config
			);
		return $out;
	}

	function rebill ($data,$config,$_db=false) {
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		$data=$data['data'];
		if (isset($data['order_id']) && isset($data['subscription_id']) && isset($data['total'])) {
			$requestParams = array(
				'clientOrderId' => $data['subscription_order_id']."-".time(),
				'invoiceId' => $data['subscription_id'],
				'amount' => bcmul($data['total'],$config['currency_convert']['usd2rub'],2),
				'orderNumber' => $data['order_id']
			);
			$this->_cache("S".$data['order_id'].$data['subscription_id'],array(
				"raw_sum"=>$data['total'],
				"sign"=>md5($data['order_id']."|".$data['subscription_id']."|".$config['shopId']."|".$config['extra_salt'])
				), 
				$config
			);
			$out = $this->_sendRequest("repeatCardPayment", http_build_query($requestParams),"x-www-form-urlencoded",$config);
			if ($out['ok']!='ok') {
				$_tmp_check=$this->_cache("S".$data['order_id'].$data['subscription_id'],false,$config,true);	// remove cache
			}

		} else {
			$out['ok']='error';
			$out['e'][]="wrong_rebill_data";
			$out['dgb']=$data;
		}
		return $out;
	}

	function result ($p,$config) {
		$p=$p['data'];
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		$_sign_txt=false;
		if ($config['security_type']=='PKCS7') {
			$_sign_txt=$p;
			$tmp=$this->_sign($p,$config);
			if ($tmp===false) {
				$out['ok']='error';
				$out['e'][]="wrong_sign";
			} else {
				$p=$tmp;
			}
		} else if ($this->_sign($p,$config)===false) {
			$out['ok']='error';
			$out['e'][]="wrong_sign";
		}
		$_force_sum=false;
		if ($p['action']=='paymentAviso' || $p['action']=='checkOrder') {
			if (!isset($p['baseInvoiceId'])) {
				// recheck ordinary payment and set RAW payment value
				$_tmp_check=$this->_cache($p['orderNumber'],false,$config,(($p['action']=='paymentAviso')?true:false));
				if (isset($p['extra_s']) && $p['extra_s']==$_tmp_check['sign']) {
					$_force_sum=$_tmp_check['raw_sum'];
				} else {
					$out['ok']='error';
					$out['e'][]="wrong_internal_sign";
				}
			} else {
				// subscription payment
				$_tmp_check=$this->_cache("S".$p['orderNumber'].$p['baseInvoiceId'],false,$config,(($p['action']=='paymentAviso')?true:false));
				if ($_tmp_check['sign']==md5($p['orderNumber']."|".$p['baseInvoiceId']."|".$config['shopId']."|".$config['extra_salt'])) {
					$_force_sum=$_tmp_check['raw_sum'];
				} else {
					$out['ok']='error';
					$out['e'][]="wrong_internal_sign_subscription";
				}
			}
		}
		if (count($out['e'])==0) {
			$out['ok']='ok';
			$out['d']=array(
					"payee_account"=>$p['paymentPayerCode'],
					"total"=>$_force_sum?$_force_sum:bcdiv($p['orderSumAmount'],$config['currency_convert']['usd2rub'],2),		// OR shopSumAmount?
					"total_currency"=>"USD",
					"order_id"=>$p['orderNumber'],
					"gateway_invoice"=>$p['invoiceId'],
					"gateway_transaction"=>$p['paymentType'].";".$p['orderSumBankPaycash'].";".$p['shopSumBankPaycash'],
					"gateway_user_account"=>isset($p['paymentPayerCode'])?$p['paymentPayerCode']:'',
					"gateway_user_id"=>"",
					"gateway_sign"=>(($_sign_txt!==false)?$_sign_txt:$p['md5']),
					"gateway_dt_unix"=>strtotime($p['requestDatetime']),
					"gateway_dt_raw"=>$p['requestDatetime'],
					"description"=>"extra[".(isset($p['extra_cur'])?$p['extra_cur']:'')."][".(isset($p['extra_sub'])?$p['extra_sub']:'')."][".(isset($p['extra_s'])?$p['extra_s']:'')."]fs=[".$_force_sum."]"
				);
				if ($p['rebillingOn']=="true") {	// string for yandex
					$out['d']["subscription_period"]=$p['extra_sub'];
					$out['d']["subscription_id"]=$p['invoiceId'];
				}
			$out['d']['special_result']=$this->_special_result($p,$config,0);	// 0 - success code
		} else {
			if ($config['security_type']=='MD5') {
				$out['d']=array();
				$out['d']['special_result']=$this->_special_result($p,$config,1,$msg="Wrong order");
			} else {
				$out['d']=array();
				$out['d']['special_result']=$this->_special_result($p,$config,200,$msg="Security error");
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

	private function _special_result($p,$config,$result_code=100,$message=false) {
		$performedDatetime = date("Y-m-d") . "T" . date("H:i:s") . ".000" . date("P");
		$response = '<?xml version="1.0" encoding="UTF-8"?><' . $p['action'] . 'Response performedDatetime="' . $performedDatetime .
		'" code="' . $result_code . '" ' . ($message !== false ? 'message="' . $message . '"' : "") . ' invoiceId="' . $p['invoiceId'] . '" shopId="' . $config['shopId'] . '"/>';
		return $response;
	}

	private function _sign($p,$config) {
		if ($config['security_type']=='MD5' && $this->_sign_MD5($p,$config)) {
			return true;
		} else if ($config['security_type']=='PKCS7') {
			$p = $this->_sign_PKCS7($p,$config);
			if ($p != false) {
				return $p;
			}
		}
		return false;
	}

	private function _sign_MD5($request,$config) {
		$str = $request['action'] . ";" .
		$request['orderSumAmount'] . ";" . $request['orderSumCurrencyPaycash'] . ";" .
		$request['orderSumBankPaycash'] . ";" . $request['shopId'] . ";" .
		$request['invoiceId'] . ";" . $request['customerNumber'] . ";" . 
		$config['shopPassword'];
		$md5 = strtoupper(md5($str));
		if ($md5 != strtoupper($request['md5'])) {
			return false;
		}
		return true;
	}

    private function _signData($data,$config) {
	//var_dump($config); die("\n");
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
        );
        $descriptorspec[2] = $descriptorspec[1];
        try {
            $opensslCommand = $config['openssl_path'].' smime -sign -signer ' . $config['cert_path'] .
                ' -inkey ' . $config['private_key_path'] .
                ' -nochain -nocerts -outform PEM -nodetach -passin pass:'.$config['cert_password'];
            //$this->log->info("opensslCommand: " . $opensslCommand);
            $process = proc_open($opensslCommand, $descriptorspec, $pipes);
            if (is_resource($process)) {
                fwrite($pipes[0], $data);
                fclose($pipes[0]);
                $pkcs7 = stream_get_contents($pipes[1]);
                //$this->log->info($pkcs7);
                fclose($pipes[1]);
                $resCode = proc_close($process);
                if ($resCode != 0) {
                    $errorMsg = 'OpenSSL call failed:' . $resCode . '\n' . $pkcs7;
		    var_dump($errorMsg);
                    //$this->log->info($errorMsg);
                    throw new \Exception($errorMsg);
                }
                return $pkcs7;
            }
        } catch (\Exception $e) {
            //$this->log->info($e);
	    var_dump($e);
            throw $e;
        }
    }

	private function _sign_PKCS7($p,$config) {
		$descriptorspec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
		$certificate = $config['cert_path'];
		$process = proc_open($config['openssl_path'].' smime -verify -inform PEM -nointern -certfile ' . $certificate . ' -CAfile ' . $certificate, $descriptorspec, $pipes);
		if (is_resource($process)) {
			// Getting data from request body.
			$data = file_get_contents($p); // "php://input"
			fwrite($pipes[0], $data);
			fclose($pipes[0]);
			$content = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			$resCode = proc_close($process);
			if ($resCode != 0) {
				return false;
			} else {
				$xml = simplexml_load_string($content);
				$array = json_decode(json_encode($xml), TRUE);
				return $array["@attributes"];
			}
		}
		return false;
	}


    // https://github.com/yandex-money/yandex-money-kassa-example/commit/49d4c50049967b294edd2ded9b7c2a230d7e87f5

	function returnPayment($invoiceId, $amount, $config) {
        $methodName = "returnPayment";
		$performedDatetime = date("Y-m-d") . "T" . date("H:i:s") . ".000" . date("P");
        $requestParams = array(
            'clientOrderId' => mktime(),
            'requestDT' => $performedDatetime,
            'invoiceId' => $invoiceId,
            'shopId' => $config['shopId'],
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 643,
            'cause' => 'Возврат средств'
        );
        $result = $this->sendXmlRequest($methodName, $requestParams,$config);
	//$result = json_decode(json_encode(simplexml_load_string($result_raw)),true); $result=$result['@attributes'];

	$out=array(
	    "ok"=>'error',
	    "d"=>array(),
	    "e"=>array(),
	    "e_dbg"=>false
	);

	$out['e_dbg']=$result;
	$out['raw']=$result['dbg'];
	if ($result['d']['error']=='0') {
		$out['ok']='ok';
		$out['d']=array(
			"return_id"=>$result['d']['clientOrderId'],
			"gateway_dt_raw"=>$result['d']['processedDT'],
			"gateway_dt_unix"=>strtotime($result['d']['processedDT']),
		);
	} else {
		$out['e'][]='return_payment_error';
		$out['e'][]='raw_msg['.json_encode($result,JSON_UNESCAPED_UNICODE).']';
	}
        return $out;
    }

    private function sendXmlRequest($paymentMethod, $data,$config) {
        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<' . $paymentMethod . 'Request ';
        foreach($data AS $param => $value) {
            $body .= $param . '="' . $value . '" ';
        }
        $body .= '/>';
        return $this->_sendRequest($paymentMethod, $this->_signData($body,$config), "pkcs7-mime",$config);
    }

	/**
	* Sends prepared request.
	 * @param  string $paymentMethod financial method name
	 * @param  string $requestBody   prepared request body
	 * @param  string $contentType   HTTP Content-Type header value
	 * @return string                response from Yandex.Money in XML format
	 */
	private function _sendRequest($paymentMethod, $requestBody, $contentType,$config=array()) {
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		$curl = curl_init();
		$params = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_HTTPHEADER => array('Content-type: application/' . $contentType),
			CURLOPT_URL => $this->_URL_mws($config['test']) . $paymentMethod,
			CURLOPT_POST => 0,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSLCERT => $config['cert_path'],
			CURLOPT_SSLKEY => $config['private_key_path'],
			CURLOPT_SSLCERTPASSWD => $config['cert_password'],
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_VERBOSE => 1,
			CURLOPT_INTERFACE => '1.2.3.4',		// TODO from config
			CURLOPT_POSTFIELDS => $requestBody
		);
		curl_setopt_array($curl, $params);
		$result = null;
		try {
			$result = curl_exec($curl);
			if (!$result) {
				$out['e'][]="internal_error";
				$out['e_dbg']=curl_error($curl);
			} else {
				// TODO VERY IMPORTANT - check status (error if !=0) https://tech.yandex.ru/money/doc/payment-solution/payment-management/payment-management-financial-repeat-card-payment-docpage/
				// TODO errors hander for all codes
				$array = json_decode(json_encode(simplexml_load_string($result)), TRUE);
				$parsed_result=$array["@attributes"];
				$out['dbg']=$result;
				if ($parsed_result['status']==0) {
					$out['ok']='ok';
					$out['d']=$parsed_result;
				} else {
					$out['e'][]= "gw_error";
					$out['e_dbg']=$parsed_result;
				}
			}
			curl_close($curl);
		} catch (HttpException $ex) {
			$out['e'][]= "request_error";
		}
		return $out;
	}

}
?>
