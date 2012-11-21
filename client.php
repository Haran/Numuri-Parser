<?php

ini_set("soap.wsdl_cache_enabled", "0");

$client = new SoapClient("http://mans.dautkom.lv/numuri/?wsdl", array("trace" => 1, "exceptions" => 0));

var_dump( $client->getNumber("65451450") );

echo "<pre>\n";
echo "Request :\n".htmlspecialchars($client->__getLastRequest()) ."\n";
echo "Response:\n".htmlspecialchars($client->__getLastResponse())."\n";
echo "</pre>";

