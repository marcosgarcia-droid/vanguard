from __future__ import annotations

import hmac
import math
import os
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import Any, Final

from fastapi import (
    Depends,
    FastAPI,
    Header,
    HTTPException,
    Request,
)
from starlette import status
from starlette.concurrency import run_in_threadpool

from app.inference_engine import (
    FacialInferenceError,
    FacialInferenceEvidence,
    FacialInferenceFailure,
    close_cached_facial_inference_engine,
    get_facial_inference_engine,
)
from app.vision_runtime import inspect_vision_runtime

SERVICE: Final[str] = "vanguard-facial-vision"
FOUNDATION_VERSION: Final[str] = "foundation-v1"

DEFAULT_MAXIMUM_REQUEST_BYTES: Final[int] = 5 * 1024 * 1024

ALLOWED_CONTENT_TYPES: Final[frozenset[str]] = frozenset(
    {
        "image/jpeg",
        "image/png",
        "image/webp",
    }
)

ENABLED_VALUES: Final[frozenset[str]] = frozenset(
    {
        "1",
        "true",
        "yes",
        "on",
    }
)

PUBLIC_SCALAR_METRIC_NAMES: Final[tuple[str, ...]] = (
    "image_width",
    "image_height",
    "face_ratio",
    "center_offset_x",
    "center_offset_y",
    "yaw_degrees",
    "pitch_degrees",
    "roll_degrees",
    "left_eye_open_score",
    "right_eye_open_score",
    "centered",
    "frontal",
    "eyes_open",
    "occluded",
    "inference_ms",
)


@asynccontextmanager
async def lifespan(
    _: FastAPI,
) -> AsyncIterator[None]:
    try:
        yield
    finally:
        close_cached_facial_inference_engine()


app = FastAPI(
    title=SERVICE,
    version=FOUNDATION_VERSION,
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
    lifespan=lifespan,
)


def configured_token() -> str:
    """Retorna somente o token interno normalizado, sem registrá-lo."""
    return os.getenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        "",
    ).strip()


def analysis_enabled() -> bool:
    """Mantém o motor desativado diante de valor ausente ou desconhecido."""
    return (
        os.getenv(
            "VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED",
            "false",
        )
        .strip()
        .lower()
        in ENABLED_VALUES
    )


def maximum_request_bytes() -> int:
    """Mantém um limite seguro diante de configuração inválida."""
    raw_value = os.getenv(
        "VANGUARD_FACIAL_VISION_MAXIMUM_REQUEST_BYTES",
        str(DEFAULT_MAXIMUM_REQUEST_BYTES),
    ).strip()

    try:
        value = int(raw_value)
    except ValueError:
        return DEFAULT_MAXIMUM_REQUEST_BYTES

    if value <= 0:
        return DEFAULT_MAXIMUM_REQUEST_BYTES

    return value


async def require_internal_token(
    supplied_token: str | None = Header(
        default=None,
        alias="X-Vanguard-Vision-Token",
    ),
) -> None:
    expected_token = configured_token()

    if not expected_token:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail={
                "code": "internal_auth_unconfigured",
                "message": (
                    "A autenticação interna ainda não foi configurada."
                ),
            },
        )

    if (
        supplied_token is None
        or not hmac.compare_digest(
            supplied_token,
            expected_token,
        )
    ):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={
                "code": "unauthorized",
                "message": "A autenticação interna não foi aceita.",
            },
        )


def request_content_type(
    request: Request,
) -> str:
    return (
        request.headers
        .get(
            "content-type",
            "",
        )
        .split(
            ";",
            maxsplit=1,
        )[0]
        .strip()
        .lower()
    )


async def read_image_bytes(
    request: Request,
) -> bytes:
    """
    Consome o corpo ASGI diretamente em memória, sem parser multipart.
    """
    content_type = request_content_type(request)

    if content_type not in ALLOWED_CONTENT_TYPES:
        raise HTTPException(
            status_code=status.HTTP_415_UNSUPPORTED_MEDIA_TYPE,
            detail={
                "code": "unsupported_media_type",
                "message": "O formato da imagem não é suportado.",
            },
        )

    maximum_bytes = maximum_request_bytes()
    contents = bytearray()

    async for chunk in request.stream():
        if not chunk:
            continue

        contents.extend(chunk)

        if len(contents) > maximum_bytes:
            contents.clear()

            raise HTTPException(
                status_code=status.HTTP_413_CONTENT_TOO_LARGE,
                detail={
                    "code": "image_too_large",
                    "message": (
                        "A imagem excede o limite permitido."
                    ),
                },
            )

    if not contents:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_CONTENT,
            detail={
                "code": "empty_image",
                "message": "A imagem enviada está vazia.",
            },
        )

    return bytes(contents)


def public_scalar_metrics(
    evidence: FacialInferenceEvidence,
) -> dict[str, bool | int | float | None]:
    """Aplica lista permitida antes de cruzar o contrato HTTP."""
    source = evidence.scalar_metrics()
    metrics: dict[str, bool | int | float | None] = {}

    for name in PUBLIC_SCALAR_METRIC_NAMES:
        value = source.get(name)

        if value is None or isinstance(value, bool):
            metrics[name] = value

            continue

        if isinstance(value, int):
            metrics[name] = value

            continue

        if isinstance(value, float) and math.isfinite(value):
            metrics[name] = value

            continue

        raise FacialInferenceError(
            FacialInferenceFailure.InvalidEngineEvidence,
            "O motor facial retornou uma métrica escalar inválida.",
        )

    return metrics


def inference_http_exception(
    error: FacialInferenceError,
) -> HTTPException:
    """Converte falhas internas sem expor exceções ou evidências brutas."""
    if error.failure is FacialInferenceFailure.InvalidImage:
        return HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_CONTENT,
            detail={
                "code": "invalid_image",
                "message": (
                    "A imagem enviada não pôde ser validada."
                ),
            },
        )

    if (
        error.failure
        is FacialInferenceFailure.UnsafeImageDimensions
    ):
        return HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_CONTENT,
            detail={
                "code": "unsafe_image_dimensions",
                "message": (
                    "As dimensões decodificadas excedem o limite seguro."
                ),
            },
        )

    if (
        error.failure
        is FacialInferenceFailure.InvalidEngineEvidence
    ):
        return HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail={
                "code": "invalid_engine_evidence",
                "message": (
                    "O motor facial não produziu evidências confiáveis."
                ),
            },
        )

    return HTTPException(
        status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
        detail={
            "code": "vision_engine_unavailable",
            "message": (
                "O motor de visão facial não está disponível."
            ),
        },
    )


@app.get(
    "/health",
    include_in_schema=False,
)
async def health() -> dict[str, str]:
    """Confirma integridade sem executar inferência."""
    inspect_vision_runtime()

    return {
        "status": "ok",
        "service": SERVICE,
        "service_version": FOUNDATION_VERSION,
        "engine_dependencies": "ready",
        "engine": (
            "ready"
            if analysis_enabled()
            else "unavailable"
        ),
    }


@app.post(
    "/v1/facial-photo/analyze",
    dependencies=[Depends(require_internal_token)],
    include_in_schema=False,
)
async def analyze_facial_photo(
    request: Request,
) -> dict[str, Any]:
    """
    Executa somente quando explicitamente habilitado.

    O corpo binário é consumido diretamente em memória e a resposta
    contém somente métricas escalares autorizadas.
    """
    image_bytes = await read_image_bytes(request)

    if not analysis_enabled():
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail={
                "code": "vision_engine_unavailable",
                "message": (
                    "O motor de visão facial ainda não está habilitado."
                ),
            },
        )

    try:
        engine = get_facial_inference_engine()

        evidence = await run_in_threadpool(
            engine.analyze,
            image_bytes,
        )

        metrics = public_scalar_metrics(evidence)
    except FacialInferenceError as error:
        raise inference_http_exception(error) from error

    return {
        "schema_version": "1.0",
        "service": SERVICE,
        "service_version": FOUNDATION_VERSION,
        "engine": evidence.engine,
        "engine_version": evidence.engine_version,
        "face_count": evidence.face_count,
        "metrics": metrics,
    }
