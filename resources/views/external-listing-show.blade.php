<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payload['title'] }} - MBPRESTIGE</title>
    <meta name="description" content="Fiche annonce MBPRESTIGE : galerie, estimation, documents, similarites et conditions d achat.">
    <link rel="stylesheet" href="/css/external-listing.css?v=20260504a">
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
    <section class="card media-card">
        <h2>Galerie</h2>
        @if(count($payload['images']) > 0)
            <img id="hero-image" class="hero-image" src="{{ $payload['images'][0] }}" alt="Photo annonce">
            <div class="thumb-grid">
                @foreach($payload['images'] as $image)
                    <button type="button" class="thumb-btn" data-image="{{ $image }}">
                        <img src="{{ $image }}" alt="Miniature">
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
        <ul class="kv-list">
            <li><strong>Marque:</strong> {{ $payload['make'] ?? '-' }}</li>
            <li><strong>Modele:</strong> {{ $payload['model'] ?? '-' }}</li>
            <li><strong>Annee:</strong> {{ $payload['year'] ?? '-' }}</li>
            <li><strong>Kilometrage:</strong> {{ $payload['mileage'] ? number_format((int) $payload['mileage'], 0, ',', ' ') . ' km' : '-' }}</li>
            <li><strong>Carburant:</strong> {{ $payload['fuel'] ?? '-' }}</li>
            <li><strong>Boite:</strong> {{ $payload['transmission'] ?? '-' }}</li>
            <li><strong>Couleur:</strong> {{ $payload['color'] ?? '-' }}</li>
            <li><strong>Pays:</strong> {{ $payload['country'] ?? '-' }}</li>
            <li><strong>Lieu:</strong> {{ $payload['location'] ?? '-' }}</li>
        </ul>
    </section>

    <section class="card price-card">
        <h2>Prix / estimation</h2>
        @if($payload['price_visible'] && $payload['price_amount'] !== null)
            <p class="price">{{ number_format((float) $payload['price_amount'], 0, ',', ' ') }} {{ $payload['currency'] }}</p>
            <p class="muted">Prix source visible.</p>
        @elseif($payload['price_estimation'])
            <p class="price">{{ number_format((float) $payload['price_estimation']['min'], 0, ',', ' ') }} - {{ number_format((float) $payload['price_estimation']['max'], 0, ',', ' ') }} {{ $payload['currency'] }}</p>
            <p class="muted">Confiance: {{ $payload['price_estimation']['confidence_label'] }} ({{ $payload['price_estimation']['sample_size'] }} similaires)</p>
            <p class="muted">{{ $payload['price_estimation']['reason'] }}</p>
        @else
            <p class="price">Prix non visible</p>
            <p class="muted">Estimation indisponible.</p>
        @endif
    </section>

    <section class="card timer-card">
        <h2>Chronometre enchere</h2>
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
        <h2>Historique / rapport</h2>
        <ul class="kv-list">
            <li><strong>Resume:</strong> {{ $payload['history_report']['summary'] ?? '-' }}</li>
            <li><strong>Accident:</strong> {{ $payload['history_report']['accident'] ?? '-' }}</li>
            <li><strong>Entretien:</strong> {{ $payload['history_report']['maintenance'] ?? '-' }}</li>
            <li><strong>Proprietaires:</strong> {{ $payload['history_report']['ownership'] ?? '-' }}</li>
        </ul>
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

    <section class="card">
        <h2>Origine / source</h2>
        <ul class="kv-list">
            <li><strong>Fournisseur:</strong> {{ $payload['source']['name'] ?? '-' }}</li>
            <li><strong>Code source:</strong> {{ $payload['source']['code'] ?? '-' }}</li>
            <li><strong>Derniere synchro:</strong> {{ $payload['source']['last_seen_at'] ?? '-' }}</li>
            <li><strong>Mise a jour source:</strong> {{ $payload['source']['source_updated_at'] ?? '-' }}</li>
            <li><strong>Lien source:</strong> @if($payload['listing_url']) <a href="{{ $payload['listing_url'] }}" target="_blank" rel="noopener noreferrer">Ouvrir</a> @else - @endif</li>
        </ul>
    </section>

    <section class="card">
        <h2>Conditions d'achat</h2>
        <ul class="kv-list">
            @foreach($payload['purchase_conditions'] as $condition)
                <li>{{ $condition }}</li>
            @endforeach
        </ul>
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
                        <a href="/vehicules/{{ $similar['slug'] ?: $similar['id'] }}">Voir la fiche</a>
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

    function renderTimer() {
      if (!endAt || Number.isNaN(endAt)) {
        timerEl.textContent = 'Aucune enchere active';
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
        if (hero && src) hero.src = src;
      });
    });
  })();
</script>
</body>
</html>