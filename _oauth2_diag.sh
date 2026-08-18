#!/usr/bin/env bash
# Diagnostic-only: curl login to Magento 1, then fetch /oauth2/client/index/ and /oauth2/client/new/
# Verify content blocks / nav links. No file changes.

set -u
BASE="http://www.m1-test.com"
COOKIE_JAR="$(mktemp -t m1cookies.XXXXXX)"
echo "Cookie jar: $COOKIE_JAR"
echo

separator() { echo; echo "============================================================"; echo "$1"; echo "============================================================"; }
hit_count() { # $1=pattern  $2=file  $3=context_lines
  local pat="$1" file="$2" ctx="${3:-2}"
  if [ ! -f "$file" ]; then echo "FILE MISSING: $file"; return; fi
  local n
  n=$(grep -c -F -- "$pat" "$file" 2>/dev/null || true)
  echo "PATTERN: $pat  ->  HITS: $n"
  if [ "$n" != "0" ]; then
    echo "--- context (first $ctx lines around each match) ---"
    grep -n -F -A "$ctx" -B "$ctx" -- "$pat" "$file" | head -40
  fi
}

separator "STEP 1a: GET /customer/account/login/ (grab form_key)"
LOGIN_HTML="$(mktemp -t loginhtml.XXXXXX)"
HTTP_CODE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -o "$LOGIN_HTML" -w "%{http_code}" \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  "$BASE/customer/account/login/")
echo "HTTP $HTTP_CODE  -> $LOGIN_HTML  size=$(wc -c < "$LOGIN_HTML")"

# Extract form_key (extract value attribute out of any input that has name="form_key").
FORM_KEY=$(grep -oE '<input[^>]*name="form_key"[^>]*>' "$LOGIN_HTML" | head -1 \
            | sed -nE 's/.*value="([^"]+)".*/\1/p')
echo "form_key candidate: '$FORM_KEY'"
if [ -z "$FORM_KEY" ]; then
  echo "ERROR: could not extract form_key. First 80 lines of login page:"
  head -80 "$LOGIN_HTML"
  exit 1
fi

separator "STEP 1b: POST /customer/account/loginPost/"
LOGINPOST_HEADERS="$(mktemp -t loginph.XXXXXX)"
LOGINPOST_RESP="$(mktemp -t loginpost.XXXXXX)"
HTTP_CODE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -L -o "$LOGINPOST_RESP" \
  -D "$LOGINPOST_HEADERS" -w "%{http_code}\n%{url_effective}\n" \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  -H "Referer: $BASE/customer/account/login/" \
  -d "login[username]=gao11@21cn.com" \
  -d "login[password]=TestPass123" \
  -d "form_key=$FORM_KEY" \
  "$BASE/customer/account/loginPost/")
echo "HTTP and effective URL:"
echo "$HTTP_CODE"
echo "--- response headers (redirect chain) ---"
head -40 "$LOGINPOST_HEADERS"
echo "--- response body size ---"
echo "size=$(wc -c < "$LOGINPOST_RESP")"
echo "--- cookies after login ---"
grep -v '^#' "$COOKIE_JAR" | awk '{print $6"="$7}'

# Verify login state by hitting /customer/account/
ACCOUNT_HTML="$(mktemp -t account.XXXXXX)"
HTTP_CODE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -o "$ACCOUNT_HTML" -w "%{http_code}" \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  "$BASE/customer/account/")
echo
echo "GET /customer/account/ -> HTTP $HTTP_CODE  size=$(wc -c < "$ACCOUNT_HTML")"
echo "  has 'Log Out'? $(grep -c -F 'Log Out' "$ACCOUNT_HTML")"
echo "  has 'My Dashboard'? $(grep -c -F 'My Dashboard' "$ACCOUNT_HTML")"
echo "  has login form (login[username])? $(grep -c -F 'login[username]' "$ACCOUNT_HTML")"
echo "  has 'My API Clients'? $(grep -c -F 'My API Clients' "$ACCOUNT_HTML")"

separator "STEP 5: /customer/account/ nav links"
for p in "My Account" "My API Clients" "Log Out" "Account Information" "Address Book" "My Orders" "OAuth2" "oauth2/client"; do
  hit_count "$p" "$ACCOUNT_HTML" 0
done

separator "STEP 2: GET /oauth2/client/index/"
INDEX_HTML="$(mktemp -t oauthindex.XXXXXX)"
HTTP_CODE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -o "$INDEX_HTML" -w "%{http_code}" \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  "$BASE/oauth2/client/index/")
echo "HTTP $HTTP_CODE  -> $INDEX_HTML  size=$(wc -c < "$INDEX_HTML")"
echo "--- first 30 lines ---"
head -30 "$INDEX_HTML"

separator "STEP 3: /oauth2/client/index/ required content"
for p in "My API Clients" "Create New API Client" "You have not created any API clients yet." "oauth2/client/new" "Log Out" "My Account"; do
  hit_count "$p" "$INDEX_HTML" 1
done

separator "STEP 4: GET /oauth2/client/new/"
NEW_HTML="$(mktemp -t oauthnew.XXXXXX)"
HTTP_CODE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -o "$NEW_HTML" -w "%{http_code}" \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  "$BASE/oauth2/client/new/")
echo "HTTP $HTTP_CODE  -> $NEW_HTML  size=$(wc -c < "$NEW_HTML")"
echo "--- first 30 lines ---"
head -30 "$NEW_HTML"

separator "STEP 4: /oauth2/client/new/ required content"
for p in "New API Client" "<form" 'name="name"' 'type="text"' "Save" "Back" "My API Clients"; do
  hit_count "$p" "$NEW_HTML" 1
done

separator "STEP 6: All <input> and <form> tags on /oauth2/client/new/"
echo "INPUT TAGS:"
grep -oE '<input[^>]*>' "$NEW_HTML" 2>/dev/null | head -50
echo
echo "FORM TAGS:"
grep -oE '<form[^>]*>' "$NEW_HTML" 2>/dev/null | head -10
echo
echo "H1 TAGS ON INDEX:"
grep -oE '<h1[^>]*>[^<]*</h1>' "$INDEX_HTML" 2>/dev/null | head -10
echo
echo "H1 TAGS ON NEW:"
grep -oE '<h1[^>]*>[^<]*</h1>' "$NEW_HTML" 2>/dev/null | head -10

separator "RAW NAV LINKS in /customer/account/ (left col)"
# Try to extract block-content of any left column link list.
grep -oE '<a[^>]*href="[^"]*oauth2[^"]*"[^>]*>[^<]*</a>' "$ACCOUNT_HTML" 2>/dev/null | head -10
echo
echo "RAW NAV LINKS in /oauth2/client/index/ (left col)"
grep -oE '<a[^>]*href="[^"]*oauth2[^"]*"[^>]*>[^<]*</a>' "$INDEX_HTML" 2>/dev/null | head -10
echo
echo "RAW NAV LINKS in /oauth2/client/new/ (left col)"
grep -oE '<a[^>]*href="[^"]*oauth2[^"]*"[^>]*>[^<]*</a>' "$NEW_HTML" 2>/dev/null | head -10

separator "DONE"
echo "Files kept for inspection:"
echo "  $LOGIN_HTML"
echo "  $LOGINPOST_HEADERS"
echo "  $LOGINPOST_RESP"
echo "  $ACCOUNT_HTML"
echo "  $INDEX_HTML"
echo "  $NEW_HTML"
echo "  $COOKIE_JAR"
