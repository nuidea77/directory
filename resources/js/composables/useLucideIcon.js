import { ref, watch } from 'vue';
import { allIconsSync, loadAllIcons } from '../data/lucideAll';

/**
 * Icon-ыг нэрээр нь шийднэ:
 *  1) статик сангаас (шууд, нэмэлт татахгүй) → Vue component
 *  2) олдохгүй бол /icons/lucide.json-оос → зурах өгөгдөл (nodes)
 *
 * @param {() => string} getName
 * @param {(name: string) => object|null} staticResolve
 * @param {object} fallback — олдохгүй үеийн component
 * @returns {{ component: import('vue').Ref, nodes: import('vue').Ref }}
 */
export function useLucideIcon(getName, staticResolve, fallback) {
    const component = ref(null);
    const nodes = ref(null);

    function apply(name, all) {
        if (all[name]) {
            component.value = null;
            nodes.value = all[name];

            return;
        }

        component.value = fallback;
        nodes.value = null;
    }

    async function resolve() {
        const name = getName();

        // Нэргүй бол шууд ерөнхий icon — сан татах шаардлагагүй
        if (! name) {
            component.value = fallback;
            nodes.value = null;

            return;
        }

        const fromStatic = staticResolve(name);

        if (fromStatic) {
            component.value = fromStatic;
            nodes.value = null;

            return;
        }

        const cached = allIconsSync();

        if (cached) {
            apply(name, cached);

            return;
        }

        // Татаж дуустал хоосон зай — хуудас үсрэхгүй
        component.value = null;
        nodes.value = null;

        const all = await loadAllIcons();

        // Хүлээх зуур нэр солигдсон бол хуучин хариуг тавихгүй
        if (getName() === name) apply(name, all);
    }

    watch(getName, resolve, { immediate: true });

    return { component, nodes };
}
