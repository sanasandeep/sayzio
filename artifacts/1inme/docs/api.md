# 1INME REST API (v1)

Base URL: `/api/v1` &nbsp;·&nbsp; Auth: `Authorization: Bearer <token>` (Sanctum personal access token)

All responses are JSON.

* Success → `{ "data": ... }` (or `204 No Content`)
* Failure → `{ "error": { "message": str, "code": str, "details"?: any } }`

---

## Authentication

| Method | Path                | Auth | Description                                  |
| ------ | ------------------- | ---- | -------------------------------------------- |
| POST   | `/auth/register`    | —    | Create account, returns user + token.        |
| POST   | `/auth/login`       | —    | Returns user + token. `device` field optional.|
| POST   | `/auth/logout`      | yes  | Revokes the current token.                   |
| GET    | `/auth/me`          | yes  | Current user details.                        |

```bash
# Register
curl -X POST $BASE/auth/register \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Jane","email":"jane@example.com","password":"password123"}'

# Login (returns token)
TOKEN=$(curl -s -X POST $BASE/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"jane@example.com","password":"password123"}' \
  | jq -r '.data.token')

# Use token
curl $BASE/auth/me -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

## Profile

| Method | Path        | Auth | Notes                                                                |
| ------ | ----------- | ---- | -------------------------------------------------------------------- |
| GET    | `/profile`  | yes  | Same payload as `/auth/me`.                                          |
| PATCH  | `/profile`  | yes  | Update `name`, `bio`, `handle`, `avatar`, `phone`, `timezone`, etc.  |

## Links (own resources only)

| Method | Path             | Description                                                                          |
| ------ | ---------------- | ------------------------------------------------------------------------------------ |
| GET    | `/links`         | Paginated list. Filters: `type`, `q`, `per_page`.                                    |
| POST   | `/links`         | Body: `type` (short/biolink/file/qr/event/vcard/social/sms/wifi/pdf), `alias?`, `title?`, `long_url?`, `visibility?`. |
| GET    | `/links/{id}`    | Show single link you own.                                                            |
| PATCH  | `/links/{id}`    | Partial update.                                                                      |
| DELETE | `/links/{id}`    | Delete link.                                                                         |

## Biolinks (public, visibility-aware)

| Method | Path                              | Description                                                                                              |
| ------ | --------------------------------- | -------------------------------------------------------------------------------------------------------- |
| GET    | `/biolinks/{alias}`               | Returns `{biolink, owner, blocks[]}`. Honors visibility tier:<br>• `public` — open<br>• `registered` — 401 if anon<br>• `followers` — 403 if anon/non-follower<br>• `subscribers` — 403 if anon/non-subscriber |
| POST   | `/biolinks/{alias}/subscribe`     | Public subscribe to creator's email list. Body: `email`, `name?`. Rate-limited 10/min.                   |

Optional bearer token honored for visibility checks (see `/biolinks/{alias}`).

## Feed

| Method | Path                          | Notes                                                                            |
| ------ | ----------------------------- | -------------------------------------------------------------------------------- |
| GET    | `/feed`                       | Global feed. Anon viewers see only `public`. Authed viewers also see `registered`, `followers` (only for creators they follow), and `subscribers` (only for creators they subscribe to via email). |
| GET    | `/creators/{handle}/feed`     | Same filtering, scoped to one creator.                                           |

## Follows (auth)

| Method | Path                       | Description                       |
| ------ | -------------------------- | --------------------------------- |
| POST   | `/follows/{userId}`        | Follow a creator.                 |
| DELETE | `/follows/{userId}`        | Unfollow.                         |
| GET    | `/follows/following`       | Paginated creators you follow.    |
| GET    | `/follows/followers`       | Paginated users following you.    |

## Subscribers (creator-side, auth)

| Method | Path                      | Description                                                  |
| ------ | ------------------------- | ------------------------------------------------------------ |
| GET    | `/subscribers`            | List of YOUR subscribers. Filters: `status`, `q`, `per_page`. |
| DELETE | `/subscribers/{id}`       | Mark a subscriber as unsubscribed (soft).                    |

## Discovery (public)

| Method | Path                                  | Description                              |
| ------ | ------------------------------------- | ---------------------------------------- |
| GET    | `/discovery/creators`                 | Paginated discoverable creators. `q?`.   |
| GET    | `/discovery/creators/{handle}`        | Public profile by handle.                |

## Health

| Method | Path        | Description                                  |
| ------ | ----------- | -------------------------------------------- |
| GET    | `/health`   | Liveness check, returns `{status, time}`.    |

---

## Error codes

| Status | code                  | When                                                    |
| ------ | --------------------- | ------------------------------------------------------- |
| 400    | (varies)              | Bad request.                                            |
| 401    | `unauthenticated`     | Missing/invalid bearer token on protected route.        |
| 401    | `auth_required`       | Biolink visibility = `registered/followers/subscribers` and viewer is anon. |
| 401    | `invalid_credentials` | Login failed.                                           |
| 403    | `follow_required`     | Biolink visibility = `followers` and viewer is not following. |
| 403    | `subscribe_required`  | Biolink visibility = `subscribers` and viewer is not subscribed. |
| 403    | `forbidden`           | General authorization failure.                          |
| 404    | `not_found`           | Unknown route or resource.                              |
| 405    | `method_not_allowed`  | Wrong HTTP method.                                      |
| 422    | `validation_failed`   | Body validation. `details` is `{field: [messages]}`.    |
| 429    | `rate_limited`        | Throttled (login/register/subscribe = 10/min).          |

## Pagination shape

```json
{
  "data": {
    "items": [ /* ... */ ],
    "meta":  { "current_page": 1, "per_page": 20, "total": 53, "last_page": 3 }
  }
}
```
