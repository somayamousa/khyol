// ============================================================
// ويدجت الطقس باستخدام Open-Meteo (مجاناً، بدون مفتاح API)
// الاستخدام: <span class="weather-widget" data-city="نابلس" data-date="2026-05-15"></span>
// ============================================================
(function() {
    const cityCoords = {
        'القدس':       { lat: 31.7683, lon: 35.2137 },
        'رام الله':    { lat: 31.9038, lon: 35.2034 },
        'نابلس':       { lat: 32.2211, lon: 35.2544 },
        'الخليل':      { lat: 31.5326, lon: 35.0998 },
        'بيت لحم':     { lat: 31.7054, lon: 35.2024 },
        'جنين':        { lat: 32.4609, lon: 35.3000 },
        'طولكرم':      { lat: 32.3104, lon: 35.0286 },
        'قلقيلية':     { lat: 32.1898, lon: 34.9706 },
        'أريحا':       { lat: 31.8548, lon: 35.4602 },
        'سلفيت':       { lat: 32.0853, lon: 35.1786 },
        'طوباس':       { lat: 32.3214, lon: 35.3692 },
        'غزة':         { lat: 31.5017, lon: 34.4668 },
        'رفح':         { lat: 31.2833, lon: 34.2500 },
        'خان يونس':    { lat: 31.3469, lon: 34.3060 },
        'دير البلح':   { lat: 31.4181, lon: 34.3506 },
        'بيت جالا':    { lat: 31.7167, lon: 35.1833 },
        'بيت ساحور':   { lat: 31.7053, lon: 35.2236 },
        'يطا':         { lat: 31.4500, lon: 35.0833 },
        'دورا':        { lat: 31.5089, lon: 35.0331 },
        'طمون':        { lat: 32.3258, lon: 35.4125 }
    };

    const codeMap = {
        0: { e: '☀️', t: 'مشمس' },
        1: { e: '🌤️', t: 'صحو غالباً' },
        2: { e: '⛅', t: 'غائم جزئياً' },
        3: { e: '☁️', t: 'غائم' },
        45: { e: '🌫️', t: 'ضباب' },
        48: { e: '🌫️', t: 'ضباب كثيف' },
        51: { e: '🌦️', t: 'رذاذ خفيف' },
        53: { e: '🌦️', t: 'رذاذ' },
        55: { e: '🌧️', t: 'رذاذ كثيف' },
        61: { e: '🌧️', t: 'مطر خفيف' },
        63: { e: '🌧️', t: 'مطر' },
        65: { e: '🌧️', t: 'مطر غزير' },
        71: { e: '❄️', t: 'ثلج خفيف' },
        73: { e: '❄️', t: 'ثلج' },
        75: { e: '❄️', t: 'ثلج كثيف' },
        80: { e: '🌧️', t: 'زخات مطر' },
        81: { e: '🌧️', t: 'زخات قوية' },
        82: { e: '⛈️', t: 'زخات عاصفة' },
        95: { e: '⛈️', t: 'عاصفة رعدية' },
        96: { e: '⛈️', t: 'عاصفة + برد' },
        99: { e: '⛈️', t: 'عاصفة شديدة' }
    };

    function pickIcon(code) {
        return codeMap[code] || { e: '🌡️', t: 'الجو' };
    }

    async function fetchWeather(lat, lon, date) {
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&start_date=${date}&end_date=${date}`;
        const res = await fetch(url);
        if (!res.ok) throw new Error('weather fetch failed');
        return res.json();
    }

    async function hydrate(el) {
        const city = el.dataset.city || 'رام الله';
        const date = el.dataset.date;
        if (!date) { el.style.display = 'none'; return; }
        const coords = cityCoords[city] || cityCoords['رام الله'];
        const cacheKey = `wx-${city}-${date}`;
        let data;
        try {
            const cached = sessionStorage.getItem(cacheKey);
            if (cached) {
                data = JSON.parse(cached);
            } else {
                data = await fetchWeather(coords.lat, coords.lon, date);
                sessionStorage.setItem(cacheKey, JSON.stringify(data));
            }
            const code = data.daily.weather_code[0];
            const tmax = Math.round(data.daily.temperature_2m_max[0]);
            const tmin = Math.round(data.daily.temperature_2m_min[0]);
            const ico = pickIcon(code);
            el.classList.remove('weather-loading');
            el.innerHTML = `
                <span class="w-emoji">${ico.e}</span>
                <span>${ico.t} في ${city}</span>
                <span class="w-temp">${tmax}°/${tmin}°</span>
            `;
        } catch (e) {
            el.style.display = 'none';
        }
    }

    function init() {
        document.querySelectorAll('.weather-widget[data-city]').forEach(hydrate);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
