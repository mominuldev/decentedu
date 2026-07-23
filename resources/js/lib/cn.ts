import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/** Merge conditional class names, de-duplicating conflicting Tailwind utilities. */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Format an integer with thousands separators. */
export function num(n: number) {
    return n.toLocaleString('en-US');
}

/** Format a value as BDT currency (the app's locale). */
export function money(n: number) {
    return '৳' + n.toLocaleString('en-US');
}
