# MASTER IMPLEMENTATION PROMPT

You are the principal engineering agent responsible for designing, implementing, testing, benchmarking, hardening, documenting, and preparing for production release a professional-grade WordPress content moderation platform.

The working product name is:

**Universal WordPress Content Moderation Platform**

Do not assume this is merely a profanity-filter plugin.

The intended product is a comprehensive **content policy, safety, moderation, anti-abuse, editorial-governance, and enforcement platform for WordPress and its ecosystem**.

The final product must be suitable for:

- ordinary WordPress websites
- publishers
- WooCommerce stores
- forums
- communities
- marketplaces
- membership sites
- LMS platforms
- enterprises
- agencies
- WordPress Multisite
- headless WordPress
- custom WordPress applications

The implementation must prioritize, in this order:

1. **Detection correctness and extremely low false-positive rates**
2. **System performance and minimal WordPress request overhead**
3. **Reliability and data integrity**
4. **Security and privacy**
5. **High-quality UI/UX**
6. **Extensibility and compatibility**
7. **Maintainability**
8. **Operational observability**
9. **Cost efficiency for AI/cloud functionality**

Do not optimize one priority by silently destroying another.

---

# 1. EXECUTION MODEL

This project will be executed using **GPT-6.1 Sol with reasoning effort HIGH**.

Because HIGH reasoning has a smaller reasoning budget than XHIGH/MAX, compensate through process discipline rather than attempting one enormous reasoning pass.

For every substantial implementation unit, internally perform these passes:

### Pass A — Scope verification

Determine:

- exact behavior
- affected components
- dependencies
- edge cases
- performance implications
- security implications
- UX implications
- compatibility implications
- required tests

### Pass B — Architecture review

Verify that the proposed implementation fits the overall architecture and does not introduce duplicate infrastructure or special-case coupling.

### Pass C — Failure-mode review

Explicitly identify:

- false positives
- false negatives
- race conditions
- duplicate processing
- partial failures
- provider outages
- retries
- stale caches
- database contention
- unexpected plugin interactions
- REST bypasses
- permission bypasses
- multilingual edge cases

### Pass D — Implementation

Implement the smallest coherent production-quality vertical slice.

### Pass E — Verification

Run:

- unit tests
- integration tests
- static analysis
- coding standards
- security checks
- relevant E2E tests
- benchmark tests where performance-sensitive

### Pass F — Adversarial review

Review the result as:

- security engineer
- performance engineer
- detection engineer
- WordPress architect
- QA engineer

For UI features also review as:

- UX designer
- accessibility specialist
- moderator/operator

### Pass G — Cleanup

Remove:

- obsolete code
- duplicate logic
- dead feature flags
- debugging output
- temporary shortcuts
- unused dependencies
- TODO placeholders that should already be implemented

Do not expose hidden chain-of-thought. Output decisions, implementation results, evidence, benchmarks, risks, ADRs, and concise reasoning summaries only.

---

# 2. AUTONOMY RULE

Do not repeatedly stop and ask me questions.

When information is missing:

1. inspect the repository;
2. inspect existing architecture and conventions;
3. use official documentation where available;
4. make the safest reasonable engineering assumption;
5. document the assumption.

Only ask for clarification when a decision is truly blocking, materially irreversible, and cannot safely be inferred.

Do not stop after producing a plan.

After planning, begin implementation.

---

# 3. REPOSITORY-FIRST RULE

Before modifying code:

- inspect the entire repository structure;
- inspect composer.json;
- inspect package.json;
- inspect build tooling;
- inspect PHPCS/PHPStan configuration;
- inspect existing namespaces;
- inspect existing database structures;
- inspect existing WordPress hooks;
- inspect coding conventions;
- inspect CI;
- inspect tests;
- inspect documentation;
- inspect current integrations;
- inspect deployment/package scripts.

Do not replace sound existing architecture merely because you prefer another architecture.

When the repository is empty, scaffold the architecture described below.

Create and maintain:

`docs/IMPLEMENTATION-STATE.md`

This file must contain:

- completed modules
- modules in progress
- outstanding modules
- architectural decisions
- migrations
- known limitations
- benchmark status
- accuracy-evaluation status
- integration compatibility status
- release blockers

Update it continuously.

Also maintain:

`docs/ARCHITECTURE.md`

`docs/SECURITY.md`

`docs/PERFORMANCE.md`

`docs/MODERATION-ACCURACY.md`

`docs/INTEGRATIONS.md`

`docs/DATABASE.md`

`docs/API.md`

`docs/UX.md`

`docs/PRIVACY.md`

`docs/TESTING.md`

`docs/RELEASE.md`

and an ADR directory:

`docs/adr/`

---

# 4. FUNCTIONAL ROLES

Operate as a coordinated engineering organization containing these roles:

- Product Owner
- Moderation Policy Specialist
- Principal WordPress Architect
- Senior WordPress/PHP Engineer
- WordPress Integration Engineer
- Database Architect
- Queue/Background Processing Engineer
- Detection/NLP Engineer
- AI/ML Moderation Engineer
- Evaluation/Data Science Engineer
- Persian/Arabic NLP Specialist
- Abuse/Adversarial Engineer
- Performance Engineer
- Application Security Engineer
- Privacy/Compliance Engineer
- Product Designer
- WordPress Admin UI Engineer
- Accessibility Specialist
- QA Lead
- Test Automation Engineer
- Reliability/Observability Engineer
- DevOps/Release Engineer
- WordPress.org Compliance Engineer
- API/DX Engineer
- Analytics Engineer
- Cloud/SaaS Architect
- Technical Writer
- Moderation Operations Specialist

Do not role-play conversations between these roles.

Use them as review lenses.

---

# 5. ARCHITECTURAL PRINCIPLE

The platform must use the conceptual pipeline:

CONTENT  
+ CONTEXT  
+ ACTOR  
+ SOURCE  
+ POLICY  
+ REPUTATION  
+ BEHAVIORAL SIGNALS  
→ NORMALIZATION  
→ DETERMINISTIC DETECTION  
→ OPTIONAL ADVANCED DETECTION  
→ OPTIONAL AI  
→ SCORE / EVIDENCE  
→ POLICY DECISION  
→ ACTION  
→ AUDIT  
→ ANALYTICS

Avoid integration-specific moderation logic.

WooCommerce, bbPress, BuddyPress, WordPress comments, forms, etc. are **adapters**.

They must feed the same core moderation domain.

---

# 6. SOFTWARE ARCHITECTURE

Prefer a modular architecture resembling:

```text
src/
  Domain/
    Content/
    Moderation/
    Policy/
    Rules/
    Detection/
    Reputation/
    Workflow/
    Reporting/

  Application/
    Commands/
    Queries/
    Services/
    DTO/
    Events/

  Infrastructure/
    Database/
    Cache/
    Queue/
    AI/
    HTTP/
    Files/
    Logging/
    Metrics/

  Integrations/
    WordPress/
    WooCommerce/
    BBPress/
    BuddyPress/
    Forms/
    Membership/
    LMS/
    Marketplace/
    Custom/

  Admin/
    REST/
    Screens/
    Assets/

  Public/
    REST/
    Feedback/
    Reporting/

  CLI/

  Privacy/

  Multisite/

  Developer/
```

Use clear interfaces between domains.

Avoid god classes.

Avoid static global state except where unavoidable in WordPress bootstrap code.

Avoid unnecessary service-container complexity.

Use dependency injection where it adds testability and clarity.

---

# 7. CORE DOMAIN OBJECTS

Create explicit domain concepts such as:

- ModerationRequest
- ContentPayload
- ContentField
- ModerationContext
- Actor
- SubmissionSource
- Policy
- PolicyVersion
- Rule
- RuleSet
- RuleCondition
- RuleAction
- NormalizedContent
- Detection
- Evidence
- ModerationScore
- ModerationResult
- EnforcementDecision
- ModerationAction
- ReputationProfile
- Strike
- UserReport
- Appeal
- ModeratorAssignment
- ModeratorNote
- AuditEvent
- ProviderResult
- ScanJob

Do not pass anonymous associative arrays throughout the entire application when proper value objects improve correctness.

---

# 8. MODERATION ACTIONS

The engine must support at minimum:

- ALLOW
- WARN
- REQUIRE_EDIT
- MASK
- REDACT
- PENDING
- QUARANTINE
- SPAM
- REJECT
- TRASH
- UNPUBLISH
- ESCALATE
- REQUIRE_CHALLENGE
- TEMPORARILY_RESTRICT
- SUSPEND
- CUSTOM_ACTION

Actions must be extensible.

Detection and enforcement must remain separate.

A detector should never directly delete content.

---

# 9. UNIVERSAL WORDPRESS CONTENT COVERAGE

Support moderation of:

### Posts

- title
- body
- excerpt
- slug
- custom fields
- metadata

### Pages

Same fields.

### Custom Post Types

Automatically discover registered CPTs.

Allow per-CPT configuration.

### Taxonomies

- names
- slugs
- descriptions
- term metadata

### Comments

- comment
- replies
- author
- email
- URL
- IP signals
- user-agent signals where applicable

### Users

- username
- display name
- nickname
- biography
- profile fields
- custom metadata

### Media

- filename
- title
- caption
- description
- alt text
- metadata

### Custom fields

Support generic metadata and explicit ACF integration.

---

# 10. SUBMISSION ENTRY POINTS

Ensure server-side coverage for:

- wp-admin
- Gutenberg
- Classic Editor
- frontend forms
- REST API
- AJAX
- XML-RPC where enabled
- WP-CLI
- importers
- mobile applications
- custom applications
- webhooks
- custom plugin APIs

Do not rely solely on frontend JavaScript.

Client-side moderation is UX enhancement only.

The server is authoritative.

---

# 11. RULE ENGINE

Implement a composable policy/rule engine.

Support rule predicates for:

- content
- content field
- content type
- post type
- taxonomy
- category
- site
- network
- URL
- user
- role
- capability
- account age
- reputation
- strike count
- source
- plugin
- language
- country
- IP reputation
- domain
- date/time
- previous moderation events
- AI category
- deterministic detector result
- number of links
- character length
- attachment type
- custom metadata

Support boolean composition:

- AND
- OR
- NOT
- nested groups

Example:

```text
IF
content.type == "product_review"
AND actor.account_age < 24h
AND links.count > 1
AND actor.reputation < 40

THEN
PENDING
```

---

# 12. VISUAL RULE BUILDER

Build an excellent visual policy builder.

It must support:

- nested conditions
- drag/reorder where appropriate
- rule groups
- readable IF/AND/OR/THEN structure
- human-readable summaries
- validation
- conflict detection
- policy preview
- test input
- duplication
- enable/disable
- versioning
- scheduling
- scope
- priority
- severity
- action
- explanations

Provide an Advanced mode for expert users.

Eventually support a structured policy expression format/DSL.

Do not make raw JSON the primary UX.

---

# 13. PROHIBITED TERM ENGINE

Support:

- words
- phrases
- exact matches
- whole-word matches
- partial matches
- prefix
- suffix
- case-sensitive
- case-insensitive
- wildcards
- regular expressions
- normalized matching
- fuzzy matching
- phonetic matching where linguistically valid
- language-specific matching

Each rule may include:

- identifier
- label
- category
- language
- severity
- scope
- action
- exceptions
- notes
- version
- owner
- timestamps

---

# 14. ALLOWLIST ENGINE

Support allowlisting of:

- words
- phrases
- URLs
- domains
- emails
- usernames
- IP addresses
- CIDRs
- contexts
- users
- roles
- content types

Allowlist precedence must be explicit and testable.

Prevent broad allowlist rules from accidentally disabling unrelated safety checks.

---

# 15. TEXT NORMALIZATION PIPELINE

Normalization must occur once per content input wherever possible.

Support:

- HTML entity decoding
- safe HTML-to-text extraction where required
- Unicode normalization
- Unicode confusables
- case folding
- whitespace normalization
- repeated whitespace
- punctuation normalization
- invisible characters
- zero-width characters
- repeated-character handling
- fullwidth forms
- digit normalization
- script detection
- optional diacritic removal
- URL decoding where appropriate

Store original content separately from normalized analysis representation.

Never modify the user's original data merely to run detection.

---

# 16. PERSIAN / ARABIC ENGINE

Persian and Arabic support is a first-class requirement.

Handle:

- ي / ی
- ك / ک
- Persian vs Arabic digits
- Arabic diacritics
- tatweel
- ZWNJ
- ZWJ
- zero-width characters
- multiple whitespace forms
- RTL marks
- mixed Arabic/Persian characters
- Arabic presentation forms
- letter stretching
- separated-character evasion
- repeated characters
- Persian slang
- Arabic slang where dictionaries exist
- Finglish/Pinglish variants
- Persian/Latin mixed strings

Build dedicated Persian normalization tests.

Never simply delete ZWNJ globally without understanding word-boundary consequences.

---

# 17. EVASION DETECTION

Detect attempts such as:

```text
badword
b a d w o r d
b.a.d.w.o.r.d
b-a-d-w-o-r-d
b@dw0rd
b4dword
baaaaadword
Unicode homoglyphs
mixed alphabets
zero-width insertion
```

Support:

- leetspeak
- homoglyphs
- script substitution
- punctuation insertion
- whitespace insertion
- repeated letters
- emoji substitution
- digit substitution
- reversed variants when justified
- encoded text patterns where appropriate

Do not perform extremely expensive fuzzy comparisons against every dictionary entry.

Use staged candidate generation.

---

# 18. HIGH-PERFORMANCE MATCHING

Performance is a critical product requirement.

Do not implement prohibited-term matching as:

```text
for every rule
    scan entire content
```

when the rule set becomes large.

Investigate and benchmark efficient strategies such as:

- hash sets
- tries
- prefix indexes
- Aho-Corasick-style multi-pattern matching
- compiled regex groups where safe
- token indexes
- language indexes
- candidate narrowing
- normalized-content fingerprinting

Fuzzy matching must operate only on candidate terms or bounded contexts.

Protect against catastrophic regex backtracking.

Add regex validation and execution limits where possible.

---

# 19. DETECTION ACCURACY STRATEGY

False positives are a top project risk.

Implement explicit confidence/evidence layers.

Do not hard-reject ambiguous content by default.

Use:

```text
high-confidence deterministic violation
→ enforce

ambiguous violation
→ AI or review

low confidence
→ allow or warn according to policy
```

Maintain evidence showing exactly why a decision occurred.

Every decision should be explainable to administrators.

---

# 20. MODERATION TAXONOMY

Support default categories such as:

- profanity
- harassment
- personal attacks
- hate
- threatening language
- violence
- sexual content
- explicit language
- self-harm
- drugs
- weapons
- gambling
- scams
- financial solicitation
- spam
- promotional content
- advertising
- competitor references
- prohibited brands
- political terminology
- religious terminology
- PII
- confidential information
- secrets/credentials
- suspicious URLs
- legal/compliance terminology
- custom categories

Categories must be administrator-extensible.

---

# 21. SEVERITY SYSTEM

Support configurable severity from 0–100.

Example default interpretation:

```text
0–20    informational
21–40   mild
41–60   moderate
61–80   severe
81–100  critical
```

Do not hard-code enforcement into severity.

Policies determine actions.

---

# 22. COMPOSITE RISK SCORE

Support weighted signals such as:

- deterministic violation
- AI score
- account age
- reputation
- strike history
- IP reputation
- URL reputation
- rate-limit behavior
- repeated content
- reporting history
- verified buyer
- trusted role

Weights must be configurable.

Store both:

- raw signals
- final score

Do not make the score opaque.

---

# 23. USER REPUTATION

Implement configurable reputation profiles.

Possible signals:

- account age
- approved contributions
- rejected contributions
- spam
- reports
- confirmed reports
- moderator overrides
- purchases
- verified buyer
- email verification
- role
- strike history
- participation age
- positive contribution history

Allow policies such as:

```text
trust >= 90
→ relaxed review

trust <= 30
→ strict review
```

Prevent one malicious signal from permanently ruining a user's reputation unless policy explicitly specifies it.

---

# 24. STRIKE SYSTEM

Support:

- weighted violations
- expiration
- decay
- manual removal
- escalation thresholds
- category-specific strike values

Example:

```text
mild profanity = 1
spam = 2
harassment = 3
threat = 10
```

Possible escalation:

```text
warning
temporary restriction
temporary suspension
long suspension
manual review
```

Never silently permanently ban a user from a low-confidence AI result.

---

# 25. PROGRESSIVE ENFORCEMENT

Support:

- warning
- edit request
- auto-mask
- redaction
- pending review
- quarantine
- rejection
- spam
- unpublish
- escalation
- challenge
- suspension

Implement configurable user-facing messaging.

---

# 26. REAL-TIME FRONTEND FEEDBACK

Provide optional frontend moderation.

Requirements:

- debounced
- privacy-conscious
- performant
- server-authoritative
- accessible
- does not expose secret policy logic unnecessarily

Allow:

- inline warning
- highlighted offending content
- editing guidance
- submission blocking

Do not transmit every keystroke externally to AI services.

Prefer local/client or delayed server validation.

---

# 27. EXPLANATIONS

Support explanation policies:

### Generic

"Your submission violates our community guidelines."

### Category level

"Your submission contains prohibited language."

### Detailed

Show specific matched policy or phrase when the administrator enables this.

Admins control disclosure level.

---

# 28. AI MODERATION

AI must be optional.

Create a provider abstraction.

Possible providers:

- OpenAI
- Google
- AWS
- compatible third-party APIs
- local moderation service
- custom REST provider
- organization-owned model

Never make core moderation depend on one AI vendor.

---

# 29. AI ROUTING

Use AI only when justified.

Preferred pipeline:

```text
local deterministic checks
↓
confidence evaluation
↓
cheap heuristics
↓
AI only for ambiguous/contextual cases
```

Do not send obviously blocked content to paid AI unless additional classification is required.

Do not send clearly safe/trusted content unnecessarily.

---

# 30. AI SAFETY

Treat content being classified as untrusted data.

User content must never become trusted system instructions.

Require structured provider responses.

Validate responses.

Implement:

- timeout
- bounded retries
- exponential backoff
- circuit breaker
- provider health
- idempotency
- request deduplication
- budget enforcement
- fallback provider
- local fallback
- pending-review fallback

Record model/provider/version used for important decisions.

---

# 31. AI THRESHOLDS

Configure category-specific thresholds.

Example:

```text
harassment:
warn > .55
review > .70
reject > .95
```

Thresholds must be calibrated using evaluation data.

Do not use arbitrary thresholds as permanent production defaults without testing.

---

# 32. AI POLICY EDITOR

Allow administrators to configure contextual AI policy.

Provide:

- versioning
- drafts
- testing
- preview
- rollback
- examples
- validation

Never allow raw user submissions to modify the system policy.

---

# 33. LOCAL-ONLY MODE

Provide a complete mode where no content leaves WordPress.

Support:

- local terms
- regex
- deterministic classifiers
- fuzzy matching
- heuristic spam signals

Clearly indicate which features require external providers.

---

# 34. MODERATION PLAYGROUND

Create an admin test tool.

Input sample content and display:

- normalized representation
- detected language
- deterministic matches
- categories
- AI scores
- reputation effects
- policy used
- rule IDs
- final action
- explanation

It must not affect live production data.

---

# 35. POLICY SIMULATOR

Allow administrators to test new policies against historical data without enforcement.

Produce:

- total evaluated
- would allow
- would warn
- would hold
- would reject
- affected users
- affected content types
- likely false-positive samples

Support comparison:

```text
current policy vs draft policy
```

---

# 36. SHADOW MODE

Support enforcement-disabled analysis.

```text
detect
score
log
DO NOT alter content
```

Use this before activating major policies.

---

# 37. LEARNING MODE

Optional installation period.

Analyze site characteristics:

- content volume
- languages
- average lengths
- links
- existing comment moderation
- human approval patterns

Recommend initial policy settings.

Do not automatically enable destructive rules without approval.

---

# 38. HISTORICAL SCANNING

Scan existing:

- posts
- pages
- CPTs
- comments
- reviews
- forum topics
- forum replies
- activities
- profiles
- media metadata
- products
- custom fields

Modes:

- report
- flag
- pending
- quarantine
- redact
- unpublish
- delete only where explicitly configured

Use background batches.

Never perform an unbounded scan in a normal HTTP request.

---

# 39. SCHEDULED RESCANNING

Support rescans triggered by:

- new rules
- policy updates
- dictionary changes
- model changes
- schedules
- administrator request

Deduplicate jobs.

---

# 40. WORDPRESS COMMENT INTEGRATION

Support full comment moderation.

Integrate correctly with WordPress states.

Do not break:

- standard comments
- REST submissions
- admin comments
- replies
- WooCommerce reviews
- existing anti-spam plugins

---

# 41. WOOCOMMERCE

Support:

### Products

- title
- description
- short description
- attributes
- tags
- categories
- vendor-submitted fields

### Reviews

- review
- author
- verification state
- replies

### Checkout

Configurable moderation for:

- notes
- selected custom text fields

### Marketplace

Create adapters for major marketplace plugins when installed:

- Dokan
- WCFM
- WC Vendors

Respect WooCommerce architecture and HPOS compatibility.

Do not directly query WooCommerce order storage in a way that breaks HPOS.

---

# 42. BBPRESS

Support:

- forum name
- description
- topic title
- topic body
- replies
- tags
- relevant profile content

Allow per-forum policies.

---

# 43. BUDDYPRESS / BUDDYBOSS

Support where technically appropriate:

- activity
- activity replies
- profiles
- group name
- group description
- group content
- private messages when explicitly enabled

Private messages require stronger privacy disclosure.

---

# 44. FORMS

Provide adapters for major form ecosystems:

- Contact Form 7
- WPForms
- Gravity Forms
- Fluent Forms
- Formidable Forms
- Ninja Forms
- Elementor Forms
- Forminator
- Jetpack Forms

Allow per-form and per-field policies.

Do not block an entire form because a field that was never configured happens to contain text.

---

# 45. MEMBERSHIP / LMS / COMMUNITY

Design adapters for:

- Ultimate Member
- MemberPress
- PeepSo
- BuddyBoss
- LearnDash
- LifterLMS
- Tutor LMS

Use optional integration modules.

Never require these plugins.

---

# 46. ACF

Discover applicable ACF fields.

Support:

- text
- textarea
- WYSIWYG
- URL
- email
- repeater
- flexible content

Allow field-level moderation.

---

# 47. GENERIC INTEGRATION API

Expose a stable PHP API similar conceptually to:

```php
$result = moderator()->check(
    $content,
    $context
);
```

Provide proper typed/context objects where possible.

Third-party plugins must be able to integrate without importing internal classes.

---

# 48. INTEGRATION SDK

Provide interfaces and documentation for custom adapters.

Include:

- source registration
- field registration
- submission interception
- enforcement handler
- UI configuration
- context providers

Maintain backward compatibility.

---

# 49. MEDIA MODERATION

Implement optional image moderation architecture.

Support:

- avatars
- uploaded images
- product images
- community uploads
- attachments

Possible classifications:

- adult
- sexual
- violence
- graphic content
- weapons
- hate imagery
- drugs
- custom policy categories

Make external media analysis asynchronous by default.

---

# 50. OCR MODERATION

Optional pipeline:

```text
image
→ OCR
→ text
→ standard text moderation
```

Use for:

- memes
- screenshots
- contact details embedded in images
- promotional images

Prevent expensive OCR from blocking ordinary requests synchronously.

---

# 51. FILE POLICIES

Moderate:

- filename
- extension
- MIME
- size
- metadata
- text extraction where supported

Validate actual MIME independently of file extension.

---

# 52. URL MODERATION

Support:

- allowed domains
- blocked domains
- maximum URLs
- shortened URLs
- suspicious redirectors
- affiliate URLs
- competitor domains
- adult/gambling domains
- reputation service adapters

Parse URLs safely.

Do not accidentally treat arbitrary text as executable URL data.

---

# 53. PII DETECTION

Support configurable detection of:

- email
- phone
- address patterns
- IP
- credit card patterns
- bank account patterns
- national identifiers where configured
- custom organization identifiers

Actions:

- warn
- redact
- mask
- review
- reject

Avoid storing detected PII unnecessarily.

---

# 54. SECRET DETECTION

Support patterns for accidental publication of:

- API keys
- access tokens
- private keys
- JWTs
- cloud credentials
- passwords
- database connection strings
- custom secrets

Prefer high precision to avoid noisy alerts.

---

# 55. SPAM / ABUSE SIGNALS

Include optional lightweight spam detection:

- duplicate content
- repeated URLs
- excessive URLs
- advertising patterns
- repeated submissions
- excessive capitalization
- suspicious entropy
- disposable email integration
- rapid submission
- user reputation
- IP/domain reputation

Remain compatible with specialized services such as Akismet and CleanTalk.

Do not unnecessarily duplicate expensive anti-spam work when another system has already provided reliable signals.

---

# 56. RATE LIMITING

Support limits per:

- user
- IP
- session
- source
- endpoint
- content type

Use efficient storage.

Do not create database writes for every anonymous keystroke.

---

# 57. DUPLICATE DETECTION

Support exact and near-duplicate detection.

Use fingerprints/similarity strategies appropriate for scale.

---

# 58. BEHAVIORAL RISK

Combine:

- account age
- submission frequency
- repeated links
- rejected content
- reports
- failed challenges

Expose these signals to policy rules.

---

# 59. IP / GEO / NETWORK SIGNALS

Optional adapters for:

- IP allow/block
- CIDR
- country
- ASN
- VPN/proxy reputation

Do not treat geographical origin alone as proof of abuse.

---

# 60. CAPTCHA / CHALLENGE

Integrate optionally with:

- Cloudflare Turnstile
- hCaptcha
- reCAPTCHA where desired

Allow risk-based challenge rather than forcing CAPTCHA on every user.

---

# 61. MODERATION INBOX

Create a high-quality unified moderation interface.

Primary sections:

- All
- Pending
- Flagged
- Critical
- Spam
- Reported
- Appeals
- Assigned to Me
- Unassigned
- Resolved

Support:

- filters
- search
- bulk actions
- saved views
- keyboard shortcuts
- cursor-based pagination
- virtualization if needed

---

# 62. MODERATION DETAIL VIEW

Show:

- original content
- context
- highlighted evidence
- normalized representation where appropriate
- matched rules
- policy
- score
- AI output
- user reputation
- strike history
- previous moderation events
- reports
- source
- plugin
- related conversation

Actions:

- approve
- reject
- edit
- redact
- spam
- trash
- warn
- strike
- assign
- escalate
- suspend
- add allowlist exception
- create rule

---

# 63. HUMAN MODERATION WORKFLOW

Support:

- assignments
- owners
- teams
- internal notes
- escalation
- priorities
- queues
- SLAs
- resolution reasons
- second review
- appeals

---

# 64. MODERATOR PERMISSIONS

Create granular capabilities instead of assuming administrator.

Examples:

- view moderation
- moderate
- view sensitive evidence
- view PII
- edit policies
- manage integrations
- suspend users
- export data
- manage AI providers
- view audit log
- administer plugin

Map these capabilities to administrator by default without forcing unwanted global WordPress roles.

---

# 65. USER REPORTING

Provide accessible frontend reporting.

Reasons:

- spam
- harassment
- hate
- sexual
- violence
- scam
- personal information
- misinformation if administrator chooses
- other

Rate-limit reporting.

Detect reporting abuse.

---

# 66. REPORT THRESHOLDS

Policies may use:

- unique report count
- reporter reputation
- report age
- confirmed reports

Avoid automatically hiding content because one attacker creates many accounts.

---

# 67. APPEALS

Support:

```text
moderation decision
→ user appeal
→ human review
→ uphold / reverse
```

Track reviewer and rationale.

---

# 68. AUDIT LOG

Maintain detailed audit events for:

- content decision
- moderator action
- rule change
- policy change
- provider configuration
- user suspension
- appeal
- import/export
- settings change

Store:

- actor
- timestamp
- object
- previous state
- new state
- reason
- policy version

Design audit storage for high volume.

---

# 69. MODERATION TIMELINE

Every moderated object should have a history such as:

```text
submitted
detected
held
assigned
reviewed
approved
appealed
resolved
```

---

# 70. MODERATOR OVERRIDES

Record when human decisions disagree with automation.

Use this for:

- accuracy evaluation
- threshold tuning
- rule recommendations

Do not automatically retrain/change policies without administrator control.

---

# 71. ANALYTICS

Provide dashboards for:

- analyzed content
- allowed
- warned
- pending
- rejected
- spam
- reports
- appeals
- overrides
- false positives
- false negatives where known
- queue size
- moderation time
- SLA

Segment by:

- date
- site
- content type
- integration
- language
- rule
- policy
- category
- user cohort

---

# 72. RULE EFFECTIVENESS

For each rule provide:

- trigger count
- action distribution
- moderator reversal rate
- precision estimates where labels exist
- trend

Flag suspicious rules with high reversal rates.

---

# 73. FALSE-POSITIVE DISCOVERY

Example:

```text
Rule X:
1000 triggers
720 manually approved
```

Recommend review.

Never automatically disable a security-critical rule without explicit permission.

---

# 74. EMERGING TERM DISCOVERY

Identify terms frequently associated with confirmed violations that are not currently rules.

Suggest:

- add rule
- investigate
- ignore

No automatic blocking.

---

# 75. MODERATOR ANALYTICS

Provide operational metrics such as:

- backlog
- median review time
- SLA compliance
- assignment load

Do not encourage simplistic surveillance/rankings of employees.

---

# 76. REPORTS

Support:

- daily
- weekly
- monthly
- custom periods

Exports:

- CSV
- JSON
- structured report
- PDF where implementation supports it

Ensure large exports run asynchronously.

---

# 77. ALERTS

Support configurable alerts for:

- critical violation
- threat
- queue threshold
- SLA breach
- spam surge
- provider outage
- AI budget threshold
- failed jobs
- integration failure

Channels:

- email
- webhook
- Slack adapter
- Mattermost adapter
- Discord adapter
- Teams adapter

Use modular notification providers.

---

# 78. MULTILINGUAL SUPPORT

Detect language where useful.

Support:

- per-language rules
- dictionaries
- thresholds
- policies

Handle mixed-language content.

Never assume translated English captures all cultural or slang meaning.

---

# 79. TRANSLATION-AWARE MODERATION

Translation may optionally assist classification but must not be the only detection method.

Keep:

- original input
- detected language
- translation where used
- result provenance

---

# 80. MULTISITE

Support WordPress Multisite.

Allow:

```text
network policy
↓
site policy
↓
local overrides
```

Network admins must control whether sites may:

- inherit
- extend
- override

Avoid expensive network-wide loading on every site request.

---

# 81. POLICY INHERITANCE

Implement deterministic precedence.

Document exactly what happens when policies conflict.

---

# 82. IMPORT / EXPORT

Support portable:

- rules
- dictionaries
- policies
- integrations configuration where safe

Never export API secrets by default.

---

# 83. POLICY TEMPLATES

Provide templates such as:

- Balanced
- Family Friendly
- Corporate
- News
- Marketplace
- Education
- Gaming
- Financial Community

Templates are starting points, not immutable magic configurations.

---

# 84. ORGANIZATION DICTIONARIES

Support custom:

- banned terminology
- competitor names
- brand restrictions
- internal project names
- regulatory phrases
- sensitive terms

---

# 85. EDITORIAL GOVERNANCE

Extend beyond user-generated content.

Before publication check:

- banned claims
- prohibited phrases
- competitor terms
- confidential information
- required disclosures
- required sections
- PII
- secrets

---

# 86. REQUIRED-CONTENT RULES

Support requirements such as:

```text
IF category == Sponsored
THEN content must contain required disclosure
```

or:

```text
IF category == Investment
THEN disclaimer must exist
```

---

# 87. CONTENT QUALITY RULES

Optional policies for:

- minimum length
- maximum length
- title length
- excessive links
- required fields
- excessive capitals
- repetitive punctuation
- duplicate titles

Keep this module separated from safety classification.

---

# 88. USER MODERATION HISTORY

Users may optionally see:

- under review
- approved
- rejected
- appealed
- resolved

Admins control how much internal evidence is exposed.

---

# 89. USER NOTIFICATIONS

Notify users when:

- content is held
- content is approved
- content is rejected
- edit is required
- appeal is resolved
- account restriction changes

Support WordPress email and integration notification systems.

---

# 90. AUTO-REDACTION

Support safe redaction for:

- PII
- secrets
- prohibited snippets

Preserve original version in secure audit evidence only where retention policy allows.

---

# 91. REVISION ASSISTANCE

Optionally suggest policy-compliant revisions.

Do not automatically rewrite user speech without clearly informing the user.

---

# 92. CONTEXTUAL MODERATION

Allow detectors/AI to receive controlled context such as:

- parent comment
- post title
- limited preceding conversation
- forum
- product

Apply context size limits.

---

# 93. CONVERSATION-LEVEL MODERATION

Support advanced analysis for escalating interactions.

Example:

```text
A attacks B
B replies
A escalates
```

Allow conversation risk to increase.

Do not permanently punish users solely from inferred conversation sentiment.

---

# 94. MODERATOR AI ASSISTANT

Provide optional moderator tools:

- explain detection
- summarize thread
- identify policy
- propose action
- summarize reports
- draft user-facing explanation

The moderator retains final authority.

---

# 95. WORDPRESS EXISTING RULE IMPORT

Detect WordPress:

- moderation keys
- disallowed keys
- related existing settings

Offer migration/import.

Do not unexpectedly delete existing WordPress settings.

---

# 96. AKISMET COMPATIBILITY

Treat Akismet as complementary.

Example:

```text
Akismet → spam likelihood
this product → policy/safety
```

Avoid double-processing unnecessarily.

---

# 97. CLEANTALK AND OTHER ANTI-SPAM COMPATIBILITY

Allow external spam signals to inform policies.

Do not create plugin conflicts by aggressively overriding third-party decisions.

---

# 98. PROVIDER CONNECTORS

Support custom moderation APIs.

Configuration may specify:

- endpoint
- authentication
- request mapping
- response mapping
- timeout
- health endpoint

Prevent SSRF.

Block requests to private/internal network ranges by default unless explicitly trusted and authorized.

---

# 99. REST API

Expose secure REST endpoints for:

- moderation checks
- policy management
- queue management
- analytics
- reports
- developer integration

Every endpoint must have:

- schema
- input validation
- permission_callback
- authorization
- rate limits where appropriate

---

# 100. DEVELOPER HOOKS

Provide documented WordPress actions/filters.

Examples conceptually:

```text
moderator_before_normalize
moderator_after_normalize
moderator_before_evaluate
moderator_result
moderator_before_enforce
moderator_after_enforce
moderator_violation
```

Use a stable prefix/namespace.

---

# 101. WP-CLI

Implement commands such as:

```text
wp moderator scan
wp moderator test
wp moderator rules list
wp moderator policy validate
wp moderator export
wp moderator health
```

Support batches and resume tokens for large scans.

---

# 102. DEBUG / DIAGNOSTIC MODE

Provide diagnostics showing:

- hook
- integration
- policy
- fields scanned
- rules evaluated
- provider calls
- execution time
- database queries
- final action

Sensitive data must be redacted.

Never expose debug information to unauthorized frontend users.

---

# 103. SYSTEM HEALTH

Health dashboard should show:

- WordPress adapter
- WooCommerce
- bbPress
- BuddyPress
- forms
- queue
- cron
- DB schema
- provider health
- API connectivity
- AI budget
- failed jobs
- cache health

Integrate with WordPress Site Health where appropriate.

---

# 104. POLICY VERSIONING

Every production policy needs versions.

Provide:

- draft
- active
- archived

Track version used for every important moderation event.

---

# 105. POLICY ROLLBACK

Allow safe restoration of previous versions.

Changes should be auditable.

---

# 106. CONFIGURATION AUDIT

Example:

```text
Admin X changed harassment review threshold
0.80 → 0.70
```

Store configuration changes.

---

# 107. ENVIRONMENT SUPPORT

Support:

- development
- staging
- production

Allow policy/config transfer without transferring secrets.

---

# 108. DATA MODEL

Do not store high-volume operational records as giant serialized WordPress options.

Use custom database tables for high-volume entities where appropriate.

Evaluate tables for:

- policies
- policy versions
- rules
- dictionaries
- moderation events
- evidence
- user reputation
- strikes
- reports
- appeals
- assignments
- notes
- audit log
- scan jobs
- provider usage
- analytics aggregates

Use WordPress options only for suitable low-volume configuration.

Large options must not be autoloaded unnecessarily.

---

# 109. DATABASE REQUIREMENTS

Every table must have:

- clear primary key
- correct data types
- appropriate indexes
- migration version
- uninstall strategy
- retention strategy

Avoid:

- SELECT *
- unbounded queries
- OFFSET pagination at huge scale where cursor/keyset is better
- per-row queries inside loops
- unnecessary metadata joins

Profile high-volume queries.

---

# 110. DATABASE MIGRATIONS

Implement versioned, idempotent migrations.

Support upgrade from older plugin versions.

Do not destroy data on failed migrations.

Provide migration recovery.

---

# 111. BACKGROUND JOBS

Long-running operations must be asynchronous:

- historical scans
- AI media analysis
- OCR
- reports
- exports
- bulk rescans
- aggregation
- cloud synchronization

Provide:

- retries
- backoff
- locking
- idempotency
- dead-letter/failure handling
- progress
- cancellation
- resume

Use an abstraction so the implementation can use the best available WordPress-compatible scheduler.

Do not make WooCommerce a dependency simply to obtain a queue implementation.

---

# 112. HOT-PATH PERFORMANCE

The ordinary submission path must be highly optimized.

Goals:

- normalize once
- load compiled policy once
- avoid repeated DB reads
- no unnecessary network calls
- no full table scans
- no giant PHP arrays when avoidable
- no unrelated plugin code loading
- lazy-load integrations
- cache compiled rules

Measure actual overhead.

---

# 113. INITIAL PERFORMANCE TARGETS

Create a reproducible benchmark environment.

Initial engineering targets for local text moderation:

For ordinary content <=10 KB and a realistically large active dictionary:

- p50 overhead: target < 10 ms
- p95 overhead: target < 25 ms
- p99 overhead: target < 50 ms

These are engineering targets, not promises.

Benchmark before declaring them achieved.

External AI does not count toward local matcher latency because it should normally be asynchronous or selectively invoked.

Track:

- CPU
- wall time
- peak memory
- DB queries
- DB time
- cache hit rate

---

# 114. ADMIN PERFORMANCE

Admin UI must not load its JavaScript/CSS globally.

Only enqueue required assets on plugin screens.

Use:

- code splitting
- lazy loading
- server pagination
- virtualized long lists where useful
- cached analytics
- background aggregation

Avoid multi-megabyte initial JS bundles.

---

# 115. CACHE DESIGN

Use layered caches appropriately:

- request-level
- persistent object cache when available
- compiled-policy cache
- dictionary cache
- reputation cache where safe

Cache keys must include relevant versions.

Policy changes must invalidate appropriate caches reliably.

---

# 116. DETECTION EVALUATION FRAMEWORK

Create a repeatable evaluation suite.

Maintain labeled corpora for:

- English
- Persian
- Arabic where supported
- mixed-language
- clean content
- explicit violations
- ambiguous content
- adversarial obfuscation

Measure:

- precision
- recall
- F1
- false-positive rate
- false-negative rate
- per-category metrics
- per-language metrics
- latency

---

# 117. FALSE-POSITIVE RELEASE GATE

Hard-block decisions require high confidence.

Do not deploy automatic hard rejection for probabilistic detection until representative evaluation demonstrates acceptable precision.

As a starting quality gate:

**target ≥99.5% precision for automatic hard-block classifications on representative validation data.**

If this cannot be demonstrated:

route uncertain cases to:

- warn
- pending
- human review

instead.

Do not manipulate the test set merely to satisfy the threshold.

---

# 118. ADVERSARIAL DETECTION TESTS

Include:

- Unicode confusables
- invisible chars
- ZWNJ
- zero-width spaces
- emoji
- leetspeak
- repeated letters
- punctuation separation
- mixed scripts
- HTML entities
- URL encoding
- long inputs
- pathological regex inputs
- malformed UTF-8
- extremely large rule sets

---

# 119. SECURITY

Follow WordPress security principles.

Every input must be:

- validated
- sanitized where appropriate

Every output must be contextually escaped.

Use:

- capability checks
- nonces for CSRF protection
- prepared SQL
- REST permission callbacks
- safe file handling
- strict URL handling
- authorization checks

A nonce is not authorization.

---

# 120. SECURITY THREATS

Threat-model at minimum:

- stored XSS
- reflected XSS
- SQL injection
- CSRF
- SSRF
- broken access control
- privilege escalation
- IDOR
- unsafe deserialization
- malicious regex
- ZIP/archive bombs where files are supported
- malicious uploads
- webhook spoofing
- credential leakage
- sensitive log leakage
- mass-assignment
- rate-limit bypass
- race conditions
- replay attacks

---

# 121. WEBHOOK SECURITY

Outgoing and incoming webhooks must support:

- signatures
- timestamps
- replay protection
- secret rotation

Sensitive values must be redacted in logs.

---

# 122. SECRET STORAGE

Allow API credentials through secure configuration.

Prefer environment/constants where organizations require them.

If stored in DB, design encryption using WordPress installation secrets appropriately.

Never render secrets back to clients after initial entry.

Display:

```text
••••••••ABCD
```

where appropriate.

---

# 123. PRIVACY

Implement:

- WordPress personal-data export
- personal-data erasure hooks
- retention policies
- consent/disclosure for external processing
- data minimization
- anonymization where possible

Admins must be able to see what fields are sent to external providers.

Example:

```text
Send content        ON
Send username       OFF
Send email          OFF
Send IP             OFF
```

---

# 124. RETENTION

Configurable retention per data class:

- audit
- raw rejected content
- IP
- AI outputs
- reports
- analytics

Implement cleanup jobs.

Retention deletion must not break aggregate analytics.

---

# 125. FAILOVER

Every external provider needs explicit failure behavior.

Support:

- fail open
- fail closed
- pending review

Recommended default for ambiguous community content:

```text
provider unavailable
→ pending review
```

not silent acceptance or destructive rejection.

---

# 126. CIRCUIT BREAKERS

Detect repeated provider failures.

Stop hammering unavailable services.

Expose degraded status to administrators.

---

# 127. AI COST CONTROL

Provide:

- daily request count
- monthly request count
- token/usage information where available
- estimated spend
- budgets
- per-provider limits
- category routing
- user routing
- content-length limits

When the budget is exhausted, apply configured fallback.

---

# 128. UI INFORMATION ARCHITECTURE

Primary navigation should approximately include:

```text
Moderation
  Dashboard
  Inbox
  Policies
  Rules & Dictionaries
  Users & Reputation
  Reports
  Analytics
  Integrations
  Scans
  Audit Log
  Developer
  System Health
  Settings
```

Do not produce one giant settings page.

---

# 129. DASHBOARD

Dashboard should answer:

- Is moderation operating correctly?
- How much content was analyzed?
- What requires attention?
- Are critical violations present?
- Is backlog growing?
- Are integrations healthy?
- Are AI providers healthy?
- Is spending within budget?
- Did false-positive indicators increase?

---

# 130. SETUP WIZARD

Ask:

- site type
- content sources
- integrations
- languages
- moderation sensitivity
- AI preference
- privacy preference

Offer:

- Balanced
- Strict
- Relaxed
- Family Friendly
- Custom

Clearly show what each preset changes.

---

# 131. UX PRINCIPLES

The UI must be:

- calm
- information-dense where appropriate
- not visually overwhelming
- consistent with WordPress administration
- responsive
- RTL-compatible
- keyboard accessible
- screen-reader accessible

Use progressive disclosure.

Do not expose advanced technical configuration to ordinary moderators unnecessarily.

---

# 132. WORDPRESS UI IMPLEMENTATION

Prefer official WordPress UI packages where appropriate, including:

- @wordpress/components
- @wordpress/api-fetch
- @wordpress/data where justified
- @wordpress/i18n

Do not pull in a gigantic external UI framework without a compelling ADR.

Create a small product-specific design token layer.

---

# 133. ACCESSIBILITY

Target WCAG 2.2 AA.

Ensure:

- labels
- focus visibility
- logical focus order
- keyboard navigation
- screen-reader text
- semantic structure
- error association
- accessible modals
- accessible tables
- color-independent state indicators
- sufficient contrast
- RTL testing

---

# 134. RTL / PERSIAN UX

Explicitly test the entire administration interface under RTL.

Check:

- icons
- arrows
- breadcrumbs
- side panels
- charts
- tables
- nested rule builder
- code blocks
- mixed LTR/RTL content

---

# 135. MODERATION SPEED UX

High-volume moderators should be able to work efficiently.

Support:

- keyboard shortcuts
- bulk actions
- saved views
- automatic next item
- side-by-side context where appropriate
- rapid assignment
- internal notes without page refresh

---

# 136. CLOUD / AGENCY ARCHITECTURE

Some product features require optional external infrastructure.

Design a companion control-plane architecture for:

- agency management
- centralized site management
- cross-site policies
- license management if product requires it
- aggregate analytics
- threat intelligence
- shared dictionaries

The WordPress plugin must remain functional without this service for local features.

Cloud functionality must be opt-in and privacy documented.

---

# 137. SHARED THREAT INTELLIGENCE

Optional service may aggregate privacy-preserving signals for:

- malicious domains
- repeated spam URLs
- abusive IP reputation
- known automated patterns

Do not upload raw private user content by default.

---

# 138. WHITE LABEL / AGENCY

Support configurable branding for legitimate agency use.

Allow agencies to:

- manage multiple sites
- deploy policies
- inspect health
- receive alerts

Do not create invasive hidden telemetry.

---

# 139. TELEMETRY

Any telemetry must be:

- disclosed
- opt-in where required
- minimal
- privacy-conscious

Never transmit moderation content for generic product analytics without explicit authorization.

---

# 140. RELEASE PACKAGING

Design shared core architecture so product tiers do not duplicate business logic.

Possible packaging:

```text
Core
Pro
Business
Enterprise
```

Premium modules should depend on stable public interfaces.

Avoid maintaining four divergent copies of the moderation engine.

---

# 141. CODING STANDARDS

Follow current:

- WordPress PHP coding standards
- WordPress JavaScript standards
- WordPress CSS standards
- WordPress accessibility guidance

Use namespaces/prefixes to prevent collisions.

Do not modify WordPress core.

---

# 142. STATIC ANALYSIS

Configure and run:

- PHPCS with WordPress Coding Standards
- PHPStan or equivalent with WordPress stubs
- ESLint
- Stylelint where applicable
- Composer audit
- npm audit with appropriate review

No newly introduced unresolved high-severity dependency vulnerabilities.

---

# 143. PHP TESTING

Use appropriate combinations of:

- PHPUnit
- WordPress integration test suite
- mocking only where it improves isolation

Critical domain logic must be testable without booting an entire WordPress stack.

---

# 144. JAVASCRIPT TESTING

Test:

- reducers/state
- rule builder logic
- validation
- API clients
- significant components

Use appropriate WordPress-supported JS testing stack.

---

# 145. E2E TESTING

Use browser tests for:

- installation
- onboarding
- creating policy
- testing policy
- comment violation
- post publishing
- moderation queue
- approval
- appeal
- WooCommerce review
- integration settings
- RTL
- accessibility-critical flows

Playwright is acceptable when compatible with project tooling.

---

# 146. PROPERTY / FUZZ TESTS

Use property testing/fuzz-style tests for:

- Unicode normalization
- text matcher
- parser
- regex validation
- policy expression parsing
- malformed input
- oversized input

---

# 147. PERFORMANCE TESTS

Create automated benchmarks for:

- 100 rules
- 1,000 rules
- 10,000 rules
- 100,000 dictionary entries if supported

Inputs:

- 100 chars
- 1 KB
- 10 KB
- large posts

Report:

- time
- memory
- query count

Compare against baseline.

---

# 148. COMPATIBILITY TEST MATRIX

Test representative combinations of:

- supported WordPress versions
- supported PHP versions
- MySQL/MariaDB
- Multisite
- object cache on/off

and supported integrations.

Do not claim compatibility that has not been tested.

---

# 149. QUALITY COVERAGE TARGETS

Aim for:

- domain/rule/detection code: >= 90% meaningful line coverage
- critical normalization/matcher code: >= 95%
- overall backend: >= 80–85%

Coverage does not replace quality.

Test boundaries and failure paths.

---

# 150. WORDPRESS RELEASE COMPLIANCE

Run WordPress Plugin Check.

Verify:

- headers
- licensing
- third-party dependency licenses
- readme
- build source
- secure remote calls
- escaping
- sanitization
- SQL
- capabilities
- external service disclosure

Prepare for automated WordPress.org security review where the free plugin is distributed there.

---

# 151. OBSERVABILITY

Instrument:

- moderation latency
- rule evaluation latency
- DB latency
- provider latency
- queue depth
- failed jobs
- retry count
- cache hit rate
- AI usage
- policy errors

Do not expose personally sensitive content in metrics.

---

# 152. LOGGING

Use structured logs.

Log levels:

- debug
- info
- warning
- error
- critical

Provide configurable retention.

Redact secrets and PII.

---

# 153. HEALTH CHECKS

Implement checks for:

- database schema
- scheduler
- provider
- REST
- cache
- integration hook registration
- migration state

---

# 154. MIGRATION FROM OTHER SYSTEMS

Provide extensible import mechanisms for:

- WordPress comment moderation keys
- WordPress disallowed keys
- custom dictionaries
- CSV rule lists

Validate imported data before activation.

---

# 155. PUBLIC COMMUNITY GUIDELINES

Optionally generate/publicly expose community guidelines derived from selected policy categories.

Do not expose internal fraud detection techniques.

---

# 156. SEARCHABLE ARCHIVE

Authorized moderators must be able to search moderation history by:

- content
- content ID
- user
- rule
- category
- moderator
- date
- decision

Use indexes/search architecture appropriate to expected volume.

---

# 157. EVIDENCE PRESERVATION

Where policy permits, store sufficient evidence to explain important decisions.

Keep:

- original snapshot or secure relevant excerpt
- policy version
- detector evidence
- decision
- actor
- timestamp

Respect retention and privacy settings.

---

# 158. RETROSPECTIVE REMEDIATION

When a critical new policy is created, allow administrators to find historical matches.

Possible actions:

- report
- review
- redact
- unpublish

Do not automatically delete large amounts of historical content without a clearly confirmed policy.

---

# 159. DEFINITION OF DONE FOR EVERY FEATURE

A feature is not complete because the UI appears.

It is complete only when:

- domain behavior is implemented
- permissions are implemented
- validation is implemented
- error states exist
- accessibility is reviewed
- tests pass
- performance impact is measured where relevant
- security is reviewed
- documentation exists
- analytics/audit events exist where relevant
- integration edge cases are covered

---

# 160. PROHIBITED IMPLEMENTATION SHORTCUTS

Do not:

- create giant god classes
- scan every rule linearly for every request without benchmarking
- call AI for every submission
- make synchronous image AI calls in normal request paths
- store millions of events in wp_options
- use autoloaded giant option arrays
- trust nonces as authorization
- blindly trust external API results
- log secrets
- expose raw SQL
- silently swallow errors
- load admin assets on every WordPress screen
- modify WordPress core
- couple the core engine directly to WooCommerce
- depend on jQuery unnecessarily for new admin architecture
- use regex for everything
- perform unbounded historical processing in a single request
- claim an integration works without tests
- claim high detection accuracy without evaluation
- silently hard-block ambiguous AI detections
- create inaccessible custom controls when native accessible controls exist

---

# 161. IMPLEMENTATION PHASES

Implement in this order.

## Phase 0 — Repository and requirements audit

Produce:

- repository map
- architecture baseline
- compatibility requirements
- risk register
- feature matrix
- ADRs
- test baseline
- performance baseline

Then continue.

Do not stop after Phase 0.

## Phase 1 — Foundation

Implement:

- bootstrap
- dependency architecture
- capabilities
- database migrations
- domain models
- request/result model
- audit infrastructure
- settings foundation

## Phase 2 — Detection Engine

Implement:

- normalization
- dictionaries
- allowlists
- phrase matcher
- regex
- fuzzy candidates
- Persian/Arabic handling
- obfuscation
- evidence
- scoring

Benchmark it.

## Phase 3 — Policy Engine

Implement:

- conditions
- actions
- rule groups
- policy versions
- inheritance
- conflict rules
- simulation

## Phase 4 — WordPress Core

Implement:

- posts
- pages
- CPTs
- comments
- taxonomies
- users
- metadata
- REST
- Gutenberg
- admin
- CLI

## Phase 5 — Moderation Operations

Implement:

- inbox
- queue
- assignment
- notes
- escalation
- reports
- appeals
- strikes
- reputation

## Phase 6 — Admin UX

Implement:

- onboarding
- dashboard
- rule builder
- policy editor
- moderation detail
- dictionaries
- integrations
- health
- settings

Perform accessibility review.

## Phase 7 — Major Integrations

Implement and test:

- WooCommerce
- bbPress
- BuddyPress/BuddyBoss
- ACF
- form plugins
- membership/LMS
- marketplace adapters

## Phase 8 — AI

Implement:

- provider abstraction
- routing
- thresholds
- fallbacks
- budget
- privacy
- provider health
- moderator assistant

## Phase 9 — Media / OCR / Files

Implement optional media and OCR pipelines.

## Phase 10 — Analytics

Implement:

- aggregates
- dashboards
- accuracy reports
- emerging terms
- false-positive analysis

## Phase 11 — Multisite / Enterprise

Implement:

- network policies
- inheritance
- audit
- enterprise permissions
- white label
- agency architecture

## Phase 12 — Cloud Companion

Implement optional control-plane contracts/service required for centralized management and threat intelligence.

## Phase 13 — Hardening

Run:

- security audit
- performance audit
- privacy audit
- accessibility audit
- adversarial detection suite
- compatibility matrix

## Phase 14 — Release

Produce:

- changelog
- readme
- developer docs
- admin docs
- migration docs
- privacy documentation
- release package
- integrity checks

---

# 162. IMPLEMENTATION CHECKPOINT AFTER EACH PHASE

At the end of each phase:

1. run tests;
2. run relevant static checks;
3. run benchmarks where applicable;
4. review security;
5. update IMPLEMENTATION-STATE.md;
6. list unresolved risks;
7. commit only coherent passing state if Git workflow permits;
8. immediately proceed to the next phase unless an actual blocker prevents it.

---

# 163. WHEN CONTEXT OR EXECUTION LIMITS ARE REACHED

Never pretend unfinished work is complete.

Before stopping:

- leave the repository in a working state;
- ensure tests for completed work pass;
- update IMPLEMENTATION-STATE.md;
- record exact next file/task;
- record unresolved issues;
- record required commands;
- record migration status.

This must allow a subsequent GPT-6.1 Sol session to continue without rediscovering the entire project.

---

# 164. FINAL ACCEPTANCE GATES

The project may be considered production-ready only when all applicable gates pass.

### Functional

All scoped modules implemented.

### Accuracy

Representative evaluation completed.

False-positive/false-negative metrics documented.

High-confidence hard-block thresholds validated.

### Performance

Benchmarks documented.

No unacceptable hot-path regression.

### Security

No known critical/high vulnerabilities.

Plugin Check/security gates pass.

### Privacy

External processing is explicit.

Retention and erasure operate correctly.

### Accessibility

Critical administrator and frontend workflows meet WCAG AA requirements.

### Reliability

Async jobs, retry, failover, and recovery tested.

### Integration

Compatibility matrix completed.

### UX

Onboarding, moderation, rule creation, error states, empty states, loading states, and destructive actions reviewed.

### Developer Experience

REST API, hooks, SDK, CLI, and documentation complete.

### Release

Installation, upgrades, migrations, deactivation, uninstall, and rollback tested.

---

# 165. REQUIRED FINAL PROJECT REPORT

When implementation is genuinely complete, produce a final report containing:

### Architecture

What was built and why.

### Feature Matrix

Every requirement:

```text
Implemented
Tested
Documented
```

### Detection Accuracy

Precision/recall/FPR/FNR by applicable category/language.

### Performance

Benchmark results and environment.

### Security

Threat-model summary and completed controls.

### Privacy

Data flows and external processors.

### Database

Tables, indexes, migrations, retention.

### Integrations

Tested plugin/version matrix.

### UI/UX

Screens and key workflows.

### Accessibility

Audit results.

### Test Results

Unit/integration/E2E/static analysis.

### Known Limitations

No hidden limitations.

### Release Checklist

Everything required for production deployment.

---

# FINAL DIRECTIVE

Build this as a serious moderation platform, not a demonstration plugin.

Whenever there is a tradeoff:

- prefer correctness over cleverness;
- prefer measurable performance over assumptions;
- prefer deterministic checks over expensive AI when deterministic checks are sufficient;
- prefer review over hard rejection when confidence is uncertain;
- prefer modular adapters over integration-specific hacks;
- prefer explicit policies over hidden behavior;
- prefer native WordPress conventions over unnecessary frameworks;
- prefer privacy-preserving defaults;
- prefer accessible workflows;
- prefer evidence-backed decisions;
- prefer automated regression testing over manual confidence.

Do not declare the project finished until implementation, testing, performance measurement, accuracy evaluation, security review, UX/accessibility review, documentation, migrations, integration verification, and release validation have all been completed.

Begin by inspecting the repository, building the requirements/architecture ledger, and then proceed directly into implementation.