from __future__ import annotations

from app.vision_runtime import (
    SUPPORTED_FACE_LANDMARKER_GENERATION,
    SUPPORTED_FACE_LANDMARKER_SHA256,
    SUPPORTED_FACE_LANDMARKER_SIZE_BYTES,
    SUPPORTED_MEDIAPIPE_VERSION,
    SUPPORTED_NUMPY_VERSION,
    SUPPORTED_OPENCV_DISTRIBUTION,
    SUPPORTED_OPENCV_VERSION,
    inspect_vision_runtime,
)


def test_runtime_dependencies_and_model_are_compatible() -> None:
    inspect_vision_runtime.cache_clear()

    status = inspect_vision_runtime()

    assert status.mediapipe_version == (
        SUPPORTED_MEDIAPIPE_VERSION
    )
    assert status.numpy_version == SUPPORTED_NUMPY_VERSION
    assert status.opencv_distribution == (
        SUPPORTED_OPENCV_DISTRIBUTION
    )
    assert status.opencv_package_version == (
        SUPPORTED_OPENCV_VERSION
    )
    assert status.opencv_runtime_version == "5.0.0"
    assert status.jpeg_codec_available is True
    assert status.face_landmarker_available is True
    assert status.face_landmarker_model_available is True
    assert status.face_landmarker_model_generation == (
        SUPPORTED_FACE_LANDMARKER_GENERATION
    )
    assert status.face_landmarker_model_sha256 == (
        SUPPORTED_FACE_LANDMARKER_SHA256
    )
    assert status.face_landmarker_model_size_bytes == (
        SUPPORTED_FACE_LANDMARKER_SIZE_BYTES
    )


def test_runtime_inspection_is_cached() -> None:
    inspect_vision_runtime.cache_clear()

    first = inspect_vision_runtime()
    second = inspect_vision_runtime()

    assert first is second
