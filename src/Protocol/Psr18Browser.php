<?php
declare(strict_types=1);

namespace Clue\React\Soap\Protocol;

use Http\Client\HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use function React\Async\await;

final class Psr18Browser implements HttpClient
{
    public function __construct(
        private Browser $browser
    ){
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return await(
            $this->browser->request(
                $request->getMethod(),
                (string) $request->getUri(),
                $request->getHeaders(),
                (string) $request->getBody()
            )
        );
    }
}
