from __future__ import annotations

import hashlib
import importlib.metadata
import json
import zipfile
from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path
from typing import Any, Final

import cv2
import mediapipe as mp
import numpy as np

SUPPORTED_MEDIAPIPE_VERSION: Final[str] = "1.0.0"
SUPPORTED_NUMPY_VERSION: Final[str] = "2.5.1"
SUPPORTED_OPENCV_DISTRIBUTION: Final[str] = (
    "opencv-contrib-python"
)
SUPPORTED_OPENCV_VERSION: Final[str] = "5.0.0.93"

SUPPORTED_FACE_LANDMARKER_GENERATION: Final[str] = (
    "1683136941468629"
)
SUPPORTED_FACE_LANDMARKER_SHA256: Final[str] = (
    "64184e229b263107bc2b804c6625db1341ff2bb731874b0bcc2fe6544e0bc9ff"
)
SUPPORTED_FACE_LANDMARKER_SIZE_BYTES: Final[int] = 3758596

FACE_LANDMARKER_MANIFEST_PATH: Final[Path] = Path(
    "/opt/facial-vision/models/face_landmarker.json"
)
FACE_LANDMARKER_MODEL_PATH: Final[Path] = Path(
    "/opt/facial-vision/models/face_landmarker.task"
)

REQUIRED_FACE_LANDMARKER_ENTRIES: Final[frozenset[str]] = (
    frozenset(
        {
            "face_blendshapes.tflite",
            "face_detector.tflite",
            "face_landmarks_detector.tflite",
            "geometry_pipeline_metadata_landmarks.binarypb",
        }
    )
)


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
    face_landmarker_model_available: bool
    face_landmarker_model_generation: str
    face_landmarker_model_sha256: str
    face_landmarker_model_size_bytes: int


@dataclass(frozen=True, slots=True)
class FaceLandmarkerModelStatus:
    generation: str
    sha256: str
    size_bytes: int


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


def _read_model_manifest() -> dict[str, Any]:
    try:
        payload = json.loads(
            FACE_LANDMARKER_MANIFEST_PATH.read_text(
                encoding="utf-8"
            )
        )
    except (OSError, json.JSONDecodeError) as exception:
        raise VisionRuntimeError(
            "O manifesto do modelo facial não está disponível."
        ) from exception

    if not isinstance(payload, dict):
        raise VisionRuntimeError(
            "O manifesto do modelo facial é inválido."
        )

    return payload


def _calculate_sha256(path: Path) -> str:
    digest = hashlib.sha256()

    try:
        with path.open("rb") as model:
            while chunk := model.read(1024 * 1024):
                digest.update(chunk)
    except OSError as exception:
        raise VisionRuntimeError(
            "O modelo facial não está disponível."
        ) from exception

    return digest.hexdigest()


def _inspect_face_landmarker_model() -> FaceLandmarkerModelStatus:
    manifest = _read_model_manifest()

    expected_manifest = {
        "schema_version": 1,
        "generation": SUPPORTED_FACE_LANDMARKER_GENERATION,
        "sha256": SUPPORTED_FACE_LANDMARKER_SHA256,
        "size_bytes": SUPPORTED_FACE_LANDMARKER_SIZE_BYTES,
    }

    for key, expected_value in expected_manifest.items():
        if manifest.get(key) != expected_value:
            raise VisionRuntimeError(
                f"Metadado inesperado do modelo facial: {key}."
            )

    required_entries = manifest.get("required_entries")

    if (
        not isinstance(required_entries, list)
        or set(required_entries)
        != REQUIRED_FACE_LANDMARKER_ENTRIES
    ):
        raise VisionRuntimeError(
            "A estrutura declarada do modelo facial é inválida."
        )

    try:
        actual_size = FACE_LANDMARKER_MODEL_PATH.stat().st_size
    except OSError as exception:
        raise VisionRuntimeError(
            "O modelo facial não está disponível."
        ) from exception

    if actual_size != SUPPORTED_FACE_LANDMARKER_SIZE_BYTES:
        raise VisionRuntimeError(
            "O tamanho do modelo facial é inválido."
        )

    calculated_sha256 = _calculate_sha256(
        FACE_LANDMARKER_MODEL_PATH
    )

    if calculated_sha256 != SUPPORTED_FACE_LANDMARKER_SHA256:
        raise VisionRuntimeError(
            "A integridade SHA-256 do modelo facial é inválida."
        )

    if not zipfile.is_zipfile(FACE_LANDMARKER_MODEL_PATH):
        raise VisionRuntimeError(
            "O bundle do modelo facial não é um ZIP válido."
        )

    try:
        with zipfile.ZipFile(
            FACE_LANDMARKER_MODEL_PATH
        ) as archive:
            archive_entries = {
                entry.filename
                for entry in archive.infolist()
                if not entry.is_dir()
            }
    except (OSError, zipfile.BadZipFile) as exception:
        raise VisionRuntimeError(
            "O bundle do modelo facial não pôde ser lido."
        ) from exception

    if not REQUIRED_FACE_LANDMARKER_ENTRIES.issubset(
        archive_entries
    ):
        raise VisionRuntimeError(
            "O bundle do modelo facial está incompleto."
        )

    return FaceLandmarkerModelStatus(
        generation=SUPPORTED_FACE_LANDMARKER_GENERATION,
        sha256=calculated_sha256,
        size_bytes=actual_size,
    )


@lru_cache(maxsize=1)
def inspect_vision_runtime() -> VisionRuntimeStatus:
    """
    Confirma runtime e modelo usando somente dados técnicos locais.

    Nenhuma foto de visitante, biometria ou resultado persistente é usado.
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

    model_status = _inspect_face_landmarker_model()

    return VisionRuntimeStatus(
        mediapipe_version=mediapipe_version,
        numpy_version=numpy_version,
        opencv_distribution=SUPPORTED_OPENCV_DISTRIBUTION,
        opencv_package_version=opencv_package_version,
        opencv_runtime_version=cv2.__version__,
        jpeg_codec_available=jpeg_codec_available,
        face_landmarker_available=face_landmarker_available,
        face_landmarker_model_available=True,
        face_landmarker_model_generation=(
            model_status.generation
        ),
        face_landmarker_model_sha256=model_status.sha256,
        face_landmarker_model_size_bytes=(
            model_status.size_bytes
        ),
    )
