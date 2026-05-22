#!/bin/bash

case "$1" in
  up)
    OFFSET=${2:-0}

    # Bucle para buscar el primer bloque de puertos libres
    while true; do
      PORT_HTTP=$((8080 + OFFSET))
      PORT_HTTPS=$((8443 + OFFSET))

      # ss revisa los puertos TCP/UDP a la escucha. \b asegura que coincida el número exacto.
      if ss -tuln | grep -qE ":($PORT_HTTP|$PORT_HTTPS)\b"; then
        OFFSET=$((OFFSET + 1))
      else
        break
      fi
    done

    echo "=> Puertos asignados: OFFSET=$OFFSET (HTTP: $PORT_HTTP | HTTPS: $PORT_HTTPS)"

    # Limpieza de variables antiguas
    sed -i '/^HOST_UID=/d' .env
    sed -i '/^HOST_GID=/d' .env
    sed -i '/^PORT_HTTP=/d' .env
    sed -i '/^PORT_HTTPS=/d' .env

    # Inserción de las nuevas variables
    echo "" >> .env
    echo "HOST_UID=$(id -u)" >> .env
    echo "HOST_GID=$(id -g)" >> .env
    echo "PORT_HTTP=$PORT_HTTP" >> .env
    echo "PORT_HTTPS=$PORT_HTTPS" >> .env

#   podman compose --profile apache up -d
#   podman compose --profile apache up --build
#   podman compose --profile nginx up -d
#   podman compose --profile nginx up --build
    docker compose --profile apache up -d
#   docker compose --profile apache up --build
#   docker compose --profile nginx up -d
#   docker compose --profile nginx up --build
    ;;
  down)

#   podman compose --profile apache down
    docker compose --profile apache down -v
#   podman compose --profile nginx down
#   docker compose --profile nginx down -v
    ;;
  *)
    echo "Uso: $0 {up|down} [desplazamiento_puertos_inicial]"
    exit 1
    ;;
esac
