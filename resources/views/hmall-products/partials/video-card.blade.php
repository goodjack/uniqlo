{{-- country 給 jp 時右上角標「日本版」，跟圖卡（x-image-card）同一種標籤。
     點影片跟點照片一樣開燈箱，跟主圖、照片同一組（data-lightbox="image"）；
     暫停鈕放在連結外面，按暫停才不會順便打開燈箱。
     標籤要放在影片後面：Tocas 會把排在第一個的 attached label 的下一個元素往下推 --}}
<div class="ts borderless card">
    <div class="video uq-video">
        <a href="{{ $video }}" rel="nofollow noopener" data-lightbox="image" data-type="video" aria-label="放大播放影片">
            <video data-src="{{ $video }}" preload="none" muted loop playsinline hidden></video>
        </a>
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
