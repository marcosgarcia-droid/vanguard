<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use InvalidArgumentException;

final readonly class AccessDeviceNetworkAddressPolicy
{
    /**
     * @var array<int, string>
     */
    private const PRIVATE_IPV4_CIDRS = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    public function __construct(
        private AccessControlRuntime $runtime,
    ) {}

    public function assertAllowed(
        string $ipAddress
    ): void {
        if (
            filter_var(
                $ipAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            ) === false
        ) {
            throw new InvalidArgumentException(
                'O endereço IP do dispositivo é inválido.'
            );
        }

        if (
            ! $this->matchesAny(
                $ipAddress,
                self::PRIVATE_IPV4_CIDRS
            )
        ) {
            throw new InvalidArgumentException(
                'Somente endereços IPv4 privados podem ser utilizados nos dispositivos de acesso.'
            );
        }

        $allowedCidrs =
            $this->runtime->allowedCidrs();

        if ($allowedCidrs === []) {
            throw new InvalidArgumentException(
                'Nenhuma rede de controle de acesso foi autorizada no ambiente.'
            );
        }

        foreach ($allowedCidrs as $cidr) {
            $this->parseCidr($cidr);
        }

        if (
            ! $this->matchesAny(
                $ipAddress,
                $allowedCidrs
            )
        ) {
            throw new InvalidArgumentException(
                'O endereço IP do dispositivo não pertence às redes autorizadas para o controle de acesso.'
            );
        }
    }

    /**
     * @param  array<int, string>  $cidrs
     */
    private function matchesAny(
        string $ipAddress,
        array $cidrs
    ): bool {
        foreach ($cidrs as $cidr) {
            [
                $network,
                $prefix,
            ] = $this->parseCidr($cidr);

            if (
                $this->matchesCidr(
                    $ipAddress,
                    $network,
                    $prefix
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseCidr(
        string $cidr
    ): array {
        $parts = explode(
            '/',
            trim($cidr),
            2
        );

        if (
            count($parts) !== 2
            || filter_var(
                $parts[0],
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            ) === false
            || ! ctype_digit($parts[1])
        ) {
            throw new InvalidArgumentException(
                "A rede autorizada {$cidr} é inválida."
            );
        }

        $prefix = (int) $parts[1];

        if ($prefix < 0 || $prefix > 32) {
            throw new InvalidArgumentException(
                "A rede autorizada {$cidr} é inválida."
            );
        }

        return [
            $parts[0],
            $prefix,
        ];
    }

    private function matchesCidr(
        string $ipAddress,
        string $network,
        int $prefix
    ): bool {
        $ipBinary = inet_pton($ipAddress);
        $networkBinary = inet_pton($network);

        if (
            $ipBinary === false
            || $networkBinary === false
        ) {
            return false;
        }

        $ipValue = unpack('N', $ipBinary)[1];
        $networkValue =
            unpack('N', $networkBinary)[1];

        $mask = $prefix === 0
            ? 0
            : (
                0xFFFFFFFF
                << (32 - $prefix)
            ) & 0xFFFFFFFF;

        return ($ipValue & $mask)
            === ($networkValue & $mask);
    }
}
