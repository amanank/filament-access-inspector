<div hidden wire:init="loadAudit" x-data="{}"
    x-on:access-inspector-result="
        const result = $el.querySelector('[data-access-inspector-result]');
        result.innerHTML = $event.detail.html;
        $dispatch('access-inspector-loaded', {
            html: result.innerHTML,
            status: $event.detail.status,
            pageUrl: $event.detail.pageUrl,
            allowedCount: $event.detail.allowedCount
        });
    ">
    <div data-access-inspector-result x-ignore></div>
</div>
