#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_NAME="${DOCKER_TEST_IMAGE:-expenzz:docker-smoke}"
CONTAINER_NAME="expenzz-smoke-${RANDOM}-$$"
TEMP_DATA="$(mktemp -d)"
COOKIE_JAR="${TEMP_DATA}/cookies.txt"
LOGIN_HTML="${TEMP_DATA}/login.html"
RESPONSE_HTML="${TEMP_DATA}/response.html"
BASE_URL=""

cleanup() {
  docker rm -f "$CONTAINER_NAME" >/dev/null 2>&1 || true
  if [[ -d "$TEMP_DATA" ]]; then
    docker run --rm --user 0 --volume "$TEMP_DATA:/cleanup" --entrypoint sh "$IMAGE_NAME" \
      -ec 'chmod -R a+rwX /cleanup' >/dev/null 2>&1 || true
  fi
  rm -rf "$TEMP_DATA"
}
trap cleanup EXIT
chmod 0777 "$TEMP_DATA"

if [[ "${DOCKER_TEST_SKIP_BUILD:-0}" != "1" ]]; then
  docker build --tag "$IMAGE_NAME" "$ROOT_DIR"
fi

echo "Checking image for baked-in runtime SQLite files"
docker run --rm --entrypoint sh "$IMAGE_NAME" -ec '
  test ! -e /var/www/html/data/finance.db
  test -z "$(find /var/www/html -type f \( -name "*.db" -o -name "*.sqlite" -o -name "*.sqlite3" -o -name "*.bak" -o -name "*.log" \) -print -quit)"
  test -z "$(find /var/www/html -type d \( -name backups -o -name logs \) -print -quit)"
'

docker run --detach --name "$CONTAINER_NAME" --publish 127.0.0.1::80 \
  --volume "$TEMP_DATA:/var/www/html/data" "$IMAGE_NAME" >/dev/null
HOST_PORT="$(docker port "$CONTAINER_NAME" 80/tcp | sed 's/.*://')"
BASE_URL="http://127.0.0.1:${HOST_PORT}"
for _ in $(seq 1 40); do
  HTTP_STATUS="$(curl --silent --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" --output "$LOGIN_HTML" --write-out '%{http_code}' "$BASE_URL/login" || true)"
  if [[ "$HTTP_STATUS" == '200' ]]; then break; fi
  sleep 1
done
if [[ "${HTTP_STATUS:-}" != '200' ]]; then
  echo "Fresh container login request returned HTTP ${HTTP_STATUS:-no response}:" >&2
  cat "$LOGIN_HTML" >&2 || true
  exit 1
fi

grep -q 'Create Account' "$LOGIN_HTML"
if grep -q 'Sign In' "$LOGIN_HTML"; then
  echo "Fresh database unexpectedly shows the sign-in flow" >&2
  exit 1
fi

csrf_token() {
  python3 -c 'import html,re,sys; m=re.search("name=\\\"csrf_token\\\" value=\\\"([^\\\"]+)\\\"",sys.stdin.read()); print(html.unescape(m.group(1)) if m else "")' < "$1"
}
TOKEN="$(csrf_token "$LOGIN_HTML")"
test -n "$TOKEN"
echo "Creating the first account through the setup page"
curl --silent --show-error --fail --location --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
  --data-urlencode "csrf_token=$TOKEN" --data-urlencode 'username=docker-smoke-user' \
  --data-urlencode 'password=docker-smoke-password-2026' "$BASE_URL/login" --output "$RESPONSE_HTML"
grep -q 'Dashboard' "$RESPONSE_HTML"

query_db() { docker exec "$CONTAINER_NAME" sqlite3 /var/www/html/data/finance.db "$1"; }
assert_zero_tables() {
  local query="SELECT (SELECT COUNT(*) FROM accounts),(SELECT COUNT(*) FROM transactions),(SELECT COUNT(*) FROM transfers),(SELECT COUNT(*) FROM bills),(SELECT COUNT(*) FROM paylater_plans),(SELECT COUNT(*) FROM commitments),(SELECT COUNT(*) FROM budgets),(SELECT COUNT(*) FROM paylater_installments);"
  local counts
  counts="$(query_db "$query")"
  [[ "$counts" == '0|0|0|0|0|0|0|0' ]] || { echo "Expected an empty finance ledger after setup; got $counts" >&2; exit 1; }
}
# Verify there is no built-in admin/admin account. Use a new session so the
# failed login cannot disturb the valid first-user session.
FRESH_COOKIES="${TEMP_DATA}/fresh-cookies.txt"
curl --silent --show-error --fail --cookie-jar "$FRESH_COOKIES" --cookie "$FRESH_COOKIES" "$BASE_URL/login" --output "$LOGIN_HTML"
TOKEN="$(csrf_token "$LOGIN_HTML")"
curl --silent --show-error --fail --location --cookie-jar "$FRESH_COOKIES" --cookie "$FRESH_COOKIES" \
  --data-urlencode "csrf_token=$TOKEN" --data-urlencode 'username=admin' --data-urlencode 'password=admin' \
  "$BASE_URL/login" --output "$RESPONSE_HTML"
grep -q 'Invalid credentials' "$RESPONSE_HTML"
echo "Verified admin/admin is rejected"

assert_zero_tables
[[ "$(query_db 'SELECT COUNT(*) FROM users')" == '1' ]]
[[ "$(query_db "SELECT COUNT(*) FROM categories WHERE name IN ('Food & Dining','Fuel & Transport','Utilities','Groceries','Entertainment','Healthcare','Motorcycle Maintenance','Subscriptions','Salary','Side Hustle','Miscellaneous')")" -ge 10 ]]
[[ "$(query_db "SELECT COUNT(*) FROM accounts WHERE name LIKE '%Maybank%'")" == '0' ]]
echo "Fresh setup created only generic category defaults and no financial records"

# Log back in as the created user, then create a real account and transaction
# through the application's CSRF-protected HTTP forms.
curl --silent --show-error --fail --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" "$BASE_URL/login" --output "$LOGIN_HTML"
TOKEN="$(csrf_token "$LOGIN_HTML")"
curl --silent --show-error --fail --location --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
  --data-urlencode "csrf_token=$TOKEN" --data-urlencode 'username=docker-smoke-user' \
  --data-urlencode 'password=docker-smoke-password-2026' "$BASE_URL/login" --output "$RESPONSE_HTML"
grep -q 'Dashboard' "$RESPONSE_HTML"

curl --silent --show-error --fail --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" "$BASE_URL/accounts" --output "$RESPONSE_HTML"
TOKEN="$(csrf_token "$RESPONSE_HTML")"
curl --silent --show-error --fail --location --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
  --data-urlencode "csrf_token=$TOKEN" --data-urlencode 'name=Docker Persistence Account' \
  --data-urlencode 'kind=savings' --data-urlencode 'opening_balance=0' \
  --data-urlencode 'start_month=2026-09' "$BASE_URL/accounts/save" --output /dev/null
ACCOUNT_ID="$(query_db "SELECT id FROM accounts WHERE name='Docker Persistence Account'")"
test -n "$ACCOUNT_ID"

curl --silent --show-error --fail --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" "$BASE_URL/transactions" --output "$RESPONSE_HTML"
TOKEN="$(csrf_token "$RESPONSE_HTML")"
CATEGORY_ID="$(query_db "SELECT id FROM categories WHERE name='Food & Dining' AND type='expense' LIMIT 1")"
TODAY="$(date +%F)"
curl --silent --show-error --fail --location --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
  --data-urlencode "csrf_token=$TOKEN" --data-urlencode 'action=add_transaction' \
  --data-urlencode "account_id=$ACCOUNT_ID" --data-urlencode 'type=expense' \
  --data-urlencode 'amount=12.34' --data-urlencode "category_id=$CATEGORY_ID" \
  --data-urlencode "date=$TODAY" --data-urlencode 'description=Docker persistence smoke transaction' \
  "$BASE_URL/transactions" --output /dev/null
[[ "$(query_db "SELECT COUNT(*) FROM transactions WHERE description='Docker persistence smoke transaction'")" == '1' ]]
echo "Created an account and transaction through the application forms"

# Recreate the container with the same empty-temp-directory bind mount.
docker rm -f "$CONTAINER_NAME" >/dev/null
CONTAINER_NAME="${CONTAINER_NAME}-recreated"
docker run --detach --name "$CONTAINER_NAME" --publish 127.0.0.1::80 \
  --volume "$TEMP_DATA:/var/www/html/data" "$IMAGE_NAME" >/dev/null
HOST_PORT="$(docker port "$CONTAINER_NAME" 80/tcp | sed 's/.*://')"
BASE_URL="http://127.0.0.1:${HOST_PORT}"
for _ in $(seq 1 40); do
  if curl --silent --fail --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" "$BASE_URL/login" --output "$LOGIN_HTML"; then break; fi
  sleep 1
done
grep -q 'Sign In' "$LOGIN_HTML"
if grep -q 'Create Account' "$LOGIN_HTML"; then
  echo "Recreated container lost its persisted user" >&2
  exit 1
fi
TOKEN="$(csrf_token "$LOGIN_HTML")"
curl --silent --show-error --fail --location --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
  --data-urlencode "csrf_token=$TOKEN" --data-urlencode 'username=docker-smoke-user' \
  --data-urlencode 'password=docker-smoke-password-2026' "$BASE_URL/login" --output "$RESPONSE_HTML"
grep -q 'Dashboard' "$RESPONSE_HTML"
[[ "$(query_db 'SELECT COUNT(*) FROM users')" == '1' ]]
[[ "$(query_db "SELECT COUNT(*) FROM accounts WHERE name='Docker Persistence Account'")" == '1' ]]
[[ "$(query_db "SELECT COUNT(*) FROM transactions WHERE description='Docker persistence smoke transaction'")" == '1' ]]

echo "Docker image, fresh-install, and persistence smoke checks passed."
