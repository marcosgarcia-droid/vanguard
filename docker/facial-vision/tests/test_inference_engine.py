from __future__ import annotations

import json
from dataclasses import dataclass
from typing import Any

import cv2
import numpy as np
import pytest

from app.inference_engine import (
    FacialInferenceEngine,
    FacialInferenceError,
    FacialInferenceFailure,
    get_facial_inference_engine,
)


@dataclass
class FakeLandmark:
    x: float
    y: float
    z: float = 0.0


@dataclass
class FakeCategory:
    category_name: str
    score: float
    display_name: str = ""


@dataclass
class FakeResult:
    face_landmarks: list[list[FakeLandmark]]
    face_blendshapes: list[list[FakeCategory]]
    facial_transformation_matrixes: list[np.ndarray]


class FakeLandmarker:
    def __init__(self, result: FakeResult) -> None:
        self.result = result
        self.detect_calls = 0
        self.close_calls = 0

    def detect(self, image: Any) -> FakeResult:
        del image

        self.detect_calls += 1

        return self.result

    def close(self) -> None:
        self.close_calls += 1


class CountingFactory:
    def __init__(self, landmarker: FakeLandmarker) -> None:
        self.landmarker = landmarker
        self.calls = 0

    def __call__(self) -> FakeLandmarker:
        self.calls += 1

        return self.landmarker


def encoded_image(
    *,
    width: int = 100,
    height: int = 80,
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


def facial_landmarks() -> list[FakeLandmark]:
    return [
        FakeLandmark(x=0.25, y=0.20),
        FakeLandmark(x=0.75, y=0.20),
        FakeLandmark(x=0.75, y=0.70),
        FakeLandmark(x=0.25, y=0.70),
    ]


def complete_result() -> FakeResult:
    return FakeResult(
        face_landmarks=[facial_landmarks()],
        face_blendshapes=[
            [
                FakeCategory(
                    category_name="eyeBlinkLeft",
                    score=0.10,
                ),
                FakeCategory(
                    category_name="eyeBlinkRight",
                    score=0.20,
                ),
            ]
        ],
        facial_transformation_matrixes=[
            np.eye(
                4,
                dtype=np.float64,
            )
        ],
    )


def test_it_extracts_only_scalar_evidence() -> None:
    landmarker = FakeLandmarker(
        complete_result()
    )

    engine = FacialInferenceEngine(
        landmarker_factory=lambda: landmarker,
    )

    evidence = engine.analyze(
        encoded_image()
    )

    assert evidence.face_count == 1
    assert evidence.image_width == 100
    assert evidence.image_height == 80
    assert evidence.face_ratio == 0.5
    assert evidence.center_offset_x == 0.0
    assert evidence.center_offset_y == -0.05
    assert evidence.yaw_degrees == 0.0
    assert evidence.pitch_degrees == 0.0
    assert evidence.roll_degrees == 0.0
    assert evidence.left_eye_open_score == 0.9
    assert evidence.right_eye_open_score == 0.8
    assert evidence.centered is True
    assert evidence.frontal is True
    assert evidence.eyes_open is True
    assert evidence.occluded is None
    assert evidence.inference_ms > 0

    metrics = evidence.scalar_metrics()

    assert set(metrics) == {
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
    }

    serialized = json.dumps(
        metrics,
        sort_keys=True,
    ).lower()

    for forbidden_term in (
        "landmark",
        "blendshape",
        "matrix",
        "embedding",
        "template",
        "image_bytes",
    ):
        assert forbidden_term not in serialized


def test_it_marks_unavailable_optional_evidence_as_null() -> None:
    result = FakeResult(
        face_landmarks=[facial_landmarks()],
        face_blendshapes=[],
        facial_transformation_matrixes=[],
    )

    evidence = FacialInferenceEngine(
        landmarker_factory=lambda: FakeLandmarker(result),
    ).analyze(
        encoded_image()
    )

    assert evidence.face_count == 1
    assert evidence.face_ratio == 0.5
    assert evidence.centered is True
    assert evidence.frontal is None
    assert evidence.eyes_open is None
    assert evidence.occluded is None


def test_it_does_not_choose_one_face_when_multiple_exist() -> None:
    result = FakeResult(
        face_landmarks=[
            facial_landmarks(),
            facial_landmarks(),
        ],
        face_blendshapes=[],
        facial_transformation_matrixes=[],
    )

    evidence = FacialInferenceEngine(
        landmarker_factory=lambda: FakeLandmarker(result),
    ).analyze(
        encoded_image()
    )

    assert evidence.face_count == 2
    assert evidence.face_ratio is None
    assert evidence.centered is None
    assert evidence.frontal is None
    assert evidence.eyes_open is None
    assert evidence.occluded is None


def test_it_rejects_invalid_image_bytes() -> None:
    engine = FacialInferenceEngine(
        landmarker_factory=lambda: FakeLandmarker(
            complete_result()
        ),
    )

    with pytest.raises(
        FacialInferenceError
    ) as captured:
        engine.analyze(
            b"not-a-valid-image"
        )

    assert captured.value.failure == (
        FacialInferenceFailure.InvalidImage
    )


def test_it_rejects_unsafe_decoded_dimensions() -> None:
    engine = FacialInferenceEngine(
        maximum_decoded_pixels=100,
        landmarker_factory=lambda: FakeLandmarker(
            complete_result()
        ),
    )

    with pytest.raises(
        FacialInferenceError
    ) as captured:
        engine.analyze(
            encoded_image(
                width=20,
                height=20,
            )
        )

    assert captured.value.failure == (
        FacialInferenceFailure.UnsafeImageDimensions
    )


def test_it_reuses_and_closes_the_landmarker() -> None:
    landmarker = FakeLandmarker(
        complete_result()
    )

    factory = CountingFactory(landmarker)

    engine = FacialInferenceEngine(
        landmarker_factory=factory,
    )

    engine.analyze(encoded_image())
    engine.analyze(encoded_image())

    assert factory.calls == 1
    assert landmarker.detect_calls == 2

    engine.close()
    engine.close()

    assert landmarker.close_calls == 1

    engine.analyze(encoded_image())

    assert factory.calls == 2
    assert landmarker.detect_calls == 3


def test_real_model_handles_a_synthetic_blank_image() -> None:
    engine = FacialInferenceEngine()

    try:
        evidence = engine.analyze(
            encoded_image(
                width=512,
                height=512,
            )
        )
    finally:
        engine.close()

    assert evidence.face_count == 0
    assert evidence.image_width == 512
    assert evidence.image_height == 512
    assert evidence.face_ratio is None
    assert evidence.centered is None
    assert evidence.frontal is None
    assert evidence.eyes_open is None
    assert evidence.occluded is None


def test_cached_accessor_reuses_the_engine() -> None:
    get_facial_inference_engine.cache_clear()

    first = get_facial_inference_engine()
    second = get_facial_inference_engine()

    assert first is second

    first.close()
    get_facial_inference_engine.cache_clear()
