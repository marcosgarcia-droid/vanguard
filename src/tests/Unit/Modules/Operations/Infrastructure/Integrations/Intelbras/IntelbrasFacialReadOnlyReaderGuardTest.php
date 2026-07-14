<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReader;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConnectionData;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntelbrasFacialReadOnlyReaderGuardTest extends TestCase
{
    public function test_disabled_reads_never_issue_an_http_request(): void
    {
        config()->set(
            'access_control.reads_enabled',
            false
        );

        config()->set(
            'access_control.allowed_cidrs',
            ['192.168.1.0/24']
        );

        Http::fake();

        $reader = app(
            AccessDeviceConfigurationReader::class
        );

        try {
            $reader->read(
                $this->connection(
                    '192.168.1.201'
                )
            );

            $this->fail(
                'Era esperado o bloqueio da leitura.'
            );
        } catch (
            AccessDeviceConfigurationReadException $exception
        ) {
            $this->assertStringContainsString(
                'desativada neste ambiente',
                $exception->getMessage()
            );
        }

        Http::assertSentCount(0);
    }

    public function test_public_ip_never_issues_an_http_request(): void
    {
        config()->set(
            'access_control.reads_enabled',
            true
        );

        config()->set(
            'access_control.allowed_cidrs',
            ['192.168.1.0/24']
        );

        Http::fake();

        $reader = app(
            AccessDeviceConfigurationReader::class
        );

        try {
            $reader->read(
                $this->connection('8.8.8.8')
            );

            $this->fail(
                'Era esperado o bloqueio do endereço público.'
            );
        } catch (
            AccessDeviceConfigurationReadException $exception
        ) {
            $this->assertStringContainsString(
                'IPv4 privados',
                $exception->getMessage()
            );
        }

        Http::assertSentCount(0);
    }

    private function connection(
        string $ipAddress
    ): AccessDeviceConnectionData {
        return new AccessDeviceConnectionData(
            deviceId: 'device-demo',
            protocol: 'http',
            ipAddress: $ipAddress,
            port: 80,
            username: 'admin',
            password: 'synthetic-secret',
        );
    }
}
