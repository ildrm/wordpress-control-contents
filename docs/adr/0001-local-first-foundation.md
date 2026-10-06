# ADR 0001 — Local-first foundation and language/tooling floor

Accepted for the development preview, 2026-10-06.

The empty repository has no prior architecture/tooling constraints. Use PHP 8.2 readonly value objects and enums with required intl/mbstring, a modular PSR-4 namespace UWCMP, pure domain/application services and WordPress adapters. GPLv2-or-later matches the retained license. No production framework or vendor dependencies are needed. Native server-rendered admin forms provide the first coherent slice. A future visual rule builder may add official WordPress UI packages when justified.

WPCS Core/Extra is enforced. Explicit conventional exceptions cover PSR-4 filenames, non-Yoda comparisons, modern arrays, concise typed documentation/ternaries and machine identifier strings that must not be changed by the capitalization fixer. Native JSON encoding is allowed in pure application/infrastructure services so tests do not require WordPress, with JSON_THROW_ON_ERROR. Domain array element types are documented where material; PHPStan does not assume PHPDoc can replace runtime boundary validation; remaining adapter array typing is a PHPStan limitation recorded in TESTING.md.

WordPress 6.8+ is the intended floor (%i supported); compatibility is claimed only for recorded test stacks. Network activation is refused until network lifecycle and policy inheritance are implemented. No telemetry, providers or cloud connection exists.
