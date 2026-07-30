from __future__ import annotations

import hmac
import os
from typing import Final

from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile
from starlette import status

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

app = FastAPI(
    title=SERVICE,
    version=FOUNDATION_VERSION,
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
)


def configured_token() -> str:
    """Retorna somente o token interno normalizado, sem registrá-lo."""
    return os.getenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        "",
    ).strip()


def maximum_request_bytes() -> int:
    """Mantém um limite seguro mesmo diante de configuração inválida."""
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
                "message": "A autenticação interna ainda não foi configurada.",
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


@app.get(
    "/health",
    include_in_schema=False,
)
async def health() -> dict[str, str]:
    """Confirma o processo e as dependências, sem afirmar modelo carregado."""
    inspect_vision_runtime()

    return {
        "status": "ok",
        "service": SERVICE,
        "service_version": FOUNDATION_VERSION,
        "engine_dependencies": "ready",
        "engine": "unavailable",
    }


@app.post(
    "/v1/facial-photo/analyze",
    dependencies=[Depends(require_internal_token)],
    include_in_schema=False,
)
async def analyze_facial_photo(
    image: UploadFile = File(...),
) -> None:
    """
    Valida somente o envelope da requisição.

    O motor facial real será integrado em bloco posterior. Até lá, nenhuma
    evidência, aprovação ou reprovação é produzida pelo serviço.
    """
    try:
        content_type = (
            image.content_type or ""
        ).strip().lower()

        if content_type not in ALLOWED_CONTENT_TYPES:
            raise HTTPException(
                status_code=status.HTTP_415_UNSUPPORTED_MEDIA_TYPE,
                detail={
                    "code": "unsupported_media_type",
                    "message": "O formato da imagem não é suportado.",
                },
            )

        total_bytes = 0
        maximum_bytes = maximum_request_bytes()

        while True:
            chunk = await image.read(64 * 1024)

            if not chunk:
                break

            total_bytes += len(chunk)

            if total_bytes > maximum_bytes:
                raise HTTPException(
                    status_code=status.HTTP_413_CONTENT_TOO_LARGE,
                    detail={
                        "code": "image_too_large",
                        "message": "A imagem excede o limite permitido.",
                    },
                )

        if total_bytes == 0:
            raise HTTPException(
                status_code=status.HTTP_422_UNPROCESSABLE_CONTENT,
                detail={
                    "code": "empty_image",
                    "message": "A imagem enviada está vazia.",
                },
            )
    finally:
        await image.close()

    raise HTTPException(
        status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
        detail={
            "code": "vision_engine_unavailable",
            "message": "O motor de visão facial ainda não está instalado.",
        },
    )
