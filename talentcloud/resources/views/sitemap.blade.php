{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($fijas as $url)
    <url><loc>{{ $url }}</loc></url>
@endforeach
@foreach ($vacantes as $vacante)
    <url><loc>{{ route('empleos.show', $vacante->id) }}</loc><lastmod>{{ $vacante->updated_at?->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
