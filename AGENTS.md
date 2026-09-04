# FULL-AUDIT AUTHORIZATION GATE

You are not authorized to run any command matching or invoking:

- `npm run fidelity:be6a1`
- `npm run fidelity:be6a1:self-check`
- the complete browser/fidelity matrix
- `composer ci:check`
- the full Laravel test suite
- full Larastan
- MySQL fresh/rollback/re-migration
- complete builds or dependency audits

unless the user's latest message contains this exact token:

`AUTHORIZE_BE6A1_FULL_AUDIT`

Passing focused or checkpoint tests does not grant this authorization.

When implementation and checkpoint validation are complete, stop and request the token. Do not infer approval, do not proceed automatically, and do not run a preliminary full audit.
