from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import urllib.parse
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Final

EXPECTED_HOST: Final[str] = "storage.googleapis.com"
DOWNLOAD_CHUNK_BYTES: Final[int] = 1024 * 1024


class ModelDownloadError(RuntimeError):
    """Indica que o modelo oficial não pôde ser obtido com integridade."""


@dataclass(frozen=True, slots=True)
class ModelManifest:
    immutable_url: str
    generation: str
    size_bytes: int
    sha256: str


def required_string(
    payload: dict[str, Any],
    key: str,
) -> str:
    value = payload.get(key)

    if not isinstance(value, str) or not value:
        raise ModelDownloadError(
            f"Campo obrigatório inválido no manifesto: {key}."
        )

    return value


def required_integer(
    payload: dict[str, Any],
    key: str,
) -> int:
    value = payload.get(key)

    if (
        not isinstance(value, int)
        or isinstance(value, bool)
        or value <= 0
    ):
        raise ModelDownloadError(
            f"Campo obrigatório inválido no manifesto: {key}."
        )

    return value


def load_manifest(path: Path) -> ModelManifest:
    try:
        payload = json.loads(
            path.read_text(encoding="utf-8")
        )
    except (OSError, json.JSONDecodeError) as exception:
        raise ModelDownloadError(
            "Não foi possível ler o manifesto do modelo."
        ) from exception

    if not isinstance(payload, dict):
        raise ModelDownloadError(
            "O manifesto do modelo deve ser um objeto JSON."
        )

    if payload.get("schema_version") != 1:
        raise ModelDownloadError(
            "Versão não suportada do manifesto do modelo."
        )

    immutable_url = required_string(
        payload,
        "immutable_url",
    )

    generation = required_string(
        payload,
        "generation",
    )

    sha256 = required_string(
        payload,
        "sha256",
    ).lower()

    size_bytes = required_integer(
        payload,
        "size_bytes",
    )

    if re.fullmatch(r"[1-9][0-9]*", generation) is None:
        raise ModelDownloadError(
            "Generation inválida no manifesto."
        )

    if re.fullmatch(r"[0-9a-f]{64}", sha256) is None:
        raise ModelDownloadError(
            "SHA-256 inválido no manifesto."
        )

    parsed_url = urllib.parse.urlparse(immutable_url)

    if (
        parsed_url.scheme != "https"
        or parsed_url.netloc != EXPECTED_HOST
    ):
        raise ModelDownloadError(
            "A origem do modelo não é permitida."
        )

    query = urllib.parse.parse_qs(
        parsed_url.query,
        keep_blank_values=True,
    )

    if query.get("alt") != ["media"]:
        raise ModelDownloadError(
            "O download oficial deve usar alt=media."
        )

    if query.get("generation") != [generation]:
        raise ModelDownloadError(
            "A URL não está fixada na generation declarada."
        )

    return ModelManifest(
        immutable_url=immutable_url,
        generation=generation,
        size_bytes=size_bytes,
        sha256=sha256,
    )


def download_model(
    manifest: ModelManifest,
    output_path: Path,
) -> None:
    output_path.parent.mkdir(
        parents=True,
        exist_ok=True,
    )

    temporary_path = output_path.with_suffix(
        output_path.suffix + ".tmp"
    )

    temporary_path.unlink(missing_ok=True)

    digest = hashlib.sha256()
    downloaded_bytes = 0

    request = urllib.request.Request(
        manifest.immutable_url,
        headers={
            "Accept": "application/octet-stream",
            "User-Agent": (
                "Vanguard-Facial-Vision-Build/1.0"
            ),
        },
    )

    try:
        with urllib.request.urlopen(
            request,
            timeout=180,
        ) as response:
            returned_generation = response.headers.get(
                "x-goog-generation"
            )

            if (
                returned_generation is not None
                and returned_generation
                != manifest.generation
            ):
                raise ModelDownloadError(
                    "A generation retornada difere "
                    "da revisão solicitada."
                )

            with temporary_path.open("wb") as output:
                while chunk := response.read(
                    DOWNLOAD_CHUNK_BYTES
                ):
                    output.write(chunk)
                    digest.update(chunk)
                    downloaded_bytes += len(chunk)

                output.flush()
                os.fsync(output.fileno())

        if downloaded_bytes != manifest.size_bytes:
            raise ModelDownloadError(
                "Tamanho inesperado do modelo: "
                f"{downloaded_bytes} bytes."
            )

        calculated_sha256 = digest.hexdigest()

        if calculated_sha256 != manifest.sha256:
            raise ModelDownloadError(
                "SHA-256 do modelo diferente "
                "do manifesto versionado."
            )

        temporary_path.replace(output_path)
        output_path.chmod(0o444)
    except BaseException:
        temporary_path.unlink(missing_ok=True)
        raise


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Baixa uma revisão imutável do modelo "
            "e exige correspondência de tamanho e SHA-256."
        )
    )

    parser.add_argument(
        "--manifest",
        required=True,
        type=Path,
    )

    parser.add_argument(
        "--output",
        required=True,
        type=Path,
    )

    return parser.parse_args()


def main() -> None:
    arguments = parse_arguments()

    manifest = load_manifest(arguments.manifest)

    download_model(
        manifest,
        arguments.output,
    )

    print(
        json.dumps(
            {
                "generation": manifest.generation,
                "sha256": manifest.sha256,
                "size_bytes": manifest.size_bytes,
                "output": str(arguments.output),
            },
            sort_keys=True,
        )
    )


if __name__ == "__main__":
    main()
