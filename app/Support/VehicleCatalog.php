<?php

namespace App\Support;

class VehicleCatalog
{
    private const MODEL_GROUPS_BY_MAKE = [
        'Audi' => [
            'TT' => ['TT', 'TTS'],
        ],
        'BMW' => [
            '1 Series' => ['114', '116', '118', '120'],
            '2 Series' => ['216', '216 Active Tourer', '216 Gran Tourer', '218', '218 Active Tourer', '218 Gran Tourer', '220', '220 Active Tourer', '225 Active Tourer', '218 Gran Coupe'],
            '3 Series' => ['316', '318', '320', '330'],
            '4 Series' => ['418', '420', '420 Gran Coupe', '430'],
            '5 Series' => ['518', '520', '530', '540', '550'],
            '6 Series' => ['620 Gran Turismo'],
            '7 Series' => ['740', '750'],
            'M Models' => ['M3', 'M5'],
            'X Series' => ['X1', 'X2', 'X3', 'X4', 'X5', 'X6', 'X7'],
        ],
        'Ford' => [
            'Tourneo' => ['Tourneo Connect', 'Tourneo Courier', 'Tourneo Custom'],
            'Transit' => ['Transit Connect', 'Transit Courier', 'Transit Custom'],
        ],
        'Lexus' => [
            'IS series' => ['IS 300'],
            'RX series' => ['RX 450'],
        ],
        'Mercedes' => [
            'A-Class' => ['A 160', 'A 180', 'A 200', 'A 250', 'A 35 AMG'],
            'B-Class' => ['B 180', 'B 200', 'B 220', 'B 250'],
            'C-Class' => ['C 180', 'C 200', 'C 220', 'C 300', 'C 400', 'C 43 AMG'],
            'CLA-Class' => ['CLA 180', 'CLA 180 Shooting Brake', 'CLA 200', 'CLA 200 Shooting Brake', 'CLA 220', 'CLA 250', 'CLA 250 Shooting Brake', 'CLA Shooting Brake'],
            'CLS-Class' => ['CLS 220'],
            'E-Class' => ['E 200', 'E 220', 'E 250', 'E 300'],
            'GL-Class' => ['GL 400'],
            'GLA-Class' => ['GLA 180', 'GLA 200', 'GLA 220', 'GLA 250'],
            'GLB-Class' => ['GLB 180', 'GLB 200', 'GLB 220'],
            'GLC-Class' => ['GLC 200', 'GLC 220', 'GLC 250', 'GLC 300', 'GLC 350'],
            'GLE-Class' => ['GLE 300', 'GLE 350', 'GLE 400', 'GLE 450'],
            'V-Class' => ['V 220', 'V 300'],
        ],
        'Mini' => [
            'Clubman Series' => ['Cooper D Clubman', 'One Clubman', 'One D Clubman'],
            'Countryman Series' => ['Cooper Countryman', 'Cooper D Countryman', 'Cooper S Countryman', 'Cooper SE Countryman', 'One D Countryman'],
            'MINI' => ['Cooper', 'Cooper S', 'Cooper SE', 'ONE', 'One D'],
        ],
        'Porsche' => [
            'Series 911' => ['992'],
        ],
        'Volkswagen' => [
            'Golf' => ['Golf', 'Golf Plus', 'Golf Sportsvan'],
            'Passat' => ['Passat', 'Passat Variant'],
            'T5' => ['T5 Transporter'],
            'T6' => ['T6 Transporter'],
        ],
    ];

    private const MODELS_BY_MAKE = [
        'Abarth' => ['595C'],
        'Alfa Romeo' => ['Giulia', 'Giulietta', 'Mito', 'Stelvio', 'Tonale'],
        'Audi' => ['A1', 'A3', 'A4', 'A4 Allroad', 'A5', 'A6', 'A6 Allroad', 'A8', 'E-TRON', 'e-tron GT', 'Q2', 'Q3', 'Q4', 'Q4 e-tron', 'Q5', 'Q6 e-tron', 'Q7', 'Q8', 'R8', 'RS3', 'S4', 'TT', 'TTS'],
        'Bentley' => ['Continental GT'],
        'BMW' => ['1 Series', '114', '116', '118', '120', '2 Series', '216', '216 Active Tourer', '216 Gran Tourer', '218', '218 Active Tourer', '218 Gran Tourer', '220', '220 Active Tourer', '225 Active Tourer', '218 Gran Coupe', '3 Series', '316', '318', '320', '330', '4 Series', '418', '420', '420 Gran Coupe', '430', '5 Series', '518', '520', '530', '540', '550', '6 Series', '620 Gran Turismo', '7 Series', '740', '750', 'i3', 'i4', 'i7', 'iX', 'iX2', 'iX3', 'M Models', 'M3', 'M5', 'X Series', 'X1', 'X2', 'X3', 'X4', 'X5', 'X6', 'X7', 'Other'],
        'BYD' => ['ATTO 3', 'HAN', 'SEAL', 'Seal U'],
        'Chevrolet' => ['Captiva'],
        'Citroen' => ['Berlingo', 'C1', 'C3', 'C3 Aircross', 'C3 Picasso', 'C4', 'C4 Aircross', 'C4 Cactus', 'C4 Picasso', 'C4 X', 'C5 Aircross', 'C5 X', 'C8', 'DS3', 'DS4', 'e-Berlingo', 'e-C4', 'Grand C4 Picasso / SpaceTourer', 'Jumper', 'Jumpy'],
        'Cupra' => ['Born', 'Formentor', 'Leon', 'Tavascan', 'Other'],
        'Dacia' => ['Bigster', 'Duster', 'Jogger', 'Lodgy', 'Logan', 'Sandero'],
        'DAF' => ['LF250'],
        'Dodge' => ['RAM'],
        'DS Automobiles' => ['DS3', 'DS3 Crossback', 'DS4', 'DS7 Crossback', 'DS9', 'Other'],
        'Fiat' => ['500', '500e', '500L', '500X', 'Doblo', 'Ducato', 'Panda', 'Scudo', 'Talento', 'Tipo', 'Other'],
        'Ford' => ['Capri', 'EcoSport', 'Explorer', 'Fiesta', 'Focus', 'Galaxy', 'Kuga', 'Mondeo', 'Mustang', 'Mustang Mach-E', 'Puma', 'Ranger', 'S-Max', 'Tourneo', 'Tourneo Connect', 'Tourneo Courier', 'Tourneo Custom', 'Transit', 'Transit Connect', 'Transit Courier', 'Transit Custom'],
        'Honda' => ['Civic', 'CR-V', 'ZR-V', 'Other'],
        'Hyundai' => ['Bayon', 'H-1', 'i10', 'i20', 'i30', 'INSTER', 'IONIQ', 'IONIQ 5', 'IONIQ 6', 'Kona', 'Nexo', 'Santa Fe', 'Tucson'],
        'Iveco' => [],
        'Jaguar' => ['E-Pace', 'F-Pace', 'I-Pace', 'X-Type', 'XE', 'XF'],
        'Jeep' => ['Compass', 'Grand Cherokee', 'Renegade'],
        'Kia' => ['cee\'d', 'cee\'d Sportswagon', 'EV6', 'Niro', 'Picanto', 'Proceed', 'Rio', 'Sorento', 'Sportage', 'Stonic', 'XCeed', 'Other'],
        'Lancia' => ['Ypsilon'],
        'Land Rover' => ['Defender', 'Discovery', 'Discovery Sport', 'Range Rover', 'Range Rover Evoque', 'Range Rover Sport', 'Range Rover Velar', 'Other'],
        'Leapmotor' => [],
        'Lexus' => ['ES series', 'IS series', 'IS 300', 'LBX', 'RX series', 'RX 450', 'RZ', 'UX'],
        'Lotus' => ['Eletre'],
        'Lynk&Co' => ['01'],
        'MAN' => ['TGE'],
        'Maserati' => ['Grecale'],
        'Maxus' => ['Deliver 9', 'Other'],
        'Mazda' => ['2', '3', '6', 'CX-3', 'CX-30', 'CX-5', 'CX-60', 'MX-30', 'MX-5'],
        'Mercedes' => ['A-Class', 'A 160', 'A 180', 'A 200', 'A 250', 'A 35 AMG', 'B-Class', 'B 180', 'B 200', 'B 220', 'B 250', 'C-Class', 'C 180', 'C 200', 'C 220', 'C 300', 'C 400', 'C 43 AMG', 'Citan', 'CLA-Class', 'CLA 180', 'CLA 180 Shooting Brake', 'CLA 200', 'CLA 200 Shooting Brake', 'CLA 220', 'CLA 250', 'CLA 250 Shooting Brake', 'CLA Shooting Brake', 'CLS-Class', 'CLS 220', 'E-Class', 'E 200', 'E 220', 'E 250', 'E 300', 'EQA', 'EQB', 'EQC', 'EQE', 'GL-Class', 'GL 400', 'GLA-Class', 'GLA 180', 'GLA 200', 'GLA 220', 'GLA 250', 'GLB-Class', 'GLB 180', 'GLB 200', 'GLB 220', 'GLC 450', 'GLC-Class', 'GLC 200', 'GLC 220', 'GLC 250', 'GLC 300', 'GLC 350', 'GLE-Class', 'GLE 300', 'GLE 350', 'GLE 400', 'GLE 450', 'Sprinter', 'V-Class', 'V 220', 'V 300', 'Vito', 'Other'],
        'MG' => ['EHS', 'HS', 'Marvel R', 'MG3', 'MG4', 'MG5', 'ZS', 'Other'],
        'Mini' => ['Clubman Series', 'Cooper D Clubman', 'One Clubman', 'One D Clubman', 'Countryman Series', 'Cooper Countryman', 'Cooper D Countryman', 'Cooper S Countryman', 'Cooper SE Countryman', 'One D Countryman', 'MINI', 'Cooper', 'Cooper S', 'Cooper SE', 'ONE', 'One D', 'Other'],
        'Mitsubishi' => ['ASX', 'Eclipse Cross', 'Outlander', 'Space Star'],
        'Nissan' => ['Evalia', 'Juke', 'Leaf', 'Micra', 'NV400', 'Primastar', 'Qashqai', 'X-Trail', 'Other'],
        'Opel' => ['Adam', 'Astra', 'Combo', 'Combo Life', 'Corsa', 'Crossland X', 'Frontera', 'Grandland X', 'Insignia', 'Karl', 'Meriva', 'Mokka', 'Mokka X', 'Mokka-e', 'Movano', 'Vivaro', 'Zafira', 'Other'],
        'Other' => [],
        'Peugeot' => ['108', '2008', '207', '208', '3008', '307', '308', '408', '5008', '508', 'Boxer', 'e-208', 'e-408', 'Expert', 'Partner', 'Rifter', 'Traveller'],
        'Polestar' => ['2', '4'],
        'Porsche' => ['Cayenne', 'Macan', 'Panamera', 'Series 911', '992', 'Taycan'],
        'Renault' => ['Arkana', 'Austral', 'Captur', 'Clio', 'Espace', 'Express', 'Fluence', 'Grand Scenic', 'Kadjar', 'Kangoo', 'Koleos', 'Laguna', 'Master', 'Megane', 'Megane E-TECH', 'Modus', 'Rafale', 'Scenic', 'Scenic E-TECH', 'Symbioz', 'Talisman', 'Trafic', 'Twingo', 'ZOE', 'Other'],
        'Saab' => ['9-5'],
        'Seat' => ['Alhambra', 'Arona', 'Ateca', 'Ibiza', 'Leon', 'Mii', 'Tarraco', 'Toledo'],
        'Skoda' => ['Enyaq', 'Fabia', 'Kamiq', 'Karoq', 'Kodiaq', 'Octavia', 'Rapid', 'Roomster', 'Scala', 'Superb'],
        'Smart' => ['#1', 'ForFour', 'ForTwo'],
        'SsangYong' => ['Actyon'],
        'Subaru' => ['Impreza'],
        'Suzuki' => ['Across', 'Alto', 'Ignis', 'S-Cross', 'Splash', 'Swift', 'Vitara'],
        'Tesla' => ['Model 3', 'Model S', 'Model Y'],
        'Toyota' => ['Auris', 'Auris Touring Sports', 'Aygo', 'bZ4X', 'C-HR', 'Camry', 'Corolla', 'Corolla Cross', 'Highlander', 'Hilux', 'Mirai', 'Prius', 'Proace', 'PROACE CITY', 'RAV 4', 'Yaris', 'Yaris Cross', 'Other'],
        'VinFast' => ['VF8'],
        'Volkswagen' => ['Amarok', 'Arteon', 'Beetle', 'Caddy', 'Caddy Maxi', 'Crafter', 'Golf', 'Golf Plus', 'Golf Sportsvan', 'ID. Buzz', 'ID.3', 'ID.4', 'ID.5', 'Jetta', 'Passat', 'Passat Variant', 'Polo', 'Sharan', 'T-Cross', 'T-Roc', 'T5', 'T5 Transporter', 'T6', 'T6 Transporter', 'Taigo', 'Tiguan', 'Tiguan Allspace', 'Touran', 'up!', 'Other'],
        'Volvo' => ['C30', 'C40', 'EX30', 'S60', 'S80', 'S90', 'V40', 'V40 Cross Country', 'V50', 'V60', 'V60 Cross Country', 'V90', 'V90 Cross Country', 'XC40', 'XC60', 'XC90', 'Other'],
        'XPENG' => ['G9'],
    ];

    public static function makes(): array
    {
        return array_keys(self::MODELS_BY_MAKE);
    }

    public static function modelsByMake(): array
    {
        return self::MODELS_BY_MAKE;
    }

    public static function modelGroupsByMake(): array
    {
        return self::MODEL_GROUPS_BY_MAKE;
    }

    public static function isValidMake(?string $make): bool
    {
        return is_string($make) && array_key_exists($make, self::MODELS_BY_MAKE);
    }

    public static function isValidModelForMake(?string $make, ?string $model): bool
    {
        if (!self::isValidMake($make)) {
            return false;
        }

        if (!is_string($model) || trim($model) === '') {
            return true;
        }

        return in_array($model, self::MODELS_BY_MAKE[$make], true);
    }

    public static function isGroupSelection(?string $make, ?string $model): bool
    {
        if (!self::isValidMake($make) || !is_string($model) || trim($model) === '') {
            return false;
        }

        return array_key_exists($model, self::MODEL_GROUPS_BY_MAKE[$make] ?? []);
    }

    public static function modelsForSelection(?string $make, ?string $model): array
    {
        if (!self::isValidMake($make) || !is_string($model) || trim($model) === '') {
            return [];
        }

        if (self::isGroupSelection($make, $model)) {
            return self::MODEL_GROUPS_BY_MAKE[$make][$model];
        }

        return [$model];
    }
}
