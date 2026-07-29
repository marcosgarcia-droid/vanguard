<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class EloquentRegisterVisitorFacialPhotoConcurrencyTest extends TestCase
{
    private const FIRST_CONFIRMATION_KEY =
        'dddddddddddddddddddddddddddddddd'
        .'dddddddddddddddddddddddddddddddd';

    private const SECOND_CONFIRMATION_KEY =
        'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'
        .'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private string $directory;

    private string $databasePath;

    private string $workerPath;

    private string $mediaRoot;

    private string $firstSourcePath;

    private string $secondSourcePath;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! extension_loaded('pdo_sqlite')
            || ! function_exists('proc_open')
        ) {
            $this->markTestSkipped(
                'O teste concorrente exige PDO SQLite e proc_open.'
            );
        }

        $this->directory = storage_path(
            'framework/testing/facial-photo-concurrency-'
            .Str::uuid()
        );

        File::deleteDirectory(
            $this->directory
        );

        File::ensureDirectoryExists(
            $this->directory
        );

        $this->databasePath =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'database.sqlite';

        $this->workerPath =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'worker.php';

        $this->mediaRoot =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'facial-photos';

        File::ensureDirectoryExists(
            $this->mediaRoot
        );

        touch(
            $this->databasePath
        );

        $this->writeWorker();

        $syntax = $this->process([
            PHP_BINARY,
            '-l',
            $this->workerPath,
        ]);

        $syntax->run();

        $this->assertProcessSuccessful(
            $syntax,
            'A sintaxe do worker concorrente é inválida.'
        );

        $this->migrateTemporaryDatabase();

        $this->firstSourcePath =
            $this->createCheckerboardJpeg(
                'concurrent-confirmed.jpg',
                false
            );

        $this->secondSourcePath =
            $this->createCheckerboardJpeg(
                'fresh-confirmed.jpg',
                true
            );
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory(
                $this->directory
            );
        }

        parent::tearDown();
    }

    public function test_same_confirmation_is_consumed_once_under_real_process_concurrency_and_a_fresh_confirmation_remains_allowed(): void
    {
        $context = $this->createTemporaryContext();

        $confirmationContext =
            'visitor.update.'
            .$context['visitor_id']
            .'.photo_capture';

        $barrier =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'concurrent.start';

        $readyOne =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'worker-one.ready';

        $readyTwo =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'worker-two.ready';

        $resultOne =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'worker-one.json';

        $resultTwo =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'worker-two.json';

        $workerOne = $this->registrationProcess(
            visitorId: $context['visitor_id'],
            userId: $context['user_id'],
            sourcePath: $this->firstSourcePath,
            confirmationKey: self::FIRST_CONFIRMATION_KEY,
            confirmationContext: $confirmationContext,
            barrierPath: $barrier,
            readyPath: $readyOne,
            resultPath: $resultOne,
        );

        $workerTwo = $this->registrationProcess(
            visitorId: $context['visitor_id'],
            userId: $context['user_id'],
            sourcePath: $this->firstSourcePath,
            confirmationKey: self::FIRST_CONFIRMATION_KEY,
            confirmationContext: $confirmationContext,
            barrierPath: $barrier,
            readyPath: $readyTwo,
            resultPath: $resultTwo,
        );

        $workerOne->start();
        $workerTwo->start();

        $this->waitForWorkers(
            processes: [
                $workerOne,
                $workerTwo,
            ],
            readyPaths: [
                $readyOne,
                $readyTwo,
            ],
        );

        File::put(
            $barrier,
            'go'
        );

        $workerOne->wait();
        $workerTwo->wait();

        $this->assertProcessSuccessful(
            $workerOne,
            'O primeiro processo concorrente falhou.'
        );

        $this->assertProcessSuccessful(
            $workerTwo,
            'O segundo processo concorrente falhou.'
        );

        $firstResult =
            $this->readResult(
                $resultOne
            );

        $secondResult =
            $this->readResult(
                $resultTwo
            );

        $outcomes = [
            $firstResult['outcome']
                ?? null,

            $secondResult['outcome']
                ?? null,
        ];

        sort(
            $outcomes
        );

        $this->assertSame(
            [
                'duplicate',
                'success',
            ],
            $outcomes,
            json_encode(
                [
                    $firstResult,
                    $secondResult,
                ],
                JSON_UNESCAPED_UNICODE
                    | JSON_PRETTY_PRINT
            )
        );

        $this->assertDatabaseState(
            visitors: 1,
            photos: 1,
            consumptions: 1,
            media: 1,
        );

        $this->assertSame(
            1,
            $this->countMediaFiles()
        );

        $this->assertSame(
            [
                self::FIRST_CONFIRMATION_KEY,
            ],
            $this->confirmationKeys()
        );

        $this->assertFileExists(
            $this->firstSourcePath
        );

        $freshBarrier =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'fresh.start';

        $freshReady =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'fresh.ready';

        $freshResultPath =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'fresh.json';

        File::put(
            $freshBarrier,
            'go'
        );

        $freshProcess = $this->registrationProcess(
            visitorId: $context['visitor_id'],
            userId: $context['user_id'],
            sourcePath: $this->secondSourcePath,
            confirmationKey: self::SECOND_CONFIRMATION_KEY,
            confirmationContext: $confirmationContext,
            barrierPath: $freshBarrier,
            readyPath: $freshReady,
            resultPath: $freshResultPath,
        );

        $freshProcess->run();

        $this->assertProcessSuccessful(
            $freshProcess,
            'A confirmação nova e legítima foi rejeitada.'
        );

        $freshResult =
            $this->readResult(
                $freshResultPath
            );

        $this->assertSame(
            'success',
            $freshResult['outcome']
                ?? null,
            json_encode(
                $freshResult,
                JSON_UNESCAPED_UNICODE
                    | JSON_PRETTY_PRINT
            )
        );

        $this->assertDatabaseState(
            visitors: 1,
            photos: 2,
            consumptions: 2,
            media: 2,
        );

        $this->assertSame(
            2,
            $this->countMediaFiles()
        );

        $this->assertSame(
            [
                self::FIRST_CONFIRMATION_KEY,
                self::SECOND_CONFIRMATION_KEY,
            ],
            $this->confirmationKeys()
        );

        $this->assertFileExists(
            $this->secondSourcePath
        );
    }

    /**
     * @return array{
     *     visitor_id: string,
     *     user_id: int
     * }
     */
    private function createTemporaryContext(): array
    {
        $resultPath =
            $this->directory
            .DIRECTORY_SEPARATOR
            .'setup.json';

        $process = $this->process([
            PHP_BINARY,
            $this->workerPath,
            'setup',
            base_path(),
            $this->databasePath,
            $this->mediaRoot,
            $resultPath,
        ]);

        $process->run();

        $this->assertProcessSuccessful(
            $process,
            'Não foi possível preparar o cenário concorrente.'
        );

        $result =
            $this->readResult(
                $resultPath
            );

        $this->assertSame(
            'setup',
            $result['outcome']
                ?? null,
            json_encode(
                $result,
                JSON_UNESCAPED_UNICODE
                    | JSON_PRETTY_PRINT
            )
        );

        $visitorId =
            $result['visitor_id']
            ?? null;

        $userId =
            $result['user_id']
            ?? null;

        $this->assertIsString(
            $visitorId
        );

        $this->assertIsInt(
            $userId
        );

        return [
            'visitor_id' => $visitorId,
            'user_id' => $userId,
        ];
    }

    private function registrationProcess(
        string $visitorId,
        int $userId,
        string $sourcePath,
        string $confirmationKey,
        string $confirmationContext,
        string $barrierPath,
        string $readyPath,
        string $resultPath,
    ): Process {
        return $this->process([
            PHP_BINARY,
            $this->workerPath,
            'register',
            base_path(),
            $this->databasePath,
            $this->mediaRoot,
            $resultPath,
            $visitorId,
            (string) $userId,
            $sourcePath,
            $confirmationKey,
            $confirmationContext,
            $barrierPath,
            $readyPath,
        ]);
    }

    /**
     * @param  array<int, Process>  $processes
     * @param  array<int, string>  $readyPaths
     */
    private function waitForWorkers(
        array $processes,
        array $readyPaths
    ): void {
        $deadline =
            microtime(true) + 20;

        while (microtime(true) < $deadline) {
            $allReady = true;

            foreach ($readyPaths as $readyPath) {
                if (! is_file($readyPath)) {
                    $allReady = false;

                    break;
                }
            }

            if ($allReady) {
                return;
            }

            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail(
                        'Um processo terminou antes da barreira.'
                        .PHP_EOL
                        .$process->getOutput()
                        .PHP_EOL
                        .$process->getErrorOutput()
                    );
                }
            }

            usleep(
                20_000
            );
        }

        $this->fail(
            'Os processos não ficaram prontos para a disputa.'
        );
    }

    private function migrateTemporaryDatabase(): void
    {
        $process = $this->process([
            PHP_BINARY,
            'artisan',
            'migrate:fresh',
            '--force',
            '--no-interaction',
        ]);

        $process->setTimeout(
            120
        );

        $process->run();

        $this->assertProcessSuccessful(
            $process,
            'As migrations do SQLite temporário falharam.'
        );
    }

    /**
     * @param  array<int, string>  $command
     */
    private function process(
        array $command
    ): Process {
        $process = new Process(
            command: $command,
            cwd: base_path(),
            env: [
                'APP_ENV' => 'testing',
                'APP_DEBUG' => 'false',
                'ACTIVITYLOG_ENABLED' => 'false',
                'CACHE_STORE' => 'array',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->databasePath,
                'DB_FOREIGN_KEYS' => 'true',
                'DB_URL' => '',
                'FILESYSTEM_DISK' => 'local',
                'MEDIA_DISK' => 'facial_photos',
                'MEDIA_CONVERSIONS_DISK' => 'facial_photos',
                'QUEUE_CONNECTION' => 'sync',
                'VANGUARD_FACIAL_PHOTO_VALIDATION_ENABLED' => 'false',
                'VANGUARD_FACIAL_PHOTO_VALIDATION_PROVIDER' => '',
                'VANGUARD_FACIAL_PHOTO_VALIDATION_SIMULATOR_ENABLED' => 'false',
            ],
        );

        $process->setTimeout(
            45
        );

        return $process;
    }

    private function assertProcessSuccessful(
        Process $process,
        string $message
    ): void {
        $this->assertTrue(
            $process->isSuccessful(),
            $message
            .PHP_EOL
            .'STDOUT:'
            .PHP_EOL
            .$process->getOutput()
            .PHP_EOL
            .'STDERR:'
            .PHP_EOL
            .$process->getErrorOutput()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readResult(
        string $path
    ): array {
        $this->assertFileExists(
            $path
        );

        $contents =
            File::get(
                $path
            );

        $decoded =
            json_decode(
                $contents,
                true,
                flags: JSON_THROW_ON_ERROR
            );

        $this->assertIsArray(
            $decoded
        );

        return $decoded;
    }

    private function assertDatabaseState(
        int $visitors,
        int $photos,
        int $consumptions,
        int $media,
    ): void {
        $pdo =
            $this->database();

        $this->assertSame(
            $visitors,
            $this->tableCount(
                $pdo,
                'visitors'
            )
        );

        $this->assertSame(
            $photos,
            $this->tableCount(
                $pdo,
                'facial_photos'
            )
        );

        $this->assertSame(
            $consumptions,
            $this->tableCount(
                $pdo,
                'facial_photo_confirmation_consumptions'
            )
        );

        $this->assertSame(
            $media,
            $this->tableCount(
                $pdo,
                'media'
            )
        );
    }

    /**
     * @return array<int, string>
     */
    private function confirmationKeys(): array
    {
        $statement =
            $this->database()
                ->query(
                    'SELECT confirmation_key '
                    .'FROM facial_photo_confirmation_consumptions '
                    .'ORDER BY confirmation_key'
                );

        $keys =
            $statement->fetchAll(
                PDO::FETCH_COLUMN
            );

        return array_map(
            static fn (mixed $key): string => (string) $key,
            $keys
        );
    }

    private function database(): PDO
    {
        return new PDO(
            'sqlite:'.$this->databasePath,
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    private function tableCount(
        PDO $pdo,
        string $table
    ): int {
        $allowed = [
            'visitors',
            'facial_photos',
            'facial_photo_confirmation_consumptions',
            'media',
        ];

        $this->assertContains(
            $table,
            $allowed
        );

        return (int) $pdo
            ->query(
                "SELECT COUNT(*) FROM {$table}"
            )
            ->fetchColumn();
    }

    private function countMediaFiles(): int
    {
        if (! is_dir($this->mediaRoot)) {
            return 0;
        }

        $count = 0;

        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->mediaRoot,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    private function createCheckerboardJpeg(
        string $fileName,
        bool $inverted
    ): string {
        $path =
            $this->directory
            .DIRECTORY_SEPARATOR
            .$fileName;

        $image =
            imagecreatetruecolor(
                720,
                900
            );

        if (! $image instanceof GdImage) {
            $this->fail(
                'Não foi possível criar a imagem sintética.'
            );
        }

        $dark =
            imagecolorallocate(
                $image,
                40,
                40,
                40
            );

        $light =
            imagecolorallocate(
                $image,
                220,
                220,
                220
            );

        for ($y = 0; $y < 900; $y += 40) {
            for ($x = 0; $x < 720; $x += 40) {
                $even =
                    (
                        intdiv($x, 40)
                        + intdiv($y, 40)
                    ) % 2 === 0;

                $useLight =
                    $inverted
                        ? ! $even
                        : $even;

                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    min(
                        $x + 39,
                        719
                    ),
                    min(
                        $y + 39,
                        899
                    ),
                    $useLight
                        ? $light
                        : $dark
                );
            }
        }

        imagejpeg(
            $image,
            $path,
            90
        );

        imagedestroy(
            $image
        );

        $this->assertFileExists(
            $path
        );

        return $path;
    }

    private function writeWorker(): void
    {
        File::put(
            $this->workerPath,
            <<<'WORKER'
<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoCommand;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoUseCase;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoConfirmationConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$mode =
    $argv[1]
    ?? '';

$basePath =
    $argv[2]
    ?? '';

$database =
    $argv[3]
    ?? '';

$mediaRoot =
    $argv[4]
    ?? '';

$resultPath =
    $argv[5]
    ?? '';

function writeResult(
    string $path,
    array $payload
): void {
    File::put(
        $path,
        json_encode(
            $payload,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        )
    );
}

try {
    require $basePath
        .DIRECTORY_SEPARATOR
        .'vendor'
        .DIRECTORY_SEPARATOR
        .'autoload.php';

    $app = require $basePath
        .DIRECTORY_SEPARATOR
        .'bootstrap'
        .DIRECTORY_SEPARATOR
        .'app.php';

    $app->make(
        Kernel::class
    )->bootstrap();

    $requiredClasses = [
        User::class,
        TenantRecord::class,
        OrganizationRecord::class,
        VisitorRecord::class,
        RegisterVisitorFacialPhotoCommand::class,
        RegisterVisitorFacialPhotoException::class,
        RegisterVisitorFacialPhotoUseCase::class,
    ];

    foreach ($requiredClasses as $requiredClass) {
        if (! class_exists($requiredClass)) {
            throw new RuntimeException(
                'Dependência necessária não carregada pelo worker: '
                .$requiredClass
            );
        }
    }

    config([
        'activitylog.enabled' =>
            false,

        'database.default' =>
            'sqlite',

        'database.connections.sqlite.database' =>
            $database,

        'database.connections.sqlite.foreign_key_constraints' =>
            true,

        'database.connections.sqlite.busy_timeout' =>
            15_000,

        'database.connections.sqlite.journal_mode' =>
            'WAL',

        'database.connections.sqlite.synchronous' =>
            'NORMAL',

        'database.connections.sqlite.transaction_mode' =>
            'IMMEDIATE',

        'filesystems.disks.facial_photos.root' =>
            $mediaRoot,

        'filesystems.disks.facial_photos.throw' =>
            true,

        'media-library.disk_name' =>
            'facial_photos',

        'media-library.conversions_disk_name' =>
            'facial_photos',
    ]);

    DB::purge(
        'sqlite'
    );

    DB::setDefaultConnection(
        'sqlite'
    );

    $connection =
        DB::connection(
            'sqlite'
        );

    $connection->getPdo();

    $connection->statement(
        'PRAGMA busy_timeout = 15000'
    );

    if ($mode === 'setup') {
        $connection->statement(
            'PRAGMA journal_mode = WAL'
        );

        File::ensureDirectoryExists(
            $mediaRoot
        );

        $tenant =
            TenantRecord::query()
                ->create([
                    'id' =>
                        (string) Str::uuid(),

                    'name' =>
                        'GRUPO CONCORRÊNCIA FACIAL',

                    'status' =>
                        'active',
                ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' =>
                        (string) Str::uuid(),

                    'tenant_id' =>
                        $tenant->id,

                    'status' =>
                        'active',

                    'legal_name' =>
                        'UNIDADE CONCORRÊNCIA FACIAL LTDA',

                    'display_name' =>
                        'UNIDADE CONCORRÊNCIA FACIAL',

                    'unit_code' =>
                        'FCN-01',
                ]);

        $visitor =
            VisitorRecord::query()
                ->create([
                    'tenant_id' =>
                        $tenant->id,

                    'organization_id' =>
                        $organization->id,

                    'full_name' =>
                        'VISITANTE CONCORRÊNCIA FACIAL',

                    'status' =>
                        VisitorStatus::Active,
                ]);

        $user =
            User::query()
                ->create([
                    'name' =>
                        'OPERADOR CONCORRÊNCIA FACIAL',

                    'email' =>
                        'facial-concurrency-'
                        .Str::uuid()
                        .'@vanguard.test',

                    'password' =>
                        Hash::make(
                            'vanguard-test'
                        ),
                ]);

        writeResult(
            $resultPath,
            [
                'outcome' =>
                    'setup',

                'visitor_id' =>
                    (string) $visitor->getKey(),

                'user_id' =>
                    (int) $user->getKey(),
            ]
        );

        exit(0);
    }

    if ($mode !== 'register') {
        throw new RuntimeException(
            'Modo de worker não reconhecido.'
        );
    }

    $visitorId =
        $argv[6]
        ?? '';

    $userId =
        (int) (
            $argv[7]
            ?? 0
        );

    $sourcePath =
        $argv[8]
        ?? '';

    $confirmationKey =
        $argv[9]
        ?? '';

    $confirmationContext =
        $argv[10]
        ?? '';

    $barrierPath =
        $argv[11]
        ?? '';

    $readyPath =
        $argv[12]
        ?? '';

    File::put(
        $readyPath,
        'ready'
    );

    $deadline =
        microtime(true) + 20;

    while (! is_file($barrierPath)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException(
                'A barreira concorrente expirou.'
            );
        }

        usleep(
            10_000
        );
    }

    $fingerprint =
        hash_file(
            'sha256',
            $sourcePath
        );

    if (! is_string($fingerprint)) {
        throw new RuntimeException(
            'Não foi possível calcular o SHA-256.'
        );
    }

    try {
        $result = app(
            RegisterVisitorFacialPhotoUseCase::class
        )->execute(
            new RegisterVisitorFacialPhotoCommand(
                visitorId: $visitorId,
                absolutePath: $sourcePath,
                originalFileName: basename(
                    $sourcePath
                ),
                expectedSha256: $fingerprint,
                source: FacialPhotoSource::Webcam,
                confirmationKey: $confirmationKey,
                confirmationContext: $confirmationContext,
                createdBy: $userId,
            )
        );

        writeResult(
            $resultPath,
            [
                'outcome' =>
                    'success',

                'photo_id' =>
                    $result->photoId,

                'photo_status' =>
                    $result->status->value,
            ]
        );
    } catch (
        RegisterVisitorFacialPhotoException $exception
    ) {
        writeResult(
            $resultPath,
            [
                'outcome' =>
                    $exception
                        ->isConfirmationAlreadyConsumed()
                            ? 'duplicate'
                            : 'registration_error',

                'exception' =>
                    $exception::class,

                'message' =>
                    $exception->getMessage(),
            ]
        );
    }
} catch (Throwable $exception) {
    if ($resultPath !== '') {
        writeResult(
            $resultPath,
            [
                'outcome' =>
                    'fatal',

                'exception' =>
                    $exception::class,

                'message' =>
                    $exception->getMessage(),
            ]
        );

        exit(0);
    }

    throw $exception;
}
WORKER
        );
    }
}
