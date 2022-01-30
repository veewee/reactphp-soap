<?php
declare(strict_types=1);

namespace Clue\React\Soap\Protocol;

use React\Http\Browser;
use Soap\Wsdl\Loader\WsdlLoader;
use function React\Async\await;

final class BrowserWsdlLoader implements WsdlLoader
{
    public function __construct(
        private Browser $browser
    ){
    }

    public function __invoke(string $location): string
    {
        return (string) await($this->browser->get($location))->getBody();
    }
}
