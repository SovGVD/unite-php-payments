<?php
/*
	coinbase wrapper
	----------------------------------
	sovgvd@gmail.com 2015

*/


class coinbase_merchant {

	function make ($data,$config) {
		// data must be okay by default
		$out=array(
			"ok"=>"ok",
			"d"=>false,
			"e"=>array()
		);

		if ($config['test']==true) {
			$api_url="https://api.sandbox.coinbase.com/v1/";
			$checkout_url="https://sandbox.coinbase.com/checkouts/";
		} else {
			$api_url="https://api.coinbase.com/v1/";
			$checkout_url="https://coinbase.com/checkouts/";
		}

		$req=array(
			"button"=>array(
					"name"=>$data['order_id'],
					"type"=>"buy_now",
					"subscription"=>false,
					"price_string"=>$data['total'],
					//"price_currency_iso"=>$data['currency'],	//TODO
					"price_currency_iso"=>"USD",
					"custom"=>$data['order_id'],
					"callback_url"=>$config['result_url'],
					"success_url"=>$config['success_url'],
					"cancel_url"=>$config['fail_url'],
					"description"=>$data['description'],
					"style"=>"none",
					"custom_secure"=>true,
					"auto_redirect"=>true,
					"variable_price"=>false,
					"choose_price"=>false
				)
		);
		$req_http=urldecode(http_build_query($req,"","&",PHP_QUERY_RFC1738));

		$ch = curl_init();
		$nonce = sprintf('%0.0f',round(microtime(true) * 1000000));
		curl_setopt_array($ch, array(
			CURLOPT_URL => $api_url."buttons",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array(
				"ACCESS_KEY: " . $config['API_KEY'],
				"ACCESS_NONCE: " . $nonce,
				"ACCESS_SIGNATURE: " . hash_hmac("sha256", $nonce . $api_url . "buttons".$req_http, $config['API_SECRET'])
			),
			CURLOPT_POSTFIELDS => $req_http,
			CURLOPT_POST => true
		));
		$raw_result=curl_exec($ch);
		curl_close($ch);

		$result = json_decode($raw_result);
		//var_dump($result);
		if (isset($result->success) && $result->success===true) {
			$out['ok']='ok';
			$out['d']=array("url"=>array("pay_url"=>$checkout_url.$result->button->code));
		} else {
			$out['ok']='error';
			$out['e'][]=$raw_result;	// TODO
		}
		return $out;
	}

	function result ($p,$config) {
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);
		
		if ($this->_verifyCallback($p['data'], $p['signature'],$config)) {
			$p=json_decode($p['data']);
			if ($p->order->status=='completed') {
				$out['ok']='ok';
				$out['d']=array(
					"payee_account"=>"",
					"total"=>$p->order->total_native->cents/100,	// todo for USD only
					"total_currency"=>$p->order->total_native->currency_iso,
					//"total"=>$p->order->total_payout->cents/100,	// todo for USD only
					//"total_currency"=>$p->order->total_payout->currency_iso,
					"order_id"=>$p->order->custom,
					"gateway_invoice"=>$p->order->id,
					"gateway_transaction"=>$p->order->transaction->id,
					"gateway_user_account"=>isset($p->customer)?$p->customer->email:'',
					"gateway_user_id"=>$p->order->receive_address,
					"gateway_sign"=>$p->order->transaction->hash,
					"gateway_dt_unix"=>strtotime($p->order->created_at),
					"gateway_dt_raw"=>$p->order->created_at,
					"description"=>$p->order->button->description
				);
			} else {
				$out['ok']='error';
				$out['e'][]='wrong_payment';
			}
		} else {
			$out['ok']='error';
			$out['e'][]='wrong_sign';
		}
		return $out;
	}

	function fail ($p,$config) {
		//todo
	}

	function success ($p,$config) {
		//todo
	}

	private function _verifyCallback($body, $signature, $config) {
		if ($config['test']==true) {
			$cert_url="https://sandbox.coinbase.com/coinbase.pub";
		} else {
			$cert_url="https://www.coinbase.com/coinbase.pub";
		}
		$cert = file_get_contents($cert_url);
		$pubkeyid = openssl_pkey_get_public($cert);
		$signature_buffer = base64_decode( $signature );
		$result=openssl_verify($body, $signature_buffer, $pubkeyid, OPENSSL_ALGO_SHA256);
		openssl_free_key($pubkeyid);
		return (1 == $result);
	}

}
?>