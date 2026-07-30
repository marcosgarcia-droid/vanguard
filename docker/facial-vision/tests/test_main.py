from __future__ import annotations

from fastapi.testclient import TestClient

from app.main import (
    DEFAULT_MAXIMUM_REQUEST_BYTES,
    FOUNDATION_VERSION,
    SERVICE,
    app,
)

TOKEN = "synthetic-internal-token"


def client() -> TestClient:
    return TestClient(app)


def test_health_does_not_require_authentication() -> None:
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
        files={
            "image": (
                "visitor.jpg",
                b"synthetic-image",
                "image/jpeg",
            )
        },
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
        headers={
            "X-Vanguard-Vision-Token": "wrong-token",
        },
        files={
            "image": (
                "visitor.jpg",
                b"synthetic-image",
                "image/jpeg",
            )
        },
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

    payload = b"synthetic-sensitive-image-content"

    response = client().post(
        "/v1/facial-photo/analyze",
        headers={
            "X-Vanguard-Vision-Token": TOKEN,
        },
        files={
            "image": (
                "visitor.jpg",
                payload,
                "image/jpeg",
            )
        },
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
        headers={
            "X-Vanguard-Vision-Token": TOKEN,
        },
        files={
            "image": (
                "visitor.txt",
                b"not-an-image",
                "text/plain",
            )
        },
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
        headers={
            "X-Vanguard-Vision-Token": TOKEN,
        },
        files={
            "image": (
                "visitor.jpg",
                b"",
                "image/jpeg",
            )
        },
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
        headers={
            "X-Vanguard-Vision-Token": TOKEN,
        },
        files={
            "image": (
                "visitor.jpg",
                b"x" * (DEFAULT_MAXIMUM_REQUEST_BYTES + 1),
                "image/jpeg",
            )
        },
    )

    assert response.status_code == 413
    assert response.json()["detail"]["code"] == "image_too_large"
