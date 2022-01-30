<?php
declare(strict_types=1);

namespace Clue\React\Soap\Protocol;

use React\Http\Browser;
use Soap\Engine\HttpBinding\SoapRequest;
use Soap\Engine\HttpBinding\SoapResponse;
use Soap\Engine\Transport;
use function React\Async\await;

final class BrowserTransport implements Transport
{
    public function __construct(
        private Browser $browser
    ){
    }

    public function request(SoapRequest $request): SoapResponse
    {
        $headers = array();

        // A better version of Header parsing is in the psr18-transport package
        // It requires some additional dependencies, so copies this from the existing encoder.

        if ($request->getVersion() === SOAP_1_1) {
            $headers = array(
                'SOAPAction' => $request->getAction(),
                'Content-Type' => 'text/xml; charset=utf-8'
            );
        } elseif ($request->getVersion() === SOAP_1_2) {
            $headers = array(
                'Content-Type' => 'application/soap+xml; charset=utf-8; action=' . $request->getAction()
            );
        }

        $httpResponse = await(
            $this->browser->request(
                'POST',
                $request->getLocation(),
                $headers,
                $request->getRequest()
            )
        );

        return new SoapResponse((string) $httpResponse->getBody());
    }
}
