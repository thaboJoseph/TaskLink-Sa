const slider = document.getElementById('priceSlider');
const sliderValue = document.getElementById('sliderValue');
const serviceCards = document.querySelectorAll('.service-card');

if (slider) {
    slider.addEventListener('input', function () {

        const maxPrice = parseInt(this.value);
        sliderValue.textContent = 'R' + maxPrice.toLocaleString();

        serviceCards.forEach(function (card) {
            const priceText = card.getAttribute('data-price');
            const cardPrice = parseInt(priceText);

            if (cardPrice <= maxPrice) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

const stars = document.querySelectorAll('.star-rating .star');
const ratingInput = document.getElementById('ratingValue');

stars.forEach(function (star, index) {
    star.addEventListener('click', function () {
        const selected = index + 1;
        ratingInput.value = selected;

        stars.forEach(function (s, i) {
            if (i < selected) {
                s.classList.add('active');
            } else {
                s.classList.remove('active');
            }
        });
    });

    star.addEventListener('mouseover', function () {
        stars.forEach(function (s, i) {
            if (i <= index) {
                s.style.color = '#F5A623';
            } else {
                s.style.color = '#CBD5E0';
            }
        });
    });

    star.addEventListener('mouseout', function () {
        stars.forEach(function (s, i) {
            if (i < parseInt(ratingInput.value || 0)) {
                s.style.color = '#F5A623';
            } else {
                s.style.color = '#CBD5E0';
            }
        });
    });
});

const hoursInput = document.getElementById('estimatedHours');
const totalDisplay = document.getElementById('totalEstimate');
const rateDisplay = document.getElementById('serviceRate');

if (hoursInput && totalDisplay) {
    hoursInput.addEventListener('input', function () {
        const hours = parseFloat(this.value) || 0;
        const rate  = parseFloat(
            rateDisplay.getAttribute('data-rate')
        ) || 0;
        const total = hours * rate;
        totalDisplay.textContent = 'R' + total.toLocaleString();
    });
}

const alerts = document.querySelectorAll('.alert-success, .alert-error');
alerts.forEach(function (alert) {
    setTimeout(function () {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity    = '0';
        setTimeout(function () {
            alert.remove();
        }, 500);
    }, 4000);
});