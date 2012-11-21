<?php

require_once('inc/Numuri.php');

/**
 * Инициализация SOAP-сервера
 */
ini_set("soap.wsdl_cache_enabled", "0");
$server = new SoapServer("numuri.wsdl");
$server->setClass("Numuri");
$server->handle();