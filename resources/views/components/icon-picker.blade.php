{{-- Blade Icon Picker — Main component view --}}
<div
    class="ip-root"
    x-data="iconPicker({
        icons: {!! \Illuminate\Support\Js::from($iconList) !!},
        currentValue: {!! \Illuminate\Support\Js::from($value) !!},
        placeholder: {!! \Illuminate\Support\Js::from($placeholder) !!},
        disabled: {{ $disabled ? 'true' : 'false' }},
        chunkSize: {{ $chunkSize }}
    })"
    {{ $attributes->except(['placeholder', 'disabled', 'value']) }}
>
    @if(empty($iconList))
        <div class="ip-empty">
            No icon sets found. Install blade-ui-kit/blade-heroicons:
            <code>composer require blade-ui-kit/blade-heroicons</code>
        </div>
    @else
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
            x-on:click.outside="close()"
            x-on:keydown="onKeydown($event)"
        >
            {{-- Search input --}}
            <input
                type="text"
                class="ip-search"
                x-model="query"
                x-ref="searchInput"
                placeholder="Search icons..."
                x-on:input="onSearch()"
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

                {{-- Sentinel for infinite scroll --}}
                <div
                    x-show="hasMore"
                    x-intersect="loadNextChunk()"
                ></div>
            </div>

            {{-- Empty search results --}}
            <div x-show="visibleIcons.length === 0" class="ip-empty">
                No icons match your search.
            </div>
        </div>
    @endif
</div>
