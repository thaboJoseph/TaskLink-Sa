// ── Price Filter Slider ──────────────────────────────────────
const slider = document.getElementById('priceSlider');
const sliderValue = document.getElementById('sliderValue');
const serviceCards = document.querySelectorAll('.service-card');

if (slider) {
    slider.addEventListener('input', function () {
        const maxPrice = parseInt(this.value);
        sliderValue.textContent = 'R' + maxPrice.toLocaleString();
        serviceCards.forEach(function (card) {
            const cardPrice = parseInt(card.getAttribute('data-price'));
            card.closest('.service-card-wrapper').style.display =
                cardPrice <= maxPrice ? 'block' : 'none';
        });
    });
}

// ── Live Price Calculator ────────────────────────────────────
const hoursInput = document.getElementById('estimatedHours');
const totalDisplay = document.getElementById('totalEstimate');

if (hoursInput && totalDisplay) {
    hoursInput.addEventListener('input', function () {
        const hours = parseFloat(this.value) || 0;
        const rate = parseFloat(
            document.getElementById('serviceRate')
                    .getAttribute('data-rate')
        ) || 0;
        totalDisplay.textContent = 'R' + (hours * rate).toLocaleString();
    });
}

// ── Star Rating ──────────────────────────────────────────────
const stars = document.querySelectorAll('.star-rating .star');
const ratingInput = document.getElementById('ratingValue');

if (stars.length > 0) {
    stars.forEach(function (star, index) {
        star.addEventListener('click', function () {
            ratingInput.value = index + 1;
            stars.forEach(function (s, i) {
                s.style.color = i <= index ? '#F5A623' : '#CBD5E0';
            });
        });
    });
}

// ── Auto Dismiss Alerts ──────────────────────────────────────
document.querySelectorAll('.alert-success, .alert-error')
    .forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 4000);
    });