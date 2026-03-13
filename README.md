# CSE 135 Project README.md File

## Unexpected Cases

1. `401.html`, `403.html`, `404.html`, `500.html`

These pages serve as endpoints for when the API returns status codes other than `200` OK.

   1. `401.html`
      - In the instance that an unregistered `user` tries to access any page, they get redirected to `401.html` then taken back to `index.html` to login. 

   2. `403.html` 
      - In the instance that a user tries to access page X and lacks the necessary roles/permissions, the API returns `error 403`. 
      - Example:
        - `analyst_pageperform` attempts to access `Errors` live backend page. 
          - `error-report.php` sends `GET` request to `/api/index.php/errors`
            - `/api/index.php/errors` runs `requireAuth()` to check if `user` exists.
            - `/api/index.php/errors` runs `requirePermissions(['super admin', 'analyst'], ['errors'])` to check `user` permissions.
            - `$permissions = get_permissions()` returns `['reporting', 'pageviews', 'performance]`
              - `requirePermissions($allowedRoles, $required)` checks `$permissions` against `$required` &rarr; returns error `403`.
          - `error-report.php` receives `resp.status 403` 
          - `error-report.php` redirects to `/403.html`
