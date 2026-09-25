<?php

declare(strict_types=1);

use Example\CompleteApp\Http\Requests\StorePostRequest;
use Example\CompleteApp\Jobs\SendPostPublishedMail;
use Example\CompleteApp\Models\Post;

get('/api/posts', static function () {
    $page = max(1, (int) request()->query('page', 1));

    return json(
        Post::query()
            ->orderBy('id', 'desc')
            ->paginate(10, $page)
    );
})->middleware('token');

post('/api/posts', static function () {
    $form = new StorePostRequest(request());

    if ($form->fails()) {
        return json(['errors' => $form->errors()], 422);
    }

    $token = request()->attribute('api_token', []);
    $userId = is_array($token) ? ($token['user_id'] ?? null) : null;

    if ($userId === null) {
        return json(['error' => 'Unauthenticated'], 401);
    }

    $data = $form->validated();

    $post = Post::create([
        'user_id' => $userId,
        'title' => $data['title'],
        'body' => $data['body'],
        'published_at' => gmdate('Y-m-d H:i:s'),
    ]);

    queue_push_unique(
        'post-published:' . $post->getKey(),
        SendPostPublishedMail::class,
        ['post_id' => $post->getKey()],
        maxAttempts: 5,
        backoffSeconds: 10,
        backoffStrategy: 'exponential',
    );

    return json($post->toArray(), 201);
})->middleware('token', 'throttle:30,60');
