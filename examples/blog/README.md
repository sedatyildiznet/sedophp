# Blog and admin example

This small example shows the intended SedoPHP 0.2 structure for a blog with an authenticated admin area.

1. Copy `create_posts.php` into `database/migrations` and run `php sedo migrate`.
2. Copy the routes from `routes.php` into `routes/web.php`.
3. Protect write routes with `auth` + `csrf` for browser forms or `token` for an API client.

The listing endpoint uses pagination, while the admin create endpoint uses validation and guarded database writes.
