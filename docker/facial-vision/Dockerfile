FROM python:3.12.13-slim-trixie AS dependencies

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

WORKDIR /opt/facial-vision

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
