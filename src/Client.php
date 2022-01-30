<?php

namespace Clue\React\Soap;

use Clue\React\Soap\Protocol\BrowserTransport;
use Clue\React\Soap\Protocol\BrowserWsdlLoader;
use Clue\React\Soap\Protocol\ClientDecoder;
use Clue\React\Soap\Protocol\ClientEncoder;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Soap\Engine\Engine;
use Soap\Engine\LazyEngine;
use Soap\Engine\Metadata\Collection\MethodCollection;
use Soap\Engine\Metadata\Collection\TypeCollection;
use Soap\Engine\SimpleEngine;
use Soap\ExtSoapEngine\AbusedClient;
use Soap\ExtSoapEngine\ExtSoapDriver;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\ExtSoapEngine\Transport\TraceableTransport;
use Soap\ExtSoapEngine\Wsdl\InMemoryWsdlProvider;
use Soap\Wsdl\Loader\FlatteningLoader;
use Soap\Wsdl\Loader\WsdlLoader;
use function React\Async\async;

/**
 * The `Client` class is responsible for communication with the remote SOAP
 * WebService server. It requires the WSDL file contents and an optional
 * array of SOAP options:
 *
 * ```php
 * $wsdl = '<?xml …';
 * $options = array();
 *
 * $client = new Clue\React\Soap\Client(null, $wsdl, $options);
 * ```
 *
 * This class takes an optional `Browser|null $browser` parameter that can be used to
 * pass the browser instance to use for this object.
 * If you need custom connector settings (DNS resolution, TLS parameters, timeouts,
 * proxy servers etc.), you can explicitly pass a custom instance of the
 * [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface)
 * to the [`Browser`](https://github.com/reactphp/http#browser) instance
 * and pass it as an additional argument to the `Client` like this:
 *
 * ```php
 * $connector = new React\Socket\Connector(array(
 *     'dns' => '127.0.0.1',
 *     'tcp' => array(
 *         'bindto' => '192.168.10.1:0'
 *     ),
 *     'tls' => array(
 *         'verify_peer' => false,
 *         'verify_peer_name' => false
 *     )
 * ));
 *
 * $browser = new React\Http\Browser($connector);
 * $client = new Clue\React\Soap\Client($browser, $wsdl);
 * ```
 *
 * The `Client` works similar to PHP's `SoapClient` (which it uses under the
 * hood), but leaves you the responsibility to load the WSDL file. This allows
 * you to use local WSDL files, WSDL files from a cache or the most common form,
 * downloading the WSDL file contents from an URL through the `Browser`:
 *
 * ```php
 * $browser = new React\Http\Browser();
 *
 * $browser->get($url)->then(
 *     function (Psr\Http\Message\ResponseInterface $response) use ($browser) {
 *         // WSDL file is ready, create client
 *         $client = new Clue\React\Soap\Client($browser, (string)$response->getBody());
 *
 *         // do something…
 *     },
 *     function (Exception $e) {
 *         // an error occured while trying to download the WSDL
 *     }
 * );
 * ```
 *
 * The `Client` constructor loads the given WSDL file contents into memory and
 * parses its definition. If the given WSDL file is invalid and can not be
 * parsed, this will throw a `SoapFault`:
 *
 * ```php
 * try {
 *     $client = new Clue\React\Soap\Client(null, $wsdl);
 * } catch (SoapFault $e) {
 *     echo 'Error: ' . $e->getMessage() . PHP_EOL;
 * }
 * ```
 *
 * > Note that if you have an old version of `ext-xdebug` < 2.7 loaded, this may
 *   halt with a fatal error instead of throwing a `SoapFault`. It is not
 *   recommended to use this extension in production, so this should only ever
 *   affect test environments.
 *
 * The `Client` constructor accepts an array of options. All given options will
 * be passed through to the underlying `SoapClient`. However, not all options
 * make sense in this async implementation and as such may not have the desired
 * effect. See also [`SoapClient`](https://www.php.net/manual/en/soapclient.soapclient.php)
 * documentation for more details.
 *
 * If working in WSDL mode, the `$options` parameter is optional. If working in
 * non-WSDL mode, the WSDL parameter must be set to `null` and the options
 * parameter must contain the `location` and `uri` options, where `location` is
 * the URL of the SOAP server to send the request to, and `uri` is the target
 * namespace of the SOAP service:
 *
 * ```php
 * $client = new Clue\React\Soap\Client(null, null, array(
 *     'location' => 'http://example.com',
 *     'uri' => 'http://ping.example.com',
 * ));
 * ```
 *
 * Similarly, if working in WSDL mode, the `location` option can be used to
 * explicitly overwrite the URL of the SOAP server to send the request to:
 *
 * ```php
 * $client = new Clue\React\Soap\Client(null, $wsdl, array(
 *     'location' => 'http://example.com'
 * ));
 * ```
 *
 * You can use the `soap_version` option to change from the default SOAP 1.1 to
 * use SOAP 1.2 instead:
 *
 * ```php
 * $client = new Clue\React\Soap\Client(null, $wsdl, array(
 *     'soap_version' => SOAP_1_2
 * ));
 * ```
 *
 * You can use the `classmap` option to map certain WSDL types to PHP classes
 * like this:
 *
 * ```php
 * $client = new Clue\React\Soap\Client(null, $wsdl, array(
 *     'classmap' => array(
 *         'getBankResponseType' => BankResponse::class
 *     )
 * ));
 * ```
 *
 * The `proxy_host` option (and family) is not supported by this library. As an
 * alternative, you can configure the given `$browser` instance to use an
 * [HTTP proxy server](https://github.com/clue/reactphp/http#http-proxy).
 * If you find any other option is missing or not supported here, PRs are much
 * appreciated!
 *
 * All public methods of the `Client` are considered *advanced usage*.
 * If you want to call RPC functions, see below for the [`Proxy`](#proxy) class.
 */
class Client
{
    private Browser $browser;
    private Engine $engine;

    /**
     * Instantiate a new SOAP client for the given WSDL contents.
     *
     * @param ?Browser $browser
     * @param ?string $wsdlContents
     * @param ?array $options
     */
    public function __construct(?Browser $browser, ?string $wsdlContents, array $options = array())
    {
        $this->browser = $browser ?? new Browser();

        // Accept HTTP responses with error status codes as valid responses.
        // This is done in order to process these error responses through the normal SOAP decoder.
        // Additionally, we explicitly limit number of redirects to zero because following redirects makes little sense
        // because it transforms the POST request to a GET one and hence loses the SOAP request body.
        $this->browser = $this->browser->withRejectErrorResponse(false);
        $this->browser = $this->browser->withFollowRedirects(0);

        $this->engine = new LazyEngine(fn() => new SimpleEngine(
            ExtSoapDriver::createFromClient(
                // You can make this private as well, giving you access to the regular SoapClient functions.
                // Like __setSoapHeaders, __setLocation, ...
                $client = AbusedClient::createFromOptions(
                    ExtSoapOptions::defaults($wsdlContents)
                        ->withWsdlProvider(new InMemoryWsdlProvider())
                )
            ),
            new TraceableTransport(
                $client,
                new BrowserTransport($browser)
            )
        ));
    }

    public static function forWsdl(Browser $browser, string $wsdlUri, array $options = [])
    {
        $loader = new FlatteningLoader(new BrowserWsdlLoader($browser));

        return new self(
            $browser,
            $loader($wsdlUri),
            $options
        );
    }

    /**
     * Queue the given function to be sent via SOAP and wait for a response from the remote web service.
     *
     * ```php
     * // advanced usage, see Proxy for recommended alternative
     * $promise = $client->soapCall('ping', array('hello', 42));
     * ```
     *
     * Note: This is considered *advanced usage*, you may want to look into using the [`Proxy`](#proxy) instead.
     *
     * ```php
     * $proxy = new Clue\React\Soap\Proxy($client);
     * $promise = $proxy->ping('hello', 42);
     * ```
     *
     * @param string $name
     * @param mixed[] $args
     * @return PromiseInterface Returns a Promise<mixed, Exception>
     */
    public function soapCall(string $name, array $args): PromiseInterface
    {
        return async(fn() => $this->engine->request($name, $args))();
    }

    /**
     * Returns an array of functions defined in the WSDL.
     *
     * It returns the equivalent of PHP's
     * [`SoapClient::__getFunctions()`](https://www.php.net/manual/en/soapclient.getfunctions.php).
     * In non-WSDL mode, this method returns `null`.
     */
    public function getFunctions(): MethodCollection
    {
        return $this->engine->getMetadata()->getMethods();
    }

    /**
     * Returns an array of types defined in the WSDL.
     *
     * It returns the equivalent of PHP's
     * [`SoapClient::__getTypes()`](https://www.php.net/manual/en/soapclient.gettypes.php).
     * In non-WSDL mode, this method returns `null`.
     */
    public function getTypes(): TypeCollection
    {
        return $this->engine->getMetadata()->getTypes();
    }
}
