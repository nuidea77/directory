// Цагийн хуваарийн нэгдсэн дүрэм — сервер талын Branch::isFullDaySlot/computeIs247-тай ижил.
export const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

const FULL_DAY_TO = ['00:00', '23:59', '24:00'];

// Тухайн өдөр бүтэн хоног (24 цаг) нээлттэй эсэх
export function isFullDay(day) {
    if (!day || day.closed || !day.from || !day.to) return false;
    return day.from === day.to || (day.from === '00:00' && FULL_DAY_TO.includes(day.to));
}

// 24/7 = долоо хоногийн өдөр бүр бүтэн хоног нээлттэй
export function hoursAre247(hours) {
    if (!hours) return false;
    return DAY_KEYS.every((key) => isFullDay(hours[key]));
}

// Хуваарийн нэг мөрийг хүнд ойлгомжтой бичих
export function dayLabel(day) {
    if (!day) return '—';
    if (day.closed) return 'Амарна';
    if (isFullDay(day)) return '24 цаг';
    return day.from ? `${day.from} – ${day.to}` : '—';
}
