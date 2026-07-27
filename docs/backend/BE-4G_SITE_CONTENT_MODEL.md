# BE-4G Governed Global Site Content

BE-4G uses one code-owned `global` Site Content aggregate. Its immutable revision payload contains typed brand identity, contact details, WhatsApp configuration, allowlisted social profiles, header and footer menus, announcements, footer columns and bounded site settings.

The aggregate keeps three independent pointers:

- Current editable draft revision.
- Candidate revision under governance.
- Current internally designated published revision.

Saving a draft never replaces the candidate or designated-published revision. Public storefront projection remains inactive.

The schema is English-only, fixes the operational timezone to `Africa/Dar_es_Salaam`, limits collection sizes and text lengths, validates announcement windows, and permits only internal paths or allowlisted URL schemes.
