# Public Page Projection Rollback

Set `PUBLIC_PAGE_PROJECTION=false`, rebuild Laravel configuration cache, and verify `/about`. This immediately restores the complete static view and requires no content mutation, unpublication, migration rollback or Media deletion. The independent global Site Content projection flag is unchanged.
