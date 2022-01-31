<?php

use Clue\React\Soap\Protocol\Psr18Browser;
use Soap\Engine\Metadata\Model\Method;
use Soap\Engine\Metadata\Model\Type;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\ExtSoapEngine\Wsdl\TemporaryWsdlLoaderProvider;
use Soap\Psr18Transport\Wsdl\Psr18Loader;
use Soap\Wsdl\Loader\FlatteningLoader;

require __DIR__ . '/../vendor/autoload.php';

$browser = new React\Http\Browser();

$httpPlugins = [];
$client = new Clue\React\Soap\Client(
    $browser,
    ExtSoapOptions::defaults('http://www.thomas-bayer.com/axis2/services/BLZService?wsdl')
        ->withWsdlProvider(new TemporaryWsdlLoaderProvider(
            new FlatteningLoader(Psr18Loader::createForClient(new Psr18Browser($browser)))
        )),
    ...$httpPlugins
);


echo 'Functions:' . PHP_EOL .
 implode(PHP_EOL, $client->getFunctions()->map(fn (Method $method) => $method->getName())) . PHP_EOL .
 PHP_EOL .
 'Types:' . PHP_EOL .
 implode(PHP_EOL, $client->getTypes()->map(fn (Type $type) => $type->getName())) . PHP_EOL;
