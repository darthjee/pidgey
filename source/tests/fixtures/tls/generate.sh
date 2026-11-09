#!/bin/sh
#
# Regenerates the TLS fixtures used by the email test infrastructure.
#
# TEST-ONLY MATERIAL: these certificates and keys are committed on purpose and
# are public. They must never be used outside tests.
#
# Each run creates a new test CA, signs the leaf certificates with it and then
# deletes the CA key, so the CA key is never committed.
#
# Files written (in this directory):
#   ca.pem                                - test CA certificate
#   server.pem / server-key.pem           - leaf for mailpit-starttls, mailpit-ssl, localhost
#                                           and the IP literal 127.0.0.1 (IP SAN, for the
#                                           fake SMTP server)
#   wrong-name.pem / wrong-name-key.pem   - leaf for wrong-name.invalid
#   untrusted.pem / untrusted-key.pem     - self-signed leaf for localhost

set -eu

cd "$(dirname "$0")"

DAYS=36500
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT INT TERM

# Test CA
openssl req -x509 -new -newkey rsa:2048 -nodes -sha256 -days "$DAYS" \
  -subj "/CN=Pidgey Test CA" \
  -keyout "$WORK/ca-key.pem" -out ca.pem \
  -addext "basicConstraints=critical,CA:true" \
  -addext "keyUsage=critical,keyCertSign,cRLSign"

# sign_leaf <name> <cn> <subjectAltName>
sign_leaf() {
  name=$1
  cn=$2
  san=$3

  openssl req -new -newkey rsa:2048 -nodes -sha256 \
    -subj "/CN=$cn" \
    -keyout "$name-key.pem" -out "$WORK/$name.csr"

  cat > "$WORK/$name.ext" <<EOF
basicConstraints=critical,CA:false
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=$san
EOF

  openssl x509 -req -sha256 -days "$DAYS" \
    -in "$WORK/$name.csr" \
    -CA ca.pem -CAkey "$WORK/ca-key.pem" -set_serial "0x$(openssl rand -hex 16)" \
    -extfile "$WORK/$name.ext" \
    -out "$name.pem"
}

sign_leaf server mailpit-ssl "DNS:mailpit-starttls,DNS:mailpit-ssl,DNS:localhost,IP:127.0.0.1"
sign_leaf wrong-name wrong-name.invalid "DNS:wrong-name.invalid"

# Self-signed leaf, not issued by the test CA
openssl req -x509 -new -newkey rsa:2048 -nodes -sha256 -days "$DAYS" \
  -subj "/CN=localhost" \
  -keyout untrusted-key.pem -out untrusted.pem \
  -addext "basicConstraints=critical,CA:false" \
  -addext "extendedKeyUsage=serverAuth" \
  -addext "subjectAltName=DNS:localhost"

# The CA key lives only in $WORK and is removed by the trap.
rm -f "$WORK/ca-key.pem"

# Test-only keys: readable by everyone so the Mailpit container user can read
# them through the read-only mount.
chmod 0644 ca.pem server.pem server-key.pem wrong-name.pem wrong-name-key.pem \
  untrusted.pem untrusted-key.pem
