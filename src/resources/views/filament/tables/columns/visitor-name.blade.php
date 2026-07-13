@php
    $disk = $record->photo_disk ?: 'local';
    $photoUrl = null;

    try {
        $storage = \Illuminate\Support\Facades\Storage::disk($disk);

        if (
            filled($record->photo_path)
            && $storage->exists($record->photo_path)
        ) {
            $photoUrl = $storage->temporaryUrl(
                $record->photo_path,
                now()->addMinutes(30),
            );
        }
    } catch (\Throwable) {
        $photoUrl = null;
    }

    $name = \App\Support\VanguardText::upper(
        $record->full_name
    );

    $initial = mb_strtoupper(
        mb_substr(
            trim((string) $record->full_name),
            0,
            1,
        )
    );

    $initial = filled($initial) ? $initial : '?';
@endphp

<div
    style="
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 15rem;
    "
>
    @if ($photoUrl)
        <img
            src="{{ $photoUrl }}"
            alt="Foto de {{ $record->full_name }}"
            loading="lazy"
            width="44"
            height="44"
            style="
                width: 2.75rem;
                height: 2.75rem;
                flex: 0 0 2.75rem;
                border-radius: 9999px;
                object-fit: cover;
            "
        >
    @else
        <div
            aria-label="Visitante sem foto"
            title="Visitante sem foto"
            style="
                width: 2.75rem;
                height: 2.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 2.75rem;
                border-radius: 9999px;
                background: rgba(148, 163, 184, 0.18);
                font-size: 0.875rem;
                font-weight: 700;
                line-height: 1;
            "
        >
            {{ $initial }}
        </div>
    @endif

    <div
        style="
            min-width: 0;
            font-weight: 600;
            line-height: 1.35;
            white-space: normal;
        "
    >
        {{ $name }}
    </div>
</div>
