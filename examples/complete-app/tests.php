<?php

declare(strict_types=1);

namespace Example\CompleteApp\Tests;

use SedoPHP\Testing\DatabaseAssertions;
use SedoPHP\Testing\TestClient;

final class PostsApiExampleTest
{
    public static function run(TestClient $client, string $token): void
    {
        $response = $client->get('/api/posts', [], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);

        DatabaseAssertions::assertMissing('posts', [
            'title' => '__definitely_missing__',
        ]);
    }
}
