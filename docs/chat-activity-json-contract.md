# Chat and Activity JSON Contract

Effective date: 2026-02-25

## Scope
- Project chat endpoints
- Task chat endpoints
- Task activity item endpoints

These endpoints are JSON-only response contracts for React/Inertia clients.

## Response envelope
- Success:
  - `ok: true`
  - `data: {...}`
- Validation/guard errors:
  - `ok: false` (where applicable)
  - `message: "..."` with relevant HTTP status code

## Item payload rule
- Chat/activity list items no longer include `html`.
- Payload is always structured JSON (`message` or `activity` object plus metadata).

## Endpoints
- Project chat
  - `GET /projects/{project}/chat/messages`
  - `POST /projects/{project}/chat/messages`
  - `PATCH /projects/{project}/chat/messages/{message}`
- Task chat
  - `GET /projects/{project}/tasks/{task}/chat/messages`
  - `POST /projects/{project}/tasks/{task}/chat/messages`
  - `PATCH /projects/{project}/tasks/{task}/chat/messages/{message}`
- Task activity
  - `GET /projects/{project}/tasks/{task}/activity/items`
  - `POST /projects/{project}/tasks/{task}/activity/items`

## Backward compatibility
- Legacy markers are now optional/ignored:
  - query `structured=1`
  - header `X-Fragment-Format: structured`
- React clients no longer send these markers.

## Non-goals
- No change to SSE stream endpoints.
- No change to upload/download/PDF/callback/webhook endpoint behavior.
