<?php

namespace Itscaro\ReactBundle\Reactor;

use React\Http\Request;
use React\Http\Response;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\Config\Loader\LoaderInterface;

class ReactKernel extends \AppKernel
{
    public function __invoke(Request $request, Response $response)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($request->getMethod() !== SymfonyRequest::METHOD_GET) {
            $request->on('data', function ($postData) use ($request, $response) {
                $this->doRequest($request, $response, $postData);
            });
        } else {
            $this->doRequest($request, $response);
        }
    }

    private function doRequest(Request $request, Response $response, $postData = null) {
        $sfRequest = $this->buildSfRequest($request, $postData);

        $sfResponse = $this->handle($sfRequest);
        $this->terminate($sfRequest, $sfResponse);

        $this->parseSfResponse($response, $sfResponse);
        $response->end($sfResponse->getContent());
    }

    /**
     * @param Request $request
     * @param string $postData
     * @return SymfonyRequest
     */
    private function buildSfRequest(Request $request, $postData = null)
    {
        if ($postData !== null) {
            parse_str($postData, $postDataArray);
            $parameters = empty($postDataArray) ? [] : $postDataArray;
            $content = empty($postDataArray) ? [] : $postData;
            // TODO Maybe check Content-Type too?

            $sfRequest = SymfonyRequest::create(
                $request->getPath(),
                $request->getMethod(),
                $parameters,
                [],
                [],
                [],
                $content
            );
        } else {
            $sfRequest = SymfonyRequest::create(
                $request->getPath(),
                $request->getMethod()
            );
        }

        $requestHeaders = $request->getHeaders();

        if (isset($requestHeaders['Cookie'])) {
            $sfRequest->cookies->replace($this->deserializeCookiesHeader($requestHeaders['Cookie']));
        }

        // $sfRequest->request->replace($parameters);
        $sfRequest->query->replace($request->getQuery());
        $sfRequest->server->set('REQUEST_URI', $request->getPath());
        $sfRequest->server->set('SERVER_NAME', rtrim($requestHeaders['Host'], ':0..9'));
        $sfRequest->headers->replace($request->getHeaders());
        return $sfRequest;
    }

    /**
     * @param Response $response
     * @param $sfResponse
     * @throws \Exception
     */
    private function parseSfResponse(Response $response, SymfonyResponse $sfResponse)
    {
        $headers = array_map('current', $sfResponse->headers->allPreserveCase());
        $cookies = array();

        if (session_status() === PHP_SESSION_ACTIVE && !array_key_exists('PHPSESSID', $_COOKIE)) {
            $cookies['PHPSESSID'] = session_id();
        }

        if (count($cookies) > 0) {
            $headers['Set-Cookie'] = $this->serializeCookiesHeader($cookies);
        }

        $response->writeHead($sfResponse->getStatusCode(), $headers);
    }

    private function serializeCookiesHeader(array $data) {
        $concat = array();
        foreach ($data as $key => $value) {
            $concat[] = $key . '=' . $value;
        }

        return implode('; ', $concat);
    }

    /**
     * @param $cookieHeader
     * @return array
     */
    private function deserializeCookiesHeader($cookieHeader)
    {
        $headerCookies = explode('; ', $cookieHeader);
        foreach ($headerCookies as $headerCookie) {
            $cookie = explode('=', $headerCookie);
            if (count($cookie) === 2) {
                $_COOKIE[$cookie[0]] = $cookie[1];
            }
        }
        return $_COOKIE;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir() . '/config/config_' . $this->getEnvironment() . '.yml');
    }

    public function getRootDir()
    {
        return KERNEL_ROOT;
    }
}
