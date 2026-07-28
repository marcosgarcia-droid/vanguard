@php
    $statePath = $getStatePath();

    $modalId = $field->getModalId();
@endphp

<style>
    .vanguard-facial-photo-modal-heading {
        display: block;
        width: 100%;
        text-align: center;
    }

    .vanguard-facial-photo-modal-description {
        display: block;
        width: 100%;
        text-align: center;
    }

    .vanguard-facial-photo-result-item {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        width: 100%;
        text-align: left;
    }

    .vanguard-facial-photo-result-item__content {
        flex: 1 1 auto;
        min-width: 0;
    }

    .vanguard-facial-photo-result-item
        .vanguard-facial-photo-result-symbol {
        margin-top: 0;
        line-height: 1.25rem;
    }
    .vanguard-facial-photo-analysis-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1.25rem;
        align-items: start;
        width: 100%;
    }

    .vanguard-facial-photo-analysis-grid--single {
        grid-template-columns: minmax(0, 1fr);
        justify-items: center;
    }

    .vanguard-facial-photo-frame {
        position: relative;
        display: block;
        width: min(100%, 400px);
        aspect-ratio: 4 / 5;
        margin-inline: auto;
        overflow: hidden;
        border-radius: 0.75rem;
        background: #111827;
        box-shadow:
            0 1px 2px rgb(0 0 0 / 0.08),
            0 0 0 1px rgb(17 24 39 / 0.12);
    }

    .vanguard-facial-photo-media {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .vanguard-facial-photo-placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 2rem;
        color: #d1d5db;
        text-align: center;
    }

    .vanguard-facial-photo-guide {
        position: absolute;
        inset: 0;
        display: flex;
        pointer-events: none;
        align-items: center;
        justify-content: center;
    }

    .vanguard-facial-photo-result-panel {
        width: 100%;
        border: 1px solid rgb(17 24 39 / 0.08);
        border-radius: 0.75rem;
        padding: 1rem;
        background: #f9fafb;
    }

    .vanguard-facial-photo-result-card {
        margin-top: 0.75rem;
        border: 1px solid transparent;
        border-radius: 0.625rem;
        padding: 0.875rem;
        text-align: left;
    }

    .vanguard-facial-photo-result-card--processing {
        border-color: rgb(59 130 246 / 0.28);
        background: rgb(59 130 246 / 0.08);
        color: #1d4ed8;
    }

    .vanguard-facial-photo-result-card--approved {
        border-color: rgb(22 163 74 / 0.28);
        background: rgb(22 163 74 / 0.08);
        color: #166534;
    }

    .vanguard-facial-photo-result-card--rejected {
        border-color: rgb(220 38 38 / 0.28);
        background: rgb(220 38 38 / 0.08);
        color: #991b1b;
    }

    .vanguard-facial-photo-result-card--inconclusive {
        border-color: rgb(217 119 6 / 0.34);
        background: rgb(245 158 11 / 0.10);
        color: #92400e;
    }

    .vanguard-facial-photo-result-card--failed {
        border-color: rgb(220 38 38 / 0.28);
        background: rgb(220 38 38 / 0.08);
        color: #991b1b;
    }

    .vanguard-facial-photo-result-symbol {
        flex: 0 0 auto;
        font-size: 1rem;
        line-height: 1.25rem;
    }

    .vanguard-facial-photo-bottom-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        width: 100%;
        padding-top: 0.75rem;
    }

    .vanguard-facial-photo-bottom-actions
        > div:not(.vanguard-facial-photo-final-actions) {
        order: 1;
        flex: 1 0 100%;
    }

    .vanguard-facial-photo-bottom-actions > button,
    .vanguard-facial-photo-bottom-actions > a,
    .vanguard-facial-photo-final-actions > button,
    .vanguard-facial-photo-final-actions > a {
        order: 2;
    }

    .vanguard-facial-photo-final-actions {
        display: contents;
    }

    @media (min-width: 1024px) {
        .vanguard-facial-photo-analysis-grid {
            grid-template-columns:
                minmax(0, 1fr)
                minmax(20rem, 22rem);
        }

        .vanguard-facial-photo-analysis-grid--single {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    .dark .vanguard-facial-photo-result-panel {
        border-color: rgb(255 255 255 / 0.10);
        background: rgb(255 255 255 / 0.04);
    }

    .dark .vanguard-facial-photo-result-card--approved {
        color: #86efac;
    }

    .dark .vanguard-facial-photo-result-card--rejected,
    .dark .vanguard-facial-photo-result-card--failed {
        color: #fca5a5;
    }

    .dark .vanguard-facial-photo-result-card--inconclusive {
        color: #fcd34d;
    }
</style>

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            modalId: @js($modalId),
            photoReady: false,
            selectedPreviewUrl: null,

            applySelectedPhoto(event) {
                const detail = event.detail ?? {}

                if (detail.id !== this.modalId) {
                    return
                }

                if (
                    this.selectedPreviewUrl
                    && this.selectedPreviewUrl
                        !== detail.previewUrl
                ) {
                    URL.revokeObjectURL(
                        this.selectedPreviewUrl,
                    )
                }

                this.selectedPreviewUrl =
                    detail.previewUrl

                this.photoReady =
                    Boolean(detail.previewUrl)
            },

            clearSelectedPhoto(event) {
                const detail = event.detail ?? {}

                if (detail.id !== this.modalId) {
                    return
                }

                if (this.selectedPreviewUrl) {
                    URL.revokeObjectURL(
                        this.selectedPreviewUrl,
                    )
                }

                this.selectedPreviewUrl = null
                this.photoReady = false
            },

            destroy() {
                if (this.selectedPreviewUrl) {
                    URL.revokeObjectURL(
                        this.selectedPreviewUrl,
                    )
                }
            },
        }"
        x-on:visitor-photo-selected.window="
            applySelectedPhoto($event)
        "
        x-on:visitor-photo-cleared.window="
            clearSelectedPhoto($event)
        "
        class="space-y-3"
    >
        <div
            class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            style="
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 1rem;
                width: 100%;
                text-align: center;
            "
        >
            <div
                style="
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 0.75rem;
                    width: 100%;
                "
            >
                <div
                    class="relative shrink-0 overflow-hidden rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-200 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20"
                    style="
                        width: 160px;
                        height: 200px;
                        min-width: 160px;
                        max-width: 100%;
                    "
                >
                    <img
                        x-show="photoReady && selectedPreviewUrl"
                        x-bind:src="selectedPreviewUrl"
                        alt="Foto adicionada ao cadastro"
                        class="h-full w-full object-cover"
                        style="
                            display: block;
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                        "
                        x-cloak
                    />

                    <div
                        x-show="! photoReady"
                        class="flex h-full w-full items-center justify-center"
                    >
                        <x-filament::icon
                            icon="heroicon-o-camera"
                            class="h-7 w-7"
                        />
                    </div>
                </div>

                <div
                    style="
                        width: 100%;
                        max-width: 240px;
                    "
                >
                    <div
                        class="text-sm font-medium text-gray-950 dark:text-white"
                        x-text="photoReady
                            ? 'Foto adicionada'
                            : 'Foto para identificação'"
                    ></div>

                    <div
                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        x-text="photoReady
                            ? 'A imagem está preparada e será salva com o visitante.'
                            : 'Capture pela câmera ou selecione uma imagem do dispositivo.'"
                    ></div>
                </div>
            </div>

            <x-filament::modal
                :id="$modalId"
                width="5xl"
                :autofocus="false"
                :close-by-clicking-away="false"
            >
                <x-slot name="trigger">
                    <x-filament::button
                        type="button"
                        icon="heroicon-m-camera"
                        style="
                            width: 100%;
                            justify-content: center;
                        "
                    >
                        <span
                            x-text="photoReady
                                ? 'Alterar foto'
                                : 'Adicionar foto'"
                        ></span>
                    </x-filament::button>
                </x-slot>

                <x-slot name="heading">
                    <span
                        class="vanguard-facial-photo-modal-heading"
                    >
                        Foto do visitante
                    </span>
                </x-slot>

                <x-slot name="description">
                    <span
                        class="vanguard-facial-photo-modal-description"
                    >
                        Use a câmera ou carregue uma imagem JPG, PNG ou WebP com até 5 MB.
                    </span>
                </x-slot>

                <div
                    x-data="{
                        modalId: @js($modalId),
                        statePath: @js($statePath),
                        receiptStatePath: @js($field->getReceiptStatePath()),
                        stream: null,
                        previewUrl: null,
                        cameraActive: false,
                        fileSelected: false,
                        uploading: false,
                        uploaded: false,
                        confirmed: false,
                        progress: 0,
                        analysisState: 'idle',
                        analysisResult: null,
                        analysisReceipt: null,
                        analysisMessage: null,
                        errorMessage: null,
                        statusMessage:
                            'Escolha uma das opções abaixo para adicionar a foto.',

                        previewEventMatches(event) {
                            const detail =
                                event.detail ?? {}

                            return detail.id === this.modalId
                                && detail.statePath === this.statePath
                        },

                        clearReceiptState() {
                            this.analysisReceipt = null

                            $wire.set(
                                this.receiptStatePath,
                                null,
                            )
                        },

                        resetAnalysis(state = 'idle') {
                            this.analysisState = state
                            this.analysisResult = null
                            this.clearReceiptState()
                            this.analysisMessage = null
                        },

                        handlePreviewCompleted(event) {
                            if (! this.previewEventMatches(event)) {
                                return
                            }

                            const detail =
                                event.detail ?? {}

                            const result =
                                detail.result ?? null

                            const decision =
                                result?.decision

                            if (
                                ! [
                                    'approved',
                                    'rejected',
                                    'inconclusive',
                                ].includes(decision)
                            ) {
                                this.handlePreviewFailed({
                                    detail: {
                                        id: this.modalId,
                                        statePath: this.statePath,
                                        message:
                                            'A resposta da análise não pôde ser interpretada.',
                                    },
                                })

                                return
                            }

                            const receipt =
                                typeof detail.receipt === 'string'
                                && detail.receipt.trim() !== ''
                                    ? detail.receipt
                                    : null

                            if (
                                result?.can_use_photo === true
                                && receipt === null
                            ) {
                                this.handlePreviewFailed({
                                    detail: {
                                        id: this.modalId,
                                        statePath: this.statePath,
                                        message:
                                            'Não foi possível preparar a confirmação temporária da foto.',
                                    },
                                })

                                return
                            }

                            this.uploading = false
                            this.uploaded = true
                            this.progress = 100
                            this.analysisState = decision
                            this.analysisResult = result
                            this.analysisReceipt = receipt
                            this.analysisMessage = null
                            this.errorMessage = null

                            this.statusMessage = {
                                approved:
                                    'A foto foi aprovada e pode ser utilizada.',
                                rejected:
                                    'A foto precisa ser refeita antes de continuar.',
                                inconclusive:
                                    'A foto pode ser usada e validada novamente depois.',
                            }[decision]
                        },

                        handlePreviewFailed(event) {
                            if (! this.previewEventMatches(event)) {
                                return
                            }

                            const detail =
                                event.detail ?? {}

                            this.uploading = false
                            this.uploaded = true
                            this.progress = 100
                            this.analysisState = 'failed'
                            this.analysisResult = null
                            this.clearReceiptState()
                            this.analysisMessage =
                                detail.message
                                ?? 'Não foi possível analisar a foto. Escolha outra imagem ou tente novamente.'
                            this.errorMessage = null
                            this.statusMessage =
                                'A análise da foto não pôde ser concluída.'
                        },

                        handlePreviewReset(event) {
                            if (! this.previewEventMatches(event)) {
                                return
                            }

                            this.uploading = false
                            this.uploaded = false
                            this.progress = 0
                            this.resetAnalysis()
                            this.errorMessage = null
                            this.statusMessage =
                                'Escolha uma das opções abaixo para adicionar a foto.'
                        },

                        canUsePhoto() {
                            return this.uploaded
                                && ! this.uploading
                                && this.analysisResult?.can_use_photo === true
                                && typeof this.analysisReceipt === 'string'
                                && this.analysisReceipt.trim() !== ''
                                && [
                                    'approved',
                                    'inconclusive',
                                ].includes(this.analysisState)
                        },

                        primaryActionLabel() {
                            return this.analysisState === 'inconclusive'
                                ? 'Usar e validar depois'
                                : 'Usar esta foto'
                        },

                        async startCamera() {
                            this.errorMessage = null

                            if (
                                ! window.isSecureContext
                                || ! navigator.mediaDevices
                                || ! navigator.mediaDevices.getUserMedia
                            ) {
                                this.errorMessage =
                                    'A câmera não está disponível neste navegador ou a conexão não é segura.'

                                return
                            }

                            this.stopCamera()

                            try {
                                this.stream =
                                    await navigator.mediaDevices.getUserMedia({
                                        audio: false,
                                        video: {
                                            facingMode: 'user',
                                            width: {
                                                ideal: 1280,
                                            },
                                            height: {
                                                ideal: 1600,
                                            },
                                        },
                                    })

                                this.$refs.video.srcObject =
                                    this.stream

                                await this.$refs.video.play()

                                this.cameraActive = true
                                this.statusMessage =
                                    'Posicione o rosto dentro da área indicada e capture a foto.'
                            } catch (error) {
                                this.cameraActive = false
                                this.errorMessage =
                                    this.cameraErrorMessage(error)
                            }
                        },

                        cameraErrorMessage(error) {
                            const messages = {
                                NotAllowedError:
                                    'A permissão da câmera foi negada. Libere o acesso nas configurações do navegador.',
                                NotFoundError:
                                    'Nenhuma câmera foi encontrada neste dispositivo.',
                                NotReadableError:
                                    'A câmera está sendo usada por outro aplicativo ou não pôde ser iniciada.',
                                OverconstrainedError:
                                    'A câmera disponível não atende às configurações necessárias.',
                                SecurityError:
                                    'O navegador bloqueou o uso da câmera por segurança.',
                            }

                            return messages[error?.name]
                                ?? 'Não foi possível iniciar a câmera. Verifique a permissão e tente novamente.'
                        },

                        capturePhoto() {
                            this.errorMessage = null

                            const video = this.$refs.video

                            if (
                                ! this.cameraActive
                                || ! video
                                || ! video.videoWidth
                                || ! video.videoHeight
                            ) {
                                this.errorMessage =
                                    'A câmera ainda não está pronta para capturar a foto.'

                                return
                            }

                            const canvas = this.$refs.canvas
                            const context =
                                canvas.getContext('2d')

                            if (! context) {
                                this.errorMessage =
                                    'O navegador não conseguiu preparar a captura.'

                                return
                            }

                            const targetWidth = 720
                            const targetHeight = 900
                            const targetRatio =
                                targetWidth / targetHeight

                            const sourceRatio =
                                video.videoWidth / video.videoHeight

                            let sourceWidth =
                                video.videoWidth

                            let sourceHeight =
                                video.videoHeight

                            let sourceX = 0
                            let sourceY = 0

                            if (sourceRatio > targetRatio) {
                                sourceWidth =
                                    video.videoHeight * targetRatio

                                sourceX =
                                    (
                                        video.videoWidth
                                        - sourceWidth
                                    ) / 2
                            } else {
                                sourceHeight =
                                    video.videoWidth / targetRatio

                                sourceY =
                                    (
                                        video.videoHeight
                                        - sourceHeight
                                    ) / 2
                            }

                            canvas.width = targetWidth
                            canvas.height = targetHeight

                            context.drawImage(
                                video,
                                sourceX,
                                sourceY,
                                sourceWidth,
                                sourceHeight,
                                0,
                                0,
                                targetWidth,
                                targetHeight,
                            )

                            canvas.toBlob(
                                (blob) => {
                                    if (! blob) {
                                        this.errorMessage =
                                            'Não foi possível gerar o arquivo da foto.'

                                        return
                                    }

                                    const file = new File(
                                        [blob],
                                        `visitante-camera-${Date.now()}.jpg`,
                                        {
                                            type: 'image/jpeg',
                                            lastModified: Date.now(),
                                        },
                                    )

                                    this.assignFile(file)
                                    this.stopCamera()
                                },
                                'image/jpeg',
                                0.92,
                            )
                        },

                        openFileSelector() {
                            this.errorMessage = null
                            this.$refs.fileInput.click()
                        },

                        selectedFileChanged(event) {
                            const file =
                                event.target.files?.[0]

                            if (! file) {
                                return
                            }

                            this.preparePreview(file)
                            this.stopCamera()
                        },

                        assignFile(file) {
                            if (! this.validateFile(file)) {
                                return
                            }

                            const transfer =
                                new DataTransfer()

                            transfer.items.add(file)

                            this.$refs.fileInput.files =
                                transfer.files

                            this.$refs.fileInput.dispatchEvent(
                                new Event(
                                    'change',
                                    {
                                        bubbles: true,
                                    },
                                ),
                            )
                        },

                        validateFile(file) {
                            const allowedTypes = [
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ]

                            if (! allowedTypes.includes(file.type)) {
                                this.errorMessage =
                                    'A foto deve estar em JPG, PNG ou WebP.'

                                return false
                            }

                            if (file.size > 5 * 1024 * 1024) {
                                this.errorMessage =
                                    'A foto deve possuir no máximo 5 MB.'

                                return false
                            }

                            return true
                        },

                        preparePreview(file) {
                            if (! this.validateFile(file)) {
                                this.$refs.fileInput.value = ''

                                return
                            }

                            if (this.previewUrl) {
                                URL.revokeObjectURL(
                                    this.previewUrl,
                                )
                            }

                            this.previewUrl =
                                URL.createObjectURL(file)

                            this.fileSelected = true
                            this.uploaded = false
                            this.confirmed = false
                            this.errorMessage = null
                            this.resetAnalysis('uploading')
                            this.statusMessage =
                                'Enviando a foto para preparação...'
                        },

                        clearPhoto() {
                            this.stopCamera()

                            if (this.previewUrl) {
                                URL.revokeObjectURL(
                                    this.previewUrl,
                                )
                            }

                            this.previewUrl = null
                            this.$refs.fileInput.value = ''

                            this.fileSelected = false
                            this.uploaded = false
                            this.confirmed = false
                            this.uploading = false
                            this.progress = 0
                            this.resetAnalysis()
                            this.errorMessage = null
                            this.statusMessage =
                                'Escolha a câmera ou selecione outro arquivo.'

                            $wire.set(
                                this.statePath,
                                null,
                            )

                            this.$dispatch(
                                'visitor-photo-cleared',
                                {
                                    id: this.modalId,
                                },
                            )
                        },

                        stopCamera() {
                            if (this.stream) {
                                this.stream
                                    .getTracks()
                                    .forEach(
                                        (track) => track.stop(),
                                    )
                            }

                            this.stream = null
                            this.cameraActive = false

                            if (this.$refs.video) {
                                this.$refs.video.srcObject =
                                    null
                            }
                        },

                        async usePhoto() {
                            if (! this.canUsePhoto()) {
                                if (
                                    this.uploading
                                    || [
                                        'uploading',
                                        'analyzing',
                                    ].includes(this.analysisState)
                                ) {
                                    this.errorMessage =
                                        'Aguarde a análise da foto ser concluída.'
                                } else if (
                                    this.analysisState === 'rejected'
                                ) {
                                    this.errorMessage =
                                        'Escolha ou capture outra foto para continuar.'
                                } else {
                                    this.errorMessage =
                                        'Esta foto ainda não pode ser utilizada.'
                                }

                                return
                            }

                            try {
                                await $wire.set(
                                    this.receiptStatePath,
                                    this.analysisReceipt,
                                )
                            } catch (error) {
                                this.errorMessage =
                                    'Não foi possível confirmar a foto. Tente novamente.'

                                return
                            }

                            this.stopCamera()
                            this.confirmed = true

                            this.$dispatch(
                                'visitor-photo-selected',
                                {
                                    id: this.modalId,
                                    previewUrl: this.previewUrl,
                                },
                            )

                            this.$dispatch(
                                'close-modal',
                                {
                                    id: this.modalId,
                                },
                            )
                        },

                        closePhotoModal() {
                            this.stopCamera()

                            this.$dispatch(
                                'close-modal',
                                {
                                    id: this.modalId,
                                },
                            )
                        },

                        destroy() {
                            this.stopCamera()

                            if (
                                this.previewUrl
                                && ! this.confirmed
                            ) {
                                URL.revokeObjectURL(
                                    this.previewUrl,
                                )
                            }
                        },
                    }"
                    x-on:close-modal.window="stopCamera()"
                    x-on:visitor-photo-preview-completed.window="
                        handlePreviewCompleted($event)
                    "
                    x-on:visitor-photo-preview-failed.window="
                        handlePreviewFailed($event)
                    "
                    x-on:visitor-photo-preview-reset.window="
                        handlePreviewReset($event)
                    "
                    class="space-y-5"
                >
                    <input
                        x-ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        wire:model="{{ $statePath }}"
                        x-on:change="selectedFileChanged($event)"
                        x-on:livewire-upload-start="
                            uploading = true;
                            uploaded = false;
                            progress = 0;
                            resetAnalysis('uploading');
                            errorMessage = null;
                            statusMessage = 'Enviando a foto...';
                        "
                        x-on:livewire-upload-progress="
                            progress = $event.detail.progress
                        "
                        x-on:livewire-upload-finish="
                            uploading = false;
                            uploaded = true;
                            progress = 100;

                            if (
                                ! [
                                    'approved',
                                    'rejected',
                                    'inconclusive',
                                    'failed',
                                ].includes(analysisState)
                            ) {
                                analysisState = 'analyzing';
                                statusMessage = 'Analisando a foto...';
                            }
                        "
                        x-on:livewire-upload-error="
                            uploading = false;
                            uploaded = false;
                            progress = 0;
                            analysisState = 'failed';
                            analysisResult = null;
                            clearReceiptState();
                            analysisMessage =
                                'Não foi possível enviar a foto. Tente novamente.';
                            errorMessage =
                                'Não foi possível enviar a foto. Tente novamente.';
                        "
                        class="hidden"
                        style="display: none;"
                        aria-hidden="true"
                        tabindex="-1"
                    />

                    <div
                        class="vanguard-facial-photo-analysis-grid"
                        x-bind:class="{
                            'vanguard-facial-photo-analysis-grid--single':
                                ! [
                                    'approved',
                                    'rejected',
                                    'inconclusive',
                                    'failed',
                                ].includes(analysisState),
                        }"
                    >
                        <div
                            class="vanguard-facial-photo-frame"
                        >
                            <video
                                x-ref="video"
                                x-show="cameraActive && ! previewUrl"
                                autoplay
                                muted
                                playsinline
                                class="vanguard-facial-photo-media"
                                style="transform: scaleX(-1);"
                            ></video>

                            <img
                                x-show="previewUrl"
                                x-bind:src="previewUrl"
                                alt="Pré-visualização da foto"
                                class="vanguard-facial-photo-media"
                                x-cloak
                            />

                            <div
                                x-show="! cameraActive && ! previewUrl"
                                class="vanguard-facial-photo-placeholder"
                            >
                                <x-filament::icon
                                    icon="heroicon-o-user-circle"
                                    class="h-16 w-16"
                                />

                                <span class="text-sm">
                                    Nenhuma foto selecionada
                                </span>
                            </div>

                            <div
                                x-show="cameraActive && ! previewUrl"
                                class="vanguard-facial-photo-guide"
                                x-cloak
                            >
                                <div
                                    class="border-2 border-dashed border-white/80"
                                    style="
                                        width: 58%;
                                        height: 70%;
                                        border-radius: 48%;
                                        box-shadow: 0 0 0 999px rgb(0 0 0 / 0.18);
                                    "
                                ></div>
                            </div>
                        </div>

                        <div
                            x-show="
                                [
                                    'approved',
                                    'rejected',
                                    'inconclusive',
                                    'failed',
                                ].includes(analysisState)
                            "
                            class="flex flex-col gap-3"
                            x-cloak
                        >
                            <section
                                class="vanguard-facial-photo-result-panel"
                                aria-live="polite"
                            >
                                <div
                                    class="text-sm font-semibold text-gray-950 dark:text-white"
                                >
                                    Resultado da análise
                                </div>

                                <div
                                    x-show="analysisState === 'idle'"
                                    class="mt-3 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    A análise começará após a captura ou seleção da foto.
                                </div>

                                <div
                                    x-show="
                                        analysisState === 'uploading'
                                        || analysisState === 'analyzing'
                                    "
                                    class="vanguard-facial-photo-result-card vanguard-facial-photo-result-card--processing flex items-center gap-3 text-sm"
                                    role="status"
                                    x-cloak
                                >
                                    <x-filament::loading-indicator
                                        class="h-5 w-5 shrink-0"
                                    />

                                    <span
                                        x-text="
                                            analysisState === 'uploading'
                                                ? 'Enviando a foto...'
                                                : 'Analisando a foto...'
                                        "
                                    ></span>
                                </div>

                                <div
                                    x-show="analysisState === 'approved'"
                                    class="vanguard-facial-photo-result-card vanguard-facial-photo-result-card--approved"
                                    x-cloak
                                >
                                    <div class="flex items-start gap-2">
                                        <x-filament::icon
                                            icon="heroicon-m-check-circle"
                                            class="mt-0.5 h-5 w-5 shrink-0"
                                        />

                                        <div>
                                            <div
                                                class="text-sm font-semibold"
                                                x-text="
                                                    analysisResult?.label
                                                    ?? 'Foto aprovada'
                                                "
                                            ></div>

                                            <p class="mt-1 text-xs">
                                                A imagem atende aos requisitos para continuar.
                                            </p>
                                        </div>
                                    </div>

                                    <ul class="mt-3 space-y-2 text-xs">
                                        <li
                                            x-show="
                                                analysisResult
                                                    ?.technical_analysis_passed
                                            "
                                            class="flex items-start gap-2"
                                        >
                                            <span
                                                    aria-hidden="true"
                                                    class="vanguard-facial-photo-result-symbol"
                                                >
                                                    ✅
                                                </span>

                                            <span>
                                                Arquivo e qualidade técnica aprovados
                                            </span>
                                        </li>

                                        <li
                                            x-show="
                                                analysisResult
                                                    ?.facial_validation_performed
                                            "
                                            class="flex items-start gap-2"
                                        >
                                            <span
                                                    aria-hidden="true"
                                                    class="vanguard-facial-photo-result-symbol"
                                                >
                                                    ✅
                                                </span>

                                            <span>
                                                Validação facial concluída
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                <div
                                    x-show="analysisState === 'rejected'"
                                    class="vanguard-facial-photo-result-card vanguard-facial-photo-result-card--rejected"
                                    x-cloak
                                >
                                    <div class="flex items-start gap-2">
                                        <x-filament::icon
                                            icon="heroicon-m-x-circle"
                                            class="mt-0.5 h-5 w-5 shrink-0"
                                        />

                                        <div>
                                            <div
                                                class="text-sm font-semibold"
                                                x-text="
                                                    analysisResult?.label
                                                    ?? 'Foto precisa ser refeita'
                                                "
                                            ></div>

                                            <p class="mt-1 text-xs">
                                                Corrija os pontos abaixo e capture outra foto.
                                            </p>
                                        </div>
                                    </div>

                                    <ul class="mt-3 space-y-3 text-xs">
                                        <template
                                            x-for="
                                                issue in (
                                                    analysisResult?.issues
                                                    ?? []
                                                )
                                            "
                                            x-bind:key="
                                                `${issue.source}-${issue.code}`
                                            "
                                        >
                                            <li
                                                class="vanguard-facial-photo-result-item"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    class="vanguard-facial-photo-result-symbol"
                                                >
                                                    ❌
                                                </span>

                                                <div
                                                    class="vanguard-facial-photo-result-item__content"
                                                >
                                                    <div
                                                        class="font-semibold"
                                                        x-text="issue.label"
                                                    ></div>

                                                    <div
                                                        class="mt-0.5"
                                                        x-text="issue.guidance"
                                                    ></div>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div
                                    x-show="analysisState === 'inconclusive'"
                                    class="vanguard-facial-photo-result-card vanguard-facial-photo-result-card--inconclusive"
                                    x-cloak
                                >
                                    <div class="flex items-start gap-2">
                                        <x-filament::icon
                                            icon="heroicon-m-exclamation-triangle"
                                            class="mt-0.5 h-5 w-5 shrink-0"
                                        />

                                        <div>
                                            <div
                                                class="text-sm font-semibold"
                                                x-text="
                                                    analysisResult?.label
                                                    ?? 'Validação inconclusiva'
                                                "
                                            ></div>

                                            <p class="mt-1 text-xs">
                                                A foto pode ser usada agora e validada novamente depois.
                                            </p>
                                        </div>
                                    </div>

                                    <ul class="mt-3 space-y-3 text-xs">
                                        <template
                                            x-for="
                                                issue in (
                                                    analysisResult?.issues
                                                    ?? []
                                                )
                                            "
                                            x-bind:key="
                                                `${issue.source}-${issue.code}`
                                            "
                                        >
                                            <li
                                                class="vanguard-facial-photo-result-item"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    class="vanguard-facial-photo-result-symbol"
                                                >
                                                    ⚠️
                                                </span>

                                                <div
                                                    class="vanguard-facial-photo-result-item__content"
                                                >
                                                    <div
                                                        class="font-semibold"
                                                        x-text="issue.label"
                                                    ></div>

                                                    <div
                                                        class="mt-0.5"
                                                        x-text="issue.guidance"
                                                    ></div>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div
                                    x-show="analysisState === 'failed'"
                                    class="vanguard-facial-photo-result-card vanguard-facial-photo-result-card--failed"
                                    role="alert"
                                    x-cloak
                                >
                                    <div class="flex items-start gap-2">
                                        <x-filament::icon
                                            icon="heroicon-m-exclamation-circle"
                                            class="mt-0.5 h-5 w-5 shrink-0"
                                        />

                                        <div>
                                            <div class="text-sm font-semibold">
                                                Não foi possível analisar a foto
                                            </div>

                                            <p
                                                class="mt-1 text-xs"
                                                x-text="analysisMessage"
                                            ></p>
                                        </div>
                                    </div>
                                </div>
                            </section>



                        </div>
                    </div>

                    <div
                        class="vanguard-facial-photo-bottom-actions"
                    >
                            <x-filament::button
                                type="button"
                                icon="heroicon-m-video-camera"
                                x-show="! cameraActive && ! previewUrl"
                                x-on:click="startCamera"
                                x-bind:disabled="uploading"
                                x-cloak
                            >
                                Usar câmera
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                color="gray"
                                icon="heroicon-m-photo"
                                x-show="! cameraActive && ! previewUrl"
                                x-on:click="openFileSelector"
                                x-bind:disabled="uploading"
                                x-cloak
                            >
                                Selecionar arquivo
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                color="success"
                                icon="heroicon-m-camera"
                                x-show="cameraActive && ! previewUrl"
                                x-on:click="capturePhoto"
                                x-bind:disabled="uploading"
                                x-cloak
                            >
                                Capturar foto
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                color="gray"
                                icon="heroicon-m-video-camera-slash"
                                x-show="cameraActive && ! previewUrl"
                                x-on:click="stopCamera"
                                x-cloak
                            >
                                Desligar câmera
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                color="gray"
                                icon="heroicon-m-arrow-path"
                                x-show="previewUrl"
                                x-on:click="clearPhoto"
                                x-bind:disabled="
                                    uploading
                                    || analysisState === 'analyzing'
                                "
                                x-cloak
                            >
                                Escolher outra
                            </x-filament::button>

                            <div
                                x-show="
                                    analysisState === 'idle'
                                    || analysisState === 'uploading'
                                    || analysisState === 'analyzing'
                                "
                                class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10"
                                x-cloak
                            >
                                <span x-text="statusMessage"></span>
                            </div>

                            <div
                                x-show="uploading"
                                class="space-y-1"
                                x-cloak
                            >
                                <div
                                    class="flex justify-between text-xs text-gray-500"
                                >
                                    <span>Enviando</span>
                                    <span x-text="`${progress}%`"></span>
                                </div>

                                <progress
                                    max="100"
                                    x-bind:value="progress"
                                    class="h-2 w-full"
                                ></progress>
                            </div>

                            <div
                                x-show="errorMessage"
                                class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 ring-1 ring-danger-200 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/30"
                                role="alert"
                                x-cloak
                            >
                                <span x-text="errorMessage"></span>
                            </div>

                            <div
                                class="vanguard-facial-photo-final-actions"
                            >
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    x-on:click="closePhotoModal"
                                >
                                    Voltar
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    icon="heroicon-m-check"
                                    x-show="previewUrl"
                                    x-on:click="usePhoto"
                                    x-bind:disabled="! canUsePhoto()"
                                    x-cloak
                                >
                                    <span
                                        x-text="primaryActionLabel()"
                                    ></span>
                                </x-filament::button>
                            </div>
                    </div>

                    <canvas
                        x-ref="canvas"
                        class="hidden"
                        aria-hidden="true"
                    ></canvas>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        A foto capturada pela câmera é preparada em JPEG vertical.
                        A análise é temporária e será confirmada novamente ao salvar o cadastro.
                    </p>
                </div>
            </x-filament::modal>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            A foto é opcional no cadastro, mas será necessária antes da liberação
            de acesso por reconhecimento facial.
        </p>
    </div>
</x-dynamic-component>
