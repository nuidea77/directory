/**
 * Огноог YYYY.MM.DD хэлбэрээр. toLocaleDateString() нь браузерын locale-оос
 * хамаарч 8/20/2026 гэх мэт болдог тул (mn-MN ихэнх браузерт байхгүй)
 * бүх хуудсанд нэг ижил хэлбэр ашиглана.
 */
export function shortDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`;
}
