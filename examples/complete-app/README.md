# Complete application example

This example shows how the 0.3 APIs fit together in a small posts API.

It demonstrates:

- models and relations;
- Schema migrations;
- FormRequest validation;
- API-token middleware;
- pagination;
- unique queued jobs;
- mail;
- scheduler tasks;
- application testing helpers.

The files are intentionally small and are not loaded by the starter application automatically. Copy/adapt the pieces you need into your application namespaces.

No frontend toolchain, Redis, queue daemon or extra Composer package is required.

Typical flow:

1. create users through your application;
2. issue an API token with `api_token_issue()`;
3. call the posts API with `Authorization: Bearer <token>`;
4. process queued notifications through cPanel Cron or another cron implementation;
5. run scheduled cleanup with the normal SedoPHP scheduler.

Example cron:

```text
* * * * * php /home/account/app/sedo queue:work 20 default
* * * * * php /home/account/app/sedo schedule:run
```
