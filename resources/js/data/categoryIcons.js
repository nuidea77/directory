/**
 * Ангиллын icon: DB дэх categories.icon (Lucide-ийн kebab-case нэр) →
 * Lucide Vue компонент. Шинэ ангилал нэмэхэд энд бүртгэнэ; байхгүй бол
 * ерөнхий Store icon-оор орлоно.
 */
import {
    Baby, BedDouble, Briefcase, Building, Building2, Cake, Camera, Car, Dumbbell,
    Factory, Gamepad2, GraduationCap, Hammer, House, Landmark, Megaphone, Monitor,
    Palette, PartyPopper, PawPrint, Plane, Scale, Scissors, Shirt, ShoppingBag,
    ShoppingCart, Sprout, Stethoscope, Store, Truck, Utensils, Wrench,
} from 'lucide-vue-next';

const ICONS = {
    baby: Baby,
    'bed-double': BedDouble,
    briefcase: Briefcase,
    building: Building,
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
    camera: Camera,
    'gamepad-2': Gamepad2,
    palette: Palette,
    shirt: Shirt,
    'shopping-bag': ShoppingBag,
    'shopping-cart': ShoppingCart,
    sprout: Sprout,
    stethoscope: Stethoscope,
    truck: Truck,
    utensils: Utensils,
    wrench: Wrench,
};

export const iconNames = Object.keys(ICONS);

export function categoryIcon(name) {
    return ICONS[name] || Store;
}
