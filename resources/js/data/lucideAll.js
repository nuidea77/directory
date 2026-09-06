// Бүх lucide icon (1,700+) — зурах өгөгдлийг нь /icons/lucide.json-оос
// ХЭРЭГТЭЙ ҮЕД нь татна (~76 KB gz). JS багцад ордоггүй тул нийтийн
// хуудсууд хурдан хэвээрээ: түгээмэл icon-ууд amenityIcons/categoryIcons
// дотор статикаар суусан байдаг.
//
// Файлыг scripts/build-lucide-data.mjs үүсгэнэ (npm run build дуудна).

let loading = null;
let icons = null;

/** Аль хэдийн татсан бол шууд буцаана (татаагүй бол null) */
export function allIconsSync() {
    return icons;
}

/** kebab нэр → [[tag, attrs], ...] зурах өгөгдөл */
export function loadAllIcons() {
    if (icons) return Promise.resolve(icons);

    loading ||= fetch('/icons/lucide.json')
        .then((res) => (res.ok ? res.json() : {}))
        .then((data) => {
            icons = data;

            return icons;
        })
        .catch(() => {
            icons = {};

            return icons;
        });

    return loading;
}
