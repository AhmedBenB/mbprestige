<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Auction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Organisations de test
        $orgs = [
            ['name' => 'Garage Dupont SARL', 'legal_name' => 'Garage Dupont SARL', 'vat_number' => 'FR12345678901', 'country' => 'FR', 'city' => 'Lyon', 'user_tier' => 'silver'],
            ['name' => 'AutoPro Belgium',    'legal_name' => 'AutoPro Belgium NV',  'vat_number' => 'BE0123456789',  'country' => 'BE', 'city' => 'Bruxelles', 'user_tier' => 'golden'],
            ['name' => 'Test Trial',         'legal_name' => 'Test Trial SAS',       'vat_number' => 'FR98765432109', 'country' => 'FR', 'city' => 'Paris', 'user_tier' => 'trial'],
        ];

        $createdOrgs = [];
        foreach ($orgs as $orgData) {
            $createdOrgs[] = Organization::create($orgData);
        }

        // Admin
        User::create([
            'name'            => 'Admin',
            'first_name'      => 'Admin',
            'last_name'       => 'System',
            'email'           => 'admin@automoto.test',
            'password'        => Hash::make('password'),
            'role'            => 'admin',
            'status'          => 'active',
            'email_verified_at' => now(),
        ]);

        // Utilisateurs de test
        foreach ($createdOrgs as $i => $org) {
            User::create([
                'name'              => "User {$i}",
                'first_name'        => 'Jean',
                'last_name'         => 'Test' . $i,
                'email'             => "user{$i}@automoto.test",
                'password'          => Hash::make('password'),
                'organization_id'   => $org->id,
                'role'              => 'client',
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);
        }

        // Véhicules et listings de démo
        $makes = [
            ['make'=>'BMW',       'models'=>['320d','520d','X5','X3']],
            ['make'=>'Audi',      'models'=>['A3','A4','Q5','Q7']],
            ['make'=>'Volkswagen','models'=>['Golf','Passat','Tiguan','T-Roc']],
            ['make'=>'Mercedes',  'models'=>['C220','E220','GLC','Classe A']],
            ['make'=>'Peugeot',   'models'=>['308','3008','5008','208']],
            ['make'=>'Renault',   'models'=>['Clio','Megane','Scenic','Kadjar']],
        ];

        $fuels      = ['Diesel', 'Essence', 'Hybride', 'Électrique'];
        $gearboxes  = ['Automatique', 'Manuelle'];
        $countries  = ['FR', 'BE', 'DE', 'NL', 'ES'];
        $bodyTypes  = ['Berline', 'Break', 'SUV', 'Citadine', 'Monospace'];
        $colors     = ['Noir', 'Blanc', 'Gris', 'Bleu', 'Rouge', 'Argent'];
        $types      = ['auction_open', 'auction_blind', 'fixed_price', 'partner_stock'];

        for ($i = 0; $i < 60; $i++) {
            $makeData = $makes[array_rand($makes)];
            $model    = $makeData['models'][array_rand($makeData['models'])];
            $year     = rand(2018, 2023);
            $mileage  = rand(15000, 180000);

            $vehicle = Vehicle::create([
                'make'                    => $makeData['make'],
                'model'                   => $model,
                'version'                 => rand(0,1) ? 'Sport' : 'Business',
                'body_type'               => $bodyTypes[array_rand($bodyTypes)],
                'fuel_type'               => $fuels[array_rand($fuels)],
                'gearbox'                 => $gearboxes[array_rand($gearboxes)],
                'power_hp'                => rand(90, 300),
                'co2'                     => rand(95, 220),
                'doors'                   => rand(3, 5),
                'seats'                   => rand(4, 7),
                'color'                   => $colors[array_rand($colors)],
                'origin_country'          => $countries[array_rand($countries)],
                'first_registration_date' => "{$year}-" . str_pad(rand(1,12),2,'0',STR_PAD_LEFT) . "-01",
                'mileage'                 => $mileage,
                'emission_class'          => 'Euro 6',
            ]);

            $type        = $types[array_rand($types)];
            $basePrice   = rand(8000, 55000);
            $isAuction   = in_array($type, ['auction_open', 'auction_blind']);
            $title       = "{$vehicle->make} {$vehicle->model} {$vehicle->version} {$year}";
            $slug        = Str::slug($title) . '-' . ($i + 1);

            $listing = Listing::create([
                'vehicle_id'         => $vehicle->id,
                'listing_type'       => $type,
                'publication_status' => 'published',
                'auction_status'     => $isAuction ? 'live' : null,
                'title'              => $title,
                'slug'               => $slug,
                'short_description'  => "Véhicule {$vehicle->make} {$vehicle->model} en excellent état. Kilométrage certifié.",
                'currency'           => 'EUR',
                'starting_price'     => $isAuction ? $basePrice : null,
                'buy_now_price'      => !$isAuction ? $basePrice : ($basePrice * 1.15),
                'current_bid'        => $isAuction ? ($basePrice + rand(0, 2000)) : null,
                'minimum_increment'  => 200,
                'bid_count'          => $isAuction ? rand(0, 15) : 0,
                'starts_at'          => $isAuction ? now()->subHours(rand(1, 48)) : null,
                'ends_at'            => $isAuction ? now()->addHours(rand(2, 96)) : null,
                'published_at'       => now()->subDays(rand(0, 7)),
                'vat_deductible'     => (bool) rand(0, 1),
                'is_featured'        => $i < 8,
            ]);

            // Image placeholder
            ListingImage::create([
                'listing_id'        => $listing->id,
                'source_url'        => "https://picsum.photos/seed/car{$i}/800/600",
                'cdn_url'           => "https://picsum.photos/seed/car{$i}/800/600",
                'sort_order'        => 0,
                'processing_status' => 'ready',
                'rights_status'     => 'licensed',
                'width'             => 800,
                'height'            => 600,
            ]);

            // Auction record si enchère
            if ($isAuction) {
                Auction::create([
                    'listing_id'                      => $listing->id,
                    'auction_mode'                    => $type === 'auction_open' ? 'open' : 'blind',
                    'status'                          => 'live',
                    'starts_at'                       => $listing->starts_at,
                    'ends_at'                         => $listing->ends_at,
                    'soft_close_seconds'              => 120,
                    'extend_if_bid_in_last_seconds'   => 120,
                    'minimum_increment'               => 200,
                ]);
            }
        }

        $this->command->info('✅ Seeding terminé : 60 véhicules/annonces créés');
        $this->command->info('   Admin : admin@automoto.test / password');
        $this->command->info('   User  : user0@automoto.test / password');
    }
}
