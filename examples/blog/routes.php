<?php

declare(strict_types=1);

get('/api/posts', static function () {
    return db('posts')->where('published', 1)->orderBy('id', 'desc')->paginate(20, (int) request()->query('page', 1));
});

post('/admin/posts', static function () {
    $errors = validate(input_all(), ['title' => 'required|string|max:255', 'body' => 'required|string']);
    if ($errors !== []) { return json(['errors' => $errors], 422); }
    $id = db('posts')->insert([
        'title' => input('title'), 'body' => input('body'), 'published' => (int) input('published', 0),
        'created_at' => gmdate('Y-m-d H:i:s'),
    ]);
    return json(['id' => $id], 201);
})->middleware('auth', 'csrf');
