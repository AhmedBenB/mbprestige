(() => {
  const makes = ['BMW','Audi','Volkswagen','Mercedes','Peugeot','Renault','Toyota','Ford','Tesla','Opel'];
  const models = ['320d','A4','Golf','C220','3008','M?gane','RAV4','Focus','Model 3','Astra'];
  const fuels = ['Diesel','Essence','Hybride','?lectrique'];
  const gearboxes = ['Automatique','Manuelle'];
  const types = ['auction_open','auction_blind','fixed_price','partner_stock'];
  const countries = ['FR','BE','DE','NL','ES','IT'];

  const ALL_LISTINGS = Array.from({ length: 48 }, (_, i) => {
    const idx = i % makes.length;
    const type = types[i % types.length];
    const isAuction = type === 'auction_open' || type === 'auction_blind';
    const basePrice = 12800 + (i * 720);
    const year = 2018 + Math.floor(i / 10);

    return {
      id: i + 1,
      title: `${makes[idx]} ${models[idx]} ${year} Edition`,
      make: makes[idx],
      model: models[idx],
      year,
      km: 18000 + (i * 3200),
      fuel: fuels[i % fuels.length],
      gearbox: gearboxes[i % gearboxes.length],
      price: basePrice,
      min_increment: 200,
      bids: isAuction ? (2 + (i % 9)) : 0,
      ends_in: isAuction ? (3600 * (2 + (i % 16))) : null,
      buy_now: !isAuction ? basePrice + 400 : null,
      type,
      country: countries[i % countries.length],
      img: `https://picsum.photos/seed/mbprestige-${i + 1}/640/480`,
      vat: i % 3 === 0,
    };
  });

  window.ALL_LISTINGS = ALL_LISTINGS;
})();
