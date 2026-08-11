# IDMIS Engineering Documentation

This Wiki-ready documentation is the operational companion to the private IDMIS source repository. GitHub's private-Wiki feature is unavailable on the repository owner's current plan, so this version-controlled directory is the canonical engineering source until the account is upgraded. It documents how engineers and authorized reviewers navigate, run, test, and assess the platform without storing credentials or controlled programme evidence here.

## Start here

- [[Architecture]] — runtime components, data boundaries, and request flow
- [[Fourteen ToR Modules]] — module-to-navigation map
- [[Local Development and Operations]] — setup, queues, scheduler, realtime, and troubleshooting
- [[Testing and Release Workflow]] — local and GitHub quality gates
- [[Enterprise Readiness]] — what engineering evidence proves and what still requires external acceptance

## Non-negotiable boundaries

- County and portfolio scope is enforced by backend authorization.
- Platform administrators are not county identities.
- Credentials, `.env`, OAuth keys, production documents, and citizen personal data never belong in Git or this documentation.
- Automated tests prove implemented behavior, not government-interface approval, owner UAT, or production acceptance.
