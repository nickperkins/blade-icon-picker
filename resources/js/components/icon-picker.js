export function iconPicker(config) {
    const PER_PAGE = 60;
    const DROPDOWN_WIDTH = 420;
    const DROPDOWN_GAP = 4;

    return {
        // --- config ---
        endpoint: config.endpoint,
        setsEndpoint: config.setsEndpoint,
        placeholder: config.placeholder,
        disabled: config.disabled,
        event: config.event || 'icon-picker-selected',
        wireModel: config.wireModel || null,
        // --- state ---
        isOpen: false,
        selectedId: config.currentValue || '',
        selectedIconData: config.initialSelectedIcon || null,
        query: '',
        selectedSet: '',       // empty = all sets
        selectedVariant: 'o',  // default to outline
        sets: [],
        allIcons: [],          // fetched icons (current filter)
        visibleCount: PER_PAGE,
        total: 0,
        loading: false,
        hasMore: false,
        activeIconIndex: -1,
        currentRequestId: 0,   // prevents race conditions
        dropdownStyle: '',     // inline position:fixed coordinates

        // --- computed ---
        get selectedIcon() {
            // Prefer the fetched icon (has fresh SVG); fall back to the
            // server-rendered initial data so the trigger shows the
            // selected icon before any AJAX fetch happens.
            return this.allIcons.find(i => i.id === this.selectedId)
                ?? this.selectedIconData;
        },
        get visibleIcons() {
            return this.allIcons.slice(0, this.visibleCount);
        },

        // --- lifecycle ---
        async toggle() {
            if (this.disabled) return;
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.positionDropdown();
                await this.loadSets();
                await this.fetchIcons();
                this.$nextTick(() => this.$refs.searchInput?.focus());
            }
        },
        close() {
            this.isOpen = false;
            this.query = '';
            this.visibleCount = PER_PAGE;
            this.activeIconIndex = -1;
            this.dropdownStyle = '';
        },

        // --- dropdown positioning ---
        // Uses position:fixed to escape overflow-clipping ancestors.
        // Anchors below the trigger; flips left if the dropdown would
        // overflow the viewport's right edge.
        positionDropdown() {
            const trigger = this.$refs.trigger;
            if (!trigger) return;
            const rect = trigger.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            let left = rect.left;
            // If dropdown would overflow right edge, anchor to trigger's right edge
            if (left + DROPDOWN_WIDTH > viewportWidth) {
                left = Math.max(0, rect.right - DROPDOWN_WIDTH);
            }

            // Position below trigger; flip above if not enough space below
            const spaceBelow = viewportHeight - rect.bottom;
            let top;
            if (spaceBelow < 200 && rect.top > 200) {
                // Not enough space below — open upward
                top = Math.max(0, rect.top - DROPDOWN_GAP);
            } else {
                top = rect.bottom + DROPDOWN_GAP;
            }

            this.dropdownStyle =
                `position:fixed; left:${left}px; top:${top}px; min-width:${DROPDOWN_WIDTH}px;`;
        },

        // --- data fetching ---
        async loadSets() {
            if (this.sets.length > 0) return;
            try {
                const res = await fetch(this.setsEndpoint);
                this.sets = await res.json();
            } catch (_) {}
        },
        async fetchIcons() {
            this.loading = true;
            this.currentRequestId++;
            const reqId = this.currentRequestId;
            const params = new URLSearchParams();
            if (this.selectedSet) params.set('set', this.selectedSet);
            if (this.selectedVariant) params.set('variant', this.selectedVariant);
            if (this.query.trim()) params.set('q', this.query.trim());
            params.set('page', '1');
            try {
                const res = await fetch(`${this.endpoint}?${params}`);
                const data = await res.json();
                if (reqId !== this.currentRequestId) return; // stale response
                this.allIcons = data.icons;
                this.total = data.total;
                this.hasMore = data.hasMore;
                this.visibleCount = PER_PAGE;
            } catch (_) {
                this.allIcons = [];
                this.hasMore = false;
            } finally {
                if (reqId === this.currentRequestId) this.loading = false;
            }
        },
        async loadMore() {
            if (!this.hasMore || this.loading) return;
            this.loading = true;
            this.currentRequestId++;
            const reqId = this.currentRequestId;
            const nextPage = Math.ceil(this.allIcons.length / PER_PAGE) + 1;
            const params = new URLSearchParams();
            if (this.selectedSet) params.set('set', this.selectedSet);
            if (this.selectedVariant) params.set('variant', this.selectedVariant);
            if (this.query.trim()) params.set('q', this.query.trim());
            params.set('page', String(nextPage));
            try {
                const res = await fetch(`${this.endpoint}?${params}`);
                const data = await res.json();
                if (reqId !== this.currentRequestId) return;
                this.allIcons = [...this.allIcons, ...data.icons];
                this.hasMore = data.hasMore;
            } catch (_) {} finally {
                if (reqId === this.currentRequestId) this.loading = false;
            }
        },
        resetAndFetch() {
            this.visibleCount = PER_PAGE;
            this.activeIconIndex = -1;
            this.fetchIcons();
        },

        // --- selection ---
        // Update selectedId locally and propagate to the parent Livewire
        // component. The third argument to $wire.$set triggers the updated()
        // lifecycle hook, which the page editor uses to reload the preview.
        // We also dispatch a generic icon-picker-selected event so consumers
        // that need their own preview logic can listen in.
        select(icon) {
            this.selectedId = icon.id;
            this.selectedIconData = icon;
            this.close();
            this.syncToLivewire(icon.id);
            this.dispatchSelected(icon.id);
        },
        clear() {
            this.selectedId = '';
            this.selectedIconData = null;
            this.syncToLivewire('');
            this.dispatchSelected('');
        },
        syncToLivewire(value) {
            const modelName = this.resolveWireModel();
            if (!modelName) return;
            try {
                this.$wire.$set(modelName, value, true);
            } catch (_) {
                // Not inside a Livewire component — no-op
            }
        },
        dispatchSelected(value) {
            // Generic event consumers can listen to. The detail includes
            // the selected icon id and the wire:model path (when present).
            const modelName = this.resolveWireModel();
            try {
                this.$dispatch(this.event, {
                    value,
                    model: modelName,
                });
            } catch (_) {}
        },
        resolveWireModel() {
            if (this.wireModel) return this.wireModel;
            const attr = Array.from(this.$el.attributes).find(a =>
                a.name.startsWith('wire:model')
            );
            return attr ? attr.value : null;
        },

        // --- scroll ---
        loadNextChunk() {
            if (this.loading) return;
            // If we have more icons loaded than visible, just show more
            if (this.allIcons.length > this.visibleCount) {
                this.visibleCount += PER_PAGE;
            } else if (this.hasMore) {
                // Need to fetch more from server
                this.loadMore().then(() => {
                    this.visibleCount += PER_PAGE;
                });
            }
        },

        // --- keyboard navigation ---
        onKeydown(event) {
            if (!this.isOpen) return;

            const total = this.visibleIcons.length;
            if (total === 0 && event.key !== 'Escape') return;

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
