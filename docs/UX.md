# UX and accessibility

The preview uses native WordPress forms, links and tables with server-rendered screens. No frontend framework or production JavaScript/CSS is shipped. Administrator navigation exposes status, policy, playground and audit. The policy editor is a simple line-per-term dictionary and shadow-mode switch, which preserves compatible identities and prevents replacement of advanced rules/scopes/exceptions. It is not the requested nested visual rule builder.

Labels are associated with fields; empty audit state is explicit; table headings have scope and a caption; submitted text and structured results are escaped; UTC is explicit on audit dates. Permission and storage errors use explanatory messages. The playground stores neither samples nor live decisions. WordPress provides CSRF and native keyboard controls.

Six Playwright tests cover save, shadow simulation, script injection escaping, missing-nonce rejection, all four screens, automated axe WCAG A/AA checks a desktop RTL direction/focus/overflow smoke check and a 320-pixel RTL viewport check. Passing axe does not establish WCAG 2.2 AA conformance. Screen-reader review, comprehensive contrast/reflow testing, real Persian locale/RTL styles, complex keyboard workflows, human usability and accessibility of future workflows remain outstanding. The desktop RTL smoke test changes direction and body class; it is not a translated locale test.

Public explanations/notifications, onboarding, frontend feedback, unified inbox, detail/evidence UI, approvals/appeals, assignments, reports, bulk operations and accessible nested rule editing remain unimplemented. The raw JSON playground result is diagnostic output for policy authors; raw JSON is not used as the primary policy authoring UI.

The audit table labels shadow proposals and incomplete analysis. Textareas/results fit narrow viewports; the audit table has a labelled, keyboard-focusable horizontal scroll region.
