<?php
include("uniteapi-php-payments.php");

$pay=new upayments(array("script_path"=>"/path/to/lib/","payments_results_url"=>"https://``","test"=>true));


$data=array(
                                        "order_id"=>time().rand(10000,99999),              //req
					"currency"=>"RUB",
                                        "description"=>"test payment",        //req
                                        "items"=>array(                 // at least one item req
                                                        "item_id_0"=>array("sku"=>1,"title"=>"title 1","qty"=>1,"price"=>"1.00")
                                                ),
                                        "total"=>"1.00"                  // req, more that zero
                                );

print "<pre>"; var_dump($data); print "</pre>";
print "<hr>";

$predata=$pay->make('paypal',$data);
if ($predata['ok']=='ok') {
	print $pay->build_html_form($predata['d'],"Pay via PayPal");
} else {
	var_dump($predata);
}
print "<hr>";
$predata=$pay->make('webmoney',$data);
if ($predata['ok']=='ok') {
	print $pay->build_html_form($predata['d'], "Pay via WebMoney");
} else {
	var_dump($predata);
}
print "<hr>";

$predata=$pay->make('coinbase',$data);
if ($predata['ok']=='ok') {
	print $pay->build_html_form($predata['d'], "Pay via BitCoin");
} else {
	var_dump($predata);
}
print "<hr>";

?>