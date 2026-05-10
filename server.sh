#!/bin/bash

case "$1" in
  up)
    sed -i '/^HOST_UID=/d' .env
    sed -i '/^HOST_GID=/d' .env
    
    echo "" >> .env
    echo "HOST_UID=$(id -u)" >> .env
    echo "HOST_GID=$(id -g)" >> .env

#   podman compose --profile apache up -d
#   podman compose --profile apache up --build
    docker compose --profile apache up -d
#   docker compose --profile apache up --build
    ;;
  down)
#   podman compose --profile apache down
    docker compose --profile apache down
    ;;
  *)
    echo "Uso: $0 {up|down}"
    exit 1
    ;;
esac
