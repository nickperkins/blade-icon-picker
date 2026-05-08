export function iconPicker(config) {
    return {
        // --- static config ---
        allIcons: config.icons,
        placeholder: config.placeholder,
        disabled: config.disabled,
        chunkSize: config.chunkSize || 30,

        // --- reactive state ---
        isOpen: false,
        selectedId: config.currentValue || '',
        query: '',
        chunkCount: 1,
        activeIconIndex: -1,

        // --- computed ---
        get selectedIcon() {
            return this.allIcons.find(i => i.id === this.selectedId) ?? null;
        },

        get filteredIcons() {
            if (!this.query.trim()) return this.allIcons;

            const tokens = this.query.trim().toLowerCase().split(/\s+/);

            return this.allIcons.filter(icon => {
                const haystack = (icon.id + ' ' + icon.label).toLowerCase();
                return tokens.every(token => haystack.includes(token));
            });
        },

        get visibleIcons() {
            return this.filteredIcons.slice(0, this.chunkCount * this.chunkSize);
        },

        get hasMore() {
            return this.visibleIcons.length < this.filteredIcons.length;
        },

        // --- methods ---
        toggle() {
            if (this.disabled) return;
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.chunkCount = 1;
                this.activeIconIndex = -1;
                this.$nextTick(() => {
                    const input = this.$refs.searchInput;
                    if (input) input.focus();
                });
            }
        },

        close() {
            this.isOpen = false;
            this.query = '';
            this.chunkCount = 1;
            this.activeIconIndex = -1;
        },

        select(icon) {
            this.selectedId = icon.id;
            this.close();
            this.syncToLivewire(icon.id);
        },

        clear() {
            this.selectedId = '';
            this.syncToLivewire('');
        },

        syncToLivewire(value) {
            const modelName = this.resolveWireModel();
            if (!modelName) return;
            try {
                this.$wire.set(modelName, value);
            } catch (_) {
                // Not inside a Livewire component — no-op
            }
        },

        resolveWireModel() {
            const attr = Array.from(this.$el.attributes).find(a =>
                a.name.startsWith('wire:model')
            );
            return attr ? attr.value : null;
        },

        onSearch() {
            this.chunkCount = 1;
            this.activeIconIndex = -1;
        },

        loadNextChunk() {
            this.chunkCount++;
        },

        // --- keyboard navigation ---
        onKeydown(event) {
            if (!this.isOpen) return;

            const total = this.visibleIcons.length;
            if (total === 0) return;

            switch (event.key) {
                case 'ArrowDown':
                case 'ArrowRight':
                    event.preventDefault();
                    this.activeIconIndex = (this.activeIconIndex + 1) % total;
                    this.scrollActiveIntoView();
                    break;
                case 'ArrowUp':
                case 'ArrowLeft':
                    event.preventDefault();
                    this.activeIconIndex = (this.activeIconIndex - 1 + total) % total;
                    this.scrollActiveIntoView();
                    break;
                case 'Enter':
                    event.preventDefault();
                    if (this.activeIconIndex >= 0) {
                        this.select(this.visibleIcons[this.activeIconIndex]);
                    }
                    break;
                case 'Escape':
                    this.close();
                    const trigger = this.$refs.trigger;
                    if (trigger) trigger.focus();
                    break;
            }
        },

        scrollActiveIntoView() {
            if (this.activeIconIndex < 0) return;
            this.$nextTick(() => {
                const buttons = this.$refs.grid?.querySelectorAll('.ip-icon-btn');
                const el = buttons?.[this.activeIconIndex];
                if (el) {
                    el.scrollIntoView({ block: 'nearest' });
                }
            });
        },
    };
}
