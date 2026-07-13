{{-- Blade Icon Picker — Main component view (AJAX-based) --}}
<div
    class="ip-root"
    x-data="iconPicker({
        endpoint: '{!! $endpoint !!}',
        setsEndpoint: '{!! $setsEndpoint !!}',
        currentValue: {!! \Illuminate\Support\Js::from($value) !!},
        placeholder: {!! \Illuminate\Support\Js::from($placeholder) !!},
        disabled: {{ $disabled ? 'true' : 'false' }},
        initialSelectedIcon: {!! \Illuminate\Support\Js::from($selectedIcon) !!},
    })"
    x-on:scroll.window="close()"
    x-on:resize.window="close()"
    {{ $attributes->except(['placeholder', 'disabled', 'value']) }}
>
    {{-- Trigger wrapper --}}
    <div class="ip-trigger-wrapper">
        <button
            type="button"
            class="ip-trigger"
            x-ref="trigger"
            :disabled="disabled"
            x-bind:aria-expanded="isOpen"
            aria-haspopup="listbox"
            x-on:click="toggle()"
        >
            <span x-show="!selectedId" x-text="placeholder"></span>
            <template x-if="selectedIcon">
                <span class="ip-trigger-selected">
                    <span x-html="selectedIcon.svg"></span>
                    <span x-text="selectedIcon.label"></span>
                </span>
            </template>
            <span class="ip-chevron" aria-hidden="true">&#9660;</span>
        </button>

        <button
            x-show="selectedId"
            type="button"
            class="ip-clear"
            x-on:click.stop="clear()"
            aria-label="Clear selection"
        >&times;</button>
    </div>

    {{-- Dropdown panel --}}
    <div
        x-show="isOpen"
        x-trap="isOpen"
        class="ip-dropdown"
        x-bind:style="dropdownStyle"
        x-on:click.outside="close()"
        x-on:keydown="onKeydown($event)"
    >
        {{-- Filter bar --}}
        <div class="ip-filters">
            <select x-model="selectedSet" x-on:change="resetAndFetch()" class="ip-filter-select">
                <option value="">All sets</option>
                <template x-for="set in sets" :key="set.prefix">
                    <option :value="set.prefix" x-text="set.label + ' (' + set.count + ')'"></option>
                </template>
            </select>
            <select x-model="selectedVariant" x-on:change="resetAndFetch()" class="ip-filter-select">
                <option value="">All styles</option>
                <option value="o">Outline</option>
                <option value="s">Solid</option>
                <option value="m">Mini</option>
            </select>
        </div>

        {{-- Search input --}}
        <input
            type="text"
            class="ip-search"
            x-model="query"
            x-ref="searchInput"
            placeholder="Search icons..."
            x-on:input.debounce.200ms="resetAndFetch()"
        />

        {{-- Icon grid --}}
        <div class="ip-grid" x-ref="grid" role="listbox">
            <template x-for="(icon, index) in visibleIcons" :key="icon.id">
                <button
                    type="button"
                    class="ip-icon-btn"
                    x-bind:class="{
                        'ip-icon-btn--selected': icon.id === selectedId,
                        'ip-icon-btn--active': index === activeIconIndex
                    }"
                    role="option"
                    x-bind:aria-selected="(icon.id === selectedId).toString()"
                    x-on:click="select(icon)"
                >
                    <span x-html="icon.svg"></span>
                    <span class="ip-icon-label" x-text="icon.label"></span>
                </button>
            </template>

            {{-- Loading indicator --}}
            <div x-show="loading" class="ip-loading">
                <span class="ip-loading-spinner"></span>
                Loading...
            </div>

            {{-- Sentinel for infinite scroll --}}
            <div
                x-show="hasMore && !loading"
                x-intersect="loadNextChunk()"
            ></div>
        </div>

        {{-- Empty results --}}
        <div x-show="!loading && visibleIcons.length === 0" class="ip-empty">
            No icons match your search.
        </div>
    </div>
</div>
