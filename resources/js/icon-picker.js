import { focus } from '@alpinejs/focus';
import { iconPicker } from './components/icon-picker.js';

// Auto-register with Alpine when it initializes (handles both CDN and bundled setups)
if (typeof document !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) {
            window.Alpine.plugin(focus);
            window.Alpine.data('iconPicker', iconPicker);
        }
    });
}

// Also expose for manual/bundled setups
if (typeof window !== 'undefined') {
    window.IconPicker = { focus, iconPicker };
}

export { focus, iconPicker };
