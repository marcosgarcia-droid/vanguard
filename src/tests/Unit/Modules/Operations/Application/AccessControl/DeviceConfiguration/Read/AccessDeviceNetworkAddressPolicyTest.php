<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceNetworkAddressPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessDeviceNetworkAddressPolicyTest extends TestCase
{
    public function test_it_accepts_private_ip_inside_an_authorized_network(): void
    {
        config()->set(
            'access_control.allowed_cidrs',
            ['192.168.50.0/24']
        );

        app(AccessDeviceNetworkAddressPolicy::class)
            ->assertAllowed('192.168.50.21');

        $this->assertTrue(true);
    }

    #[DataProvider('blockedAddressProvider')]
    public function test_it_blocks_non_private_addresses(
        string $ipAddress
    ): void {
        config()->set(
            'access_control.allowed_cidrs',
            ['0.0.0.0/0']
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        app(AccessDeviceNetworkAddressPolicy::class)
            ->assertAllowed($ipAddress);
    }

    public function test_it_blocks_private_ip_outside_authorized_networks(): void
    {
        config()->set(
            'access_control.allowed_cidrs',
            ['192.168.50.0/24']
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'não pertence às redes autorizadas'
        );

        app(AccessDeviceNetworkAddressPolicy::class)
            ->assertAllowed('192.168.60.21');
    }

    public function test_it_fails_closed_when_no_network_is_configured(): void
    {
        config()->set(
            'access_control.allowed_cidrs',
            []
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Nenhuma rede de controle de acesso foi autorizada'
        );

        app(AccessDeviceNetworkAddressPolicy::class)
            ->assertAllowed('192.168.50.21');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedAddressProvider(): array
    {
        return [
            'internet público' => ['8.8.8.8'],
            'loopback' => ['127.0.0.1'],
            'link local' => ['169.254.10.20'],
            'multicast' => ['224.0.0.1'],
            'ipv6 local' => ['::1'],
            'endereço inválido' => ['device.local'],
        ];
    }
}
