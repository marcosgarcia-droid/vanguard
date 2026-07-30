from __future__ import annotations

import json
from dataclasses import dataclass
from typing import Any

import cv2
import numpy as np
import pytest
from fastapi.testclient import TestClient

from app import main
from app.inference_engine import (
    FacialInferenceError,
    FacialInferenceEvidence,
    FacialInferenceFailure,
    close_cached_facial_inference_engine,
    get_facial_inference_engine,
)


@dataclass
class FakeEngine:
    evidence: FacialInferenceEvidence | None = None
    error: FacialInferenceError | None = None
    calls: int = 0

    def analyze(
        self,
        image_bytes: bytes,
    ) -> FacialInferenceEvidence:
        assert image_bytes

        self.calls += 1

        if self.error is not None:
            raise self.error

        assert self.evidence is not None

        return self.evidence


@pytest.fixture(autouse=True)
def reset_runtime(
    monkeypatch: pytest.MonkeyPatch,
) -> Any:
    close_cached_facial_inference_engine()

    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_TOKEN",
        "synthetic-secret",
    )
    monkeypatch.delenv(
        "VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED",
        raising=False,
    )

    yield

    close_cached_facial_inference_engine()


def encoded_image(
    *,
    width: int = 512,
    height: int = 512,
) -> bytes:
    synthetic = np.full(
        (height, width, 3),
        255,
        dtype=np.uint8,
    )

    encoded, buffer = cv2.imencode(
        ".jpg",
        synthetic,
    )

    assert encoded is True

    return buffer.tobytes()


def post_image(
    client: TestClient,
    image_bytes: bytes,
) -> Any:
    return client.post(
        "/v1/facial-photo/analyze",
        headers={
            "X-Vanguard-Vision-Token": "synthetic-secret",
            "Content-Type": "image/jpeg",
        },
        content=image_bytes,
    )


def safe_evidence() -> FacialInferenceEvidence:
    return FacialInferenceEvidence(
        engine="mediapipe-opencv",
        engine_version="synthetic-engine-v1",
        face_count=1,
        image_width=512,
        image_height=512,
        face_ratio=0.50,
        center_offset_x=0.01,
        center_offset_y=-0.02,
        yaw_degrees=1.0,
        pitch_degrees=-2.0,
        roll_degrees=0.5,
        left_eye_open_score=0.90,
        right_eye_open_score=0.85,
        centered=True,
        frontal=True,
        eyes_open=True,
        occluded=None,
        inference_ms=5.25,
    )


def test_health_reports_engine_unavailable_by_default() -> None:
    with TestClient(main.app) as client:
        response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["engine"] == "unavailable"


def test_health_reports_ready_only_when_explicitly_enabled(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED",
        "true",
    )

    assert (
        get_facial_inference_engine
        .cache_info()
        .currsize
        == 0
    )

    with TestClient(main.app) as client:
        response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["engine"] == "ready"

    assert (
        get_facial_inference_engine
        .cache_info()
        .currsize
        == 0
    )


def test_disabled_endpoint_does_not_execute_the_engine(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    fake_engine = FakeEngine(
        evidence=safe_evidence()
    )

    monkeypatch.setattr(
        main,
        "get_facial_inference_engine",
        lambda: fake_engine,
    )

    with TestClient(main.app) as client:
        response = post_image(
            client,
            encoded_image(),
        )

    assert response.status_code == 503
    assert response.json()["detail"]["code"] == (
        "vision_engine_unavailable"
    )
    assert fake_engine.calls == 0


def test_enabled_endpoint_returns_only_allowlisted_scalars(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED",
        "true",
    )

    fake_engine = FakeEngine(
        evidence=safe_evidence()
    )

    monkeypatch.setattr(
        main,
        "get_facial_inference_engine",
        lambda: fake_engine,
    )

    with TestClient(main.app) as client:
        response = post_image(
            client,
            encoded_image(),
        )

    assert response.status_code == 200

    payload = response.json()

    assert payload["schema_version"] == "1.0"
    assert payload["service"] == "vanguard-facial-vision"
    assert payload["engine"] == "mediapipe-opencv"
    assert payload["engine_version"] == "synthetic-engine-v1"
    assert payload["face_count"] == 1

    assert set(payload["metrics"]) == set(
        main.PUBLIC_SCALAR_METRIC_NAMES
    )

    assert payload["metrics"]["face_ratio"] == 0.50
    assert payload["metrics"]["centered"] is True
    assert payload["metrics"]["frontal"] is True
    assert payload["metrics"]["eyes_open"] is True
    assert payload["metrics"]["occluded"] is None

    serialized = json.dumps(
        payload,
        sort_keys=True,
    ).lower()

    for forbidden_term in (
        "landmark",
        "blendshape",
        "matrix",
        "embedding",
        "template",
        "image_bytes",
        "raw_payload",
    ):
        assert forbidden_term not in serialized

    assert fake_engine.calls == 1


@pytest.mark.parametrize(
    (
        "failure",
        "expected_status",
        "expected_code",
    ),
    [
        (
            FacialInferenceFailure.InvalidImage,
            422,
            "invalid_image",
        ),
        (
            FacialInferenceFailure.UnsafeImageDimensions,
            422,
            "unsafe_image_dimensions",
        ),
        (
            FacialInferenceFailure.EngineUnavailable,
            503,
            "vision_engine_unavailable",
        ),
        (
            FacialInferenceFailure.InvalidEngineEvidence,
            503,
            "invalid_engine_evidence",
        ),
    ],
)
def test_it_maps_typed_engine_failures(
    monkeypatch: pytest.MonkeyPatch,
    failure: FacialInferenceFailure,
    expected_status: int,
    expected_code: str,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED",
        "true",
    )

    fake_engine = FakeEngine(
        error=FacialInferenceError(
            failure,
            "Synthetic internal error.",
        )
    )

    monkeypatch.setattr(
        main,
        "get_facial_inference_engine",
        lambda: fake_engine,
    )

    with TestClient(main.app) as client:
        response = post_image(
            client,
            encoded_image(),
        )

    assert response.status_code == expected_status
    assert response.json()["detail"]["code"] == expected_code
    assert "Synthetic internal error" not in response.text
    assert fake_engine.calls == 1


def test_real_engine_analyzes_only_a_synthetic_blank_image(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setenv(
        "VANGUARD_FACIAL_VISION_ANALYSIS_ENABLED",
        "true",
    )

    with TestClient(main.app) as client:
        response = post_image(
            client,
            encoded_image(),
        )

    assert response.status_code == 200

    payload = response.json()

    assert payload["engine"] == "mediapipe-opencv"
    assert payload["face_count"] == 0

    assert payload["metrics"]["image_width"] == 512
    assert payload["metrics"]["image_height"] == 512
    assert payload["metrics"]["face_ratio"] is None
    assert payload["metrics"]["centered"] is None
    assert payload["metrics"]["frontal"] is None
    assert payload["metrics"]["eyes_open"] is None
    assert payload["metrics"]["occluded"] is None


def test_lifespan_closes_and_clears_the_cached_engine() -> None:
    close_cached_facial_inference_engine()

    get_facial_inference_engine()

    assert (
        get_facial_inference_engine
        .cache_info()
        .currsize
        == 1
    )

    with TestClient(main.app) as client:
        response = client.get("/health")

        assert response.status_code == 200

    assert (
        get_facial_inference_engine
        .cache_info()
        .currsize
        == 0
    )
