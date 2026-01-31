---
name: reviewing-pull-requests
description: 執行完整的 GitHub Pull Request code review，包含取得 PR 資訊、切換到 merge result、分析程式碼、發佈批次 review comments。當使用者要求進行 PR review、code review，或提供 PR 編號/連結時使用。
---

# Pull Request Code Review

使用 gh CLI 和 Git 進行完整審查。Review 基於 merge result（目標 branch + PR 變更）。

## 基本原則

- 使用台灣正體中文回應
- 使用 TaskCreate、TaskUpdate、TaskList 工具管理審查進度
- 只專注於問題、改進建議和風險，不提及優點或正面評價

## 審查流程

複製此 checklist 並追蹤進度：

```
PR Review Progress:
- [ ] 步驟 1: 準備工作（fetch、status、stash）
- [ ] 步驟 2: 檢查 PR 狀態和 mergeable
- [ ] 步驟 3: 切換到 merge result 或 PR branch
- [ ] 步驟 4: 讀取和分析修改檔案
- [ ] 步驟 5: 取得準確行號
- [ ] 步驟 6: 發佈批次 Review
- [ ] 步驟 7: 發布 diff 範圍外的 review（如需要）
- [ ] 步驟 8: 完成審查
- [ ] 步驟 9: 返回原始分支
```

## 詳細步驟

### 步驟 1: 準備工作

```bash
git fetch
git status
git branch --show-current
```

如果有未提交的變更：
```bash
git stash
```

記錄當前分支名稱。

---

### 步驟 2: 檢查 PR 狀態

```bash
gh pr view <number> --json baseRefName,baseRefOid,headRefName,headRefOid,mergeable
```

記錄：
- `headRefOid`（步驟 6 作為 `commit_id`）
- `baseRefName`
- `mergeable`

如果 `mergeable` 為 `null`：
- 等待 5 秒後重新執行
- 仍為 `null` 則視為 `false`

---

### 步驟 3: 切換分支

**`mergeable` 為 `true`**
```bash
git fetch origin pull/<number>/merge:pr-<number>-merge
git checkout pr-<number>-merge
```

**`mergeable` 為 `false`**
```bash
gh pr checkout <number>

# 發布警告
gh pr review <number> --comment -b "$(cat <<'EOF'
⚠️ 此 PR 有 merge conflict，review 基於 PR branch。
EOF
)"
```

查看變更差異：
```bash
git diff origin/<baseRefName>...HEAD
```

---

### 步驟 4: 讀取和分析修改檔案

使用 Read 工具讀取所有修改檔案，並行調用一次讀取。

盡可能使用 LSP 工具獲取型別資訊、引用關係和函數簽名。

分析以下面向：
- **程式碼正確性**：如函數參數是否完整、型別標註是否正確、有無型別轉換風險
- **遵循專案規範**：如是否符合專案架構模式、一致性、風格、命名慣例（類別/函數/變數）
- **效能影響**：如是否有效能問題或可優化之處
- **測試涵蓋率**：如是否有對應的測試案例
- **安全性考量**：如是否有安全漏洞或風險

準備具體的改進建議。分析完成後如有疑問，使用 AskUserQuestion 工具。

---

### 步驟 5: 取得準確行號

GitHub review comments 需要的是**來源 branch（PR head）的行號**，而非 merge result 的行號。

使用 git grep 在來源 branch 搜尋程式碼行：

```bash
git grep -nF -C 3 "exact code line" origin/<headRefName> -- path/to/file.php
```

處理多處匹配：
- 增加上下文行數
- 使用更精確的搜尋字串
- 使用完整的函數簽名

如果 git grep 找不到，使用 GitHub API patch：

```bash
gh api repos/OWNER/REPO/pulls/NUMBER/files | jq -r '.[N].patch'
```

行號解析邏輯：
- `@@` 標頭格式：`@@ -老檔案 +新檔案 @@`
- `+行號` 是來源 branch 的實際行號
- `+` 前綴的行：行號 +1
- 空格前綴的行：行號 +1
- `-` 前綴的行：不增加行號

---

### 步驟 6: 發佈批次 Review

發佈 review：

```bash
gh api repos/OWNER/REPO/pulls/NUMBER/reviews --input - <<'EOF'
{
  "event": "COMMENT",
  "commit_id": "步驟 2 記錄的 headRefOid",
  "body": "共發現 N 個回饋：\n\n**嚴重問題** (X)\n- EMOJI 標題1\n\n**需要改進** (Y)\n- EMOJI 標題2",
  "comments": [
    {
      "path": "file.py",
      "line": 10,
      "side": "RIGHT",
      "body": "![等級](BADGE_URL)\nEMOJI **標題**\n\n說明\n\n建議"
    }
  ]
}
EOF
```

Badge URL 格式：`https://img.shields.io/badge/<等級>-<顏色>?style=for-the-badge`
- 嚴重問題 → red
- 需要改進 → orange
- 建議優化 → blue

關鍵技術細節：
- `commit_id` 使用步驟 2 的 `headRefOid`（不是 `baseRefOid`）
- HEREDOC 使用單引號 `'EOF'` 形式
- 內容不要跳脫（不要用 `\"` 或 `\``），以使特殊字元在 GitHub 正確顯示
- Line comments 只能在 diff 範圍內，否則 HTTP 422 錯誤

---

### 步驟 7: 發布 diff 範圍外的 review

僅在 line comment 無法表達時使用：

```bash
gh pr review <NUMBER> --comment -b "$(cat <<'EOF'
審查內容
EOF
)"
```

---

### 步驟 8: 完成審查

提供審查摘要：
- 總共發佈的 comments 數量
- 按嚴重度分類統計

---

### 步驟 9: 返回原始分支

```bash
git checkout <步驟 1 記錄的原始分支>
```

如果步驟 1 有執行 stash：
```bash
git stash pop
```

---

## 驗證原則

- **查證而非猜測**：能查證的必須查證
  - 使用 Grep 查找定義
  - 使用 Read 閱讀相關實作
  - 必要時使用 podman 或其他工具測試
- **量化而非模糊**：提供具體數字、影響範圍、問題發生條件，避免「需要確認」等模糊表述
- **誠實標示**：明確區分三種類型的意見
  - 查證事實：已驗證的問題
  - 主觀建議：基於經驗的建議
  - 無法驗證：需要進一步確認的問題
- **修正前確認**：任何修正或刪除操作（如 comment、code、config）前必須先詢問使用者確認
