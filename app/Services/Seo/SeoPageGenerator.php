<?php

namespace App\Services\Seo;

use App\Models\Listing;
use App\Models\Vehicle;

class SeoPageGenerator
{
    /**
     * Retourne les métadonnées SEO pour une page marque.
     */
    public function forBrand(string $make): array
    {
        $count = Listing::published()
            ->whereHas('vehicle', fn ($q) => $q->where('make', $make))
            ->count();

        return [
            'title'            => "Acheter {$make} d'occasion B2B – {$count} véhicules disponibles",
            'meta_description' => "Trouvez des véhicules {$make} d'occasion à prix professionnel. Enchères, prix fixes, stock disponible. {$count} annonces actuellement.",
            'h1'               => "Véhicules {$make} d'occasion – Marketplace B2B",
            'intro'            => $this->brandIntro($make, $count),
            'schema'           => $this->itemListSchema($make),
        ];
    }

    /**
     * Retourne les métadonnées SEO pour une page marque + modèle.
     */
    public function forModel(string $make, string $model): array
    {
        $count = Listing::published()
            ->whereHas('vehicle', fn ($q) => $q->where('make', $make)->where('model', $model))
            ->count();

        $avgPrice = Listing::published()
            ->whereHas('vehicle', fn ($q) => $q->where('make', $make)->where('model', $model))
            ->avg('buy_now_price');

        $avgPriceStr = $avgPrice ? number_format($avgPrice, 0, ',', ' ') . ' €' : 'Prix sur demande';

        return [
            'title'            => "{$make} {$model} d'occasion – Achat B2B professionnel",
            'meta_description' => "{$count} {$make} {$model} disponibles. Prix moyen {$avgPriceStr}. Enchères et prix fixes pour professionnels.",
            'h1'               => "{$make} {$model} d'occasion pour professionnels",
            'intro'            => $this->modelIntro($make, $model, $count, $avgPrice),
            'faq'              => $this->modelFaq($make, $model),
            'schema'           => $this->itemListSchema("{$make} {$model}"),
        ];
    }

    /**
     * Retourne les métadonnées SEO pour une page pays.
     */
    public function forCountry(string $country): array
    {
        $names = ['FR'=>'France','BE'=>'Belgique','DE'=>'Allemagne','NL'=>'Pays-Bas','ES'=>'Espagne','IT'=>'Italie'];
        $name  = $names[$country] ?? $country;

        $count = Listing::published()
            ->whereHas('vehicle', fn ($q) => $q->where('origin_country', $country))
            ->count();

        return [
            'title'            => "Véhicules d'occasion en provenance de {$name} – AutoMoto B2B",
            'meta_description' => "Achetez des voitures d'occasion importées de {$name}. {$count} véhicules disponibles pour professionnels.",
            'h1'               => "Véhicules d'origine {$name}",
            'intro'            => "Découvrez notre sélection de {$count} véhicules d'occasion en provenance de {$name}. Achetez en toute transparence via enchères ou prix fixes.",
            'country_name'     => $name,
        ];
    }

    /**
     * Génère le sitemap des pages SEO (marques + modèles + pays).
     */
    public function buildSitemapUrls(): array
    {
        $urls = [];

        // Pages marques
        Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
            ->where('listings.publication_status', 'published')
            ->distinct()->pluck('vehicles.make')
            ->each(function ($make) use (&$urls) {
                $urls[] = [
                    'url'        => route('brands.show', strtolower($make)),
                    'changefreq' => 'daily',
                    'priority'   => '0.8',
                ];

                // Pages modèles
                Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
                    ->where('listings.publication_status', 'published')
                    ->where('vehicles.make', $make)
                    ->distinct()->pluck('vehicles.model')
                    ->each(function ($model) use ($make, &$urls) {
                        $urls[] = [
                            'url'        => route('brands.model', [strtolower($make), strtolower($model)]),
                            'changefreq' => 'daily',
                            'priority'   => '0.7',
                        ];
                    });
            });

        // Pages pays
        Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
            ->where('listings.publication_status', 'published')
            ->distinct()->pluck('vehicles.origin_country')
            ->filter()->each(function ($country) use (&$urls) {
                $urls[] = [
                    'url'        => route('countries.show', strtolower($country)),
                    'changefreq' => 'weekly',
                    'priority'   => '0.6',
                ];
            });

        return $urls;
    }

    private function brandIntro(string $make, int $count): string
    {
        return "AutoMoto B2B référence actuellement {$count} véhicules {$make} d'occasion "
            . "disponibles à l'achat pour les professionnels de l'automobile. "
            . "Chaque annonce est vérifiée par nos équipes. "
            . "Achetez via enchères ou à prix fixe, avec livraison ou enlèvement possible.";
    }

    private function modelIntro(string $make, string $model, int $count, ?float $avgPrice): string
    {
        $price = $avgPrice ? 'à partir de ' . number_format($avgPrice * 0.85, 0, ',', ' ') . ' €' : '';
        return "Le {$make} {$model} est l'un des véhicules les plus demandés sur notre plateforme. "
            . "{$count} exemplaires sont actuellement disponibles {$price}. "
            . "Participez aux enchères ou achetez directement à prix fixe.";
    }

    private function modelFaq(string $make, string $model): array
    {
        return [
            [
                'q' => "Comment acheter une {$make} {$model} sur AutoMoto B2B ?",
                'a' => "Créez votre compte professionnel gratuitement, parcourez les annonces {$make} {$model} disponibles, et participez aux enchères ou achetez à prix fixe. Le paiement s'effectue par virement depuis le compte de votre société.",
            ],
            [
                'q' => "Quels sont les frais sur l'achat d'une {$make} {$model} ?",
                'a' => "Une commission est appliquée sur chaque vente, détaillée sur notre page Frais. Elle couvre la mise en relation, la gestion documentaire et le suivi post-achat.",
            ],
            [
                'q' => "Peut-on importer une {$make} {$model} depuis un autre pays européen ?",
                'a' => "Oui. Nous proposons des véhicules de Belgique, Allemagne, France, Pays-Bas et d'autres pays. Nous assurons ou guidons les démarches d'importation (quitus fiscal, immatriculation).",
            ],
        ];
    }

    private function itemListSchema(string $name): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'ItemList',
            'name'     => "Véhicules {$name} – AutoMoto B2B",
        ];
    }
}
