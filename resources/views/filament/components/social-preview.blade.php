@php
    /** @var string $title  @var string $description  @var string $url  @var ?string $image  @var string $imageNote */
    $host = strtoupper((string) parse_url($url, PHP_URL_HOST));
@endphp

<style>
    .oc-social { max-width: 520px; border: 1px solid rgba(128,128,128,.35); border-radius: .5rem; overflow: hidden; background: #fff; color: #1c1e21; font-family: Helvetica, Arial, sans-serif; }
    .dark .oc-social { background: #242526; color: #e4e6eb; }
    .oc-social-img { aspect-ratio: 1.91 / 1; background: #e4e6eb center / cover no-repeat; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #65676b; text-align: center; padding: .5rem; }
    .dark .oc-social-img { background-color: #3a3b3c; color: #b0b3b8; }
    .oc-social-body { padding: .6rem .8rem; border-top: 1px solid rgba(128,128,128,.25); background: #f0f2f5; }
    .dark .oc-social-body { background: #3a3b3c; }
    .oc-social-host { font-size: 12px; color: #65676b; letter-spacing: .02em; }
    .dark .oc-social-host { color: #b0b3b8; }
    .oc-social-title { font-size: 16px; font-weight: 600; line-height: 1.25; margin: .1rem 0; }
    .oc-social-desc { font-size: 14px; color: #65676b; line-height: 1.3; }
    .dark .oc-social-desc { color: #b0b3b8; }
</style>

<div class="oc-social" data-social-preview>
    @if ($image)
        <div class="oc-social-img" style="background-image: url('{{ $image }}');" role="img" aria-label="Share image"></div>
    @else
        <div class="oc-social-img">{{ $imageNote }}</div>
    @endif
    <div class="oc-social-body">
        <div class="oc-social-host">{{ $host }}</div>
        <div class="oc-social-title">{{ \Illuminate\Support\Str::limit($title, 70, '…') ?: '—' }}</div>
        <div class="oc-social-desc">{{ \Illuminate\Support\Str::limit($description, 110, '…') }}</div>
    </div>
</div>
