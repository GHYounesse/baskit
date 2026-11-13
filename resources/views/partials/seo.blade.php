{{--
    The SEO tags of a page, written into the HTML <head> by the server.
    Included by app.blade.php. It receives $seo, the array made by App\Support\PageSeo::toArray().
    Each block below is one kind of tag; blocks only appear when there is something to say.
--}}

{{-- "May search engines list this page?" Always present. --}}
<meta name="robots" content="{{ $seo['robots'] }}">

{{-- The short text under the title in Google results, and in link previews. --}}
@if (! empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
@endif

{{-- The official address of the page, so Google does not treat "/shop" and "/shop?sort=name" as two pages. --}}
@if (! empty($seo['canonical']))
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
@endif

{{-- Link previews (WhatsApp, LinkedIn, Facebook). "og:" stands for Open Graph. "twitter:" is the same idea for X. --}}
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:site_name" content="{{ $seo['site_name'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta name="twitter:title" content="{{ $seo['title'] }}">

{{-- The picture in the preview. With a picture the card is large; without one it is a small card. --}}
@if (! empty($seo['image']))
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif

{{--
    Facts written for Google's eyes only (for example a product's price and stock status), in a
    format called JSON-LD. This is what lets Google show a price or stars in its results.

    SECURITY: product names and descriptions come from the database. Without protection, a name
    containing "</script><script>..." could close this tag early and run code in visitors' browsers.
    JSON_HEX_TAG and JSON_HEX_AMP rewrite "<", ">" and "&" as safe escapes, so that cannot happen.
--}}
@if (! empty($seo['json_ld']))
    <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
