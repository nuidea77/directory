/**
 * Ангиллын icon: DB дэх categories.icon (Lucide-ийн kebab-case нэр) →
 * Lucide Vue компонент. Шинэ ангилал нэмэхэд энд бүртгэнэ; байхгүй бол
 * ерөнхий Store icon-оор орлоно.
 */
import {
    BedDouble, Building2, Cake, Car, Dumbbell, Factory, GraduationCap, Hammer,
    House, Landmark, Megaphone, Monitor, PartyPopper, PawPrint, Plane, Scale,
    Scissors, ShoppingBag, Sprout, Stethoscope, Store, Truck, Utensils,
} from 'lucide-vue-next';

const ICONS = {
    'bed-double': BedDouble,
    'building-2': Building2,
    cake: Cake,
    car: Car,
    dumbbell: Dumbbell,
    factory: Factory,
    'graduation-cap': GraduationCap,
    hammer: Hammer,
    house: House,
    landmark: Landmark,
    megaphone: Megaphone,
    monitor: Monitor,
    'party-popper': PartyPopper,
    'paw-print': PawPrint,
    plane: Plane,
    scale: Scale,
    scissors: Scissors,
    'shopping-bag': ShoppingBag,
    sprout: Sprout,
    stethoscope: Stethoscope,
    truck: Truck,
    utensils: Utensils,
};

export const iconNames = Object.keys(ICONS);

export function categoryIcon(name) {
    return ICONS[name] || Store;
}
