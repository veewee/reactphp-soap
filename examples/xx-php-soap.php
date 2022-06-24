<?php

require __DIR__ . '/../vendor/autoload.php';

use Clue\React\Soap\Protocol\Psr18Browser;
use Http\Client\Common\PluginClient;
use Soap\Engine\SimpleEngine;
use Soap\ExtSoapEngine\AbusedClient;
use Soap\ExtSoapEngine\ExtSoapDriver;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\Psr18Transport\Psr18Transport;
use function React\Async\async;
use function React\Async\await;
use function React\Async\parallel;

$browser = new React\Http\Browser();
$asyncHttpClient = new Psr18Browser($browser);
$engine = new SimpleEngine(
    ExtSoapDriver::createFromClient(
        $client = AbusedClient::createFromOptions(
            ExtSoapOptions::defaults('http://www.dneonline.com/calculator.asmx?wsdl', [])
                ->disableWsdlCache()
        )
    ),
    $transport = Psr18Transport::createForClient(
        new PluginClient(
            $asyncHttpClient,
            []
        )
    )
);

$add = async(function ($a, $b) use ($engine) {
    return $engine->request('Add', [['intA' => $a, 'intB' => $b]]);
});
$addWithLogger = fn ($a, $b) => $add($a, $b)->then(
    function ($result) use ($a, $b) {
        echo "SUCCESS {$a}+{$b} = {$result->AddResult}!" . PHP_EOL;
        return $result;
    },
    function (Exception $e) {
        echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    }
);

$results = await(parallel([
    fn() => $addWithLogger(1, 2),
    fn() => $addWithLogger(3, 4),
    fn() => $addWithLogger(5, 6)
]));

var_dump($results);


