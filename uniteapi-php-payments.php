<?php

/*
	uniteapi-php-payments
	---------------------
	sovgvd@gmail.com 2015
*/


class upayments {
	private $_script_path="";
	private $_form_lists=array();
	private $_gateways=array(
			"coinbase"=>array(
				"title"=>"Bitcoin/Coinbase",
				"active"=>true,
				"result_url"=>'_default',
				"success_url"=>'_default',
				"fail_url"=>'_default',
				"test"=>'_default',
				"currency"=>'_default',
				"API_KEY"=>"",
				"API_SECRET"=>"",
				"class"=>"coinbase_merchant",
				"sdk"=>"coinbase/SDK/autoload.php",
				"subscriptions_support"=>false,
				"include"=>"coinbase/classes/coinbase_merchant.php",
				"answer"=>array("data"=>"_RAWPOST","signature"=>"_SERVER.HTTP_X_SIGNATURE")
			),
			"paypal"=>array(
				"title"=>"PayPal",
				"active"=>true,
				"result_url"=>'_default',
				"success_url"=>'_default',
				"fail_url"=>'_default',
				"test"=>'_default',
				"currency"=>'_default',
				"clientId"=>"",
				"clientSecret"=>"",
				"partner_id"=>false,
				"class"=>"paypal_merchant",
				"sdk"=>"paypal/SDK/autoload.php",
				"subscriptions_support"=>false,
				"include"=>"paypal/classes/paypal_merchant.php",
				"answer"=>array("data"=>"_GET")
			),
			"paytechnique"=>array(
				"title"=>"Card",
				"active"=>true,
				"result_url"=>'_default',
				"success_url"=>'_default',
				"return_url"=>'_default',		// unknown result
				"fail_url"=>'_default',
				"test"=>'_default',
				"currency"=>'_default',
				"pregateway_url"=>'_default',
				"WSDL"=>'_default',
				"merchant_id"=>"",
				"password"=>"",
				"cache_dir"=>'_default',
				"scheduled_recurring"=>'_default',
				"class"=>"paytechnique_merchant",
				"subscriptions_support"=>true,
				"include"=>"paytechnique/classes/paytechnique_merchant.php",
				"answer"=>array("data"=>"_POST")
			),
			"webmoney"=>array(
				"title"=>"Webmoney",
				"active"=>true,
				"result_url"=>'_default',
				"success_url"=>'_default',
				"fail_url"=>'_default',
				"test"=>'0',
				"currency"=>'_default',
				"class"=>"webmoney_merchant",
				"wallet"=>"",
				"sign"=>"",
				"subscriptions_support"=>false,
				"include"=>"webmoney/classes/webmoney_merchant.php",
				"answer"=>array("data"=>"_POST")
			),
			"gcard"=>array(
				"merchantId"=>"",
				"merchantSign"=>"",
				"title"=>"GiftCard",
				"active"=>true,
				"cache_dir"=>'_default',
				"result_url"=>'_default',
				"success_url"=>'_default',
				"fail_url"=>'_default',
				"test"=>'0',
				"currency"=>'_default',
				"class"=>"gcard_merchant",
				"sign"=>"",
				"subscriptions_support"=>false,
				"include"=>"gcard/classes/gcard_merchant.php",
				"answer"=>array("data"=>"_POST"),
			),
			"yakassa"=>array(
				"security_type"=>"PKCS7",
				"extra_salt"=>"",
				"shopId"=>"",
				"scid"=>"",
				"cert_path"=>"",
				"private_key_path"=>"",
				"cert_password"=>"",
				"shopPassword"=>"",
				"openssl_path"=>"/usr/bin/openssl",
				"title"=>"YandexKassa",
				"currency_convert"=>array(),
				"active"=>false,
				"cache_dir"=>'_default',
				"result_url"=>'_default',
				"success_url"=>'_default',
				"fail_url"=>'_default',
				"test"=>false,
				"currency"=>'_default',
				"class"=>"yakassa_merchant",
				"subscriptions_support"=>true,
				"include"=>"yakassa/classes/yakassa_merchant.php",
				"answer"=>array("data"=>"_RAWORPOST")
			)
		);
	private $_out=array(
			"ok"=>"ok",
			"d"=>false,
			"e"=>array()
		);
	private $_defaults=array(
			"result_url"=>"http://localhost/payments/result/",
			"success_url"=>"http://localhost/payments/success/",
			"fail_url"=>"http://localhost/payments/fail/",
			"return_url"=>"http://localhost/payments/return/",
			"currency"=>"USD",
			"test"=>false		// TEST MODE
		);

	private $udb=false;
	function _clear_out() {
		$this->_out=array(
			"ok"=>"ok",
			"d"=>false,
			"e"=>array()
		);
	}
	function upayments ($init_data=false,$_db=false) {
		require_once("uniteapi-php-payments_vars.php");
		$this->_form_lists=$_include_form_lists;
		if (is_array($init_data)) {
			if (isset($init_data['script_path'])) $this->_script_path=$init_data['script_path'];
			if (isset($init_data['payments_results_url'])) {
				$this->_defaults['result_url']=$init_data['payments_results_url']."result/";
				$this->_defaults['success_url']=$init_data['payments_results_url']."success/";
				$this->_defaults['fail_url']=$init_data['payments_results_url']."fail/";
				$this->_defaults['return_url']=$init_data['payments_results_url']."return/";
			}
			if (isset($init_data['test']) && $init_data['test']===true) $this->_defaults['test']=$init_data['test'];
			if (isset($init_data['gateways'])) {
				foreach ($init_data['gateways'] as $gw=>$gw_settings) {
					if (isset($this->_gateways[$gw])) {
						foreach($gw_settings as $k=>$v) {
							if (isset($this->_gateways[$gw][$k])) $this->_gateways[$gw][$k]=$v;
						}
					}
				}
			}
			if ($_db!==false) $this->udb=$_db;
		}
		foreach($this->_gateways as $_gw=>$_data) {
			foreach ($_data as $k=>$v) {
				if ($k=='sdk') {
					$this->_gateways[$_gw][$k]=$this->_script_path."sources/".$this->_gateways[$_gw][$k];
				}
				if ($v==='_default') {
					$this->_gateways[$_gw][$k]=isset($this->_defaults[$k])?$this->_defaults[$k]:false;
					if (($k=='success_url' || $k=='result_url' || $k=='fail_url') && $this->_gateways[$_gw][$k]!==false) $this->_gateways[$_gw][$k].=$_gw."/";
				}
				if ($k=='currency_convert') {
					// fill currency data
					$_cur_tmp=array();
					foreach ($v as $_cur=>$_cur_val) {
						if ($_cur_val=='_fill') {
							$_cur_codes=explode("2",strtoupper($_cur));
							$_cur_val=$this->currency($_cur_codes[0],$_cur_codes[1]);
						}
						$_cur_tmp[$_cur]=$_cur_val;
					}
					$this->_gateways[$_gw][$k]=$_cur_tmp;
				}
			}
		}
	}

	function currency($cfrom,$cto,$v=false,$dt=false) {
		if ($v!==false && $dt!==false) {
			if ($this->udb->query("INSERT INTO `uniteapi_payments_currency_convert` SET `dt`='".$this->udb->real_escape_string($dt)."', `dt_ts`=".time().", `cfrom`='".$this->udb->real_escape_string($cfrom)."', `cto`='".$this->udb->real_escape_string($cto)."', `value`='".$this->udb->real_escape_string($v)."'")) {
				return true;
			}
		} else {
			$v=$this->udb->query("select value from uniteapi_payments_currency_convert where `cfrom`='".$this->udb->real_escape_string($cfrom)."' AND `cto`='".$this->udb->real_escape_string($cto)."' ORDER BY id desc limit 1");
			if ($v && $v->num_rows>0) {
				return $v->fetch_object()->value;
			}
		}
		return false;
	}

	function make ($gateway,$data=array()) {
		/*
			$data=array(
					"order_id"=>(int),		//req
					"description"=>(string),	//req
					"currency"=>'_default',		// default if is not set
					"items"=>array(			// at least one item req
							"item_id_0"=>array("sku"=>(int),"title"=>(string),"qty"=>1,"price"=>x.xx),
							....
						),
					"tax"=>0,
					"shipping"=>0,
					"subtotal"=>0,
					"total"=>x.xx			// req, more that zero
				);
		*/
		if ($this->is_gateway_active($gateway)) {
			if (isset($data['order_id']) && ((is_numeric($data['order_id']) && $data['order_id']>0) || $data['order_id']=='to_be_fill_after_reg') && $data['description']!='' && count($data['items'])>0 && isset($data['total']) && $data['total']>0) {
				if (!isset($data['currency']) || $data['currency']=='_default') $data['currency']=$this->_defaults['currency'];
				require_once ($this->_script_path."sources/".$this->_gateways[$gateway]['include']);
				$gw_classname=$this->_gateways[$gateway]['class'];
				$gw=new $gw_classname();
				$tmp=$gw->make($data,$this->_gateways[$gateway],$this->udb);
				if ($tmp['ok']=='ok') {
					$this->_result($tmp);
				} else {
					$this->_error('make_error',$tmp['e']);
				}
			} else {
				$this->_error("wrong_data",array("gateway"=>$gateway,"data"=>$data));
			}
		} else {
			if (!isset($this->_gateways[$gateway])) {
				$this->_error("wrong_gateway",array("gateway"=>$gateway,"data"=>$data));
			} else if ($this->_gateways[$gateway]['active']!==true) {
				//$this->_error("disabled_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway],"data"=>$data));
				$this->_error("disabled_gateway",array("gateway"=>$gateway,"data"=>$data));
			}
		}
		return $this->_result();
	}

	function payment_methods() {
		$tmp=array();
		foreach ($this->_gateways as $gw=>$gw_info) {
			if ($gw_info['active']==true) {
				$tmp[$gw]=$gw_info['title'];
			}
		}
		asort($tmp);
		$this->_result(array("ok"=>'ok',"d"=>$tmp));
		return $this->_result();
	}

	private function _result_prepare($gateway,$__rawpost,$__post,$__get,$__server) {
		if (isset($this->_gateways[$gateway]) && $this->_gateways[$gateway]['active']===true) {
			// todo: agrrr, it could be awesome, but I will done it better...
			$gw_answer=$this->_gateways[$gateway]['answer'];
			switch ($gw_answer['data']) {
				case '_RAWPOST':
					$gw_answer['data']=$__rawpost;
				break;
				case '_RAWORPOST':
					$gw_answer['data']=is_array($__post)?$__post:$__rawpost;
				break;
				case '_POST':
					$gw_answer['data']=$__post;
				break;
				case '_GET':
					$gw_answer['data']=$__get;
				break;
			}
			if (isset($gw_answer['signature'])) {
				switch ($gw_answer['signature']) {
					case '_SERVER.HTTP_X_SIGNATURE':
						$gw_answer['signature']=$__server['HTTP_X_SIGNATURE'];
					break;
				}
			}
			return $gw_answer;
		} else {
			if (!isset($this->_gateways[$gateway])) {
				$this->_error("wrong_gateway",array("gateway"=>$gateway,"data"=>array("rp"=>$__rawpost,"p"=>$__post,"g"=>$__get,"s"=>$__server)));
			} else if ($this->_gateways[$gateway]['active']!==true) {
				//$this->_error("disabled_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway],"data"=>array("rp"=>$__rawpost,"p"=>$__post,"g"=>$__get,"s"=>$__server)));
				$this->_error("disabled_gateway",array("gateway"=>$gateway,"data"=>array("rp"=>$__rawpost,"p"=>$__post,"g"=>$__get,"s"=>$__server)));
			}
			return false;
		}
	}


	function unsubscribe($gateway,$data=array()) {
		if ($this->is_gateway_active($gateway)) {
			require_once ($this->_script_path."sources/".$this->_gateways[$gateway]['include']);
			$gw_classname=$this->_gateways[$gateway]['class'];
			$gw=new $gw_classname();
			$tmp=$gw->unsubscribe($data,$this->_gateways[$gateway],$this->udb);
			if ($tmp['ok']=='ok') {
				$this->_result($tmp);
			} else if ($tmp['e'][0]=='form_error') {
				//$this->_error('form_error',array("e"=>$tmp['e'],"data"=>$data,"gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error('form_error',array("e"=>$tmp['e'],"data"=>$data,"gateway"=>$gateway));
			} else {
				//$this->_error('submit_error',array("e"=>$tmp['e'],"data"=>$data,"gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error('submit_error',array("e"=>$tmp['e'],"data"=>$data,"gateway"=>$gateway));
			}
		} else {
			if (!isset($this->_gateways[$gateway])) {
				$this->_error("wrong_gateway",array("gateway"=>$gateway,"data"=>$data));
			} else if ($answer!==false) {
				//$this->_error("empty_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error("empty_gateway",array("gateway"=>$gateway));
			} else if ($this->_gateways[$gateway]['active']!==true) {
				//$this->_error("disabled_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway],"data"=>$data));
				$this->_error("disabled_gateway",array("gateway"=>$gateway,"data"=>$data));
			}
		}
		return $this->_result();
	}
	function rebill($gateway,$data=array()) {
		if ($this->is_gateway_active($gateway) && $this->is_gateway_with_subscription($gateway)) {
			require_once ($this->_script_path."sources/".$this->_gateways[$gateway]['include']);
			$gw_classname=$this->_gateways[$gateway]['class'];
			$gw=new $gw_classname();
			$tmp=$gw->rebill($data,$this->_gateways[$gateway],$this->udb);
			if ($tmp['ok']=='ok') {
				$this->_result($tmp);
			} else if ($tmp['e'][0]=='form_error') {
				//$this->_error('form_error',array("e"=>$tmp['e'],"data"=>$data,"gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error('form_error',array("e"=>$tmp['e'],"data"=>$data,"gateway"=>$gateway));
			} else {
				//$this->_error('submit_error',array("e"=>$tmp['e'],"data"=>$data,"gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				//$this->_error($tmp['e'][0],array("e"=>$tmp['e'],"out_raw"=>$tmp,"data"=>$data,"gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error($tmp['e'][0],array("e"=>$tmp['e'],"out_raw"=>$tmp,"data"=>$data,"gateway"=>$gateway));
			}
		} else {
			if (!isset($this->_gateways[$gateway])) {
				$this->_error("wrong_gateway",array("gateway"=>$gateway,"data"=>$data));
			} else if (!$this->is_gateway_with_subscription($gateway)) {
				//$this->_error("withoutsubscriptions_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error("withoutsubscriptions_gateway",array("gateway"=>$gateway));
			} else if ($answer!==false) {
				//$this->_error("empty_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error("empty_gateway",array("gateway"=>$gateway));
			} else if ($this->_gateways[$gateway]['active']!==true) {
				//$this->_error("disabled_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway],"data"=>$data));
				$this->_error("disabled_gateway",array("gateway"=>$gateway,"data"=>$data));
			}
		}
		return $this->_result();
	}

	// todo - a lot or copy-paste
	function submit ($gateway,$answer=false) {
		if ($answer===false) {
			$answer=$this->_result_prepare($gateway,file_get_contents("php://input"),$_POST,$_GET,$_SERVER);
		}
		if ($this->is_gateway_active($gateway) && $answer!==false) {
			require_once ($this->_script_path."sources/".$this->_gateways[$gateway]['include']);
			$gw_classname=$this->_gateways[$gateway]['class'];
			$gw=new $gw_classname();
			$tmp=$gw->submit($answer,$this->_gateways[$gateway],$this->udb);
			if ($tmp['ok']=='ok') {
				$this->_result($tmp);
			} else if ($tmp['e'][0]=='form_error') {
				//$this->_error('form_error',array("e"=>$tmp['e'],"data"=>$answer,"gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error('form_error',array("e"=>$tmp['e'],"data"=>$answer,"gateway"=>$gateway));
			} else {
				//$this->_error('submit_error',array("e"=>$tmp['e'],"data"=>$answer,"gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error('submit_error',array("e"=>$tmp['e'],"data"=>$answer,"gateway"=>$gateway));
			}
		} else {
			if (!isset($this->_gateways[$gateway])) {
				$this->_error("wrong_gateway",array("gateway"=>$gateway,"data"=>$answer));
			} else if ($answer!==false) {
				//$this->_error("empty_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error("empty_gateway",array("gateway"=>$gateway));
			} else if ($this->_gateways[$gateway]['active']!==true) {
				//$this->_error("disabled_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway],"data"=>$answer));
				$this->_error("disabled_gateway",array("gateway"=>$gateway,"data"=>$answer));
			}
		}
		return $this->_result();
	}

	function result ($gateway,$answer=false) {
		if ($answer===false) {
			$answer=$this->_result_prepare($gateway,file_get_contents("php://input"),$_POST,$_GET,$_SERVER);
		}
		if ($this->is_gateway_active($gateway) && $answer!==false) {
			require_once ($this->_script_path."sources/".$this->_gateways[$gateway]['include']);
			$gw_classname=$this->_gateways[$gateway]['class'];
			$gw=new $gw_classname();
			$tmp=$gw->result($answer,$this->_gateways[$gateway],$this->udb);
			if ($tmp['ok']=='ok') {
				$this->_result($tmp);
			} else {
				//$this->_error('result_error',array("e"=>$tmp['e'],"data"=>$answer,"gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error('result_error',array("e"=>$tmp['e'],"data"=>$answer,"gateway"=>$gateway));
			}
		} else {
			if (!isset($this->_gateways[$gateway])) {
				$this->_error("wrong_gateway",array("gateway"=>$gateway,"data"=>$answer));
			} else if ($answer!==false) {
				//$this->_error("empty_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway]));
				$this->_error("empty_gateway",array("gateway"=>$gateway));
			} else if ($this->_gateways[$gateway]['active']!==true) {
				//$this->_error("disabled_gateway",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway],"data"=>$answer));
				$this->_error("disabled_gateway",array("gateway"=>$gateway,"data"=>$answer));
			} else {
				//$this->_error("unknown",array("gateway"=>$gateway,"gateway_config"=>$this->_gateways[$gateway],"data"=>$answer));
				$this->_error("unknown",array("gateway"=>$gateway,"data"=>$answer));
			}
		}
		return $this->_result();
	}

	function is_gateway_active($gateway) {
		if (isset($this->_gateways[$gateway]) && $this->_gateways[$gateway]['active']===true) {
			return true;
		} else {
			return false;
		}
	}

	function is_gateway_with_subscription($gateway) {
		if (isset($this->_gateways[$gateway]) && $this->_gateways[$gateway]['subscriptions_support']===true) {
			return true;
		} else {
			return false;
		}
	}

	function build_html_form ($form_data,$buttom_text="Pay",$autosubmit=false,$api=false) {
		if (isset($form_data['form'])) {
			return $this->_build_by_form($form_data['form'],$buttom_text,$autosubmit,$api);
		} else if (isset($form_data['user_form'])) {
			return $this->_build_by_form($form_data['user_form'],$buttom_text,false,$api);
		} else if (isset($form_data['url'])) {
			return $this->_build_by_url($form_data['url'],$buttom_text,$autosubmit,$api);
		} else {
			return false;
		}
	}

	private function _build_form_autosubmit($autosubmit) {
		$tmp_id="autosubmit_".md5(time().rand(0,9999));
		if ($autosubmit) {
			$js='<script>setTimeout(function () {document.getElementById("'.$tmp_id.'").submit();}, 500);</script>';
		} else {
			$js="";
		}
		return array(
				"id"=>$tmp_id,
				"js"=>$js
			);
	}

	private function _build_by_form($form_data,$button_text="Pay",$autosubmit=false,$api=false) {
		if ($autosubmit===false) {
			$autosubmit=array("id"=>"form_payment_submit");
		} else {
			$autosubmit=$this->_build_form_autosubmit($autosubmit);
		}
		$out_txt=array("form"=>array(
			), "inputs"=>array());
		foreach ($form_data['form'] as $k=>$v) {
			$out_txt['form'][]="".$k."=\"".$v."\"";
		}
		$out_txt['form'][]="id=\"".$autosubmit['id']."\"";
		$out_txt['form']="<form ".implode(" ",$out_txt['form']).">";
		if (isset($form_data['inputs_hidden'])) {foreach ($form_data['inputs_hidden'] as $k=>$v) {
			$out_txt['inputs'][]="<input type=\"hidden\" name=\"".$k."\" value=\"".$v."\">";
		}}
		if (isset($form_data['inputs'])) {foreach ($form_data['inputs'] as $k=>$v) {
			$colspan=isset($v['colspan'])?$v['colspan']:1;
			$width=isset($v['width'])?$v['width']:false;
			if (isset($v['field']) && $v['field']=='multi') {
				$tmp=array();
				foreach ($v['fields'] as $mfk=>$mfv) {
					$tmp[]=$this->_build_html_block($mfk,$mfv,$colspan,false,$width,$api);
				}
				$out_txt['inputs'][]="<tr class=\"payment_form_".$k."\"><th>%".$k."%</th>".implode("",$tmp)."</tr>";
			} else {
				$out_txt['inputs'][]="<tr class=\"payment_form_".$k."\">".$this->_build_html_block($k,$v,$colspan,true,$width)."</tr>";
			}
		}}
		$out_txt['inputs']="<table class=\"payment_form\">".implode("",$out_txt['inputs'])."</table>";
		$out_txt=implode("",$out_txt)."<input type=submit value=\"".$button_text."\"></form>".((isset($autosubmit['js']) && !$api)?$autosubmit['js']:'');
		if ($api) {
			return array("form_txt"=>$out_txt, "form_data"=>$form_data, "button_text"=>$button_text, "autosubmit"=>$autosubmit,"type"=>'form');
		} else {
			return $out_txt;
		}
	}

	private function _build_html_block ($k,$v,$colspan=1,$with_title=true,$width=false,$api=false) {
		$out_txt="";
		if (!isset($v['field']) || $v['field']=='input') {
			if ($with_title) $out_txt="<th>%".$k."%</th>";
			//$out_txt.="<td".($colspan>1?(' colspan='.$colspan):(''))."><input".($width?(" style=\"width:".$width."\""):(''))."".($k=='card_number'?' maxlength="16"':'')." name=\"".$k."\"".((isset($v['ro'])&&$v['ro'])?' readonly':'')." value=\"".(isset($v['value'])?$v['value']:'')."\"></td>";
			$out_txt.="<td".($colspan>1?(' colspan='.$colspan):(''))."><input name=\"".$k."\"".((isset($v['ro'])&&$v['ro'])?' readonly':'')."``".($width?(" style=\"width:".$width."\""):(''))." value=\"".(isset($v['value'])?$v['value']:'')."\"></td>";
		} else if (isset($v['field'])) {
			if ($v['field']=='list') {
				$_tmp_options=array();
				if ($this->_form_lists[($v['type'])]) {
					foreach ($this->_form_lists[($v['type'])] as $__k=>$__v) {
						$_tmp_options[]="<option value=\"".$__k."\"".(isset($v['selected_key'])?($v['selected_key']==$__k?' selected':''):'').">".(is_array($__v)?$__v['v']:$__v)."</option>";
					}
				}
				if ($with_title) $out_txt.="<th>%".$k."%</th>";
				$out_txt.="<td".($colspan>1?(' colspan='.$colspan):(''))."><select".($width?(" style=\"width:".$width."\""):(''))." name=\"".$k."\"".((isset($v['ro'])&&$v['ro'])?' disabled':'').">".implode("",$_tmp_options)."</select></td>";
			}
		}
		return $out_txt;
	}

	private function _build_by_url($url_data,$button_text="Pay",$autosubmit=false,$api=false) {
		$autosubmit=$this->_build_form_autosubmit($autosubmit);
		$tmp=parse_url($url_data['pay_url']);
		$out_txt="<form action=\"".$tmp['scheme']."://".$tmp['host']."".$tmp['path']."\" method=\"GET\" id=\"".$autosubmit['id']."\">";
		if (isset($tmp['query']) && $tmp['query']!='') {
			parse_str($tmp['query'], $tmp['query_r']);
			foreach ($tmp['query_r'] as $k=>$v) {
				$out_txt.="<input type=hidden name=\"".$k."\" value=\"".$v."\">";
			}
		}
		$out_txt.="<input type=submit value=\"".$button_text."\"></form>".$autosubmit['js'];
		return $out_txt;
	}

	private function _result($add=false) {
		if ($add===false) {
			return $this->_out;
		} else {
			if ($add['ok']=='ok') {
				$this->_out['d']=$add['d'];
				if (isset($add['dbg'])) {
					$this->_out['dbg']=$add['dbg'];
				}
			}
		}
	}

	private function _error($error_title,$error_dbg) {
		$this->_out['ok']='error';
		if (isset($error_title)) {
			$this->_out['e'][]=$error_title;
		}
		if (isset($error_dbg)) {
			if (!isset($this->_out['e_dbg'])) $this->_out['e_dbg']=array();
			$this->_out['e_dbg'][]=$error_dbg;
		}
	}
}

?>
