<?php

use Clue\React\Soap\Protocol\Psr18Browser;
use Http\Client\Common\PluginClient;
use Soap\Engine\SimpleEngine;
use Soap\ExtSoapEngine\ExtSoapDriver;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\ExtSoapEngine\Wsdl\TemporaryWsdlLoaderProvider;
use Soap\Psr18Transport\Psr18Transport;
use Soap\Psr18Transport\Wsdl\Psr18Loader;
use Soap\Wsdl\Loader\FlatteningLoader;
use function React\Async\async;
use function React\Async\await;
use function React\Async\parallel;

require __DIR__ . '/../vendor/autoload.php';

$browser = new React\Http\Browser();

$blz = isset($argv[1]) ? $argv[1] : '12070000';


$browser = new React\Http\Browser();
$asyncHttpClient = new Psr18Browser($browser);
$engine = new SimpleEngine(
    ExtSoapDriver::createFromOptions(
        ExtSoapOptions::defaults('http://www.thomas-bayer.com/axis2/services/BLZService?wsdl')
            ->withWsdlProvider(new TemporaryWsdlLoaderProvider(
                new FlatteningLoader(Psr18Loader::createForClient(new Psr18Browser($browser)))
            ))
    ),
    $transport = Psr18Transport::createForClient(
        new PluginClient(
            $asyncHttpClient,
            []
        )
    )
);

$run = fn () => async(fn () => $engine->request('getBank', [array('blz' => $blz)]))()->then(
    function ($result) {
        echo 'SUCCESS!' . PHP_EOL;
        return $result;
    },
    function (Exception $e) {
        echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    }
);

$results = await(parallel([
    $run,
    $run,
    $run
]));

var_dump($results);


