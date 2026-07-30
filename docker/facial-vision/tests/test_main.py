from __future__ import annotations

from fastapi.testclient import TestClient

from app.main import (
    DEFAULT_MAXIMUM_REQUEST_BYTES,
    FOUNDATION_VERSION,
    SERVICE,
    app,
)

TOKEN = "synthetic-secret"


def client() -> TestClient:
    return TestClient(app)


def request_headers(
    *,
    token: str | None = None,
    content_type: str = "image/jpeg",
) -> dict[str, str]:
    headers = {
        "Content-Type": content_type,
    }

    if token is not None:
        headers["X-Vanguard-Vision-Token"] = token

    return headers


def test_health_reports_foundation_status(
    monkeypatch,
) -> None:
    monkeypatch.delenv(
        "VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED",
        raising=False,
    )

    response = client().get("/health")

    assert response.status_code == 200
    assert response.json() == {
        "status": "ok",
        "service": SERVICE,
        "service_version": FOUNDATION_VERSION,
        "engine_dependencies": "ready",
        "engine": "unavailable",
    }


def test_analysis_fails_safely_when_authentication_is_unconfigured(
    monkeypatch,
) -> None:
    monkeypatch.delenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        raising=False,
    )

    response = client().post(
        "/v1/facial-photo/analyze",
        headers=request_headers(),
        content=b"synthetic-image",
    )

    assert response.status_code == 503
    assert response.json()["detail"]["code"] == (
        "internal_auth_unconfigured"
    )


def test_analysis_rejects_an_invalid_internal_token(
    monkeypatch,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        TOKEN,
    )

    response = client().post(
        "/v1/facial-photo/analyze",
        headers=request_headers(
            token="wrong-token",
        ),
        content=b"synthetic-image",
    )

    assert response.status_code == 401
    assert response.json()["detail"]["code"] == "unauthorized"


def test_foundation_returns_unavailable_without_echoing_the_image(
    monkeypatch,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        TOKEN,
    )
    monkeypatch.delenv(
        "VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED",
        raising=False,
    )

    payload = b"synthetic-sensitive-image-content"

    response = client().post(
        "/v1/facial-photo/analyze",
        headers=request_headers(
            token=TOKEN,
        ),
        content=payload,
    )

    assert response.status_code == 503
    assert response.json()["detail"]["code"] == (
        "vision_engine_unavailable"
    )
    assert payload.decode() not in response.text


def test_analysis_rejects_an_unsupported_media_type(
    monkeypatch,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        TOKEN,
    )

    response = client().post(
        "/v1/facial-photo/analyze",
        headers=request_headers(
            token=TOKEN,
            content_type="text/plain",
        ),
        content=b"not-an-image",
    )

    assert response.status_code == 415
    assert response.json()["detail"]["code"] == (
        "unsupported_media_type"
    )


def test_analysis_rejects_an_empty_image(
    monkeypatch,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        TOKEN,
    )

    response = client().post(
        "/v1/facial-photo/analyze",
        headers=request_headers(
            token=TOKEN,
        ),
        content=b"",
    )

    assert response.status_code == 422
    assert response.json()["detail"]["code"] == "empty_image"


def test_analysis_rejects_an_oversized_image(
    monkeypatch,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        TOKEN,
    )

    response = client().post(
        "/v1/facial-photo/analyze",
        headers=request_headers(
            token=TOKEN,
        ),
        content=b"x" * (
            DEFAULT_MAXIMUM_REQUEST_BYTES + 1
        ),
    )

    assert response.status_code == 413
    assert response.json()["detail"]["code"] == "image_too_large"
