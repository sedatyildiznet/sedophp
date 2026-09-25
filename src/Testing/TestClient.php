<?php

declare(strict_types=1);

namespace SedoPHP\Testing;

use SedoPHP\Core\Application;
use SedoPHP\Http\Request;
use SedoPHP\Routing\Router;

final class TestClient
{
    public function __construct(private readonly Router $router)
    {
    }

    public static function fromApplication(Application $app): self
    {
        return new self($app->router());
    }

    /** @param array<string,mixed> $query @param array<string,string> $headers */
    public function get(string $uri, array $query = [], array $headers = []): TestResponse
    {
        return $this->request('GET', $uri, $query, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PUT', $uri, $data, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PATCH', $uri, $data, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('DELETE', $uri, $data, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->jsonRequest('POST', $uri, $data, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function putJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->jsonRequest('PUT', $uri, $data, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function patchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->jsonRequest('PATCH', $uri, $data, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function deleteJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->jsonRequest('DELETE', $uri, $data, $headers);
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    public function request(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $request = Request::fake($method, $uri, $data, $headers);
        return new TestResponse($this->router->dispatch($request));
    }

    /** @param array<string,mixed> $data @param array<string,string> $headers */
    private function jsonRequest(string $method, string $uri, array $data, array $headers): TestResponse
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        return $this->request($method, $uri, $data, $headers);
    }
}
