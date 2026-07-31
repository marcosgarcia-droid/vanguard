#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_FILE="scripts/test-facial-vision-e2e.sh"
readonly TEST_HOST_FILE="src/tests/E2E/Modules/Operations/Infrastructure/Images/LocalVision/LocalVisionFacialPhotoEndToEndTest.php"
readonly TEST_CONTAINER_FILE="tests/E2E/Modules/Operations/Infrastructure/Images/LocalVision/LocalVisionFacialPhotoEndToEndTest.php"
readonly LOCK_FILE="/tmp/vanguard-facial-vision-e2e.lock"

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"

if [[ -z "$ROOT" ]]; then
    printf 'Execute este script dentro do repositório VANGUARD.\n' >&2
    exit 1
fi

cd "$ROOT"

MODE="${1:---run}"

case "$MODE" in
    --run)
        ;;
    --check)
        ;;
    *)
        printf 'Uso: %s [--check|--run]\n' "$0" >&2
        exit 64
        ;;
esac

fail()
{
    printf '\nERRO: %s\n' "$1" >&2
    exit 1
}

section()
{
    printf '\n============================================================\n'
    printf '%s\n' "$1"
    printf '============================================================\n'
}

require_command()
{
    local command_name="$1"

    if ! command -v "$command_name" >/dev/null 2>&1; then
        fail "Comando obrigatório não encontrado: ${command_name}"
    fi
}

repository_status()
{
    git status \
        --short \
        --untracked-files=all \
        | sort
}

app_running_id()
{
    docker compose \
        ps \
        --status running \
        -q app 2>/dev/null \
        | sed -n '1p' \
        || true
}

facial_running_id()
{
    docker compose \
        --profile facial-vision \
        ps \
        --status running \
        -q facial-vision 2>/dev/null \
        | sed -n '1p' \
        || true
}

facial_existing_ids()
{
    docker compose \
        --profile facial-vision \
        ps \
        --all \
        -q facial-vision 2>/dev/null \
        || true
}

remove_synthetic_images()
{
    local app_id

    app_id="$(app_running_id)"

    if [[ -z "$app_id" ]]; then
        return 0
    fi

    docker compose exec -T app sh -lc \
        "find /tmp -maxdepth 1 -type f -name 'vanguard-facial-vision-e2e-*' -delete" \
        </dev/null
}

validate_repository()
{
    if [[ "$(git branch --show-current)" != "main" ]]; then
        fail "A branch atual não é main."
    fi

    if [[ -n "$(git diff --cached --name-only)" ]]; then
        fail "O staging precisa estar vazio."
    fi

    if [[ ! -f "$SCRIPT_FILE" ]]; then
        fail "O script executor não foi encontrado."
    fi

    if [[ ! -x "$SCRIPT_FILE" ]]; then
        fail "O script executor não está executável."
    fi

    if [[ ! -f "$TEST_HOST_FILE" ]]; then
        fail "O teste E2E não foi encontrado."
    fi

    git diff --check
    git diff --cached --check
}

validate_environment()
{
    local command_name
    local app_id
    local running_facial
    local existing_facial

    for command_name in \
        bash \
        docker \
        flock \
        git \
        mktemp \
        od \
        sha256sum
    do
        require_command "$command_name"
    done

    if ! docker info >/dev/null 2>&1; then
        fail "O Docker não está acessível."
    fi

    docker compose config >/dev/null

    if ! docker compose \
        --profile facial-vision \
        config \
        --services \
        | grep -Fxq facial-vision
    then
        fail "O serviço facial-vision não foi encontrado."
    fi

    app_id="$(app_running_id)"

    if [[ -z "$app_id" ]]; then
        fail "O container app precisa estar em execução."
    fi

    running_facial="$(facial_running_id)"

    if [[ -n "$running_facial" ]]; then
        fail "O facial-vision já está em execução."
    fi

    existing_facial="$(facial_existing_ids)"

    if [[ -n "$existing_facial" ]]; then
        fail "Existe um container facial-vision residual."
    fi

    bash -n "$SCRIPT_FILE"

    docker compose exec -T app php -l \
        "$TEST_CONTAINER_FILE" \
        </dev/null
}

validate_repository
validate_environment

if [[ "$MODE" == "--check" ]]; then
    section "VERIFICAÇÃO ESTÁTICA APROVADA"

    printf 'Script Bash: sintaxe válida\n'
    printf 'Teste PHP: sintaxe válida\n'
    printf 'Container app: em execução\n'
    printf 'facial-vision: parado\n'
    printf 'Nenhum container ou rede temporária foi criado\n'

    exit 0
fi

exec 9>"$LOCK_FILE"

if ! flock -n 9; then
    fail "Já existe uma execução E2E facial em andamento."
fi

INITIAL_HEAD="$(git rev-parse HEAD)"
INITIAL_STATUS="$(repository_status)"
INITIAL_SCRIPT_SHA="$(sha256sum "$SCRIPT_FILE" | awk '{print $1}')"
INITIAL_TEST_SHA="$(sha256sum "$TEST_HOST_FILE" | awk '{print $1}')"

TOKEN=""
NETWORK_NAME=""
OVERRIDE_FILE=""
APP_ID="$(app_running_id)"
FACIAL_ID=""

compose_e2e()
{
    docker compose \
        --project-directory "$ROOT" \
        -f "$ROOT/compose.yaml" \
        -f "$OVERRIDE_FILE" \
        --profile facial-vision \
        "$@"
}

cleanup()
{
    local original_status=$?
    local final_status=$original_status
    local container_id
    local remaining_facial
    local current_status
    local current_script_sha
    local current_test_sha

    trap - EXIT INT TERM
    set +e

    if (( original_status != 0 )) && [[ -n "$FACIAL_ID" ]]; then
        printf '\n--- logs sanitizados do facial-vision ---\n' >&2

        if [[ -n "$TOKEN" ]]; then
            docker logs "$FACIAL_ID" 2>&1 \
                | sed "s/${TOKEN}/[TOKEN-REMOVIDO]/g" \
                | tail -n 150 \
                >&2
        else
            docker logs "$FACIAL_ID" 2>&1 \
                | tail -n 150 \
                >&2
        fi
    fi

    while IFS= read -r container_id; do
        if [[ -z "$container_id" ]]; then
            continue
        fi

        docker rm \
            --force \
            "$container_id" \
            >/dev/null 2>&1
    done < <(facial_existing_ids)

    remove_synthetic_images \
        >/dev/null 2>&1

    if [[ -n "$NETWORK_NAME" ]]; then
        if docker network inspect "$NETWORK_NAME" >/dev/null 2>&1; then
            if [[ -n "$APP_ID" ]]; then
                docker network disconnect \
                    --force \
                    "$NETWORK_NAME" \
                    "$APP_ID" \
                    >/dev/null 2>&1
            fi

            docker network rm \
                "$NETWORK_NAME" \
                >/dev/null 2>&1
        fi
    fi

    if [[ -n "$OVERRIDE_FILE" ]]; then
        rm -f -- "$OVERRIDE_FILE"
    fi

    unset VANGUARD_FACIAL_PHOTO_VALIDATION_LOCAL_VISION_TOKEN
    unset VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED

    remaining_facial="$(facial_existing_ids)"

    if [[ -n "$remaining_facial" ]]; then
        printf 'ERRO: existe container facial-vision residual.\n' >&2
        final_status=1
    fi

    if [[ -n "$NETWORK_NAME" ]]; then
        if docker network inspect "$NETWORK_NAME" >/dev/null 2>&1; then
            printf 'ERRO: a rede temporária não foi removida.\n' >&2
            final_status=1
        fi
    fi

    if [[ -z "$(app_running_id)" ]]; then
        printf 'ERRO: o container app não permaneceu em execução.\n' >&2
        final_status=1
    fi

    if [[ "$(git rev-parse HEAD 2>/dev/null)" != "$INITIAL_HEAD" ]]; then
        printf 'ERRO: o HEAD mudou durante o E2E.\n' >&2
        final_status=1
    fi

    current_status="$(repository_status 2>/dev/null)"

    if [[ "$current_status" != "$INITIAL_STATUS" ]]; then
        printf 'ERRO: o workspace mudou durante o E2E.\n' >&2
        printf '%s\n' "$current_status" >&2
        final_status=1
    fi

    current_script_sha="$(
        sha256sum "$SCRIPT_FILE" 2>/dev/null \
            | awk '{print $1}'
    )"

    if [[ "$current_script_sha" != "$INITIAL_SCRIPT_SHA" ]]; then
        printf 'ERRO: o script mudou durante o E2E.\n' >&2
        final_status=1
    fi

    current_test_sha="$(
        sha256sum "$TEST_HOST_FILE" 2>/dev/null \
            | awk '{print $1}'
    )"

    if [[ "$current_test_sha" != "$INITIAL_TEST_SHA" ]]; then
        printf 'ERRO: o teste mudou durante o E2E.\n' >&2
        final_status=1
    fi

    TOKEN=""

    if (( final_status == 0 )); then
        section "E2E FACIAL SINTÉTICO CONCLUÍDO"

        printf 'Resultado: aprovado\n'
        printf 'facial-vision: removido\n'
        printf 'Rede interna temporária: removida\n'
        printf 'Container app: preservado\n'
        printf 'Workspace: inalterado\n'
        printf 'Token efêmero: descartado\n'
        printf 'Imagem sintética: removida\n'
    fi

    exit "$final_status"
}

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

remove_synthetic_images

TOKEN="$(
    od \
        -An \
        -N32 \
        -tx1 \
        /dev/urandom \
        | tr -d ' \n'
)"

if [[ ${#TOKEN} -ne 64 ]]; then
    fail "Não foi possível gerar o token efêmero."
fi

NETWORK_NAME="vanguard-facial-e2e-$(date +%s)-$$"

OVERRIDE_FILE="$(
    mktemp \
        /tmp/vanguard-facial-e2e-compose.XXXXXX.yaml
)"

chmod 0600 "$OVERRIDE_FILE"

section "CRIANDO REDE INTERNA"

docker network create \
    --driver bridge \
    --internal \
    "$NETWORK_NAME" \
    >/dev/null

docker network connect \
    "$NETWORK_NAME" \
    "$APP_ID"

cat >"$OVERRIDE_FILE" <<YAML
services:
  facial-vision:
    networks:
      facial_e2e:
        aliases:
          - facial-vision

networks:
  facial_e2e:
    external: true
    name: ${NETWORK_NAME}
YAML

compose_e2e config >/dev/null

NETWORK_INTERNAL="$(
    docker network inspect \
        --format '{{.Internal}}' \
        "$NETWORK_NAME"
)"

if [[ "$NETWORK_INTERNAL" != "true" ]]; then
    fail "A rede temporária não é interna."
fi

printf 'Rede interna: %s\n' "$NETWORK_NAME"

section "CONSTRUINDO E INICIANDO FACIAL-VISION"

export VANGUARD_FACIAL_PHOTO_VALIDATION_LOCAL_VISION_TOKEN="$TOKEN"
export VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED=true

compose_e2e up \
    --detach \
    --build \
    --no-deps \
    facial-vision

unset VANGUARD_FACIAL_PHOTO_VALIDATION_LOCAL_VISION_TOKEN
unset VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED

FACIAL_ID="$(
    compose_e2e ps \
        -q facial-vision \
        | sed -n '1p'
)"

if [[ -z "$FACIAL_ID" ]]; then
    fail "O container facial-vision não foi criado."
fi

mapfile -t FACIAL_NETWORKS < <(
    docker inspect \
        --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' \
        "$FACIAL_ID" \
        | sed '/^$/d' \
        | sort
)

if (( ${#FACIAL_NETWORKS[@]} != 1 )); then
    printf '%s\n' "${FACIAL_NETWORKS[@]}" >&2
    fail "O facial-vision está conectado a mais de uma rede."
fi

if [[ "${FACIAL_NETWORKS[0]}" != "$NETWORK_NAME" ]]; then
    fail "O facial-vision não está exclusivamente na rede interna."
fi

printf 'Rede exclusiva do serviço: confirmada\n'

section "AGUARDANDO HEALTHCHECK"

HEALTHY=false

for (( attempt = 1; attempt <= 60; attempt++ )); do
    STATE="$(
        docker inspect \
            --format '{{.State.Status}}' \
            "$FACIAL_ID"
    )"

    HEALTH="$(
        docker inspect \
            --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}sem-healthcheck{{end}}' \
            "$FACIAL_ID"
    )"

    if [[ "$STATE" != "running" ]]; then
        fail "facial-vision encerrou com estado ${STATE}."
    fi

    if [[ "$HEALTH" == "healthy" ]]; then
        HEALTHY=true
        break
    fi

    printf 'Tentativa %02d: %s\n' "$attempt" "$HEALTH"
    sleep 2
done

if [[ "$HEALTHY" != "true" ]]; then
    fail "O healthcheck não ficou saudável."
fi

printf 'Healthcheck: healthy\n'

section "VALIDANDO HARDENING"

READ_ONLY="$(
    docker inspect \
        --format '{{.HostConfig.ReadonlyRootfs}}' \
        "$FACIAL_ID"
)"

if [[ "$READ_ONLY" != "true" ]]; then
    fail "O filesystem não está somente leitura."
fi

CAP_DROP="$(
    docker inspect \
        --format '{{json .HostConfig.CapDrop}}' \
        "$FACIAL_ID"
)"

if ! grep -Fq '"ALL"' <<<"$CAP_DROP"; then
    fail "As capabilities não foram removidas."
fi

SECURITY_OPTIONS="$(
    docker inspect \
        --format '{{json .HostConfig.SecurityOpt}}' \
        "$FACIAL_ID"
)"

if ! grep -Fq 'no-new-privileges' <<<"$SECURITY_OPTIONS"; then
    fail "no-new-privileges não está ativo."
fi

PUBLISHED_PORTS="$(
    docker port "$FACIAL_ID" 2>/dev/null \
        || true
)"

if [[ -n "$PUBLISHED_PORTS" ]]; then
    fail "O serviço publicou porta no host."
fi

printf 'Filesystem somente leitura: confirmado\n'
printf 'Capabilities removidas: confirmado\n'
printf 'no-new-privileges: confirmado\n'
printf 'Portas publicadas: nenhuma\n'
printf 'Rede externa: indisponível\n'

section "VALIDANDO DNS INTERNO"

docker compose exec -T app php -r '
    $host = "facial-vision";
    $address = gethostbyname($host);

    if ($address === $host) {
        fwrite(
            STDERR,
            "O serviço facial não foi resolvido internamente.\n"
        );

        exit(1);
    }

    echo "facial-vision resolvido internamente.\n";
' </dev/null

section "EXECUTANDO E2E REAL"

docker compose exec \
    -T \
    -e VANGUARD_FACIAL_VISION_E2E_TOKEN="$TOKEN" \
    app \
    php artisan test \
    --do-not-cache-result \
    "$TEST_CONTAINER_FILE" \
    </dev/null

section "VALIDANDO ARQUIVOS TEMPORÁRIOS"

LEFTOVER_IMAGES="$(
    docker compose exec -T app sh -lc \
        "find /tmp -maxdepth 1 -type f -name 'vanguard-facial-vision-e2e-*' -print" \
        </dev/null
)"

if [[ -n "$LEFTOVER_IMAGES" ]]; then
    printf '%s\n' "$LEFTOVER_IMAGES" >&2
    fail "A imagem sintética não foi removida."
fi

printf 'Imagem sintética temporária: removida\n'

section "VALIDANDO ESTADO FINAL"

if [[ "$(git rev-parse HEAD)" != "$INITIAL_HEAD" ]]; then
    fail "O HEAD mudou durante o teste."
fi

FINAL_STATUS="$(repository_status)"

if [[ "$FINAL_STATUS" != "$INITIAL_STATUS" ]]; then
    printf '%s\n' "$FINAL_STATUS" >&2
    fail "O workspace mudou durante o teste."
fi

printf 'HEAD: inalterado\n'
printf 'Workspace: inalterado\n'
printf 'Execução funcional concluída; iniciando cleanup.\n'
