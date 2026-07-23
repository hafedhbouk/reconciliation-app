# Core Workflow Sequences

Three flows that best illustrate how the pieces fit together. All three are
covered by automated tests (`ProcessImportJobTest`, `RuleMatcherTest`,
`ReconciliationTest`, `NotificationTest`) and were manually verified against
real bank/payment export files.

## 1. Import flow

Upload a file, validate its columns against the source's saved mapping,
process it in a chunked queued job, notify the uploader when it's done.

```mermaid
sequenceDiagram
    actor Admin
    participant UI as ImportController
    participant Engine as MappingEngine
    participant Queue as Queue (database)
    participant Job as ProcessImportJob
    participant DB as MySQL
    participant Notif as ImportProcessedNotification

    Admin->>UI: POST /admin/imports (source_id, file)
    UI->>UI: hash file, check for a prior identical import
    UI->>Engine: validateHeaders(file headers, required mappings)
    alt required columns missing
        Engine-->>UI: missing column names
        UI-->>Admin: redirect to Sources → mappings (fix required)
    else headers satisfy the mapping
        UI->>DB: create Import (status=pending)
        UI->>Queue: dispatch ProcessImportJob(importId)
        UI-->>Admin: redirect to import show page
        Queue->>Job: handle()
        Job->>DB: update Import (status=processing, started_at)
        loop each chunk (config('imports.chunk_size'))
            Job->>Engine: transformRow() per row (try/catch per row)
            Job->>DB: bulk insert import_rows / transactions / normalized_transactions
            Job->>DB: update Import running totals
        end
        Job->>DB: update Import (status=completed/failed/partially_completed, finished_at)
        Job->>Notif: notify(import.importedByUser)
        Notif->>DB: insert notifications row
    end
```

## 2. Matching flow ("Lancer tout")

Runs every active rule in priority order (rules cumulatively consume the
shared unmatched pool, so order matters), then duplicate detection, then
the unmatched sweep, then one aggregate notification.

```mermaid
sequenceDiagram
    actor Admin
    participant UI as MatchingRuleController
    participant Chain as Bus::chain
    participant RMJ as RunMatchingRuleJob (× active rules)
    participant DDJ as DetectDuplicatesJob
    participant SUJ as SweepUnmatchedJob
    participant NBJ as NotifyMatchingBatchCompleteJob
    participant Matcher as RuleMatcher
    participant DB as MySQL

    Admin->>UI: POST /admin/matching-rules/run-all
    UI->>Chain: dispatch([RMJ×N ordered by priority, DDJ, SUJ, NBJ])
    loop each active rule, in priority order
        Chain->>RMJ: handle()
        RMJ->>Matcher: match(rule, batchReference)
        Matcher->>DB: load unmatched candidates per side, group by reference
        Matcher->>Matcher: 3-way tolerance branch per reference group
        alt exact multiset or sum+date-spread match
            Matcher->>DB: create MatchingResult + MatchingDetails, mark rows matched
        else exactly one of amount/date within tolerance
            Matcher->>DB: create MatchingResult(conflict) + MatchingDetails + ExceptionRecord
        else neither matches
            Matcher->>Matcher: do nothing (likely reference collision, not a real relationship)
        end
    end
    Chain->>DDJ: handle() → DuplicateDetector scans dedup_hash collisions
    Chain->>SUJ: handle() → UnmatchedSweeper flags every still-unmatched row
    Chain->>NBJ: handle() → aggregate counts for batchReference
    NBJ->>Admin: notify (one summary, not one per rule)
```

## 3. Manual reconciliation flow

For whatever the automated rules can't resolve — an operator searches both
sides independently and links a specific set of rows by hand.

```mermaid
sequenceDiagram
    actor Operator
    participant UI as ReconciliationController
    participant DB as MySQL

    Operator->>UI: GET /admin/reconciliation (two search panels)
    Operator->>UI: GET /admin/reconciliation/search?side=a&... (repeat for side=b)
    UI->>DB: NormalizedTransaction::where(matching_status=unmatched)->where(filters)
    DB-->>Operator: paginated results per side
    Operator->>Operator: selects rows on both sides
    Operator->>UI: POST /admin/reconciliation (ids_a[], ids_b[])
    UI->>UI: validate: ≥1 per side, no id on both sides, every id still unmatched
    UI->>DB: create MatchingResult(matching_rule_id=null, matched_by=operator, status=matched)
    UI->>DB: create MatchingDetail per selected row (side a/b)
    UI->>DB: update matching_status=matched on every selected row
    UI-->>Operator: redirect to the new MatchingResult
```
