FROM python:3.12.13-slim-trixie AS dependencies

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    HOME=/tmp \
    MPLCONFIGDIR=/tmp/matplotlib \
    XDG_CACHE_HOME=/tmp/cache

WORKDIR /opt/facial-vision

RUN set -eux; \
    apt-get update; \
    if apt-cache show libglib2.0-0t64 >/dev/null 2>&1; then \
        GLIB_PACKAGE="libglib2.0-0t64"; \
    else \
        GLIB_PACKAGE="libglib2.0-0"; \
    fi; \
    apt-get install \
        --yes \
        --no-install-recommends \
        libgl1 \
        libsm6 \
        libx11-6 \
        libxcb1 \
        libxext6 \
        "$GLIB_PACKAGE"; \
    rm -rf /var/lib/apt/lists/*

COPY requirements.txt ./

RUN python -m pip install \
    --no-cache-dir \
    --requirement requirements.txt

FROM dependencies AS test

COPY requirements-dev.txt ./

RUN python -m pip install \
    --no-cache-dir \
    --requirement requirements-dev.txt

COPY app ./app
COPY tests ./tests

RUN python -m compileall -q app \
    && python -m pytest -q tests

FROM dependencies AS runtime

COPY --chown=10001:10001 app ./app

USER 10001:10001

EXPOSE 8000

CMD ["python", "-m", "uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--no-access-log"]
