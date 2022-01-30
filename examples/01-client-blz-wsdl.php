<?php

require __DIR__ . '/../vendor/autoload.php';

$browser = new React\Http\Browser();

$blz = isset($argv[1]) ? $argv[1] : '12070000';


$client = Clue\React\Soap\Client::forWsdl($browser, 'http://www.thomas-bayer.com/axis2/services/BLZService?wsdl');
$api = new Clue\React\Soap\Proxy($client);

$api->getBank(array('blz' => $blz))->then(
    function ($result) {
        echo 'SUCCESS!' . PHP_EOL;
        var_dump($result);
    },
    function (Exception $e) {
        echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    }
);
