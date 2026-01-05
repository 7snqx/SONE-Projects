function qs(a){return document.querySelector(a);}
function qsa(a) {return document.querySelectorAll(a);}

qsa('input[type="number"]').forEach(input => {
  input.setAttribute('min', '0');
});


fetch('scraper.php')
  .then(res => res.json())
  .then(data => {
    if (data.ceny_paliw) {
      for (const rodzaj in data.ceny_paliw) {
        const label = document.getElementById(rodzaj);
        if (label) {
          const cena = (data.ceny_paliw[rodzaj].toFixed(2));
          label.textContent = `${cena} PLN`;
          const button = label.closest('button.fuelButton');
          button.setAttribute('onclick', `fuelFast('${cena}')`);
        } else {
          console.warn(`Brak labela o id="${rodzaj}"`);
        }
      }
    } else {
      console.error("Błąd w danych:", data.error || "Brak danych");
    }
  })
  .catch(() => {
    console.error("Błąd w pobieraniu danych");
  });

function fuelFast(price) {
    const fuelPriceInput = qs('#fuelPriceInput');
    const inputP = fuelPriceInput.closest('p')
    fuelPriceInput.value = price;
    inputP.style.scale = 0.99
    setTimeout(()=>{
      inputP.style.scale = 1
    }, 100)
}

function calculate() {
  const consumption = parseFloat(qs('#consumptionInput').value);
  const distance = parseFloat(qs('#distanceInput').value);
  const fuelPrice = parseFloat(qs('#fuelPriceInput').value);

  const passengersCount = parseInt(qs('#passengersNumberInput').value) || 1;
  const avgPersonWeight = parseFloat(qs('#avgWeightInput').value) || 75;

  let fuelUsed  = (consumption/100) * distance;
  const includePassengers = qs('#includePassengersCheckbox').checked;
  if(!consumption || !distance || !fuelPrice) {
    alert('wypelnij wszystkie dane')
    return
  }
  if(includePassengers) {
    if(!passengersCount || !avgPersonWeight) {
      alert('wypelnij wszystkie dane')
      return
    }
    const totalAdditionalWeight = passengersCount * avgPersonWeight;
    const increasePerKg = 0.001;
    const loadFactor = 1 + (totalAdditionalWeight * increasePerKg);
    const adjustedConsumption = consumption * loadFactor;

    fuelUsed = (adjustedConsumption/100) * distance
  }
  let totalCost = fuelUsed * fuelPrice;

  const consumptionDisplay = qs('#consumptionDisplay');
  const distanceDisplay = qs('#distanceDisplay');
  const priceDisplay = qs('#priceDisplay');

  consumptionDisplay.innerHTML = `${consumption.toFixed(2)} <label class="summaryUnit">L/100Km</label>`;
  distanceDisplay.innerHTML = `${distance.toFixed(2)} <label class="summaryUnit">Km</label>`;
  priceDisplay.innerHTML = `${fuelPrice.toFixed(2)} <label class="summaryUnit">PLN</label>`;

  const fuelUsedDisplay = qs('#fuelUsed');
  const totalCostDisplay = qs('#totalCost');

  fuelUsedDisplay.innerHTML = `${fuelUsed.toFixed(2)} <label class="summaryUnit">L</label>`;
  totalCostDisplay.innerHTML = `${totalCost.toFixed(2)} <label class="summaryUnit">PLN</label>`

}
