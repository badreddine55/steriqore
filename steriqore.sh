#!/usr/bin/env bash
# =============================================================================
# steriqore.sh — local Docker deploy / watch helper
# =============================================================================
# Usage:
#   ./steriqore.sh              Build, start, and watch for code changes (default)
#   ./steriqore.sh up           Build & start in the background (no watch)
#   ./steriqore.sh watch        Same as default: up --build --watch
#   ./steriqore.sh rebuild      Force recreate all containers (fresh deploy)
#   ./steriqore.sh down         Stop and remove containers
#   ./steriqore.sh restart      Restart app container
#   ./steriqore.sh logs [svc]   Tail logs (default: app)
#   ./steriqore.sh status       Show container status
#   ./steriqore.sh shell        Open a shell in the app container
#   ./steriqore.sh art <cmd>    Run artisan inside the app container
#   ./steriqore.sh assets       Build frontend assets on the host, then sync into app
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

COMPOSE=(docker compose -f "$ROOT_DIR/docker-compose.yml")

red()    { printf '\033[0;31m%s\033[0m\n' "$*"; }
green()  { printf '\033[0;32m%s\033[0m\n' "$*"; }
yellow() { printf '\033[0;33m%s\033[0m\n' "$*"; }
blue()   { printf '\033[0;34m%s\033[0m\n' "$*"; }

require_docker() {
  if ! command -v docker >/dev/null 2>&1; then
    red "Docker is not installed or not in PATH."
    exit 1
  fi

  if ! docker compose version >/dev/null 2>&1; then
    red "Docker Compose V2 is required (docker compose)."
    exit 1
  fi
}

print_urls() {
  echo
  green "Steriqore is running:"
  echo "  App:          http://localhost:8010"
  echo "  Horizon:      http://localhost:8010/horizon"
  echo "  Mailpit:      http://localhost:8035"
  echo "  MinIO API:    http://localhost:9023"
  echo "  MinIO UI:     http://localhost:9024  (minioadmin / minioadmin)"
  echo "  Postgres:     localhost:5455  (saw / secret)"
  echo "  Redis:        localhost:6395"
  echo
}

cmd_up() {
  blue "→ Building and starting containers..."
  "${COMPOSE[@]}" up -d --build --remove-orphans
  print_urls
  yellow "Tip: run ./steriqore.sh watch to auto-apply code changes."
}

cmd_watch() {
  blue "→ Building, starting, and watching for changes..."
  yellow "PHP/config changes sync + restart Octane automatically."
  yellow "Frontend JS/CSS: run ./steriqore.sh assets after UI changes (or npm run build)."
  echo
  print_urls
  # Foreground: keeps watching until Ctrl+C
  "${COMPOSE[@]}" up --build --remove-orphans --watch
}

cmd_rebuild() {
  blue "→ Force rebuilding and recreating containers..."
  "${COMPOSE[@]}" up -d --build --force-recreate --remove-orphans
  print_urls
}

cmd_down() {
  blue "→ Stopping containers..."
  "${COMPOSE[@]}" down --remove-orphans
  green "Stopped."
}

cmd_restart() {
  blue "→ Restarting app..."
  "${COMPOSE[@]}" restart app
  green "App restarted."
}

cmd_logs() {
  local service="${1:-app}"
  "${COMPOSE[@]}" logs -f --tail=100 "$service"
}

cmd_status() {
  "${COMPOSE[@]}" ps
}

cmd_shell() {
  "${COMPOSE[@]}" exec app sh
}

cmd_art() {
  if [[ $# -eq 0 ]]; then
    red "Usage: ./steriqore.sh art <artisan args>"
    exit 1
  fi
  "${COMPOSE[@]}" exec app php artisan "$@"
}

cmd_assets() {
  if [[ ! -f package.json ]]; then
    red "package.json not found."
    exit 1
  fi

  blue "→ Building frontend assets on the host..."
  if command -v npm >/dev/null 2>&1; then
    npm run build
  else
    red "npm is not available on the host. Install Node/npm or rebuild the image."
    exit 1
  fi

  if "${COMPOSE[@]}" ps --status running --services 2>/dev/null | grep -qx app; then
    blue "→ Copying build into the running app container..."
    docker cp "$ROOT_DIR/public/build/." steriqore-app:/app/public/build/
    # Octane keeps the Vite manifest in memory; restart so new hashed assets resolve.
    blue "→ Restarting app so Octane picks up the new Vite manifest..."
    "${COMPOSE[@]}" restart app
    green "Assets updated in container."
  else
    yellow "App container is not running. Start with ./steriqore.sh up — next image build will include assets."
  fi
}

cmd_help() {
  cat <<'EOF'
steriqore.sh — Docker deploy helper

Commands:
  (none) / watch   Build, start, and watch file changes (recommended for day-to-day)
  up               Build & start in background
  rebuild          Force recreate everything
  down             Stop containers
  restart          Restart the app container
  logs [service]   Follow logs (default: app)
  status           Show container status
  shell            Shell into the app container
  art <args>       Run php artisan inside the app
  assets           Build Vite assets and copy into the running container
  help             Show this help

Examples:
  ./steriqore.sh
  ./steriqore.sh up
  ./steriqore.sh logs
  ./steriqore.sh art migrate
  ./steriqore.sh assets
EOF
}

main() {
  require_docker

  local cmd="${1:-watch}"
  shift || true

  case "$cmd" in
    up)       cmd_up "$@" ;;
    watch)    cmd_watch "$@" ;;
    rebuild)  cmd_rebuild "$@" ;;
    down)     cmd_down "$@" ;;
    restart)  cmd_restart "$@" ;;
    logs)     cmd_logs "$@" ;;
    status|ps) cmd_status "$@" ;;
    shell|bash|sh) cmd_shell "$@" ;;
    art|artisan) cmd_art "$@" ;;
    assets|build-assets) cmd_assets "$@" ;;
    help|-h|--help) cmd_help ;;
    *)
      red "Unknown command: $cmd"
      echo
      cmd_help
      exit 1
      ;;
  esac
}

main "$@"
