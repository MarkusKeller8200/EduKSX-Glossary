# Datenklassen je Spalte

Erzeugt aus `app/Data/DataClasses.php` mit `php artisan glossary:datenklassen` – nicht von Hand ändern.
Regeln nach dem DB-Standard (B2): In der Historie (`data_changes`) stehen Werte nur bei den Klassen öffentlich
und intern, sonst nur «changed». Abbilder (Revisionen) enthalten nur Felder der Klassen öffentlich und intern.

| Klasse | In der Historie | Beim Löschen der Person |
| --- | --- | --- |
| öffentlich | alter und neuer Wert | bleibt |
| intern | alter und neuer Wert | bleibt |
| personenbezogen | nur «changed» | Bezug pseudonymisieren |
| besonders schützenswert | nur «changed» | sofort löschen |
| geheim | nur «changed» | löschen |

## `categories`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `key` | öffentlich |
| `abbreviation` | öffentlich |
| `name` | öffentlich |
| `label` | öffentlich |
| `description` | öffentlich |
| `url` | öffentlich |
| `note` | intern |
| `color` | öffentlich |
| `sort_order` | öffentlich |
| `created_at` | intern |
| `updated_at` | intern |
| `lock_version` | intern |
| `deleted_at` | intern |
| `deleted_by_id` | intern |

## `category_translations`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `category_id` | intern |
| `locale` | öffentlich |
| `name` | öffentlich |
| `label` | öffentlich |
| `description` | öffentlich |
| `created_at` | intern |
| `updated_at` | intern |

## `data_changes`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `event_id` | intern |
| `subject_type` | intern |
| `subject_id` | intern |
| `organization_id` | intern |
| `action` | intern |
| `changes` | intern |
| `actor_type` | intern |
| `actor_id` | intern |
| `actor_label` | personenbezogen |
| `source` | intern |
| `transaction_id` | intern |
| `request_id` | intern |
| `schema_version` | intern |
| `occurred_at` | intern |

## `duplicate_decisions`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `signature` | intern |
| `match_type` | intern |
| `value` | intern |
| `term_ids` | intern |
| `decided_by_id` | intern |
| `created_at` | intern |

## `import_batches`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `user_id` | intern |
| `user_label` | personenbezogen |
| `file_name` | intern |
| `format_version` | intern |
| `rows` | intern |
| `created_count` | intern |
| `updated_count` | intern |
| `error_count` | intern |
| `errors` | intern |
| `created_at` | intern |

## `organization_invitations`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `organization_id` | intern |
| `email` | personenbezogen |
| `invited_by_id` | intern |
| `expires_at` | intern |
| `created_at` | intern |

## `organization_members`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `organization_id` | intern |
| `user_id` | intern |
| `role` | intern |
| `status` | intern |
| `is_role_granted` | intern |
| `created_at` | intern |
| `updated_at` | intern |

## `organization_term_overrides`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `organization_id` | intern |
| `term_id` | intern |
| `abbreviation_override` | intern |
| `name_override` | intern |
| `short_description_override` | intern |
| `description_override` | intern |
| `aliases_override` | intern |
| `updated_by_id` | intern |
| `created_at` | intern |
| `updated_at` | intern |

## `organizations`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `platform_id` | intern |
| `name` | intern |
| `created_by_id` | intern |
| `created_at` | intern |
| `updated_at` | intern |
| `entra_tenant_id` | intern |
| `lock_version` | intern |
| `deleted_at` | intern |
| `deleted_by_id` | intern |

## `proposal_events`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `event_id` | intern |
| `proposal_id` | intern |
| `event_type` | intern |
| `actor_id` | intern |
| `actor_label` | personenbezogen |
| `is_by_author` | intern |
| `message` | personenbezogen |
| `details` | intern |
| `created_at` | intern |

## `proposals`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `uuid` | intern |
| `proposal_type` | intern |
| `term_id` | intern |
| `base_revision` | intern |
| `changes` | intern |
| `reason` | personenbezogen |
| `status` | intern |
| `user_id` | intern |
| `service_account_id` | intern |
| `organization_id` | intern |
| `author_label` | personenbezogen |
| `result_term_id` | intern |
| `result_revision` | intern |
| `resolved_at` | intern |
| `created_at` | intern |
| `updated_at` | intern |

## `publication_releases`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `term_id` | intern |
| `action` | öffentlich |
| `checksum` | öffentlich |
| `term_count` | öffentlich |
| `published_by_id` | intern |
| `created_at` | intern |

## `received_events`

| Spalte | Klasse |
| --- | --- |
| `event_id` | intern |
| `type` | intern |
| `source` | intern |
| `received_at` | intern |

## `role_permissions`

| Spalte | Klasse |
| --- | --- |
| `role_id` | intern |
| `permission` | intern |

## `roles`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `key` | intern |
| `name` | intern |
| `description` | intern |
| `is_system` | intern |
| `sort_order` | intern |
| `created_at` | intern |
| `updated_at` | intern |

## `search_misses`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `query` | intern |
| `count` | intern |
| `first_seen_at` | intern |
| `last_seen_at` | intern |

## `service_account_audit`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `event_id` | intern |
| `service_account_id` | intern |
| `token_id` | intern |
| `actor_id` | intern |
| `actor_label` | personenbezogen |
| `action` | intern |
| `details` | intern |
| `result` | intern |
| `created_at` | intern |

## `service_account_tokens`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `service_account_id` | intern |
| `name` | intern |
| `token_hash` | geheim |
| `prefix` | intern |
| `expires_at` | intern |
| `last_used_at` | intern |
| `revoked_at` | intern |
| `created_at` | intern |
| `updated_at` | intern |

## `service_accounts`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `name` | intern |
| `purpose` | intern |
| `responsible_user_id` | intern |
| `organization_id` | intern |
| `status` | intern |
| `scopes` | intern |
| `created_at` | intern |
| `updated_at` | intern |

## `settings`

| Spalte | Klasse |
| --- | --- |
| `key` | intern |
| `value` | intern |
| `updated_by_id` | intern |
| `updated_at` | intern |

## `source_translations`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `source_id` | intern |
| `locale` | öffentlich |
| `name` | öffentlich |
| `label` | öffentlich |
| `description` | öffentlich |
| `created_at` | intern |
| `updated_at` | intern |

## `sources`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `key` | öffentlich |
| `abbreviation` | öffentlich |
| `name` | öffentlich |
| `label` | öffentlich |
| `description` | öffentlich |
| `url` | öffentlich |
| `note` | intern |
| `color` | öffentlich |
| `sort_order` | öffentlich |
| `created_at` | intern |
| `updated_at` | intern |
| `lock_version` | intern |
| `deleted_at` | intern |
| `deleted_by_id` | intern |

## `term_aliases`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `term_id` | intern |
| `alias` | öffentlich |
| `alias_type` | öffentlich |
| `language` | öffentlich |

## `term_categories`

| Spalte | Klasse |
| --- | --- |
| `term_id` | intern |
| `category_id` | intern |

## `term_publication_events`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `event_id` | intern |
| `term_id` | intern |
| `action` | öffentlich |
| `revision` | öffentlich |
| `release_id` | intern |
| `merged_into_id` | intern |
| `actor_type` | intern |
| `actor_label` | personenbezogen |
| `actor_id` | intern |
| `created_at` | intern |

## `term_relations`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `term_id` | intern |
| `related_term_id` | intern |
| `relation_type` | öffentlich |
| `created_at` | intern |
| `updated_at` | intern |

## `term_revisions`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `term_id` | intern |
| `revision` | öffentlich |
| `change_type` | intern |
| `snapshot` | öffentlich |
| `change_set` | intern |
| `actor_type` | intern |
| `context` | intern |
| `author_id` | intern |
| `author_label` | personenbezogen |
| `approved_by_id` | intern |
| `published_at` | öffentlich |
| `created_at` | intern |

## `term_sources`

| Spalte | Klasse |
| --- | --- |
| `term_id` | intern |
| `source_id` | intern |
| `retrieved_on` | intern |
| `note` | intern |
| `evidence` | intern |

## `term_translations`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `term_id` | intern |
| `locale` | öffentlich |
| `abbreviation` | öffentlich |
| `name` | öffentlich |
| `short_description` | öffentlich |
| `description` | öffentlich |
| `created_at` | intern |
| `updated_at` | intern |

## `term_user_overrides`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `term_id` | intern |
| `user_id` | intern |
| `abbreviation_override` | personenbezogen |
| `name_override` | personenbezogen |
| `short_description_override` | personenbezogen |
| `description_override` | personenbezogen |
| `aliases_override` | personenbezogen |
| `approved_organization_id` | intern |
| `approved_by_id` | intern |
| `approved_at` | intern |
| `created_at` | intern |
| `updated_at` | intern |

## `terms`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `slug` | öffentlich |
| `code` | öffentlich |
| `official_abbreviation` | öffentlich |
| `official_name` | öffentlich |
| `short_description` | öffentlich |
| `description` | öffentlich |
| `note` | intern |
| `url` | öffentlich |
| `lifecycle_status` | öffentlich |
| `is_active` | öffentlich |
| `valid_from` | öffentlich |
| `valid_until` | öffentlich |
| `revision` | öffentlich |
| `published_revision_id` | intern |
| `merged_into_id` | intern |
| `merged_at` | öffentlich |
| `created_at` | intern |
| `updated_at` | intern |
| `lock_version` | intern |

## `user_audit`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `event_id` | intern |
| `actor_id` | intern |
| `actor_label` | personenbezogen |
| `target_user_id` | intern |
| `target_role_id` | intern |
| `action` | intern |
| `old` | personenbezogen |
| `new` | personenbezogen |
| `result` | intern |
| `created_at` | intern |

## `user_identities`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `user_id` | intern |
| `provider` | intern |
| `issuer` | personenbezogen |
| `subject` | personenbezogen |
| `tenant_id` | personenbezogen |
| `last_used_at` | personenbezogen |
| `created_at` | intern |
| `updated_at` | intern |

## `user_roles`

| Spalte | Klasse |
| --- | --- |
| `user_id` | intern |
| `role_id` | intern |

## `users`

| Spalte | Klasse |
| --- | --- |
| `id` | intern |
| `platform_id` | intern |
| `name` | personenbezogen |
| `first_name` | personenbezogen |
| `last_name` | personenbezogen |
| `email` | personenbezogen |
| `email_verified_at` | personenbezogen |
| `password` | geheim |
| `has_password` | intern |
| `remember_token` | geheim |
| `two_factor_secret` | geheim |
| `two_factor_recovery_codes` | geheim |
| `two_factor_confirmed_at` | intern |
| `two_factor_last_step` | intern |
| `status` | intern |
| `blocked_at` | personenbezogen |
| `community_name` | personenbezogen |
| `function` | personenbezogen |
| `institution_type` | intern |
| `institution_name` | personenbezogen |
| `last_login_on` | personenbezogen |
| `privacy_notice_version` | intern |
| `privacy_notice_acknowledged_at` | personenbezogen |
| `inactivity_notice_sent_at` | personenbezogen |
| `has_news_opt_in` | personenbezogen |
| `news_answered_at` | personenbezogen |
| `created_at` | intern |
| `updated_at` | intern |
