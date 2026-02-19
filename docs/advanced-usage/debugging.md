---
title: Debugging
weight: 3
---

Pass `-vvv` to any command to see the full request before it's sent:

```bash
php artisan bookstore:get-books -vvv
```

```
  Request
  -------
  GET https://api.example-bookstore.com/books

  Request Headers
  ---------------
  Accept: application/json
  Content-Type: application/json
  Authorization: Bearer sk-abc...
```

This shows the HTTP method, resolved URL, request headers (including authentication), and request body when present.
