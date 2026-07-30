from __future__ import annotations

import importlib.metadata
from dataclasses import dataclass
from functools import lru_cache
from typing import Final

import cv2
import mediapipe as mp
import numpy as np

SUPPORTED_MEDIAPIPE_VERSION: Final[str] = "1.0.0"
SUPPORTED_NUMPY_VERSION: Final[str] = "2.5.1"
SUPPORTED_OPENCV_DISTRIBUTION: Final[str] = (
    "opencv-contrib-python"
)
SUPPORTED_OPENCV_VERSION: Final[str] = "5.0.0.93"


class VisionRuntimeError(RuntimeError):
    """Indica que o runtime técnico não é seguro para análise."""


@dataclass(frozen=True, slots=True)
class VisionRuntimeStatus:
    mediapipe_version: str
    numpy_version: str
    opencv_distribution: str
    opencv_package_version: str
    opencv_runtime_version: str
    jpeg_codec_available: bool
    face_landmarker_available: bool


def _installed_opencv_distributions() -> tuple[str, ...]:
    distributions: list[str] = []

    for distribution in importlib.metadata.distributions():
        name = distribution.metadata.get("Name", "").lower()

        if name.startswith("opencv-"):
            distributions.append(name)

    return tuple(sorted(distributions))


def _package_version(package: str) -> str:
    try:
        return importlib.metadata.version(package)
    except importlib.metadata.PackageNotFoundError as exception:
        raise VisionRuntimeError(
            f"Dependência obrigatória ausente: {package}."
        ) from exception


@lru_cache(maxsize=1)
def inspect_vision_runtime() -> VisionRuntimeStatus:
    """
    Confirma o runtime usando somente uma imagem sintética em memória.

    Nenhuma foto de visitante, modelo biométrico ou dado persistente é usado.
    """
    opencv_distributions = (
        _installed_opencv_distributions()
    )

    expected_opencv = (
        SUPPORTED_OPENCV_DISTRIBUTION,
    )

    if opencv_distributions != expected_opencv:
        raise VisionRuntimeError(
            "Era esperada exatamente uma distribuição OpenCV: "
            f"{SUPPORTED_OPENCV_DISTRIBUTION}."
        )

    mediapipe_version = _package_version("mediapipe")
    numpy_version = _package_version("numpy")
    opencv_package_version = _package_version(
        SUPPORTED_OPENCV_DISTRIBUTION
    )

    expected_versions = {
        "mediapipe": (
            mediapipe_version,
            SUPPORTED_MEDIAPIPE_VERSION,
        ),
        "numpy": (
            numpy_version,
            SUPPORTED_NUMPY_VERSION,
        ),
        SUPPORTED_OPENCV_DISTRIBUTION: (
            opencv_package_version,
            SUPPORTED_OPENCV_VERSION,
        ),
    }

    for package, versions in expected_versions.items():
        actual, expected = versions

        if actual != expected:
            raise VisionRuntimeError(
                f"Versão incompatível de {package}: "
                f"esperada {expected}, encontrada {actual}."
            )

    synthetic_image = np.zeros(
        (32, 32, 3),
        dtype=np.uint8,
    )

    encoded, encoded_buffer = cv2.imencode(
        ".jpg",
        synthetic_image,
    )

    decoded_image = (
        cv2.imdecode(
            encoded_buffer,
            cv2.IMREAD_COLOR,
        )
        if encoded
        else None
    )

    jpeg_codec_available = bool(
        encoded and decoded_image is not None
    )

    if not jpeg_codec_available:
        raise VisionRuntimeError(
            "O codec JPEG do OpenCV não está disponível."
        )

    face_landmarker = getattr(
        mp.tasks.vision,
        "FaceLandmarker",
        None,
    )

    face_landmarker_options = getattr(
        mp.tasks.vision,
        "FaceLandmarkerOptions",
        None,
    )

    running_mode = getattr(
        mp.tasks.vision,
        "RunningMode",
        None,
    )

    face_landmarker_available = all(
        (
            face_landmarker is not None,
            face_landmarker_options is not None,
            running_mode is not None,
            getattr(running_mode, "IMAGE", None) is not None,
        )
    )

    if not face_landmarker_available:
        raise VisionRuntimeError(
            "A API FaceLandmarker em modo IMAGE não está disponível."
        )

    return VisionRuntimeStatus(
        mediapipe_version=mediapipe_version,
        numpy_version=numpy_version,
        opencv_distribution=SUPPORTED_OPENCV_DISTRIBUTION,
        opencv_package_version=opencv_package_version,
        opencv_runtime_version=cv2.__version__,
        jpeg_codec_available=jpeg_codec_available,
        face_landmarker_available=face_landmarker_available,
    )
