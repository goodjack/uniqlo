{{-- 結構化資料。JSON_HEX_TAG 讓內容裡的 </script> 無法提早結束這段 script --}}
<script type="application/ld+json">@json($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
