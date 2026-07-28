@php
    $statePath = $getStatePath();

    $modalId = $field->getModalId();
@endphp

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
                    Foto do visitante
                </x-slot>

                <x-slot name="description">
                    Use a câmera ou carregue uma imagem JPG, PNG ou WebP com até 5 MB.
                </x-slot>

                <div
                    x-data="{
                        modalId: @js($modalId),
                        statePath: @js($statePath),
                        stream: null,
                        previewUrl: null,
                        cameraActive: false,
                        fileSelected: false,
                        uploading: false,
                        uploaded: false,
                        confirmed: false,
                        progress: 0,
                        errorMessage: null,
                        statusMessage:
                            'Escolha uma das opções abaixo para adicionar a foto.',

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

                        usePhoto() {
                            if (! this.uploaded) {
                                this.errorMessage =
                                    'Aguarde o envio da foto ser concluído.'

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
                            statusMessage =
                                'Foto preparada. Clique em Usar esta foto.';
                        "
                        x-on:livewire-upload-error="
                            uploading = false;
                            uploaded = false;
                            progress = 0;
                            errorMessage =
                                'Não foi possível enviar a foto. Tente novamente.';
                        "
                        class="hidden"
                    />

                    <div
                        class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_17rem]"
                    >
                        <div
                            class="relative mx-auto w-full overflow-hidden rounded-xl bg-gray-950 shadow-sm ring-1 ring-gray-950/10 dark:ring-white/10"
                            style="max-width: 400px; aspect-ratio: 4 / 5;"
                        >
                            <video
                                x-ref="video"
                                x-show="cameraActive && ! previewUrl"
                                autoplay
                                muted
                                playsinline
                                class="h-full w-full object-cover"
                                style="transform: scaleX(-1);"
                            ></video>

                            <img
                                x-show="previewUrl"
                                x-bind:src="previewUrl"
                                alt="Pré-visualização da foto"
                                class="h-full w-full object-cover"
                                x-cloak
                            />

                            <div
                                x-show="! cameraActive && ! previewUrl"
                                class="flex h-full flex-col items-center justify-center gap-3 px-8 text-center text-gray-300"
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
                                class="pointer-events-none absolute inset-0 flex items-center justify-center"
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

                        <div class="flex flex-col gap-3">
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
                                x-bind:disabled="uploading"
                                x-cloak
                            >
                                Escolher outra
                            </x-filament::button>

                            <div
                                class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10"
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
                                class="mt-auto flex flex-wrap justify-end gap-2 pt-3"
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
                                    x-bind:disabled="! uploaded || uploading"
                                    x-cloak
                                >
                                    Usar esta foto
                                </x-filament::button>
                            </div>
                        </div>
                    </div>

                    <canvas
                        x-ref="canvas"
                        class="hidden"
                        aria-hidden="true"
                    ></canvas>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        A foto capturada pela câmera é preparada em JPEG vertical.
                        A análise automática de qualidade será adicionada na próxima etapa.
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
