# Factory Content Manifest

The versioned source is `database/factory/william-taylor-v1`. It contains primary navigation, footer navigation, one independent announcement, and Site profile data extracted without editorial rewriting from the frontend partial fallbacks.

Each resource records its source file and surface. Installation validates the current typed schema, creates immutable revisions, records review, approval, and publication transitions, and designates the resulting revision as public. Repeated install reuses an exact published checksum. Drift requires the explicit content reset.

Manifest checksum is printed by `factory:install`; canonical key ordering and stable list ordering make it deterministic.
