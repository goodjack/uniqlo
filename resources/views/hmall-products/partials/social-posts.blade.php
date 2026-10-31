@php
    $platformNames = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'threads' => 'Threads', 'x' => 'X', 'facebook' => 'Facebook'];
    $platformIcons = ['youtube' => 'youtube', 'instagram' => 'instagram', 'threads' => 'threads', 'x' => 'x-twitter', 'facebook' => 'facebook'];
@endphp
<div class="uq-social-shelf" data-social-posts>
    <button type="button" class="ts circular icon button uq-social-arrow is-prev" aria-label="往左看" hidden>
        <i class="chevron left icon"></i>
    </button>
    <div class="ts flatted cards uq-social-strip">
        @foreach ($socialPosts as $post)
            {{-- 只有 YouTube 能從指定秒數開始播，其他平台填了時間也不顯示，免得點開對不上 --}}
            @php($start = $post->youtube_id ? $post->start_seconds : null)
            <div class="ts borderless card uq-social-card" data-index="{{ $loop->index }}" data-platform="{{ $post->platform }}"
                @if ($post->youtube_id) data-youtube-id="{{ $post->youtube_id }}" data-vertical="{{ $post->kind === 'Shorts' ? 1 : 0 }}" @endif
                @if ($start) data-start="{{ $start }}" @endif>
                <div class="uq-social-media">
                    @if ($post->image_url)
                        {{-- Shorts 的直式縮圖沒有官方文件保證，沒有就退回一般縮圖；IG、Threads、X 的圖過期或抓不到就改顯示文字卡 --}}
                        <x-lazy-load-image src="{{ $post->image_url }}" alt="" width="2" height="3"
                            :onerror="$post->kind === 'Shorts'
                                ? 'this.onerror=null;this.src=\'https://i.ytimg.com/vi/'.$post->youtube_id.'/hqdefault.jpg\''
                                : 'this.nextElementSibling.hidden=false;this.remove()'" />
                    @endif
                    <div class="uq-social-quote" @if ($post->image_url) hidden @endif>
                        <i class="fa-brands fa-{{ $platformIcons[$post->platform] }}" aria-hidden="true"></i>
                        <p>{{ $post->text }}</p>
                    </div>
                    @if ($post->youtube_id || in_array($post->kind, ['Reels', '影片']))
                        <span class="uq-social-badge">
                            <i class="play icon" aria-hidden="true"></i>
                            @if ($start)
                                {{ $start >= 3600 ? sprintf('%d:%02d:%02d', intdiv($start, 3600), intdiv($start % 3600, 60), $start % 60) : sprintf('%d:%02d', intdiv($start, 60), $start % 60) }}
                            @endif
                        </span>
                    @endif
                </div>
                <div class="content">
                    {{-- 沒有 JS 時直接開原貼文；有 JS 時改開彈出視窗 --}}
                    <a class="uq-social-title" href="{{ $post->canonical_url }}" target="_blank" rel="nofollow noopener"
                        aria-label="{{ $platformNames[$post->platform] }}：{{ $post->author }}">{{ $post->text }}</a>
                    <div class="uq-social-by">
                        <i class="fa-brands fa-{{ $platformIcons[$post->platform] }} is-{{ $post->platform }}" aria-hidden="true"></i>
                        <span class="uq-social-author">{{ $post->handle }}</span>
                        @if ($post->youtube_id)
                            {{-- 卡片上的靜音預覽要能停；鈕放在影片外面，不蓋住播放器 --}}
                            <button type="button" class="ts mini basic circular icon button uq-social-toggle" aria-label="播放預覽" hidden>
                                <i class="play icon" aria-hidden="true"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <button type="button" class="ts circular icon button uq-social-arrow is-next" aria-label="往右看" hidden>
        <i class="chevron right icon"></i>
    </button>

    {{-- 平台的嵌入腳本只要貼文網址就能長出貼文，這裡只放網址，不輸出平台回傳的 HTML --}}
    @foreach ($socialPosts as $post)
        @if ($post->platform !== 'youtube')
            <template data-embed="{{ $loop->index }}">
                @switch($post->platform)
                    @case('instagram')
                        <blockquote class="instagram-media" data-instgrm-permalink="{{ $post->canonical_url }}"
                            data-instgrm-version="14" data-instgrm-captioned>
                            <a href="{{ $post->canonical_url }}" target="_blank" rel="nofollow noopener">在 Instagram 查看</a>
                        </blockquote>
                    @break
                    @case('threads')
                        @php($threadsCode = basename($post->canonical_url))
                        <blockquote class="text-post-media" id="ig-tp-{{ $threadsCode }}"
                            data-text-post-permalink="https://www.threads.com/t/{{ $threadsCode }}" data-text-post-version="0">
                            <a href="{{ $post->canonical_url }}" target="_blank" rel="nofollow noopener">在 Threads 查看</a>
                        </blockquote>
                    @break
                    @case('x')
                        <blockquote class="twitter-tweet" data-dnt="true" data-conversation="none">
                            <a href="{{ str_replace('https://x.com/', 'https://twitter.com/', $post->canonical_url) }}"
                                target="_blank" rel="nofollow noopener">在 X 查看</a>
                        </blockquote>
                    @break
                    @case('facebook')
                        <iframe src="https://www.facebook.com/plugins/{{ $post->kind === '影片' ? 'video' : 'post' }}.php?{{ http_build_query(['href' => $post->canonical_url, 'show_text' => 'true', 'width' => 500]) }}"
                            width="500" height="600" style="border: none; overflow: hidden" scrolling="no" frameborder="0"
                            allowfullscreen allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
                    @break
                @endswitch
                {{-- 平台的嵌入被瀏覽器或擴充套件（例如 Facebook Container）擋掉時，改顯示自己存的內容 --}}
                <div class="ts card uq-social-fallback" hidden>
                    @if ($post->image_url)
                        <div class="image"><img src="{{ $post->image_url }}" alt=""></div>
                    @endif
                    <div class="content">
                        <div class="header">{{ $post->author }}</div>
                        <div class="description">{{ $post->text }}</div>
                    </div>
                    <a class="ts fluid bottom attached button" href="{{ $post->canonical_url }}" target="_blank" rel="nofollow noopener">
                        到 {{ $platformNames[$post->platform] }} 看完整貼文
                    </a>
                </div>
            </template>
        @endif
    @endforeach
</div>
