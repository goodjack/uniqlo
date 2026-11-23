{{-- country 給 jp 時右上角標「日本版」，跟圖卡（x-image-card）同一種標籤。
     點影片跟點照片一樣開燈箱，跟主圖、照片同一組（data-lightbox="image"）；
     暫停鈕放在連結外面，按暫停才不會順便打開燈箱。
     標籤要放在影片後面：Tocas 會把排在第一個的 attached label 的下一個元素往下推。
     燈箱連結只給格式正確的 https 網址：燈箱建影片時把網址直接拼進 HTML，網址解析失敗時瀏覽器會原樣交出去 --}}
@php($lightboxable = filter_var($video, FILTER_VALIDATE_URL) && preg_match('#^https://[^\s"\'<>`]+$#', $video))
<div class="ts borderless card">
    <div class="video uq-video">
        @if ($lightboxable)
            <a href="{{ $video }}" rel="nofollow noopener" data-lightbox="image" data-type="video" aria-label="放大播放影片">
                <video data-src="{{ $video }}" preload="none" muted loop playsinline hidden></video>
            </a>
        @else
            <video data-src="{{ $video }}" preload="none" muted loop playsinline hidden></video>
        @endif
        <noscript>
            <video src="{{ $video }}" preload="none" muted loop playsinline controls></video>
        </noscript>
        <button type="button" class="uq-video-toggle" aria-label="播放影片" hidden>
            <i class="play icon" aria-hidden="true"></i>
        </button>
        @if (($country ?? null) === 'jp')
            <div class="ts mini top right attached label">日本版</div>
        @endif
    </div>
</div>
