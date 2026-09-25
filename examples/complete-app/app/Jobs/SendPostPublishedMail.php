<?php

declare(strict_types=1);

namespace Example\CompleteApp\Jobs;

use Example\CompleteApp\Models\Post;
use SedoPHP\Queue\JobInterface;

final class SendPostPublishedMail implements JobInterface
{
    public function handle(array $payload): void
    {
        $postId = $payload['post_id'] ?? null;
        if (!is_int($postId) && !is_string($postId)) {
            return;
        }

        $post = Post::find($postId);
        if ($post === null) {
            return;
        }

        $user = $post->user()->first();
        $email = $user?->get('email');

        if (!is_string($email) || $email === '') {
            return;
        }

        mail_send(
            $email,
            'Post published',
            '<p>Your post was published.</p>',
            'Your post was published.'
        );
    }
}
