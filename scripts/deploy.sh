#!/usr/bin/env bash
#
# Deploy script for EICAL-REGISTRO (bundle + server-side merge/build).
#
# Targets:
#   sandbox    -> sandbox host (branch develop)
#   production -> production host (branch main)
#
# Usage:
#   ./scripts/deploy.sh sandbox
#   ./scripts/deploy.sh production
#
# Credentials are NEVER embedded in this repo. Export them in the shell or
# put them in ~/.config/eical/deploy.env (chmod 600, not versioned), e.g.:
#
#   EICAL_SSH_HOST_SANDBOX="sandbox.ejemplo.org"
#   EICAL_SSH_HOST_PRODUCTION="registro.ejemplo.org"
#   EICAL_SSH_USER="user"
#   EICAL_SUDO_PW="<password sudo>"
#   EICAL_DEPLOY_ROOTS_SANDBOX="/www/wwwroot/sandbox.ejemplo.org"
#   EICAL_DEPLOY_ROOTS_PRODUCTION="/www/wwwroot/registro.ejemplo.org"
#   EICAL_PHP_BIN="/www/server/php/84/bin/php"
#
# Prerequisitos: sshpass.

set -euo pipefail

TARGET="${1:-sandbox}"

ENV_FILE="${EICAL_DEPLOY_ENV_FILE:-$HOME/.config/eical/deploy.env}"
if [ -f "$ENV_FILE" ]; then
    # shellcheck source=/dev/null
    . "$ENV_FILE"
fi

SSH_USER="${EICAL_SSH_USER:-}"
SUDO_PW="${EICAL_SUDO_PW:-}"
PHP_BIN="${EICAL_PHP_BIN:-/www/server/php/84/bin/php}"

if [ -z "$SSH_USER" ] || [ -z "$SUDO_PW" ]; then
    echo "Error: faltan credenciales (EICAL_SSH_USER / EICAL_SUDO_PW)." >&2
    echo "Defínelas en el entorno o en ${ENV_FILE} (chmod 600)." >&2
    exit 1
fi

# --- Configuración por objetivo -----------------------------------------
if [ "$TARGET" = "sandbox" ]; then
    SSH_HOST="${EICAL_SSH_HOST_SANDBOX:-}"
    DEPLOY_ROOT="${EICAL_DEPLOY_ROOTS_SANDBOX:-}"
    BRANCH="develop"
    BUNDLE="/tmp/eical-sandbox-deploy.bundle"
elif [ "$TARGET" = "production" ]; then
    SSH_HOST="${EICAL_SSH_HOST_PRODUCTION:-}"
    DEPLOY_ROOT="${EICAL_DEPLOY_ROOTS_PRODUCTION:-}"
    BRANCH="main"
    BUNDLE="/tmp/eical-prod-deploy.bundle"
else
    echo "Error: target debe ser 'sandbox' o 'production'" >&2
    exit 1
fi

if [ -z "$SSH_HOST" ] || [ -z "$DEPLOY_ROOT" ]; then
    echo "Error: faltan rutas/host para '$TARGET' en el entorno o ${ENV_FILE}." >&2
    exit 1
fi

echo "==> Desplegando rama '$BRANCH' a '$TARGET' ($SSH_HOST)"


echo "==> 0. Asegurando branch de trabajo '$BRANCH' local"
LOCAL_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [ "$LOCAL_BRANCH" != "$BRANCH" ]; then
    git checkout "$BRANCH"
fi
git pull --ff-only origin "$BRANCH"

SSH_TARGET="${SSH_USER}@${SSH_HOST}"
ssh_cmd() { sshpass -p "$SUDO_PW" ssh -o StrictHostKeyChecking=accept-new "$SSH_TARGET" "$@"; }
scp_up()  { sshpass -p "$SUDO_PW" scp -o StrictHostKeyChecking=accept-new "$1" "${SSH_TARGET}:$2"; }


echo "==> 1. Creando bundle de '$BRANCH'"
git bundle create "$BUNDLE" "$BRANCH"
echo "    bundle: $(git rev-parse --short "$BRANCH")"

echo "==> 2. Subiendo bundle"
scp_up "$BUNDLE" "$BUNDLE"

echo "==> 3. Integrando en el servidor ($DEPLOY_ROOT)"
PRIOR_HEAD="$(ssh_cmd "echo '$SUDO_PW' | base64 -d | sudo -S bash -c 'cd '$DEPLOY_ROOT' && git rev-parse HEAD 2>/dev/null' | tr -d '[:space:]' || true")"
ssh_cmd "echo '$SUDO_PW' | base64 -d | sudo -S bash -c '
set -e
cd '$DEPLOY_ROOT'
git fetch '$BUNDLE' '$BRANCH':refs/remotes/deploy/$BRANCH 2>&1 | tail -2
REMOTE=\$(git rev-parse deploy/$BRANCH)
CUR=\$(git rev-parse HEAD)
if [ \"\$REMOTE\" != \"\$CUR\" ]; then
    echo \"    Historial divergido; alineando a \$(git rev-parse --short deploy/$BRANCH)\"
    git reset --hard deploy/$BRANCH
else
    echo \"    Ya alineado en \$(git rev-parse --short HEAD)\"
fi
'"
NEW_HEAD="$(ssh_cmd "echo '$SUDO_PW' | base64 -d | sudo -S bash -c 'cd '$DEPLOY_ROOT' && git rev-parse HEAD 2>/dev/null' | tr -d '[:space:]'")"

echo "==> 4. Build y optimize (solo si cambian recursos)"
ssh_cmd "echo '$SUDO_PW' | base64 -d | sudo -S -u www bash -c '
set -e
cd '$DEPLOY_ROOT'
export PATH='$PHP_BIN':\$PATH
MIG=\$(git diff --name-only '$PRIOR_HEAD' '$NEW_HEAD' -- database/migrations 2>/dev/null | wc -l | tr -d \" \")
VUE=\$(git diff --name-only '$PRIOR_HEAD' '$NEW_HEAD' -- resources package.json package-lock.json 2>/dev/null | wc -l | tr -d \" \")
echo \"    migraciones_afectadas=\$MIG vue_afectado=\$VUE\"
if [ \"\$MIG\" -gt 0 ]; then
    echo \"    -> Ejecutando migrate --force\"
    php artisan migrate --force 2>&1 | tail -5
fi
if [ \"\$VUE\" -gt 0 ] || [ ! -d public/build ]; then
    echo \"    -> npm ci && npm run build\"
    npm ci 2>&1 | tail -3
    npm run build 2>&1 | tail -5
fi
echo \"    -> php artisan optimize\"
php artisan optimize 2>&1 | tail -6
'"

echo "==> 5. Verificación HTTP"
code=$(curl -s -o /dev/null -w "%{http_code}" "https://$SSH_HOST/login")
echo "    https://$SSH_HOST/login -> HTTP $code"

echo "==> 6. Limpieza"
ssh_cmd "rm -f '$BUNDLE'" 2>/dev/null || true
rm -f "$BUNDLE"

echo ""
echo "==> Deploy completado a '$TARGET' en $(git rev-parse --short "$BRANCH")"