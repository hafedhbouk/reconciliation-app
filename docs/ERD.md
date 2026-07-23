# Entity-Relationship Diagram

Built across Phases 1-3; every table below already existed by the end of
Phase 1 except `source_column_mappings` (Phase 2). Standard Laravel
`sessions`/`password_reset_tokens`/`jobs`/`job_batches`/`failed_jobs`/`cache`/
`cache_locks` tables and Spatie's `roles`/`permissions`/`model_has_roles`/
`model_has_permissions`/`role_has_permissions` tables are omitted — they're
framework/package-owned schema, not part of this app's domain model.

Every domain table also carries `created_at`/`updated_at`,
`deleted_at` (soft deletes), and `created_by`/`updated_by` (nullable FKs to
`users`) unless noted otherwise — omitted from the diagram for readability.

```mermaid
erDiagram
    BANKS ||--o{ SOURCES : "has"
    CURRENCIES ||--o{ SOURCES : "default for"
    SOURCES ||--o{ SOURCE_COLUMN_MAPPINGS : "configures"
    SOURCES ||--o{ IMPORTS : "receives"
    SOURCES ||--o{ TRANSACTIONS : "produces"
    SOURCES ||--o{ MATCHING_RULES : "source_a"
    SOURCES ||--o{ MATCHING_RULES : "source_b"
    BANKS ||--o{ IMPORTS : "for"
    BANKS ||--o{ TRANSACTIONS : "for"
    CURRENCIES ||--o{ TRANSACTIONS : "denominates"
    USERS ||--o{ IMPORTS : "imported_by"
    IMPORTS ||--o{ IMPORT_ROWS : "contains"
    IMPORTS ||--o{ TRANSACTIONS : "produces"
    IMPORT_ROWS ||--o| TRANSACTIONS : "yields"
    TRANSACTIONS ||--|| NORMALIZED_TRANSACTIONS : "normalizes to"
    MATCHING_RULES ||--o{ MATCHING_RESULTS : "produces"
    USERS ||--o{ MATCHING_RESULTS : "matched_by (manual only)"
    MATCHING_RESULTS ||--o{ MATCHING_DETAILS : "contains"
    NORMALIZED_TRANSACTIONS ||--o{ MATCHING_DETAILS : "participates in"
    NORMALIZED_TRANSACTIONS ||--o{ EXCEPTIONS : "flagged as (row-level)"
    MATCHING_RESULTS ||--o{ EXCEPTIONS : "flagged as (group-level, conflicts)"
    EXCEPTIONS ||--o{ EXCEPTION_ATTACHMENTS : "has"
    USERS ||--o{ EXCEPTIONS : "assigned_to / resolved_by"
    USERS ||--o{ AUDIT_LOGS : "acted"
    USERS ||--o{ NOTIFICATIONS : "receives"

    BANKS {
        bigint id PK
        string code UK
        string name
        string swift_code
        boolean is_active
    }
    CURRENCIES {
        bigint id PK
        char iso_code UK
        string name
        tinyint decimal_places
    }
    SOURCES {
        bigint id PK
        string code UK "ALPHA/BNA/WEB/SMT/STEG"
        string name
        bigint bank_id FK
        string file_type "csv/xls/xlsx"
        bigint default_currency_id FK
        boolean is_active
        json config "e.g. csv_delimiter"
    }
    SOURCE_COLUMN_MAPPINGS {
        bigint id PK
        bigint source_id FK
        string target_field "MappingTargetField enum"
        string source_column "literal file header"
        json transform "ordered transform-step array"
        boolean is_required
    }
    HOLIDAYS {
        bigint id PK
        date holiday_date
        string country_code
    }
    SETTINGS {
        bigint id PK
        string group
        string key
        json value
    }
    USERS {
        bigint id PK
        string name
        string email UK
        string password
        boolean is_active
        timestamp last_login_at
    }
    IMPORTS {
        bigint id PK
        bigint source_id FK
        bigint bank_id FK
        string original_filename
        string file_hash "sha256, dedup detection"
        string status "ImportStatus enum"
        int total_rows
        int success_rows
        int error_rows
        bigint imported_by FK
    }
    IMPORT_ROWS {
        bigint id PK
        bigint import_id FK
        int row_number
        json raw_data
        json transformed_data
        json normalized_data
        string status "ImportRowStatus enum"
    }
    TRANSACTIONS {
        bigint id PK
        bigint import_id FK
        bigint import_row_id FK
        bigint source_id FK
        bigint bank_id FK
        bigint currency_id FK
        string external_reference
        date transaction_date
        bigint amount_millimes
        string canal
        json raw_payload "full transformed row, incl. auxiliary fields"
    }
    NORMALIZED_TRANSACTIONS {
        bigint id PK
        bigint transaction_id FK UK "1:1 with transactions"
        string normalized_reference
        bigint normalized_amount_millimes
        date normalized_date
        string dedup_hash
        string matching_status "MatchingStatus enum"
    }
    MATCHING_RULES {
        bigint id PK
        string name
        bigint source_a_id FK
        bigint source_b_id FK
        string cardinality "descriptive only, never enforced"
        int priority
        boolean is_active
        json criteria "tolerance_amount_millimes, tolerance_days, excluded_status_raw"
    }
    MATCHING_RESULTS {
        bigint id PK
        bigint matching_rule_id FK "null = manual match"
        string batch_reference
        string status "MatchingResultStatus enum"
        decimal confidence_score
        bigint matched_by FK "null = automatic"
        timestamp matched_at
        text notes "cardinality-mismatch note, if any"
    }
    MATCHING_DETAILS {
        bigint id PK
        bigint matching_result_id FK
        bigint normalized_transaction_id FK
        string side "a or b"
    }
    EXCEPTIONS {
        bigint id PK
        bigint normalized_transaction_id FK "row-level (unmatched/duplicate)"
        bigint matching_result_id FK "group-level (conflicts)"
        string type "ExceptionType enum"
        string status "ExceptionStatus enum"
        bigint assigned_to FK
        text resolution_comment
        bigint resolved_by FK
        timestamp resolved_at
    }
    EXCEPTION_ATTACHMENTS {
        bigint id PK
        bigint exception_id FK
        string path
        string original_name
        bigint uploaded_by FK
    }
    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        string event
        string auditable_type "morph"
        bigint auditable_id "morph"
        json old_values
        json new_values
    }
    NOTIFICATIONS {
        uuid id PK
        string type
        string notifiable_type "morph, always User here"
        bigint notifiable_id "morph"
        json data
        timestamp read_at
    }
```

## Notes on relationships that look unusual

- **`normalized_transactions.transaction_id` is unique** — a strict 1:1 with
  `transactions`. The two tables exist separately because `transactions`
  holds the raw imported shape (incl. auxiliary fields in `raw_payload`)
  while `normalized_transactions` holds only the 3 fields the matching
  engine actually keys on (reference, amount, date) plus matching state —
  keeping the matching engine's hot-path table narrow.
- **`exceptions` has two mutually-exclusive nullable FKs**
  (`normalized_transaction_id` for a single row, `matching_result_id` for a
  whole matched/conflicting group). A conflict spanning multiple rows on
  both sides couldn't be expressed by a single-row FK alone — see
  `docs/SEQUENCES.md`'s matching flow for how a conflict actually gets
  created via `matching_results`/`matching_details` (the same mechanism a
  real match uses) plus one exception pointing at the group.
- **`matching_rule_sources`** (not shown as a distinct table above beyond
  the `SOURCES ||--o{ MATCHING_RULES` edges) is a pivot table reserved in
  Phase 1 for future N-way matching rules. It exists in the schema but is
  never written to — Phase 3 deliberately kept rules strictly pairwise
  (`source_a_id`/`source_b_id` columns directly on `matching_rules`).
