<!DOCTYPE html>
<html lang="{{ $page->locale }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Draft preview - {{ $page->title }}</title>@vite(['resources/css/cms-preview.css'])</head>
<body class="cms-preview">
    <header class="cms-preview-banner"><strong>Draft preview</strong><span>Revision {{ $revision->revision_number }} - {{ $revision->created_at->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i') }}</span><span>Private, signed, and not published</span></header>
    <main>
        @foreach($revision->payload['sections'] as $section)
            @include('content.sections.'.str_replace('_','-',$section['type']), ['section'=>$section,'media'=>$media])
        @endforeach
    </main>
</body>
</html>
