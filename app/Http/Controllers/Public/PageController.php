<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Vehicle;
use App\Services\Seo\SeoPageGenerator;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly SeoPageGenerator $seo) {}

    public function brands(): View
    {
        $brands = Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
            ->where('listings.publication_status', 'published')
            ->selectRaw('vehicles.make, COUNT(listings.id) as total')
            ->groupBy('vehicles.make')
            ->orderBy('total', 'desc')
            ->get();

        return view('public.seo.brands', compact('brands'));
    }

    public function brand(string $make): View
    {
        $make = ucfirst(strtolower($make));
        $seoData = $this->seo->forBrand($make);

        $listings = Listing::published()
            ->whereHas('vehicle', fn ($q) => $q->where('make', $make))
            ->with(['vehicle', 'coverImage', 'auction'])
            ->latest('published_at')
            ->paginate(24);

        $models = Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
            ->where('listings.publication_status', 'published')
            ->where('vehicles.make', $make)
            ->selectRaw('vehicles.model, COUNT(listings.id) as total')
            ->groupBy('vehicles.model')
            ->orderBy('total', 'desc')
            ->get();

        return view('public.seo.brand', compact('make', 'seoData', 'listings', 'models'));
    }

    public function model(string $make, string $model): View
    {
        $make  = ucfirst(strtolower($make));
        $model = ucfirst(strtolower($model));

        $seoData = $this->seo->forModel($make, $model);

        $listings = Listing::published()
            ->whereHas('vehicle', fn ($q) => $q->where('make', $make)->where('model', $model))
            ->with(['vehicle', 'coverImage', 'auction'])
            ->latest('published_at')
            ->paginate(24);

        return view('public.seo.model', compact('make', 'model', 'seoData', 'listings'));
    }

    public function modelYear(string $make, string $model, int $year): View
    {
        $make  = ucfirst(strtolower($make));
        $model = ucfirst(strtolower($model));

        $listings = Listing::published()
            ->whereHas('vehicle', fn ($q) =>
                $q->where('make', $make)
                  ->where('model', $model)
                  ->whereYear('first_registration_date', $year))
            ->with(['vehicle', 'coverImage', 'auction'])
            ->latest('published_at')
            ->paginate(24);

        $seoData = [
            'title'            => "{$make} {$model} {$year} d'occasion – AutoMoto B2B",
            'meta_description' => "Achetez une {$make} {$model} de {$year} pour professionnels. Enchères et prix fixes.",
            'h1'               => "{$make} {$model} {$year} d'occasion",
            'intro'            => "Découvrez les {$make} {$model} millésime {$year} disponibles sur notre plateforme B2B.",
        ];

        return view('public.seo.model-year', compact('make', 'model', 'year', 'seoData', 'listings'));
    }

    public function country(string $country): View
    {
        $country = strtoupper($country);
        $seoData = $this->seo->forCountry($country);

        $listings = Listing::published()
            ->whereHas('vehicle', fn ($q) => $q->where('origin_country', $country))
            ->with(['vehicle', 'coverImage', 'auction'])
            ->latest('published_at')
            ->paginate(24);

        return view('public.seo.country', compact('country', 'seoData', 'listings'));
    }

    /** Sitemap XML */
    public function sitemap(): \Illuminate\Http\Response
    {
        $urls = $this->seo->buildSitemapUrls();

        // Listings individuels
        Listing::published()->select('slug', 'updated_at')->each(function ($l) use (&$urls) {
            $urls[] = [
                'url'        => route('vehicles.show', $l),
                'changefreq' => 'weekly',
                'priority'   => '0.9',
                'lastmod'    => $l->updated_at->toDateString(),
            ];
        });

        $xml = view('public.seo.sitemap', compact('urls'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
