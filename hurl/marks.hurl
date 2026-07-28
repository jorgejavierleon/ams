# Marks API smoke flow (Sanctum) — see issue #49.
#
# Run:
#   hurl --variables-file hurl/marks.env --test hurl/marks.hurl
# or pass variables inline:
#   hurl --variable host=http://localhost \
#        --variable email=you@example.com \
#        --variable password=secret \
#        --variable device_name=hurl-cli \
#        --test hurl/marks.hurl
#
# Copy hurl/marks.env.example to hurl/marks.env and fill in a real employee's
# credentials first. The employee must be active and hold ClockOwn:Mark /
# ViewOwn:Mark (any seeded employee does).

# 1. Unauthenticated punches are rejected.
POST {{host}}/api/marks
Accept: application/json
Content-Type: application/json
{
  "type": "IN",
  "datetime": "2026-07-24T09:00:00-04:00"
}
HTTP 401

# 2. Exchange the employee credentials for a device bearer token.
POST {{host}}/api/sanctum/token
Accept: application/json
Content-Type: application/json
{
  "email": "{{email}}",
  "password": "{{password}}",
  "device_name": "{{device_name}}"
}
HTTP 200
[Captures]
token: jsonpath "$.token"

# 3. Register an entry punch with geolocation.
POST {{host}}/api/marks
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{token}}
{
  "type": "IN",
  "datetime": "2026-07-24T09:00:00-04:00",
  "lat": -33.4489,
  "lng": -70.6693
}
HTTP 201
[Asserts]
jsonpath "$.mark_id" isInteger
jsonpath "$.hash" exists
jsonpath "$.type" == "in"
jsonpath "$.datetime" exists
[Captures]
mark_id: jsonpath "$.mark_id"
hash: jsonpath "$.hash"

# 4. Read that punch back — reads are scoped to the authenticated employee.
GET {{host}}/api/marks/{{mark_id}}
Accept: application/json
Authorization: Bearer {{token}}
HTTP 200
[Asserts]
jsonpath "$.mark_id" == {{mark_id}}
jsonpath "$.hash" == "{{hash}}"

# 5. The recent-marks list includes the punch we just created.
GET {{host}}/api/marks
Accept: application/json
Authorization: Bearer {{token}}
HTTP 200
[Asserts]
jsonpath "$" count >= 1
jsonpath "$[0].mark_id" exists

# 6. Register the matching exit punch.
POST {{host}}/api/marks
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{token}}
{
  "type": "OUT",
  "datetime": "2026-07-24T18:00:00-04:00"
}
HTTP 201
[Asserts]
jsonpath "$.type" == "out"
