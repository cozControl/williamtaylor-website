<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $siteContent->title }} storefront preview - Revision {{ $revision->revision_number }}</title><style>html,body{height:100%;margin:0;background:#251f1a;font:14px system-ui}.preview-bar{box-sizing:border-box;height:44px;padding:12px 18px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.preview-frame{display:block;width:100%;height:calc(100% - 44px);border:0;background:#fff}</style></head>
<body><div class="preview-bar" role="status">Private storefront preview · {{ str_replace('_', ' ', $siteContent->type) }} · Version {{ $revision->revision_number }} · Not published</div><iframe class="preview-frame" src="{{ $renderUrl }}" title="William Taylor storefront with the selected revision"></iframe></body>
</html>
