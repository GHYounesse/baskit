#!/usr/bin/env bash
# Deploy script that runs ON THE SERVER, from the folder holding compose.yaml and .env.
# GitHub Actions copies it there and calls it over SSH; you can also run it by hand.
#
#   ./deploy.sh deploy <image-tag>   pull the image, start it, migrate, health-check;
#                                    roll back automatically if the health check fails
#   ./deploy.sh rollback             go back to the previously deployed image tag
#   ./deploy.sh status               show the running tag and containers
#
# The image tag lives in the IMAGE_TAG line of .env, so a plain "docker compose up -d"
# later keeps running the same version. The tag that was running before a deploy is
# kept in .previous_image_tag.
#
# Database migrations are NOT undone by a rollback. Write migrations that the previous
# release can still run against (add columns/tables first, remove them in a later release).
set -euo pipefail

cd "$(dirname "$0")"

ENV_FILE=".env"
PREVIOUS_FILE=".previous_image_tag"
LOCAL_URL="${LOCAL_URL:-http://127.0.0.1:8081/up}"
HEALTH_RETRIES="${HEALTH_RETRIES:-30}"   # x 2 seconds

log() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

current_tag() {
    grep -E '^IMAGE_TAG=' "$ENV_FILE" | tail -n1 | cut -d= -f2- || true
}

set_tag() {
    local tag="$1"
    if grep -qE '^IMAGE_TAG=' "$ENV_FILE"; then
        sed -i "s|^IMAGE_TAG=.*|IMAGE_TAG=${tag}|" "$ENV_FILE"
    else
        printf 'IMAGE_TAG=%s\n' "$tag" >> "$ENV_FILE"
    fi
    export IMAGE_TAG="$tag"
}

healthy() {
    local i
    for ((i = 1; i <= HEALTH_RETRIES; i++)); do
        if curl -fsS --max-time 5 -o /dev/null "$LOCAL_URL"; then
            return 0
        fi
        sleep 2
    done
    return 1
}

start_stack() {
    docker compose up -d --remove-orphans
}

deploy() {
    local new_tag="$1"
    # Tags are git SHAs or "latest"; refuse anything else before it reaches a file or a command.
    [[ "$new_tag" =~ ^[A-Za-z0-9_.-]{1,128}$ ]] || die "invalid image tag: $new_tag"
    [[ -f "$ENV_FILE" ]] || die "$ENV_FILE not found in $(pwd)"

    local old_tag
    old_tag="$(current_tag)"

    log "Deploying $new_tag (currently: ${old_tag:-none})"
    set_tag "$new_tag"

    docker compose pull
    start_stack

    log "Running migrations in a one-off container"
    if ! docker compose run --rm --no-deps -T -e RUN_OPTIMIZE=false app php artisan migrate --force; then
        log "Migration failed"
        rollback_to "$old_tag"
        die "migration failed; rolled back to ${old_tag:-<none>}"
    fi

    log "Waiting for $LOCAL_URL"
    if ! healthy; then
        log "Health check failed"
        rollback_to "$old_tag"
        die "health check failed; rolled back to ${old_tag:-<none>}"
    fi

    # Only a deploy that passed its health check becomes the rollback target.
    if [[ -n "$old_tag" && "$old_tag" != "$new_tag" ]]; then
        printf '%s\n' "$old_tag" > "$PREVIOUS_FILE"
    fi

    docker image prune -f >/dev/null
    log "Deployed $new_tag"
}

rollback_to() {
    local tag="$1"
    if [[ -z "$tag" ]]; then
        echo "No previous image tag known; nothing to roll back to." >&2
        return 0
    fi
    log "Rolling back to $tag"
    set_tag "$tag"
    docker compose pull
    start_stack
    healthy || echo "WARNING: still unhealthy after rolling back to $tag" >&2
}

case "${1:-}" in
    deploy)
        [[ $# -eq 2 ]] || die "usage: $0 deploy <image-tag>"
        deploy "$2"
        ;;
    rollback)
        [[ -f "$PREVIOUS_FILE" ]] || die "no $PREVIOUS_FILE: there is no earlier deploy to go back to"
        rollback_to "$(cat "$PREVIOUS_FILE")"
        ;;
    status)
        echo "IMAGE_TAG=$(current_tag)"
        docker compose ps
        ;;
    *)
        die "usage: $0 {deploy <image-tag>|rollback|status}"
        ;;
esac
