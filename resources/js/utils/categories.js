// Ангиллын мод (3 түвшин хүртэл) — сонголтын цэс, хайлтад зориулсан туслахууд.

// Модыг хавтгай жагсаалт болгоно: [{ ...cat, depth }]
export function flattenCategories(tree, depth = 1) {
    const out = [];
    for (const c of tree || []) {
        const d = c.depth || depth;
        out.push({ ...c, depth: d });
        if (c.children?.length) out.push(...flattenCategories(c.children, d + 1));
    }
    return out;
}

// <option>-д гүнийг харуулах угтвар: дэд ангиллыг догол мөрөөр ялгана
export function optionLabel(cat) {
    // HTML-д энгийн зай хумигддаг тул тасрахгүй зай ашиглана
    const prefix = cat.depth > 1 ? `${'   '.repeat(cat.depth - 1)}└ ` : '';
    return `${prefix}${cat.name}`;
}

// Тухайн ангиллын өвөг эцгүүдийн гинж (үндсэнээс эхлээд)
export function ancestorsOf(tree, id) {
    const path = [];
    const walk = (nodes, trail) => {
        for (const c of nodes || []) {
            const next = [...trail, c];
            if (c.id === id) { path.push(...trail); return true; }
            if (c.children?.length && walk(c.children, next)) return true;
        }
        return false;
    };
    walk(tree, []);
    return path;
}

// Модноос id-гаар олох
export function findCategory(tree, id) {
    return flattenCategories(tree).find((c) => c.id === Number(id)) || null;
}
