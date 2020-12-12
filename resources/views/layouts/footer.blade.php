<div class="ts attached very padded horizontally fitted fluid inverted segment">
    <div class="ts container stackable grid">
        <div class="eleven wide column">
            <h4 class="ts big inverted header">
                <i class="clone top aligned icon"></i>
                <div class="content">
                    UQ 搜尋
                    <div class="sub header">
                        UNIQLO 價格查詢比價網
                    </div>
                </div>
            </h4>
            <div class="ts large compact inverted basic faded message">
                <p>
                    UQ 搜尋是一個社群作品，由
                    <a class="ts link button" href="https://twitter.com/littlegoodjack" target="_blank"
                        rel="nofollow noopener" aria-label="Twitter @littlegoodjack">
                        小克
                    </a>
                    製作。
                </p>
                <p>與 FAST RETAILING 或 UNIQLO 無關。</p>
                <p>不保證本網站內容正確無誤。</p>
                <p>本網站所有提及之商標與名稱皆屬該公司所有。</p>

                <div class="ts hidden divider"></div>

                <a class="ts inverted basic circular very compact button" href="{{ route('pages.changelog') }}"
                    aria-label="changelog">
                    v1.34.0 更新日誌
                </a>
            </div>
        </div>
        <div class="five wide column">
            <div class="ts link secondary vertical inverted borderless big menu">
                <a href="{{ route('home') }}" class="fitted item" aria-label="home">
                    <p><i class="home icon"></i> 首頁</p>
                </a>
                <a href="{{ route('products.limited-offers') }}" class="fitted item" aria-label="limited-offers">
                    <p><i class="certificate icon"></i> 期間限定特價商品</p>
                </a>
                <a href="{{ route('products.sales') }}" class="fitted item" aria-label="sales">
                    <p><i class="shopping basket icon"></i> 特價商品</p>
                </a>
                <a href="{{ route('products.most-reviewed') }}" class="fitted item" aria-label="most-reviewed">
                    <p><i class="comments outline icon"></i> 熱門評論商品</p>
                </a>
                <a href="{{ route('products.news') }}" class="fitted item" aria-label="news">
                    <p><i class="leaf icon"></i> 新品</p>
                </a>
                <a href="{{ route('products.multi-buys') }}" class="fitted item" aria-label="multi-buys">
                    <p><i class="cubes icon"></i> 合購優惠</p>
                </a>
                <a href="{{ route('products.stockouts') }}" class="fitted item" aria-label="stockouts">
                    <p><i class="archive icon"></i> 可能已經沒有庫存</p>
                </a>
            </div>
        </div>
    </div>
</div>
