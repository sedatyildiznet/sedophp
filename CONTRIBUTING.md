# Contributing

SedoPHP values small, readable changes.

Before proposing a feature, ask three questions:

1. Can ordinary PHP already solve this clearly?
2. Does this belong in the framework core?
3. Can a PHP developer understand the API without learning new framework-specific language?

Run before submitting changes:

```bash
php tests/run.php
find src app bootstrap config routes public database tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Keep public APIs small and avoid hidden behavior unless it removes substantial application complexity.
