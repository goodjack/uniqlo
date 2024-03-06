@extends('layouts.master')

@section('title', '更新日誌')

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
                        v3.0.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增日本熱門評論商品列表</li>
                            <li>商品頁顯示日本商品相關資訊、圖片、影片</li>
                        </ul>
                        <div class="header">更新功能</div>
                        <ul>
                            <li>StyleHint 卡片明確標示日本版與美國版</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.16.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">更新功能</div>
                        <ul>
                            <li>列表頁現在會排除已售罄的商品了</li>
                            <li>商品頁最多顯示六個延伸商品，以提升瀏覽體驗</li>
                        </ul>
                        <div class="header">修復錯誤</div>
                        <ul>
                            <li>修正全站圖片因 lazy loading 造成滾動時版面配置位移的問題</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.15.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">更新功能</div>
                        <ul>
                            <li>調整熱門評論列表的商品數量</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.14.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增回到頁首的按鈕</li>
                            <li>列表頁加上如何排序的描述</li>
                        </ul>
                        <div class="header">更新功能</div>
                        <ul>
                            <li>調整熱門評論、熱門穿搭列表呈現的商品數量</li>
                            <li>StyleHint 陸續加入台灣店員穿搭照</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.13.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">更新功能</div>
                        <ul>
                            <li>StyleHint 陸續加入台灣店員穿搭照</li>
                            <li>StyleHint 超連結改指向官網，而不是 stylehint.com</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.12.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>產品頁新增商品實照區塊，目前先增加同商品的各種顏色實照</li>
                        </ul>
                        <div class="header">更新功能</div>
                        <ul>
                            <li>列表中卡片價目現在更清晰了</li>
                            <li>改善產品敘述的內容呈現，去除了一些雜訊</li>
                        </ul>
                        <div class="header">修復功能</div>
                        <ul>
                            <li>隱藏 DISQUS 噁心的廣告</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.11.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增熱門穿搭列表，來看看最多人穿搭的商品有哪些吧！</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.10.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>網友穿搭頁全新登場！</li>
                        </ul>
                        <div class="header">修復錯誤</div>
                        <ul>
                            <li>修正 GU 商品編號無法搜尋的問題</li>
                            <li>修正多個商品編號無法正確顯示搜尋結果的問題</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.9.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">更新功能</div>
                        <ul>
                            <li>調整期間限定特價排序方式為：降價幅度多至少 → 評論人數多至少 → 評分高至低 → 商品新至舊</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.8.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增網友穿搭</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.7.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增關鍵字搜尋功能</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.6.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>開始支援 GU 商品了，歡迎提供建議與意見！</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.5.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增「網路限定特價」標籤</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.4.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>商品頁新增精選穿搭區塊</li>
                            <li>新增「APP 限定特價」標籤</li>
                            <li>網站開放支援 Discord 與 Pinterest 取得資料</li>
                        </ul>
                        <div class="header">更新功能</div>
                        <ul>
                            <li>首頁的 footer 現在會置底了（終於！特別感謝 Wama 貢獻）</li>
                            <li>提升商品頁載入速度與體驗</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.3.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>舊系統商品頁新增提示，並盡可能引導至新系統的最新內容</li>
                            <li>新系統商品頁提供舊系統商品列表，以便比較價格</li>
                            <li>搜尋頁現在會顯示更完整的舊系統延伸商品</li>
                        </ul>
                        <div class="header">更新功能</div>
                        <ul>
                            <li>舊系統卡片不顯示圖片，以因應 UNIQLO 移除舊系統商品圖</li>
                            <li>若商品同時存在於新舊系統，則舊系統商品頁大圖替換成新系統的商品圖</li>
                            <li>修正部分頁面爆版問題</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.2.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增特價商品列表</li>
                            <li>新增熱門評論商品列表</li>
                            <li>新增新款商品列表</li>
                            <li>新增即將上市商品列表</li>
                            <li>新增合購優惠商品列表</li>
                            <li>新增網路獨家販售商品列表</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.1.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增期間限定特價商品列表</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v2.0.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>千呼萬喚，全新支援新版 UNIQLO 官網！</li>
                            <li>任何建議與回饋，歡迎在此頁留言，您的互動是我維護的最大動力 💪</li>
                        </ul>
                        <div class="header">已知限制</div>
                        <ul>
                            <li>目前僅先提供搜尋商品編號，後續會繼續擴充各式列表</li>
                            <li>目前商品頁僅提供簡介、評論數、歷史價格等資訊</li>
                            <li>新版上線前的歷史價格為不規律手動抓取匯入，資料僅供參考</li>
                        </ul>
                        <div class="header">預計下一版提供</div>
                        <ul>
                            <li>各式商品列表</li>
                            <li>商品頁擴充更多內容</li>
                            <li>感謝大家耐心等候 🙏</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v1.35.1
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增公告</div>
                        <ul>
                            <li>本站尚未支援新版 UNIQLO 官網，目前網站內容皆為過往資訊，部分功能亦受到限制，敬請見諒。</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v1.34.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>新增商品評論區，歡迎大家一起來討論</li>
                        </ul>
                    </div>
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">更新功能</div>
                        <ul>
                            <li>歷史價位全新升級！現在範圍更大更好讀了</li>
                            <li>歷史價位對於手機版更友善，手指點選空白區域也可拖曳指標</li>
                            <li>現在商品排序會針對熱門評論商品優先了</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
                <!-- 文章 -->
                <div class="ts basic segment">
                    <!-- 標題 -->
                    <h2 class="ts dividing header">
                        v1.32.0
                    </h2>
                    <!-- / 標題 -->
                    <div class="ts large compact basic fitted secondary message">
                        <div class="header">新增功能</div>
                        <ul>
                            <li>社群網站上的描述可以看到評價資訊了</li>
                        </ul>
                    </div>
                </div>
                <!-- / 文章 -->
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
                            var d = document,
                                s = d.createElement('script');
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

@section('css')
    <style>
        #disqus_thread>iframe[sandbox] {
            display: none !important
        }
    </style>
@endsection
