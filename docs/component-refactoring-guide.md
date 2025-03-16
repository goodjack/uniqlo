# Tocas UI v2 元件重構規則文件

## 重構思路與原則

1. **保持現有功能**：
   - 優先確保元件在 Tocas UI v2 環境下正常運作
   - 保留原有的類別名稱和結構
   - 不急於引入 v5 的新特性

2. **YAGNI 原則**：
   - 只實作當前需要的功能，避免過早最佳化
   - 不添加未使用的參數和選項
   - 簡化元件設計，減少不必要的複雜性

3. **測試驅動開發**：
   - 先撰寫測試案例，再實作元件
   - 測試案例應反映實際使用情境
   - 每個功能點都有對應的測試覆蓋
   - 確保舊元件的所有測試案例都被新元件涵蓋

4. **合併重複元件**：
   - 識別功能相似的元件（如 Container 和 Header）
   - 設計統一的介面來整合功能
   - 確保向後相容性
   - 逐步替換舊有使用方式

## 元件開發流程

1. **分析現有程式碼**：
   - 找出重複出現的 HTML 結構
   - 識別變化的部分（如標題、副標題等）
   - 確認元件的核心功能和可選功能
   - 檢視現有元件的使用情境

2. **設計元件介面**：
   - 定義必要的參數和選用的參數
   - 確保參數命名符合直覺
   - 考慮 HTML 安全性（如 `rightAction` 的 HTML 內容）
   - 設計合理的預設值

3. **撰寫測試案例**：
   - 基本功能測試（如標題渲染）
   - 特殊情況測試（如 XSS 防護）
   - 組合功能測試（如同時使用多個功能）
   - 確保涵蓋所有舊元件的測試案例

4. **實作元件**：
   - 建立元件類別和視圖
   - 處理參數和渲染邏輯
   - 確保 HTML 安全性
   - 實作參數的相依性處理（如 secondary/tertiary 互斥）

5. **重構現有程式碼**：
   - 逐步替換重複的 HTML 結構
   - 確認功能正常運作
   - 進行視覺檢查
   - 移除舊元件檔案

## 元件設計規範

1. **命名規範**：
   - 元件類別使用 PascalCase（如 `Section`）
   - 元件視圖使用 kebab-case（如 `section.blade.php`）
   - 參數使用 camelCase（如 `rightAction`）

2. **參數處理**：
   - 使用型別提示和預設值
   - 考慮使用 nullable 型別（如 `?string`）
   - 處理參數間的相依性
   - 提供合理的預設值

3. **測試案例命名**：
   - 使用 `test_` 前綴
   - 描述測試目的（如 `test_renders_basic_header`）
   - 按功能分組測試案例
   - 測試案例應放在 `tests/Feature/Components` 目錄下
   - 包含邊界條件測試

4. **元件註冊**：
   - Laravel 8+ 會自動發現 `app/View/Components` 目錄下的元件
   - 無需在 `AppServiceProvider` 中手動註冊元件
   - 元件可以通過 `<x-元件名稱>` 在 Blade 中使用

## 實際案例：Section 元件（合併 Container 和 Header）

### 元件類別
```php
namespace App\View\Components;

use Illuminate\View\Component;

class Section extends Component
{
    /**
     * Create a new component instance.
     *
     * @param  string|null  $title  區段標題
     * @param  string|null  $subTitle  副標題（可選）
     * @param  string|null  $rightAction  右側動作（可選）
     * @param  bool  $secondary  Secondary 樣式
     * @param  bool  $tertiary  Tertiary 樣式
     * @param  string|null  $grid  Grid 系統
     * @param  bool  $veryNarrow  Very Narrow 容器
     * @return void
     */
    public function __construct(
        public ?string $title = '',
        public ?string $subTitle = null,
        public ?string $rightAction = null,
        public bool $secondary = false,
        public bool $tertiary = false,
        public ?string $grid = '',
        public bool $veryNarrow = false,
    ) {
        // 確保 secondary 和 tertiary 不會同時為 true
        if ($this->secondary && $this->tertiary) {
            $this->tertiary = false;
        }
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('components.section');
    }
}
```

### 元件視圖
```blade
<div @class([
    'ts very padded horizontally fitted attached fluid',
    'secondary segment' => $secondary,
    'tertiary segment' => $tertiary,
    'segment' => !$secondary && !$tertiary,
])>
    <div @class([
        'ts',
        'very narrow' => $veryNarrow,
        'container',
        $grid ? "{$grid} grid" : null,
    ])>
        @if ($title)
            <h2 class="ts large dividing header">
                {{ $title }}
                @if ($subTitle)
                    <div class="inline sub header">{{ $subTitle }}</div>
                @endif
                @if ($rightAction)
                    <div class="right floated">
                        {!! $rightAction !!}
                    </div>
                @endif
            </h2>
            <div class="ts hidden divider"></div>
        @endif

        {{ $slot ?? '' }}
    </div>
</div>
```

### 測試案例重點
1. **基本功能測試**：
   - 渲染基本區段
   - 標題和副標題顯示
   - 右側動作按鈕

2. **樣式測試**：
   - Secondary 和 Tertiary 樣式
   - Grid 系統
   - Very Narrow 容器

3. **特殊情況測試**：
   - 空值處理
   - XSS 防護
   - 特殊字元處理

4. **組合功能測試**：
   - 多重功能組合
   - 樣式互斥處理

### 重構步驟
1. 建立新元件
2. 撰寫完整測試
3. 更新使用處
4. 移除舊元件

## 注意事項

1. **安全性與資料處理**：
   - 謹慎處理用戶輸入的 HTML 內容，避免 XSS 風險
   - 考慮如 `rightAction` 參數不轉義，允許 HTML 內容
   - 明確處理互斥參數（如 secondary/tertiary）
   - 在建構函式中處理相依邏輯
   - 提供清晰的文檔說明參數相依性

2. **測試與品質保證**：
   - 確保測試覆蓋所有功能點與舊元件功能
   - 測試 HTML 結構和類別名稱
   - 測試特殊情況（如空值、特殊字元）
   - 考慮使用 `assertSee()` 和 `assertDontSee()` 進行斷言
   - 測試參數組合與邊界條件
   - 請參考 Laravel 官方文件和最佳實踐

3. **效能與架構**：
   - 避免在元件中進行複雜的邏輯運算
   - 考慮使用 `@once` 指令避免重複渲染
   - 適當使用快取機制提升效能
   - 最小化 DOM 結構
   - 適當使用條件渲染
   - 避免不必要的包裝元素
   - 保持 HTML 結構的一致性
   - 考慮容器的正確巢狀關係

4. **文檔與維護性**：
   - 為每個元件建立使用文檔
   - 使用 PHPDoc 註解說明參數用途
   - 提供使用範例和常見問題解答

5. **未來升級考量**：
   - 元件設計應便於未來升級到 Tocas UI v5
   - 保持元件介面簡潔，便於擴展
   - 避免過度依賴 Tocas UI v2 特有的功能
   - 預留彈性以適應未來框架更新
   - 考慮向下相容性


## 常見問題與解決方案

1. **Undefined variable $slot**：
   - 問題：在元件視圖中使用 `$slot` 變數時，可能會出現 "Undefined variable $slot" 錯誤
   - 解決方案：使用 `$slot ?? ''` 來處理 `$slot` 變數可能不存在的情況
   ```php
   <div class="container">
       {{ $slot ?? '' }}
   </div>
   ```

2. **HTML 輸出中的多餘空格**：
   - 問題：使用條件式輸出 CSS 類別時，可能會產生多餘的空格，導致測試失敗
   - 解決方案：使用緊湊的條件式輸出，避免多餘的空格
   ```php
   <!-- 不好的寫法 -->
   <div class="ts {{ $padded ? 'very padded' : '' }} {{ $fitted ? 'horizontally fitted' : '' }} segment">

   <!-- 好的寫法 -->
   <div class="ts{{ $padded ? ' very padded' : '' }}{{ $fitted ? ' horizontally fitted' : '' }} segment">
   ```

3. **測試方法命名的 linter 警告**：
   - 問題：使用 `test_` 前綴的測試方法名稱可能會導致某些 linter 工具的警告，因為它違反了 PSR-1/PSR-12 的駝峰式命名規則
   - 解決方案：在 phpcs.xml 中配置忽略此規則，因為 Laravel 的最佳實踐是使用 `test_` 前綴
   ```xml
   <?xml version="1.0"?>
   <ruleset name="Laravel Standards">
       <description>The Laravel Coding Standards</description>

       <rule ref="PSR12">
           <exclude name="PSR1.Methods.CamelCapsMethodName.NotCamelCaps">
               <exclude-pattern>*/tests/*</exclude-pattern>
           </exclude>
       </rule>
   </ruleset>
   ```
   - 這樣可以保持 PSR-12 的所有規則檢查，但只忽略測試目錄中的方法命名規則

4. **元件參數的重複定義**：
   - 問題：在元件類別和視圖中都定義了相同的參數，可能導致混淆
   - 解決方案：在元件類別中定義參數，並在視圖中使用 `@props` 指令來接收這些參數
   ```php
   // 元件類別
   public function __construct(
       public bool $tertiary = false,
       public bool $padded = false,
   ) {}

   // 元件視圖
   @props(['tertiary' => false, 'padded' => false])
   ```

## 實際案例：SectionContainer 元件重構經驗

### 重構前後對比

原始 HTML 結構：
```blade
<div class="ts very padded horizontally fitted attached fluid tertiary segment">
    <div class="ts container">
        <!-- 內容 -->
    </div>
</div>
```

重構後的元件使用方式：
```blade
<x-section-container tertiary grid="relaxed stackable">
    <!-- 內容 -->
</x-section-container>
```

### 元件設計重點

1. **使用 @class 指令**：
   ```blade
   <div @class([
       'ts',
       'very padded',
       'horizontally fitted',
       'attached',
       'fluid',
       'segment',
       'secondary' => $secondary,
       'tertiary' => $tertiary,
   ])>
   ```
   - 使用 Laravel 的 @class 指令來管理複雜的類別邏輯
   - 提高程式碼可讀性和維護性
   - 自動處理條件類別的空格問題

2. **參數設計**：
   ```php
   public function __construct(
       public bool $secondary = false,
       public bool $tertiary = false,
       public string $grid = '',
       public bool $veryNarrow = false,
   )
   ```
   - 使用型別宣告增加程式碼可讀性
   - 提供合理的預設值
   - 使用描述性的參數名稱

3. **測試案例設計**：
   ```php
   class SectionContainerTest extends TestCase
   {
       public function test_renders_basic_container() { ... }
       public function test_renders_with_secondary_style() { ... }
       public function test_renders_with_tertiary_style() { ... }
       public function test_renders_with_grid() { ... }
       public function test_renders_with_relaxed_stackable_grid() { ... }
       public function test_renders_with_very_narrow_container() { ... }
       public function test_renders_with_slot_content() { ... }
       public function test_renders_with_combined_features() { ... }
       public function test_handles_empty_grid_string() { ... }
   }
   ```
   - 測試基本渲染功能
   - 測試各個修飾符的組合
   - 測試特殊情況（如空值處理）
   - 測試 slot 內容渲染

### 最佳實踐要點

1. **類別管理**：
   - 使用 @class 指令管理複雜的條件類別
   - 將固定的類別和條件類別分開管理
   - 保持類別順序的一致性

2. **參數設計**：
   - 優先使用布林值參數處理單一修飾符
   - 使用字串參數處理複雜的類別組合（如 grid）
   - 提供清晰的參數文檔說明

3. **測試策略**：
   - 確保每個參數組合都有對應的測試
   - 測試特殊情況和邊界條件
   - 使用描述性的測試方法名稱

4. **效能考量**：
   - 最小化 DOM 結構
   - 避免不必要的包裝元素
   - 使用 @class 指令優化類別處理

### 使用範例

基本用法：
```blade
<x-section-container>
    <!-- 內容 -->
</x-section-container>
```

使用修飾符：
```blade
<x-section-container tertiary>
    <!-- 內容 -->
</x-section-container>
```

使用 grid：
```blade
<x-section-container grid="relaxed stackable">
    <!-- 內容 -->
</x-section-container>
```

組合使用：
```blade
<x-section-container tertiary grid="relaxed stackable" veryNarrow>
    <!-- 內容 -->
</x-section-container>
```

### 重構核心原則

1. **一致性**：
   - 保持參數命名風格一致
   - 保持類別結構一致
   - 保持測試命名一致

2. **可維護性**：
   - 使用 @class 指令管理類別
   - 提供清晰的參數文檔
   - 編寫完整的測試案例

3. **使用者體驗**：
   - 提供直覺的參數名稱
   - 支援彈性的使用方式
   - 保持與原始 HTML 結構的相似性