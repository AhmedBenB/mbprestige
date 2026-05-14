<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payload['title'] }} - MBPRESTIGE</title>
    <meta name="description" content="Fiche annonce MBPRESTIGE : galerie, estimation, documents, similitudes et conditions d'achat.">
    <link rel="stylesheet" href="/css/external-listing.css?v=20260514b">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a href="/" class="brand">MBPRESTIGE</a>
        <nav class="topnav">
            <a href="/catalogue">Catalogue</a>
            <a href="/demande">Demande</a>
            <a href="/contact">Contact</a>
        </nav>
    </div>
</header>

<main class="container main-grid">
    @if(session('success'))
        <section class="card full-width" style="border-color:#86efac;background:#f0fdf4;">
            <p style="margin:0;color:#166534;font-weight:600;">{{ session('success') }}</p>
        </section>
    @endif

    @if(!empty($payload['is_expired']))
        <section class="card full-width">
            <h2>Annonce expiree</h2>
            <p class="muted">Cette annonce est marquee comme expiree. Suppression automatique apres la periode de retention.</p>
        </section>
    @endif

    <section class="card media-card">
        <h2>Galerie</h2>
        @if(count($payload['images']) > 0)
            <img id="hero-image" class="hero-image" src="{{ $payload['images'][0] }}" alt="Photo annonce" loading="eager" decoding="async" referrerpolicy="no-referrer">
            <div class="thumb-grid">
                @foreach($payload['images'] as $image)
                    <button type="button" class="thumb-btn" data-image="{{ $image }}">
                        <img src="{{ $image }}" alt="Miniature" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                    </button>
                @endforeach
            </div>
        @else
            <p class="muted">Aucune image disponible pour cette annonce.</p>
        @endif
    </section>

    <section class="card summary-card">
        <h1>{{ $payload['title'] }}</h1>
        <p class="muted">Source: {{ $payload['source']['name'] }} · Ref: {{ $payload['external_id'] }}</p>

        <div class="badge-row">
            <span class="badge">{{ $payload['listing_type'] }}</span>
            <span class="badge">{{ $payload['status'] }}</span>
            <span class="badge">{{ $payload['source_status'] }}</span>
        </div>

        <h2>Resume</h2>
        @php
            $fuelLabels = [
                'diesel' => 'Diesel',
                'essence' => 'Essence',
                'hybride' => 'Hybride',
                'electrique' => 'Electrique',
                'électrique' => 'Electrique',
                'ã©lectrique' => 'Electrique',
                'gpl' => 'GPL',
                'gaz' => 'Gaz',
            ];
            $gearboxLabels = [
                'automatic' => 'Automatique',
                'manual' => 'Manuelle',
                'manuel' => 'Manuelle',
                'manuelle' => 'Manuelle',
                'semi-automatic' => 'Semi-automatique',
                'direct no gearbox' => 'Semi-automatique',
            ];
        @endphp
        <ul class="kv-list">
            <li><strong>Marque:</strong> {{ $payload['make'] ?? '-' }}</li>
            <li><strong>Modele:</strong> {{ $payload['model'] ?? '-' }}</li>
            <li><strong>Annee:</strong> {{ $payload['year'] ?? '-' }}</li>
            <li><strong>Kilometrage:</strong> {{ $payload['mileage'] ? number_format((int) $payload['mileage'], 0, ',', ' ') . ' km' : '-' }}</li>
            <li><strong>Carburant:</strong> {{ $fuelLabels[$payload['fuel'] ?? ''] ?? ($payload['fuel'] ?? '-') }}</li>
            <li><strong>Boite:</strong> {{ $gearboxLabels[$payload['transmission'] ?? ''] ?? ($payload['transmission'] ?? '-') }}</li>
            <li><strong>Couleur:</strong> {{ $payload['color'] ?? '-' }}</li>
            <li><strong>Vues:</strong> {{ number_format((int) ($payload['views_count'] ?? 0), 0, ',', ' ') }}</li>
        </ul>
    </section>

    <section class="card price-card">
        <h2>Prix / estimation</h2>
        @if($payload['price_visible'] && $payload['price_amount'] !== null)
            <p class="price">{{ number_format((float) $payload['price_amount'], 0, ',', ' ') }} {{ $payload['currency'] }}</p>
            <p class="muted">Prix MBPRESTIGE (marge incluse: {{ number_format((float) ($payload['price_margin_amount'] ?? 0), 0, ',', ' ') }} {{ $payload['currency'] }}).</p>
            @if(isset($payload['source_price_amount']) && $payload['source_price_amount'] !== null)
                <p class="muted">Prix source: {{ number_format((float) $payload['source_price_amount'], 0, ',', ' ') }} {{ $payload['currency'] }}</p>
            @endif
        @elseif($payload['price_estimation'])
            <p class="price">{{ number_format((float) $payload['price_estimation']['min'], 0, ',', ' ') }} - {{ number_format((float) $payload['price_estimation']['max'], 0, ',', ' ') }} {{ $payload['currency'] }}</p>
            <p class="muted">Confiance: {{ $payload['price_estimation']['confidence_label'] }} ({{ $payload['price_estimation']['sample_size'] }} similaires)</p>
            <p class="muted">{{ $payload['price_estimation']['reason'] }}</p>
        @else
            <p class="price">Prix non visible</p>
            <p class="muted">Prix estime en cours.</p>
        @endif

        @if($payload['is_auction'])
            <hr style="margin: 14px 0; border: none; border-top: 1px solid #e5e7eb;">
            <p><strong>Encheres recues:</strong> {{ number_format((int) ($payload['bids_count'] ?? 0), 0, ',', ' ') }}</p>
            @if(!empty($payload['top_bid_amount']))
                <p><strong>Meilleure enchere:</strong> {{ number_format((float) $payload['top_bid_amount'], 0, ',', ' ') }} {{ $payload['currency'] }}</p>
            @endif
            @if(!empty($payload['auction_available']))
                @auth
                    <form method="POST" action="{{ route('app.external_bids.store', ['listing' => $listing->id]) }}" style="display:grid;gap:8px;margin-top:10px;">
                        @csrf
                        <label for="bid_amount"><strong>Votre enchere ({{ $payload['currency'] }})</strong></label>
                        <input id="bid_amount" name="bid_amount" type="number" min="1" step="1" required style="padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                        <button type="submit" class="download-btn" style="width:max-content;">Proposer une enchere</button>
                        @error('bid_amount')
                            <p style="color:#b91c1c;margin:0;">{{ $message }}</p>
                        @enderror
                    </form>
                @else
                    <p class="muted" style="margin-top:10px;">Connecte-toi pour proposer une enchere.</p>
                    <a href="{{ route('login') }}" class="download-btn">Se connecter</a>
                @endauth
            @else
                <p class="muted" style="margin-top:10px;">Enchere terminee sur la source.</p>
            @endif
        @endif
    </section>

    <section class="card timer-card">
        <h2>Chronometre</h2>
        <p id="auction-timer" class="timer">-</p>
        <p class="muted">Fin source: {{ $payload['auction_end_at'] ?? 'non communiquee' }}</p>
    </section>

    <section class="card">
        <h2>Caracteristiques techniques</h2>
        @if(count($payload['technical_data']) > 0)
            <ul class="kv-list">
                @foreach($payload['technical_data'] as $key => $value)
                    <li><strong>{{ $key }}:</strong> {{ is_scalar($value) ? $value : '-' }}</li>
                @endforeach
            </ul>
        @else
            <p class="muted">Aucune caracteristique technique detaillee.</p>
        @endif
    </section>

    <section class="card">
        <h2>Equipements</h2>
        @if(count($payload['equipment']) > 0)
            <ul class="pill-list">
                @foreach($payload['equipment'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @else
            <p class="muted">Aucun equipement renseigne.</p>
        @endif
    </section>

    <section class="card">
        <h2>Documents</h2>
        @if(count($payload['documents']) > 0)
            <ul class="doc-list">
                @foreach($payload['documents'] as $doc)
                    <li>
                        <span>{{ $doc['title'] ?: ($doc['file_name'] ?: 'Document') }}</span>
                        <a href="{{ $doc['file_url'] }}" target="_blank" rel="noopener noreferrer" class="download-btn">Telecharger</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="muted">Aucun document disponible.</p>
        @endif
    </section>

    <section class="card full-width">
        <h2>Annonces similaires</h2>
        @if(count($payload['similar_listings']) > 0)
            <div class="similar-grid">
                @foreach($payload['similar_listings'] as $similar)
                    <article class="similar-card">
                        <h3>{{ $similar['title'] }}</h3>
                        <p class="muted">Score: {{ $similar['score'] }}</p>
                        <p>{{ $similar['year'] ?? '-' }} · {{ $similar['mileage'] ? number_format((int) $similar['mileage'], 0, ',', ' ') . ' km' : '-' }}</p>
                        @if($similar['price_visible'] && $similar['price_amount'] !== null)
                            <p><strong>{{ number_format((float) $similar['price_amount'], 0, ',', ' ') }} EUR</strong></p>
                        @elseif($similar['estimate_min'] !== null && $similar['estimate_max'] !== null)
                            <p><strong>{{ number_format((float) $similar['estimate_min'], 0, ',', ' ') }} - {{ number_format((float) $similar['estimate_max'], 0, ',', ' ') }} EUR</strong></p>
                        @else
                            <p><strong>Prix non disponible</strong></p>
                        @endif
                        <a href="/annonces/{{ $similar['slug'] ?: $similar['id'] }}">Voir la fiche</a>
                    </article>
                @endforeach
            </div>
        @else
            <p class="muted">Aucune annonce similaire disponible.</p>
        @endif
    </section>
</main>

<script>
  (function () {
    const payload = @json($payload);
    const timerEl = document.getElementById('auction-timer');
    const endAt = payload.auction_end_at ? new Date(payload.auction_end_at).getTime() : null;

    function upgradeImageUrl(url) {
      if (!url) return url;
      let next = String(url)
        .replace(/\/(thumb|thumbs|thumbnail|thumbnails|small|preview)\//gi, '/')
        .replace(/([_-])(thumb|thumbnail|small|preview)(?=\.)/gi, '');

      try {
        const u = new URL(next, window.location.origin);
        ['w', 'width', 'h', 'height', 'q', 'quality', 'fit', 'resize', 'dpr'].forEach((key) => {
          u.searchParams.delete(key);
        });
        return u.toString();
      } catch (_) {
        return next;
      }
    }

    function renderTimer() {
      if (!payload.is_auction || !endAt || Number.isNaN(endAt)) {
        timerEl.textContent = 'Pas de compte a rebours actif';
        return;
      }

      const now = Date.now();
      const diff = endAt - now;
      if (diff <= 0) {
        timerEl.textContent = 'Enchere terminee';
        return;
      }

      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const minutes = Math.floor((diff / (1000 * 60)) % 60);
      const seconds = Math.floor((diff / 1000) % 60);
      timerEl.textContent = `${days}j ${hours}h ${minutes}m ${seconds}s`;
    }

    renderTimer();
    setInterval(renderTimer, 1000);

    document.querySelectorAll('.thumb-btn').forEach((button) => {
      button.addEventListener('click', () => {
        const src = button.getAttribute('data-image');
        const hero = document.getElementById('hero-image');
        if (hero && src) {
          hero.src = upgradeImageUrl(src);
        }
      });
    });

    const hero = document.getElementById('hero-image');
    if (hero && hero.src) {
      hero.src = upgradeImageUrl(hero.src);
    }
  })();
</script>
</body>
</html>

