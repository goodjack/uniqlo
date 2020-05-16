@extends('layouts.master')

@section('title', "更新日誌")

@section('content')
<div class="ts attached very padded horizontally fitted secondary segment">
    <div class="ts container">
        <div class="ts hidden divider"></div>
        <div class="ts basic segment">
            <h1 class="ts header">
                UQ 搜尋更新日誌
                <div class="sub header">
                    紀錄本站的成長史
                </div>
            </h1>
        </div>
        <div class="ts hidden divider"></div>
    </div>
</div>
<div class="ts attached very padded horizontally fitted segment">
    <div class="ts container relaxed stackable grid">
        <!-- 左側區塊 -->
        <div class="eight wide column">
            <!-- 文章 -->
            <div class="ts basic segment">
                <!-- 標題 -->
                <h2 class="ts dividing header">
                    v1.31.0
                </h2>
                <!-- / 標題 -->
                <div class="ts large compact basic fitted secondary message">
                    <div class="header">新增功能</div>
                    <ul>
                        <li>加入了更新日誌頁！（就是這頁啦）</li>
                        <li>精選穿搭的照片現在更大了！（使用 Styling Book 手機版提供的版本）</li>
                        <li>新增了評價資訊，在卡片與產品頁中顯示星等與評論數</li>
                        <li>頁尾變得更 fancy 了</li>
                        <li>如果搜尋的產品不存在，可以繼續在搜尋列尋找其他產品</li>
                    </ul>
                </div>
                <div class="ts large compact basic fitted secondary message">
                    <div class="header">修復錯誤</div>
                    <ul>
                        <li>搜尋列如果忘記打產品編號不會出錯了</li>
                    </ul>
                </div>
            </div>
            <!-- / 文章 -->
        </div>
        <!-- / 左側區塊 -->

        <!-- 留白 -->
        <div class="one wide column"></div>

        <!-- 右側區塊 -->
        <div class="seven wide column">
            <!-- 側邊區塊 -->
            <div class="ts basic segment">
                <h4 class="ts header">有建議或想法嗎？</h4>
                <div id="disqus_thread"></div>
                <script>
                    (function() { // DON'T EDIT BELOW THIS LINE
                    var d = document, s = d.createElement('script');
                    s.src = 'https://uq-sou-xun.disqus.com/embed.js';
                    s.setAttribute('data-timestamp', +new Date());
                    (d.head || d.body).appendChild(s);
                    })();
                </script>
                <noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript">comments
                        powered by Disqus.</a></noscript>
            </div>
            <!-- / 側邊區塊 -->
        </div>
        <!-- / 右側區塊 -->
    </div>
</div>
@endsection
