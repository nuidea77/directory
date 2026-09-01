// Зээл, хэсэгчилсэн төлбөрийн аппуудын өнгө, товчлол.
// Лого нь эрх бүхий байгууллагын өмч тул зурган логог ашиглахгүй —
// брэндийн өнгөтэй, товчилсон тэмдэг харуулна.
const BRANDS = {
    lendmn: { short: 'L', color: '#1a7f5a' },
    storepay: { short: 'S', color: '#e0342b' },
    pocket: { short: 'P', color: '#7b3fe4' },
    sono: { short: 'So', color: '#f0a500' },
    ard: { short: 'A', color: '#0b63ce' },
    toki: { short: 'T', color: '#111827' },
    hipay: { short: 'Hi', color: '#00a1e0' },
    monpay: { short: 'M', color: '#e11d48' },
    qpay: { short: 'Q', color: '#0f766e' },
    socialpay: { short: 'SP', color: '#c2185b' },
};

const FALLBACK = { short: '₮', color: '#566a65' };

export function paymentBrand(slugOrName) {
    if (!slugOrName) return FALLBACK;
    const key = String(slugOrName).toLowerCase().replace(/[^a-z0-9]/g, '');
    return BRANDS[key] || FALLBACK;
}
