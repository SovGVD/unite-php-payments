<?php
/*
	php sdk wrapper for unite php api payments
	------------------------------------------
	sovgvd@gmail.com 2015

*/

require __DIR__."/../SDK/autoload.php";
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\InputFields;

use PayPal\Api\ExecutePayment;
use PayPal\Api\PaymentExecution;

use PayPal\Exception\PayPalConnectionException;


class paypal_merchant {

	function _init($config) {
		$apiContext = new ApiContext(new OAuthTokenCredential($config['clientId'],$config['clientSecret']));
		if ($config['test']===false) {
			$apiContext->setConfig(array(
				'mode' => 'live',
				'log.LogEnabled' => false,
				'cache.enabled' => false
				)
			);
		} else {
			$apiContext->setConfig(array(
				'mode' => 'sandbox',
				'log.LogEnabled' => true,
				'log.FileName' => '/tmp/PayPal.log',	//TODO
				'log.LogLevel' => 'DEBUG', // PLEASE USE `FINE` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
				'validation.level' => 'log',
				'cache.enabled' => false
				)
			);
		}

		if ($config['partner_id']!==false) $apiContext->addRequestHeader('PayPal-Partner-Attribution-Id', $config['partner_id']);
		return $apiContext;
	}

	function make ($data,$config) {
		// data must be okay by default
		$out=array(
			"ok"=>"ok",
			"d"=>false,
			"e"=>array()
		);

		$apiContext=$this->_init($config);

		$payer = new Payer();
		$payer->setPaymentMethod("paypal");
		
		$items=array(); $i=0;
		foreach ($data['items'] as $item) {
			$items[$i]=new Item();
			$items[$i]->setName($item['title'])->setCurrency($data['currency'])->setQuantity($item['qty'])->setSku($item['sku'])->setPrice($item['price']);
			$i++;
		}
		if (count($items)>0) {
			$itemList = new ItemList();
			$itemList->setItems($items);
		}
		$amount = new Amount();
		if (isset($data['shipping'])  && $data['tax'] && $data['subtotal']) {
			$details = new Details();
			$details->setShipping($data['shipping'])->setTax($data['tax'])->setSubtotal($data['subtotal']);
			$amount->setCurrency($data['currency'])->setTotal($data['total'])->setDetails($details);
		} else {
			//$inputFields = new InputFields();
			//$inputFields->setAllowNote(false)->setNoShipping(1)->setAddressOverride(0);
			$amount->setCurrency($data['currency'])->setTotal($data['total']);
		}

		$transaction = new Transaction();
		$transaction->setAmount($amount)->setDescription($data['description'])->setInvoiceNumber($data['order_id']);
		if (isset($itemList)) {
			$transaction->setItemList($itemList);
		}
		$redirectUrls = new RedirectUrls();
		$redirectUrls->setReturnUrl($config['result_url'])->setCancelUrl($config['fail_url']);

		$payment = new Payment();
		$payment->setIntent("sale")->setPayer($payer)->setRedirectUrls($redirectUrls)->setTransactions(array($transaction));

		try {
			$payment->create($apiContext);
			$out['d']=array("url"=>array("pay_url"=>$payment->getApprovalLink()));
		} catch (Exception $ex) {
			$out['ok']='error';
			$out['e'][]=$ex;
			//$out['dbg']=$request;
			return $out;
		}

		return $out;
	}

	function result ($p,$config) {
		$apiContext=$this->_init($config);
		$p=$p['data'];
		$out=array(
			"ok"=>"error",
			"d"=>false,
			"e"=>array()
		);

		$payment = Payment::get($p['paymentId'], $apiContext);
		$execution = new PaymentExecution();
		$execution->setPayerId($p['PayerID']);


		try {
			$result = $payment->execute($execution, $apiContext);
			$out['d']=array("pay_id"=>$payment->getId(), "ex"=>$execution, "r"=>$result);
			try {
				$payment = Payment::get($p['paymentId'], $apiContext);
				$p=json_decode($payment->toJSON());
				if ($p->state=='approved') {
					//var_dump($p);
					$out['ok']='ok';
					$out['d']=array(
					"payee_account"=>"",
					"total"=>$p->transactions[0]->amount->total,
					"total_currency"=>$p->transactions[0]->amount->currency,
					"order_id"=>$p->transactions[0]->invoice_number,
					"gateway_invoice"=>$p->transactions[0]->related_resources[0]->sale->id,
					"gateway_transaction"=>$p->id,
					"gateway_user_account"=>$p->payer->payer_info->email,
					"gateway_user_id"=>$p->payer->payer_info->payer_id,
					"gateway_sign"=>$p->id,
					"gateway_dt_unix"=>strtotime($p->update_time),
					"gateway_dt_raw"=>$p->update_time,
					"description"=>$p->transactions[0]->description
					);
				} else {
					$out['ok']='error';
					$out['e'][]="wrong_payment";
				}
			} catch (PayPalConnectionException $ex) {
				$out['ok']='error';
				$out['e'][]=array("raw"=>json_decode($ex->getData())->name);
				return $out;
			}
		} catch (PayPalConnectionException $ex) {
			$out['ok']='error';
			$out['e'][]=array("raw"=>json_decode($ex->getData())->name);
			return $out;
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
?>