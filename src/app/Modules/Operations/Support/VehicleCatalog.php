<?php

namespace App\Modules\Operations\Support;

final class VehicleCatalog
{
    public const OTHER = '__other__';

    /**
     * Catálogo operacional simplificado.
     *
     * Não representa versões, motorizações ou anos-modelo.
     * Os valores selecionados continuam sendo persistidos como texto.
     *
     * @var array<string, list<string>>
     */
    private const MODELS_BY_BRAND = [
        'Agrale' => [
            'Marruá',
        ],
        'Audi' => [
            'A1',
            'A3',
            'A4',
            'A5',
            'A6',
            'A7',
            'A8',
            'E-Tron',
            'Q3',
            'Q5',
            'Q7',
            'Q8',
            'R8',
            'TT',
        ],
        'BMW' => [
            'Série 1',
            'Série 2',
            'Série 3',
            'Série 4',
            'Série 5',
            'Série 7',
            'i3',
            'i4',
            'iX',
            'X1',
            'X2',
            'X3',
            'X4',
            'X5',
            'X6',
            'X7',
            'Z4',
        ],
        'BYD' => [
            'Dolphin',
            'Dolphin Mini',
            'Han',
            'King',
            'Seal',
            'Song Plus',
            'Song Pro',
            'Tan',
            'Yuan Plus',
        ],
        'Caoa Chery' => [
            'Arrizo 5',
            'Arrizo 6',
            'Celer',
            'Face',
            'iCar',
            'Tiggo 2',
            'Tiggo 3X',
            'Tiggo 5X',
            'Tiggo 7',
            'Tiggo 8',
        ],
        'Chevrolet' => [
            'Agile',
            'Astra',
            'Blazer',
            'Camaro',
            'Captiva',
            'Celta',
            'Classic',
            'Cobalt',
            'Corsa',
            'Cruze',
            'Equinox',
            'Joy',
            'Meriva',
            'Montana',
            'Onix',
            'Prisma',
            'S10',
            'Spin',
            'Tracker',
            'Trailblazer',
            'Vectra',
            'Zafira',
        ],
        'Citroën' => [
            'Aircross',
            'Basalt',
            'Berlingo',
            'C3',
            'C3 Aircross',
            'C4',
            'C4 Cactus',
            'C4 Lounge',
            'C5 Aircross',
            'Jumper',
            'Jumpy',
            'Xsara Picasso',
        ],
        'Fiat' => [
            'Argo',
            'Bravo',
            'Cronos',
            'Doblò',
            'Ducato',
            'Fastback',
            'Fiorino',
            'Freemont',
            'Grand Siena',
            'Idea',
            'Linea',
            'Marea',
            'Mobi',
            'Palio',
            'Pulse',
            'Punto',
            'Siena',
            'Stilo',
            'Strada',
            'Toro',
            'Uno',
        ],
        'Ford' => [
            'Bronco Sport',
            'Courier',
            'EcoSport',
            'Edge',
            'Escort',
            'Explorer',
            'F-150',
            'F-250',
            'Fiesta',
            'Focus',
            'Fusion',
            'Ka',
            'Maverick',
            'Mondeo',
            'Mustang',
            'Ranger',
            'Territory',
            'Transit',
        ],
        'GWM' => [
            'Haval H6',
            'Haval H6 GT',
            'Ora 03',
            'Poer',
            'Tank 300',
        ],
        'Honda' => [
            'Accord',
            'City',
            'City Hatch',
            'Civic',
            'CR-V',
            'Fit',
            'HR-V',
            'WR-V',
            'ZR-V',
        ],
        'Hyundai' => [
            'Azera',
            'Creta',
            'Elantra',
            'Equus',
            'Genesis',
            'HB20',
            'HB20S',
            'HR',
            'i30',
            'ix35',
            'Santa Fe',
            'Sonata',
            'Tucson',
            'Veloster',
            'Veracruz',
        ],
        'Iveco' => [
            'Daily',
            'Eurocargo',
            'Stralis',
            'Tector',
        ],
        'JAC' => [
            'E-JS1',
            'E-JS4',
            'iEV20',
            'J2',
            'J3',
            'J5',
            'J6',
            'T40',
            'T50',
            'T60',
        ],
        'Jeep' => [
            'Cherokee',
            'Commander',
            'Compass',
            'Gladiator',
            'Grand Cherokee',
            'Renegade',
            'Wrangler',
        ],
        'Kia' => [
            'Besta',
            'Bongo',
            'Carnival',
            'Cerato',
            'Mohave',
            'Niro',
            'Optima',
            'Picanto',
            'Rio',
            'Sorento',
            'Soul',
            'Sportage',
            'Stonic',
        ],
        'Land Rover' => [
            'Defender',
            'Discovery',
            'Discovery Sport',
            'Freelander',
            'Range Rover',
            'Range Rover Evoque',
            'Range Rover Sport',
            'Range Rover Velar',
        ],
        'Mercedes-Benz' => [
            'Classe A',
            'Classe B',
            'Classe C',
            'Classe E',
            'Classe G',
            'Classe S',
            'CLA',
            'CLS',
            'GLA',
            'GLB',
            'GLC',
            'GLE',
            'GLS',
            'Sprinter',
            'Vito',
        ],
        'Mitsubishi' => [
            'ASX',
            'Eclipse Cross',
            'L200',
            'Lancer',
            'Outlander',
            'Pajero',
            'Pajero Full',
            'Pajero Sport',
            'Triton',
        ],
        'Nissan' => [
            'Altima',
            'Frontier',
            'Kicks',
            'Leaf',
            'Livina',
            'March',
            'Maxima',
            'Murano',
            'Pathfinder',
            'Sentra',
            'Tiida',
            'Versa',
            'X-Trail',
        ],
        'Peugeot' => [
            '2008',
            '206',
            '207',
            '208',
            '3008',
            '307',
            '308',
            '408',
            '5008',
            'Boxer',
            'Expert',
            'Hoggar',
            'Partner',
        ],
        'RAM' => [
            '1500',
            '2500',
            '3500',
            'Classic',
            'Rampage',
        ],
        'Renault' => [
            'Captur',
            'Clio',
            'Duster',
            'Fluence',
            'Kangoo',
            'Kardian',
            'Kwid',
            'Logan',
            'Master',
            'Mégane',
            'Oroch',
            'Sandero',
            'Scénic',
            'Symbol',
            'Twingo',
            'Zoe',
        ],
        'Suzuki' => [
            'Grand Vitara',
            'Jimny',
            'Jimny Sierra',
            'S-Cross',
            'Swift',
            'SX4',
            'Vitara',
        ],
        'Toyota' => [
            'Bandeirante',
            'Camry',
            'Corolla',
            'Corolla Cross',
            'Etios',
            'Fielder',
            'Hilux',
            'Land Cruiser',
            'Prius',
            'RAV4',
            'SW4',
            'Yaris',
            'Yaris Sedan',
        ],
        'Volkswagen' => [
            'Amarok',
            'Bora',
            'CrossFox',
            'Delivery',
            'Fox',
            'Fusca',
            'Gol',
            'Golf',
            'Jetta',
            'Kombi',
            'Nivus',
            'Parati',
            'Passat',
            'Polo',
            'Saveiro',
            'SpaceFox',
            'T-Cross',
            'Taos',
            'Tiguan',
            'Touareg',
            'Up!',
            'Virtus',
            'Voyage',
        ],
        'Volvo' => [
            'C30',
            'C40',
            'EX30',
            'S40',
            'S60',
            'S90',
            'V40',
            'V60',
            'XC40',
            'XC60',
            'XC90',
        ],
    ];

    /**
     * @var list<string>
     */
    private const COLORS = [
        'Amarelo',
        'Azul',
        'Bege',
        'Branco',
        'Bronze',
        'Cinza',
        'Dourado',
        'Laranja',
        'Marrom',
        'Prata',
        'Preto',
        'Roxo',
        'Verde',
        'Vermelho',
        'Vinho',
    ];

    /**
     * @return array<string, string>
     */
    public static function brandOptions(): array
    {
        $options = [];

        foreach (array_keys(self::MODELS_BY_BRAND) as $brand) {
            $options[$brand] = $brand;
        }

        $options[self::OTHER] = 'Outra marca';

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function modelOptions(?string $brand): array
    {
        if (
            ! filled($brand)
            || $brand === self::OTHER
            || ! array_key_exists($brand, self::MODELS_BY_BRAND)
        ) {
            return [];
        }

        $models = self::MODELS_BY_BRAND[$brand];

        $options = [];

        foreach ($models as $model) {
            $options[$model] = $model;
        }

        $options[self::OTHER] = 'Outro modelo';

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function colorOptions(): array
    {
        $options = [];

        foreach (self::COLORS as $color) {
            $options[$color] = $color;
        }

        $options[self::OTHER] = 'Outra cor';

        return $options;
    }

    public static function resolveSelection(
        mixed $selected,
        mixed $other
    ): ?string {
        $selectedValue = trim((string) $selected);

        if ($selectedValue === self::OTHER) {
            $otherValue = trim((string) $other);

            return $otherValue !== ''
                ? $otherValue
                : null;
        }

        return $selectedValue !== ''
            ? $selectedValue
            : null;
    }

    public static function hasBrand(string $brand): bool
    {
        return array_key_exists($brand, self::MODELS_BY_BRAND);
    }

    public static function hasModel(
        string $brand,
        string $model
    ): bool {
        return in_array(
            $model,
            self::MODELS_BY_BRAND[$brand] ?? [],
            true
        );
    }
}
