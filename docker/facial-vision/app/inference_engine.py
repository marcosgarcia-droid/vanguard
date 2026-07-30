from __future__ import annotations

import math
import time
from dataclasses import dataclass
from enum import StrEnum
from functools import lru_cache
from io import BytesIO
from threading import RLock
from typing import Any, Callable, Final, Protocol

import cv2
import mediapipe as mp
import numpy as np
from PIL import Image, UnidentifiedImageError

from app.vision_runtime import (
    FACE_LANDMARKER_MODEL_PATH,
    SUPPORTED_FACE_LANDMARKER_GENERATION,
    inspect_vision_runtime,
)

ENGINE: Final[str] = "mediapipe-opencv"
ENGINE_VERSION: Final[str] = (
    "face-landmarker-"
    f"{SUPPORTED_FACE_LANDMARKER_GENERATION}-inference-v1"
)

DEFAULT_MAXIMUM_DECODED_PIXELS: Final[int] = 16_000_000
MAXIMUM_DETECTED_FACES: Final[int] = 2

CENTER_HORIZONTAL_TOLERANCE: Final[float] = 0.10
CENTER_VERTICAL_TOLERANCE: Final[float] = 0.10

MAXIMUM_ABSOLUTE_YAW_DEGREES: Final[float] = 15.0
MAXIMUM_ABSOLUTE_PITCH_DEGREES: Final[float] = 15.0
MAXIMUM_ABSOLUTE_ROLL_DEGREES: Final[float] = 10.0

MINIMUM_EYE_OPEN_SCORE: Final[float] = 0.55

ALLOWED_IMAGE_FORMATS: Final[frozenset[str]] = frozenset(
    {
        "JPEG",
        "PNG",
        "WEBP",
    }
)


class FacialInferenceFailure(StrEnum):
    InvalidImage = "invalid_image"
    UnsafeImageDimensions = "unsafe_image_dimensions"
    EngineUnavailable = "engine_unavailable"
    InvalidEngineEvidence = "invalid_engine_evidence"


class FacialInferenceError(RuntimeError):
    def __init__(
        self,
        failure: FacialInferenceFailure,
        message: str,
    ) -> None:
        super().__init__(message)

        self.failure = failure


class FaceLandmarkerProtocol(Protocol):
    def detect(self, image: Any) -> Any:
        ...

    def close(self) -> None:
        ...


@dataclass(frozen=True, slots=True)
class DecodedFacialImage:
    mediapipe_image: Any
    width: int
    height: int


@dataclass(frozen=True, slots=True)
class FacialInferenceEvidence:
    engine: str
    engine_version: str
    face_count: int
    image_width: int
    image_height: int
    face_ratio: float | None
    center_offset_x: float | None
    center_offset_y: float | None
    yaw_degrees: float | None
    pitch_degrees: float | None
    roll_degrees: float | None
    left_eye_open_score: float | None
    right_eye_open_score: float | None
    centered: bool | None
    frontal: bool | None
    eyes_open: bool | None
    occluded: bool | None
    inference_ms: float

    def scalar_metrics(
        self,
    ) -> dict[str, bool | int | float | None]:
        """
        Expõe apenas evidências escalares.

        Landmarks, blendshapes, matrizes e imagens nunca fazem parte
        deste contrato.
        """
        return {
            "image_width": self.image_width,
            "image_height": self.image_height,
            "face_ratio": self.face_ratio,
            "center_offset_x": self.center_offset_x,
            "center_offset_y": self.center_offset_y,
            "yaw_degrees": self.yaw_degrees,
            "pitch_degrees": self.pitch_degrees,
            "roll_degrees": self.roll_degrees,
            "left_eye_open_score": self.left_eye_open_score,
            "right_eye_open_score": self.right_eye_open_score,
            "centered": self.centered,
            "frontal": self.frontal,
            "eyes_open": self.eyes_open,
            "occluded": self.occluded,
            "inference_ms": self.inference_ms,
        }


class FacialInferenceEngine:
    def __init__(
        self,
        *,
        maximum_decoded_pixels: int = (
            DEFAULT_MAXIMUM_DECODED_PIXELS
        ),
        landmarker_factory: (
            Callable[[], FaceLandmarkerProtocol] | None
        ) = None,
    ) -> None:
        if maximum_decoded_pixels <= 0:
            raise ValueError(
                "O limite de pixels decodificados deve ser positivo."
            )

        self._maximum_decoded_pixels = maximum_decoded_pixels
        self._landmarker_factory = (
            landmarker_factory
            or self._create_face_landmarker
        )
        self._landmarker: FaceLandmarkerProtocol | None = None
        self._lock = RLock()

    def analyze(
        self,
        image_bytes: bytes,
    ) -> FacialInferenceEvidence:
        decoded = self._decode_image(image_bytes)

        started_at = time.perf_counter()

        try:
            with self._lock:
                result = self._landmarker_instance().detect(
                    decoded.mediapipe_image
                )
        except FacialInferenceError:
            raise
        except Exception as exception:
            raise FacialInferenceError(
                FacialInferenceFailure.EngineUnavailable,
                "O motor facial não conseguiu executar a inferência.",
            ) from exception

        inference_ms = max(
            (time.perf_counter() - started_at) * 1000,
            0.001,
        )

        try:
            return self._extract_evidence(
                result=result,
                width=decoded.width,
                height=decoded.height,
                inference_ms=inference_ms,
            )
        except FacialInferenceError:
            raise
        except Exception as exception:
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidEngineEvidence,
                "O motor facial retornou evidências inválidas.",
            ) from exception

    def close(self) -> None:
        with self._lock:
            landmarker = self._landmarker
            self._landmarker = None

            if landmarker is not None:
                landmarker.close()

    def _decode_image(
        self,
        image_bytes: bytes,
    ) -> DecodedFacialImage:
        if not isinstance(image_bytes, bytes) or not image_bytes:
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidImage,
                "A imagem enviada está vazia ou é inválida.",
            )

        try:
            with Image.open(BytesIO(image_bytes)) as probe:
                image_format = str(
                    probe.format or ""
                ).upper()

                width, height = probe.size

                if image_format not in ALLOWED_IMAGE_FORMATS:
                    raise FacialInferenceError(
                        FacialInferenceFailure.InvalidImage,
                        "O formato real da imagem não é suportado.",
                    )

                self._assert_safe_dimensions(
                    width=width,
                    height=height,
                )

                probe.verify()
        except FacialInferenceError:
            raise
        except (
            Image.DecompressionBombError,
            UnidentifiedImageError,
            OSError,
            ValueError,
        ) as exception:
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidImage,
                "A imagem não pôde ser validada.",
            ) from exception

        encoded_buffer = np.frombuffer(
            image_bytes,
            dtype=np.uint8,
        )

        decoded_bgr = cv2.imdecode(
            encoded_buffer,
            cv2.IMREAD_COLOR,
        )

        if (
            decoded_bgr is None
            or decoded_bgr.ndim != 3
            or decoded_bgr.shape[2] != 3
        ):
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidImage,
                "A imagem não pôde ser decodificada.",
            )

        decoded_height, decoded_width = decoded_bgr.shape[:2]

        if (
            decoded_width != width
            or decoded_height != height
        ):
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidImage,
                "As dimensões decodificadas são inconsistentes.",
            )

        self._assert_safe_dimensions(
            width=decoded_width,
            height=decoded_height,
        )

        decoded_rgb = cv2.cvtColor(
            decoded_bgr,
            cv2.COLOR_BGR2RGB,
        )

        contiguous_rgb = np.ascontiguousarray(
            decoded_rgb,
            dtype=np.uint8,
        )

        return DecodedFacialImage(
            mediapipe_image=mp.Image(
                image_format=mp.ImageFormat.SRGB,
                data=contiguous_rgb,
            ),
            width=decoded_width,
            height=decoded_height,
        )

    def _assert_safe_dimensions(
        self,
        *,
        width: int,
        height: int,
    ) -> None:
        if width <= 0 or height <= 0:
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidImage,
                "As dimensões da imagem são inválidas.",
            )

        if width * height > self._maximum_decoded_pixels:
            raise FacialInferenceError(
                FacialInferenceFailure.UnsafeImageDimensions,
                "A imagem excede o limite seguro de pixels.",
            )

    def _landmarker_instance(
        self,
    ) -> FaceLandmarkerProtocol:
        if self._landmarker is None:
            try:
                self._landmarker = self._landmarker_factory()
            except FacialInferenceError:
                raise
            except Exception as exception:
                raise FacialInferenceError(
                    FacialInferenceFailure.EngineUnavailable,
                    "O modelo facial não pôde ser carregado.",
                ) from exception

        return self._landmarker

    @staticmethod
    def _create_face_landmarker() -> FaceLandmarkerProtocol:
        inspect_vision_runtime()

        options = mp.tasks.vision.FaceLandmarkerOptions(
            base_options=mp.tasks.BaseOptions(
                model_asset_path=str(
                    FACE_LANDMARKER_MODEL_PATH
                ),
            ),
            running_mode=mp.tasks.vision.RunningMode.IMAGE,
            num_faces=MAXIMUM_DETECTED_FACES,
            min_face_detection_confidence=0.5,
            min_face_presence_confidence=0.5,
            min_tracking_confidence=0.5,
            output_face_blendshapes=True,
            output_facial_transformation_matrixes=True,
        )

        return (
            mp.tasks.vision.FaceLandmarker
            .create_from_options(options)
        )

    def _extract_evidence(
        self,
        *,
        result: Any,
        width: int,
        height: int,
        inference_ms: float,
    ) -> FacialInferenceEvidence:
        detected_faces = list(
            getattr(
                result,
                "face_landmarks",
                [],
            )
            or []
        )

        face_count = len(detected_faces)

        if face_count > MAXIMUM_DETECTED_FACES:
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidEngineEvidence,
                "O motor retornou quantidade inesperada de faces.",
            )

        empty_evidence = {
            "face_ratio": None,
            "center_offset_x": None,
            "center_offset_y": None,
            "yaw_degrees": None,
            "pitch_degrees": None,
            "roll_degrees": None,
            "left_eye_open_score": None,
            "right_eye_open_score": None,
            "centered": None,
            "frontal": None,
            "eyes_open": None,
            "occluded": None,
        }

        if face_count != 1:
            return FacialInferenceEvidence(
                engine=ENGINE,
                engine_version=ENGINE_VERSION,
                face_count=face_count,
                image_width=width,
                image_height=height,
                inference_ms=self._rounded(inference_ms),
                **empty_evidence,
            )

        geometry = self._face_geometry(
            detected_faces[0]
        )

        pose = self._face_pose(result)
        eyes = self._eye_evidence(result)

        centered = (
            abs(geometry["center_offset_x"])
            <= CENTER_HORIZONTAL_TOLERANCE
            and abs(geometry["center_offset_y"])
            <= CENTER_VERTICAL_TOLERANCE
        )

        frontal = (
            abs(pose["yaw_degrees"])
            <= MAXIMUM_ABSOLUTE_YAW_DEGREES
            and abs(pose["pitch_degrees"])
            <= MAXIMUM_ABSOLUTE_PITCH_DEGREES
            and abs(pose["roll_degrees"])
            <= MAXIMUM_ABSOLUTE_ROLL_DEGREES
            if pose is not None
            else None
        )

        eyes_open = (
            eyes["left_eye_open_score"]
            >= MINIMUM_EYE_OPEN_SCORE
            and eyes["right_eye_open_score"]
            >= MINIMUM_EYE_OPEN_SCORE
            if eyes is not None
            else None
        )

        return FacialInferenceEvidence(
            engine=ENGINE,
            engine_version=ENGINE_VERSION,
            face_count=face_count,
            image_width=width,
            image_height=height,
            face_ratio=geometry["face_ratio"],
            center_offset_x=geometry["center_offset_x"],
            center_offset_y=geometry["center_offset_y"],
            yaw_degrees=(
                pose["yaw_degrees"]
                if pose is not None
                else None
            ),
            pitch_degrees=(
                pose["pitch_degrees"]
                if pose is not None
                else None
            ),
            roll_degrees=(
                pose["roll_degrees"]
                if pose is not None
                else None
            ),
            left_eye_open_score=(
                eyes["left_eye_open_score"]
                if eyes is not None
                else None
            ),
            right_eye_open_score=(
                eyes["right_eye_open_score"]
                if eyes is not None
                else None
            ),
            centered=centered,
            frontal=frontal,
            eyes_open=eyes_open,
            # O modelo atual não fornece evidência confiável de oclusão.
            occluded=None,
            inference_ms=self._rounded(inference_ms),
        )

    def _face_geometry(
        self,
        landmarks: Any,
    ) -> dict[str, float]:
        coordinates: list[tuple[float, float]] = []

        for landmark in landmarks:
            x = float(getattr(landmark, "x"))
            y = float(getattr(landmark, "y"))

            if not math.isfinite(x) or not math.isfinite(y):
                continue

            coordinates.append(
                (
                    min(max(x, 0.0), 1.0),
                    min(max(y, 0.0), 1.0),
                )
            )

        if len(coordinates) < 4:
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidEngineEvidence,
                "Não há landmarks suficientes para a geometria.",
            )

        x_values = [coordinate[0] for coordinate in coordinates]
        y_values = [coordinate[1] for coordinate in coordinates]

        minimum_x = min(x_values)
        maximum_x = max(x_values)
        minimum_y = min(y_values)
        maximum_y = max(y_values)

        face_width_ratio = maximum_x - minimum_x
        face_height_ratio = maximum_y - minimum_y

        if face_width_ratio <= 0 or face_height_ratio <= 0:
            raise FacialInferenceError(
                FacialInferenceFailure.InvalidEngineEvidence,
                "A geometria facial retornada é inválida.",
            )

        center_x = (minimum_x + maximum_x) / 2
        center_y = (minimum_y + maximum_y) / 2

        return {
            "face_ratio": self._rounded(
                max(
                    face_width_ratio,
                    face_height_ratio,
                )
            ),
            "center_offset_x": self._rounded(
                center_x - 0.5
            ),
            "center_offset_y": self._rounded(
                center_y - 0.5
            ),
        }

    def _face_pose(
        self,
        result: Any,
    ) -> dict[str, float] | None:
        matrices = list(
            getattr(
                result,
                "facial_transformation_matrixes",
                [],
            )
            or []
        )

        if len(matrices) != 1:
            return None

        matrix = np.asarray(
            matrices[0],
            dtype=np.float64,
        )

        if (
            matrix.ndim != 2
            or matrix.shape[0] < 3
            or matrix.shape[1] < 3
            or not np.isfinite(matrix[:3, :3]).all()
        ):
            return None

        rotation = matrix[:3, :3]

        left, _, right = np.linalg.svd(rotation)
        rotation = left @ right

        if np.linalg.det(rotation) < 0:
            left[:, -1] *= -1
            rotation = left @ right

        horizontal = math.sqrt(
            float(rotation[0, 0] ** 2)
            + float(rotation[1, 0] ** 2)
        )

        singular = horizontal < 1e-6

        if not singular:
            pitch = math.atan2(
                float(rotation[2, 1]),
                float(rotation[2, 2]),
            )
            yaw = math.atan2(
                -float(rotation[2, 0]),
                horizontal,
            )
            roll = math.atan2(
                float(rotation[1, 0]),
                float(rotation[0, 0]),
            )
        else:
            pitch = math.atan2(
                -float(rotation[1, 2]),
                float(rotation[1, 1]),
            )
            yaw = math.atan2(
                -float(rotation[2, 0]),
                horizontal,
            )
            roll = 0.0

        return {
            "yaw_degrees": self._rounded(
                math.degrees(yaw)
            ),
            "pitch_degrees": self._rounded(
                math.degrees(pitch)
            ),
            "roll_degrees": self._rounded(
                math.degrees(roll)
            ),
        }

    def _eye_evidence(
        self,
        result: Any,
    ) -> dict[str, float] | None:
        face_blendshapes = list(
            getattr(
                result,
                "face_blendshapes",
                [],
            )
            or []
        )

        if len(face_blendshapes) != 1:
            return None

        scores: dict[str, float] = {}

        for category in face_blendshapes[0]:
            name = str(
                getattr(
                    category,
                    "category_name",
                    "",
                )
                or getattr(
                    category,
                    "display_name",
                    "",
                )
            )

            score = float(
                getattr(
                    category,
                    "score",
                    math.nan,
                )
            )

            if name and math.isfinite(score):
                scores[name] = min(
                    max(score, 0.0),
                    1.0,
                )

        left_blink = scores.get("eyeBlinkLeft")
        right_blink = scores.get("eyeBlinkRight")

        if left_blink is None or right_blink is None:
            return None

        return {
            "left_eye_open_score": self._rounded(
                1.0 - left_blink
            ),
            "right_eye_open_score": self._rounded(
                1.0 - right_blink
            ),
        }

    @staticmethod
    def _rounded(value: float) -> float:
        return round(float(value), 6)


@lru_cache(maxsize=1)
def get_facial_inference_engine() -> FacialInferenceEngine:
    return FacialInferenceEngine()


def close_cached_facial_inference_engine() -> bool:
    """
    Fecha e remove a instância compartilhada sem criar uma nova no shutdown.
    """
    if (
        get_facial_inference_engine
        .cache_info()
        .currsize
        == 0
    ):
        return False

    engine = get_facial_inference_engine()

    engine.close()
    get_facial_inference_engine.cache_clear()

    return True
