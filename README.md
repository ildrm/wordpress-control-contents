# Universal WordPress Content Moderation Platform

A local-first content policy and moderation platform in active development. **This is a development preview, not the completed production product.** The distribution header is Universal Content Moderation.

Implemented: immutable moderation models, Unicode/Persian/Arabic normalization, indexed term matching with scoped exceptions and review-grade evasion evidence, nested policy rules, shadow mode, versioned transactional policies, metadata-only audit, WordPress post/comment/REST adapters, native policy/playground/audit screens, registered-user privacy callbacks and bounded retention.

Requires PHP 8.2+, intl/mbstring/dom; intended WordPress floor 6.8. Tested stacks: WordPress 6.9.4/PHP 8.2.31 and WordPress 7.1.2/PHP 8.2.34, MariaDB 11.4.13. No external processing or telemetry. Default shadow mode changes no content status when its policy can be loaded. Only pending review is currently enforced; original text is preserved.

## Development

Install dependencies with composer install and npm ci --ignore-scripts. Run composer test, composer analyse, composer lint, composer evaluate, composer benchmark and composer package. See [testing](docs/TESTING.md) for the isolated Docker and browser suite. No runtime JavaScript/vendor dependencies are shipped.

## Implementation and release records

- [Role-based review and fixes](docs/REVIEW-FIXES.md)
- [Implementation state and exact next task](docs/IMPLEMENTATION-STATE.md)
- [Every master requirement](docs/REQUIREMENTS.md) and [original specification](docs/MASTER-SPECIFICATION.md)
- [Architecture](docs/ARCHITECTURE.md), [security](docs/SECURITY.md), [privacy](docs/PRIVACY.md)
- [Performance measurements](docs/PERFORMANCE.md), [accuracy limitations](docs/MODERATION-ACCURACY.md)
- [Integration coverage](docs/INTEGRATIONS.md), [API](docs/API.md), [database](docs/DATABASE.md)
- [UX/accessibility](docs/UX.md), [release blockers](docs/RELEASE.md), [risk register](docs/RISK-REGISTER.md)

Full operations workflow, visual rule builder, third-party integrations, reputation/strikes/reports/appeals, queue/historical scans, AI/media/OCR, analytics, network/enterprise/cloud modules and production acceptance gates remain outstanding. The low-level comment insertion visibility gap is an explicit release blocker. Never deploy this preview as a complete production moderation system.
