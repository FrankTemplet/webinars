@php($live = $live ?? false)
<div
    x-data="{
        src: null,
        ready: false,
        scale: 1,
        width: 1440,
        live: false,

        fit() {
            this.scale = Math.min(1, (this.$refs.frame.clientWidth || this.width) / this.width);
        },

        device(width) {
            this.width = width;
            this.$nextTick(() => this.fit());
        },

        init() {
            this.live = this.$el.dataset.live === '1';

            this.$nextTick(() => {
                const frame = this.$refs.frame;

                const fit = () => this.fit();
                fit();
                new ResizeObserver(fit).observe(frame);

                // La página no se pide hasta que el iframe es visible. El
                // observador sigue activo para reenviar el estado cada vez
                // que se vuelve a abrir el modal.
                new IntersectionObserver(([entry]) => {
                    if (! entry.isIntersecting) return;

                    if (this.src !== this.$el.dataset.src) {
                        this.ready = false;
                        this.src = this.$el.dataset.src;

                        return;
                    }

                    this.push();
                }).observe(frame);
            });

            if (! this.live) return;

            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) return;
                if (event.data?.type !== 'templet:preview:ready') return;

                this.ready = true;
                this.push();
            });
        },

        reload() {
            this.ready = false;
            this.$refs.iframe.contentWindow.location.reload();
        },

        push() {
            if (! this.ready) return;

            this.$refs.iframe?.contentWindow?.postMessage({
                type: 'templet:preview:state',
                state: { ...JSON.parse(JSON.stringify(this.$wire.data ?? {})), ...JSON.parse(this.$el.dataset.images) },
            }, window.location.origin);
        },
    }"
    data-src="{{ $url }}"
    data-live="{{ $live ? '1' : '0' }}"
    data-images="{{ json_encode($images ?? []) }}"
    class="space-y-3"
>
    <div class="flex gap-4 text-sm">
        <button type="button" x-on:click="device(1440)" x-bind:aria-pressed="width === 1440">Escritorio</button>
        <button type="button" x-on:click="device(390)" x-bind:aria-pressed="width === 390">Mobile</button>
    </div>
    <div
        x-ref="frame"
        class="relative mx-auto w-full overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700"
        x-bind:style="`max-width: ${width}px; height: ${900 * scale}px;`"
    >
        <iframe
            x-ref="iframe"
            x-bind:src="src"
            x-bind:style="`width: ${width}px; height: 900px; transform: scale(${scale}); transform-origin: top left; border: 0;`"
            title="Page preview"
        ></iframe>

        <div
            x-show="! src"
            class="absolute inset-0 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400"
        >
            Loading preview…
        </div>
    </div>

    <div class="flex items-center justify-between gap-4 text-sm text-gray-500 dark:text-gray-400">
        <span>
            <span x-text="width === 390 ? 'Mobile · 390px' : 'Escritorio · 1440px'"></span>
            @if ($live)
                Includes your unsaved changes.
            @endif
        </span>

        <span class="flex items-center gap-4">
            @if (! empty($liveUrl))
                <a href="{{ $liveUrl }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                    Open live page
                </a>
            @endif
            <button type="button" x-on:click="reload()" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                Reload
            </button>
        </span>
    </div>
</div>
