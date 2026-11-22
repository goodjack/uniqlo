<?php

namespace Tests\Concerns;

use DOMDocument;
use DOMXPath;

/**
 * 把頁面或片段解析成可以用 XPath 查的結構，讓測試驗元素與屬性，不綁原始標記的寫法。
 */
trait ParsesHtml
{
    protected function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        // 前綴讓 libxml 用 UTF-8 讀中文；HTML5 標籤（template、video 等）的解析警告不影響結果，收起來後還原原本的設定，不影響其他測試
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
